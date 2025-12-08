const app = {
    user: null,
    token: null,
    salesperson: null,
    employee: null,
    currentScreen: 'login',
    screenHistory: [],
    
    init() {
        this.token = localStorage.getItem('mobile_token');
        const userData = localStorage.getItem('mobile_user');
        if (userData) {
            this.user = JSON.parse(userData);
        }
        
        if (this.token && this.user) {
            this.validateSession();
        }
        
        document.getElementById('login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.login();
        });
        
        document.getElementById('new-order-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.createOrder();
        });
        
        this.updateClock();
        setInterval(() => this.updateClock(), 1000);
        
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('SW registered'))
                .catch(err => console.log('SW registration failed:', err));
        }
    },
    
    async api(endpoint, method = 'GET', data = null) {
        const headers = { 'Content-Type': 'application/json' };
        if (this.token) {
            headers['Authorization'] = 'Bearer ' + this.token;
        }
        
        const options = { method, headers };
        if (data) {
            options.body = JSON.stringify(data);
        }
        
        this.showLoading();
        try {
            const response = await fetch('/mobile-api.php?action=' + endpoint, options);
            const result = await response.json();
            this.hideLoading();
            return result;
        } catch (error) {
            this.hideLoading();
            console.error('API Error:', error);
            return { success: false, error: 'Network error' };
        }
    },
    
    async login() {
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        
        const result = await this.api('login', 'POST', { email, password });
        
        if (result.success) {
            this.user = result.user;
            this.token = result.token;
            this.salesperson = result.salesperson;
            this.employee = result.employee;
            
            localStorage.setItem('mobile_token', this.token);
            localStorage.setItem('mobile_user', JSON.stringify(this.user));
            
            document.getElementById('login-error').classList.add('d-none');
            this.showDashboard();
        } else {
            const errorEl = document.getElementById('login-error');
            errorEl.textContent = result.error || 'Login failed';
            errorEl.classList.remove('d-none');
        }
    },
    
    async validateSession() {
        const result = await this.api('validate', 'POST');
        if (result.success) {
            this.user = result.user;
            this.salesperson = result.salesperson;
            this.employee = result.employee;
            this.showDashboard();
        } else {
            this.logout();
        }
    },
    
    showDashboard() {
        if (this.salesperson) {
            this.showScreen('salesperson-screen');
            document.getElementById('sp-user-name').textContent = this.user.name;
            this.loadSalespersonDashboard();
        } else {
            this.showScreen('technician-screen');
            document.getElementById('tech-user-name').textContent = this.user.name;
            this.loadTechnicianDashboard();
        }
    },
    
    async loadSalespersonDashboard() {
        const result = await this.api('salesperson-dashboard');
        if (result.success) {
            const { stats, orders } = result.data;
            document.getElementById('sp-total-orders').textContent = stats.total_orders || 0;
            document.getElementById('sp-total-sales').textContent = this.formatNumber(stats.total_sales || 0);
            document.getElementById('sp-pending').textContent = stats.pending_orders || 0;
            document.getElementById('sp-commission').textContent = this.formatNumber(stats.total_commission || 0);
            this.renderOrders(orders);
        }
    },
    
    renderOrders(orders) {
        const container = document.getElementById('sp-orders-list');
        if (orders && orders.length > 0) {
            container.innerHTML = orders.map(order => `
                <div class="list-item">
                    <div class="list-item-header">
                        <div>
                            <h6 class="list-item-title">${order.customer_name}</h6>
                            <p class="list-item-subtitle">${order.order_number}</p>
                        </div>
                        <span class="badge ${this.getStatusBadge(order.order_status)} badge-status">
                            ${order.order_status}
                        </span>
                    </div>
                    <div class="list-item-meta">
                        <span><i class="bi bi-telephone"></i> ${order.customer_phone}</span>
                        <span><i class="bi bi-cash"></i> KES ${this.formatNumber(order.amount || 0)}</span>
                    </div>
                    ${order.package_name ? `<div class="list-item-meta"><span><i class="bi bi-wifi"></i> ${order.package_name} - ${order.speed}</span></div>` : ''}
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="empty-state"><i class="bi bi-inbox"></i><p>No orders found</p></div>';
        }
    },
    
    async loadSalespersonOrders(status = '') {
        const result = await this.api('salesperson-orders' + (status ? '&status=' + status : ''));
        const container = document.getElementById('sp-orders-list');
        
        if (result.success && result.data.length > 0) {
            container.innerHTML = result.data.map(order => `
                <div class="list-item">
                    <div class="list-item-header">
                        <div>
                            <h6 class="list-item-title">${order.customer_name}</h6>
                            <p class="list-item-subtitle">${order.order_number}</p>
                        </div>
                        <span class="badge ${this.getStatusBadge(order.order_status)} badge-status">
                            ${order.order_status}
                        </span>
                    </div>
                    <div class="list-item-meta">
                        <span><i class="bi bi-telephone"></i> ${order.customer_phone}</span>
                        <span><i class="bi bi-cash"></i> KES ${this.formatNumber(order.amount || 0)}</span>
                    </div>
                    ${order.package_name ? `<div class="list-item-meta"><span><i class="bi bi-wifi"></i> ${order.package_name} - ${order.speed}</span></div>` : ''}
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>No orders found</p>
                </div>
            `;
        }
    },
    
    async showNewOrder() {
        this.showScreen('new-order-screen');
        
        const result = await this.api('packages');
        if (result.success) {
            const select = document.getElementById('order-package');
            select.innerHTML = '<option value="">Select package...</option>';
            result.data.forEach(pkg => {
                select.innerHTML += `<option value="${pkg.id}" data-price="${pkg.price}">${pkg.name} - ${pkg.speed} (KES ${this.formatNumber(pkg.price)})</option>`;
            });
            
            select.addEventListener('change', () => {
                const option = select.options[select.selectedIndex];
                if (option.dataset.price) {
                    document.getElementById('order-amount').value = option.dataset.price;
                }
            });
        }
    },
    
    async createOrder() {
        const data = {
            customer_name: document.getElementById('order-name').value,
            customer_phone: document.getElementById('order-phone').value,
            customer_address: document.getElementById('order-address').value,
            package_id: document.getElementById('order-package').value || null,
            notes: document.getElementById('order-notes').value
        };
        
        if (!data.customer_name || !data.customer_phone || !data.customer_address) {
            this.showToast('Please fill in all required fields', 'warning');
            return;
        }
        
        const result = await this.api('create-order', 'POST', data);
        
        if (result.success) {
            this.showToast('Order created successfully!', 'success');
            document.getElementById('new-order-form').reset();
            this.goBack();
            this.loadSalespersonDashboard();
        } else {
            this.showToast(result.error || 'Failed to create order', 'danger');
        }
    },
    
    showNewLead() {
        this.showNewOrder();
    },
    
    async loadTechnicianDashboard() {
        const result = await this.api('technician-dashboard');
        if (result.success) {
            const { stats, tickets, attendance } = result.data;
            document.getElementById('tech-total').textContent = stats.total_tickets || 0;
            document.getElementById('tech-open').textContent = stats.open_tickets || 0;
            document.getElementById('tech-progress').textContent = stats.in_progress_tickets || 0;
            document.getElementById('tech-resolved').textContent = stats.resolved_tickets || 0;
            this.renderTickets(tickets);
            this.renderAttendanceStatus(attendance);
        }
    },
    
    renderTickets(tickets) {
        const container = document.getElementById('tech-tickets-list');
        if (tickets && tickets.length > 0) {
            container.innerHTML = tickets.map(ticket => `
                <div class="list-item" onclick="app.showTicketDetail(${ticket.id})">
                    <div class="list-item-header">
                        <div>
                            <h6 class="list-item-title">${ticket.subject}</h6>
                            <p class="list-item-subtitle">${ticket.ticket_number} - ${ticket.customer_name || 'Unknown'}</p>
                        </div>
                        <span class="badge ${this.getStatusBadge(ticket.status)} badge-status">
                            ${ticket.status}
                        </span>
                    </div>
                    <div class="list-item-meta">
                        <span class="priority-${ticket.priority}"><i class="bi bi-flag"></i> ${ticket.priority}</span>
                        <span><i class="bi bi-folder"></i> ${ticket.category}</span>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="empty-state"><i class="bi bi-ticket"></i><p>No tickets assigned</p></div>';
        }
    },
    
    renderAttendanceStatus(attendance) {
        const statusEl = document.getElementById('attendance-status');
        const clockInBtn = document.getElementById('btn-clock-in');
        const clockOutBtn = document.getElementById('btn-clock-out');
        
        if (attendance) {
            if (attendance.clock_in && !attendance.clock_out) {
                statusEl.innerHTML = `<span class="clocked-in"><i class="bi bi-check-circle"></i> Clocked in at ${attendance.clock_in}</span>`;
                clockInBtn.disabled = true;
                clockOutBtn.disabled = false;
            } else if (attendance.clock_in && attendance.clock_out) {
                statusEl.innerHTML = `<span class="clocked-out"><i class="bi bi-check-circle"></i> Worked ${attendance.hours_worked || 0} hours today</span>`;
                clockInBtn.disabled = true;
                clockOutBtn.disabled = true;
            } else {
                statusEl.innerHTML = '<span class="text-muted">Not clocked in today</span>';
                clockInBtn.disabled = false;
                clockOutBtn.disabled = true;
            }
        } else {
            statusEl.innerHTML = '<span class="text-muted">Not clocked in today</span>';
            clockInBtn.disabled = false;
            clockOutBtn.disabled = true;
        }
    },
    
    async loadAttendanceStatus() {
        const result = await this.api('today-attendance');
        if (result.success) {
            this.renderAttendanceStatus(result.data);
        }
    },
    
    async getLocation() {
        return new Promise((resolve) => {
            if (!navigator.geolocation) {
                resolve({ latitude: null, longitude: null, error: 'Geolocation not supported' });
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        error: null
                    });
                },
                (error) => {
                    let errorMsg = 'Location unavailable';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg = 'Location permission denied';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg = 'Location unavailable';
                            break;
                        case error.TIMEOUT:
                            errorMsg = 'Location request timed out';
                            break;
                    }
                    resolve({ latitude: null, longitude: null, error: errorMsg });
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        });
    },
    
    async clockIn() {
        const location = await this.getLocation();
        
        const result = await this.api('clock-in', 'POST', {
            latitude: location.latitude || null,
            longitude: location.longitude || null
        });
        
        if (result.success) {
            this.showToast(result.message, 'success');
            this.loadAttendanceStatus();
        } else {
            this.showToast(result.message || result.error, 'warning');
        }
    },
    
    async clockOut() {
        const location = await this.getLocation();
        
        const result = await this.api('clock-out', 'POST', {
            latitude: location.latitude || null,
            longitude: location.longitude || null
        });
        
        if (result.success) {
            this.showToast(result.message, 'success');
            this.loadAttendanceStatus();
        } else {
            this.showToast(result.message || result.error, 'warning');
        }
    },
    
    async loadTechnicianTickets(status = '') {
        const result = await this.api('technician-tickets' + (status ? '&status=' + status : ''));
        if (result.success) {
            this.renderTickets(result.data);
        }
    },
    
    filterTickets(status) {
        this.loadTechnicianTickets(status);
    },
    
    async showTicketDetail(ticketId) {
        const result = await this.api('ticket-detail&id=' + ticketId);
        
        if (result.success) {
            const ticket = result.data;
            const container = document.getElementById('ticket-detail-content');
            
            container.innerHTML = `
                <div class="ticket-detail-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5>${ticket.subject}</h5>
                            <p class="text-muted mb-0">${ticket.ticket_number}</p>
                        </div>
                        <span class="badge ${this.getStatusBadge(ticket.status)}">${ticket.status}</span>
                    </div>
                    <hr>
                    <p>${ticket.description}</p>
                    <div class="d-flex gap-3 text-muted small">
                        <span><i class="bi bi-flag"></i> ${ticket.priority}</span>
                        <span><i class="bi bi-folder"></i> ${ticket.category}</span>
                    </div>
                </div>
                
                <div class="ticket-detail-card">
                    <h6><i class="bi bi-person"></i> Customer</h6>
                    <p class="mb-1"><strong>${ticket.customer_name || 'Unknown'}</strong></p>
                    ${ticket.customer_phone ? `<p class="mb-1"><i class="bi bi-telephone"></i> ${ticket.customer_phone}</p>` : ''}
                    ${ticket.customer_address ? `<p class="mb-1"><i class="bi bi-geo-alt"></i> ${ticket.customer_address}</p>` : ''}
                    ${ticket.customer_phone ? `
                    <div class="customer-actions">
                        <a href="tel:${ticket.customer_phone}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-telephone"></i> Call
                        </a>
                        <a href="https://wa.me/${ticket.customer_phone.replace(/[^0-9]/g, '')}" class="btn btn-outline-success btn-sm" target="_blank">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                    </div>` : ''}
                </div>
                
                <div class="ticket-detail-card">
                    <h6><i class="bi bi-arrow-repeat"></i> Update Status</h6>
                    <div class="status-actions">
                        ${ticket.status !== 'in_progress' ? `<button class="btn btn-warning btn-sm" onclick="app.updateTicketStatus(${ticket.id}, 'in_progress')">Start Working</button>` : ''}
                        ${ticket.status !== 'resolved' ? `<button class="btn btn-success btn-sm" onclick="app.showCloseTicketModal(${ticket.id})">Close Ticket</button>` : ''}
                        ${ticket.status !== 'on_hold' ? `<button class="btn btn-secondary btn-sm" onclick="app.updateTicketStatus(${ticket.id}, 'on_hold')">On Hold</button>` : ''}
                    </div>
                </div>
                
                ${ticket.equipment && ticket.equipment.length > 0 ? `
                <div class="ticket-detail-card">
                    <h6><i class="bi bi-box-seam"></i> Customer Equipment</h6>
                    ${ticket.equipment.map(eq => `
                        <div class="equipment-item p-2 mb-2 bg-light rounded">
                            <strong>${eq.name || 'Equipment'}</strong>
                            <div class="small text-muted">${eq.brand || ''} ${eq.model || ''}</div>
                            ${eq.serial_number ? `<div class="small"><i class="bi bi-upc"></i> ${eq.serial_number}</div>` : ''}
                            ${eq.mac_address ? `<div class="small"><i class="bi bi-ethernet"></i> ${eq.mac_address}</div>` : ''}
                        </div>
                    `).join('')}
                </div>
                ` : ''}
                
                <div class="ticket-detail-card">
                    <h6><i class="bi bi-chat-dots"></i> Comments</h6>
                    <div class="comment-list">
                        ${(ticket.comments || []).map(c => `
                            <div class="comment-item">
                                <div class="d-flex justify-content-between">
                                    <span class="author">${c.user_name || 'System'}</span>
                                    <span class="time">${this.formatDate(c.created_at)}</span>
                                </div>
                                <div class="text">${c.comment}</div>
                            </div>
                        `).join('') || '<p class="text-muted">No comments yet</p>'}
                    </div>
                    <div class="mt-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="new-comment" placeholder="Add a comment...">
                            <button class="btn btn-primary" onclick="app.addComment(${ticket.id})">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            this.showScreen('ticket-detail-screen');
        }
    },
    
    async updateTicketStatus(ticketId, status) {
        const result = await this.api('update-ticket', 'POST', { ticket_id: ticketId, status });
        
        if (result.success) {
            this.showToast('Status updated', 'success');
            this.showTicketDetail(ticketId);
        } else {
            this.showToast(result.error || 'Failed to update', 'danger');
        }
    },
    
    async showCloseTicketModal(ticketId) {
        const modalHtml = `
            <div class="modal-overlay" id="close-ticket-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5><i class="bi bi-check-circle"></i> Close Ticket</h5>
                        <button class="btn-close" onclick="app.hideCloseTicketModal()"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Cable Used (meters)</label>
                            <input type="number" class="form-control" id="close-cable-meters" min="0" step="0.5" placeholder="e.g., 25">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Router/Equipment Name</label>
                            <input type="text" class="form-control" id="close-router-model" placeholder="e.g., TP-Link Archer C6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="close-router-serial" placeholder="Enter serial number" required>
                            <div class="invalid-feedback">Serial number is required</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Resolution Notes</label>
                            <textarea class="form-control" id="close-comment" rows="3" placeholder="What was done to resolve this ticket?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" onclick="app.hideCloseTicketModal()">Cancel</button>
                        <button class="btn btn-success" onclick="app.submitCloseTicket(${ticketId})">
                            <i class="bi bi-check-lg"></i> Close Ticket
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    },
    
    hideCloseTicketModal() {
        const modal = document.getElementById('close-ticket-modal');
        if (modal) modal.remove();
    },
    
    async submitCloseTicket(ticketId) {
        const serialInput = document.getElementById('close-router-serial');
        const serialValue = serialInput.value.trim();
        
        if (!serialValue) {
            serialInput.classList.add('is-invalid');
            this.showToast('Serial number is required', 'warning');
            serialInput.focus();
            return;
        }
        serialInput.classList.remove('is-invalid');
        
        const data = {
            ticket_id: ticketId,
            cable_meters: document.getElementById('close-cable-meters').value || null,
            router_model: document.getElementById('close-router-model').value || null,
            router_serial: serialValue,
            comment: document.getElementById('close-comment').value || ''
        };
        
        const result = await this.api('close-ticket', 'POST', data);
        
        if (result.success) {
            this.hideCloseTicketModal();
            this.showToast('Ticket closed successfully!', 'success');
            this.loadTechnicianDashboard();
            this.goBack();
        } else {
            this.showToast(result.error || 'Failed to close ticket', 'danger');
        }
    },
    
    async addComment(ticketId) {
        const input = document.getElementById('new-comment');
        const comment = input.value.trim();
        
        if (!comment) return;
        
        const result = await this.api('add-comment', 'POST', { ticket_id: ticketId, comment });
        
        if (result.success) {
            input.value = '';
            this.showTicketDetail(ticketId);
        } else {
            this.showToast(result.error || 'Failed to add comment', 'danger');
        }
    },
    
    async showEquipment() {
        this.showScreen('equipment-screen');
        
        const container = document.getElementById('equipment-list');
        container.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary"></div></div>';
        
        const result = await this.api('assigned-equipment');
        
        if (!result.success) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-exclamation-triangle text-danger"></i>
                    <p>${result.error || 'Failed to load equipment'}</p>
                </div>
            `;
            return;
        }
        
        if (result.data && result.data.length > 0) {
            container.innerHTML = result.data.map(eq => `
                <div class="list-item">
                    <h6 class="list-item-title">${eq.equipment_name}</h6>
                    <p class="list-item-subtitle">${eq.brand || ''} ${eq.model || ''}</p>
                    <div class="list-item-meta">
                        <span><i class="bi bi-upc"></i> ${eq.serial_number || 'N/A'}</span>
                    </div>
                    ${eq.customer_name ? `
                    <div class="list-item-meta">
                        <span><i class="bi bi-person"></i> ${eq.customer_name}</span>
                    </div>` : ''}
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-box"></i>
                    <p>No equipment assigned to you</p>
                </div>
            `;
        }
    },
    
    async showAttendanceHistory() {
        this.showScreen('attendance-history-screen');
        
        const result = await this.api('attendance-history');
        const container = document.getElementById('attendance-list');
        
        if (result.success && result.data.length > 0) {
            container.innerHTML = result.data.map(att => `
                <div class="list-item">
                    <div class="list-item-header">
                        <h6 class="list-item-title">${this.formatDate(att.date)}</h6>
                        <span class="badge ${att.status === 'present' ? 'bg-success' : 'bg-warning'}">${att.status}</span>
                    </div>
                    <div class="list-item-meta">
                        <span><i class="bi bi-box-arrow-in-right"></i> In: ${att.clock_in || '-'}</span>
                        <span><i class="bi bi-box-arrow-right"></i> Out: ${att.clock_out || '-'}</span>
                        <span><i class="bi bi-clock"></i> ${att.hours_worked || 0}h</span>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-calendar"></i>
                    <p>No attendance records</p>
                </div>
            `;
        }
    },
    
    showScreen(screenId) {
        if (this.currentScreen && this.currentScreen !== 'login-screen') {
            this.screenHistory.push(this.currentScreen);
        }
        
        document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
        document.getElementById(screenId).classList.add('active');
        this.currentScreen = screenId;
    },
    
    goBack() {
        const prevScreen = this.screenHistory.pop();
        if (prevScreen) {
            document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
            document.getElementById(prevScreen).classList.add('active');
            this.currentScreen = prevScreen;
        }
    },
    
    logout() {
        this.api('logout', 'POST');
        localStorage.removeItem('mobile_token');
        localStorage.removeItem('mobile_user');
        this.user = null;
        this.token = null;
        this.salesperson = null;
        this.employee = null;
        this.screenHistory = [];
        this.showScreen('login-screen');
    },
    
    showLoading() {
        document.getElementById('loading-overlay').classList.remove('d-none');
    },
    
    hideLoading() {
        document.getElementById('loading-overlay').classList.add('d-none');
    },
    
    showToast(message, type = 'info') {
        const toast = document.getElementById('app-toast');
        const body = document.getElementById('toast-message');
        body.textContent = message;
        toast.className = 'toast bg-' + type + ' text-white';
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    },
    
    updateClock() {
        const now = new Date();
        document.getElementById('current-time').textContent = now.toLocaleTimeString();
        document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', { 
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
        });
    },
    
    formatNumber(num) {
        return Number(num).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    },
    
    formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    },
    
    getStatusBadge(status) {
        const badges = {
            'new': 'bg-primary',
            'open': 'bg-warning',
            'in_progress': 'bg-info',
            'pending': 'bg-warning',
            'confirmed': 'bg-info',
            'completed': 'bg-success',
            'resolved': 'bg-success',
            'closed': 'bg-secondary',
            'cancelled': 'bg-danger',
            'on_hold': 'bg-secondary'
        };
        return badges[status] || 'bg-secondary';
    },
    
    async showPerformance() {
        this.showScreen('performance-screen');
        const container = document.getElementById('performance-content');
        container.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary"></div></div>';
        
        const result = await this.api('salesperson-performance');
        if (result.success) {
            const p = result.data;
            container.innerHTML = `
                <div class="performance-header">
                    <div class="rank-badge">
                        <span class="rank-number">#${p.rank}</span>
                        <span class="rank-label">of ${p.total_salespersons}</span>
                    </div>
                    <h5>This Month's Performance</h5>
                </div>
                
                ${p.achievements.length > 0 ? `
                <div class="achievements-section">
                    <h6><i class="bi bi-trophy"></i> Achievements</h6>
                    <div class="achievements-grid">
                        ${p.achievements.map(a => `
                            <div class="achievement-badge ${a.color}">
                                <i class="bi bi-${a.icon}"></i>
                                <span>${a.title}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
                
                <div class="performance-stats">
                    <div class="perf-stat-card">
                        <div class="perf-stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                        <div class="perf-stat-value">${p.conversion_rate}%</div>
                        <div class="perf-stat-label">Conversion Rate</div>
                    </div>
                    <div class="perf-stat-card">
                        <div class="perf-stat-icon"><i class="bi bi-cart-check"></i></div>
                        <div class="perf-stat-value">${p.this_month.completed_orders}</div>
                        <div class="perf-stat-label">Completed Orders</div>
                    </div>
                    <div class="perf-stat-card">
                        <div class="perf-stat-icon"><i class="bi bi-cash-stack"></i></div>
                        <div class="perf-stat-value">KES ${this.formatNumber(p.this_month.total_sales)}</div>
                        <div class="perf-stat-label">Total Sales</div>
                    </div>
                    <div class="perf-stat-card ${p.sales_growth >= 0 ? 'positive' : 'negative'}">
                        <div class="perf-stat-icon"><i class="bi bi-${p.sales_growth >= 0 ? 'arrow-up' : 'arrow-down'}"></i></div>
                        <div class="perf-stat-value">${p.sales_growth >= 0 ? '+' : ''}${p.sales_growth}%</div>
                        <div class="perf-stat-label">vs Last Month</div>
                    </div>
                </div>
                
                <div class="performance-summary">
                    <div class="summary-row">
                        <span>Total Orders</span>
                        <span>${p.this_month.total_orders}</span>
                    </div>
                    <div class="summary-row">
                        <span>Completed</span>
                        <span class="text-success">${p.this_month.completed_orders}</span>
                    </div>
                    <div class="summary-row">
                        <span>Cancelled</span>
                        <span class="text-danger">${p.this_month.cancelled_orders}</span>
                    </div>
                </div>
            `;
        } else {
            const errorMsg = result.error || 'Could not load performance data';
            container.innerHTML = `<div class="empty-state"><i class="bi bi-exclamation-circle"></i><p>${errorMsg}</p></div>`;
        }
    },
    
    async showTechPerformance() {
        this.showScreen('tech-performance-screen');
        const container = document.getElementById('tech-performance-content');
        container.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-success"></div></div>';
        
        const result = await this.api('technician-performance');
        if (result.success) {
            const p = result.data;
            container.innerHTML = `
                <div class="performance-header tech">
                    <div class="rank-badge">
                        <span class="rank-number">#${p.rank}</span>
                        <span class="rank-label">of ${p.total_technicians}</span>
                    </div>
                    <h5>This Month's Performance</h5>
                </div>
                
                ${p.achievements.length > 0 ? `
                <div class="achievements-section">
                    <h6><i class="bi bi-trophy"></i> Achievements</h6>
                    <div class="achievements-grid">
                        ${p.achievements.map(a => `
                            <div class="achievement-badge ${a.color}">
                                <i class="bi bi-${a.icon}"></i>
                                <span>${a.title}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
                
                <div class="performance-stats">
                    <div class="perf-stat-card">
                        <div class="perf-stat-icon"><i class="bi bi-check-circle"></i></div>
                        <div class="perf-stat-value">${p.resolution_rate}%</div>
                        <div class="perf-stat-label">Resolution Rate</div>
                    </div>
                    <div class="perf-stat-card">
                        <div class="perf-stat-icon"><i class="bi bi-clock-history"></i></div>
                        <div class="perf-stat-value">${p.sla_compliance}%</div>
                        <div class="perf-stat-label">SLA Compliance</div>
                    </div>
                    <div class="perf-stat-card">
                        <div class="perf-stat-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div class="perf-stat-value">${p.avg_resolution_hours ? p.avg_resolution_hours + 'h' : 'N/A'}</div>
                        <div class="perf-stat-label">Avg Resolution</div>
                    </div>
                    <div class="perf-stat-card">
                        <div class="perf-stat-icon"><i class="bi bi-calendar-check"></i></div>
                        <div class="perf-stat-value">${p.attendance_rate}%</div>
                        <div class="perf-stat-label">Attendance</div>
                    </div>
                </div>
                
                <div class="performance-summary">
                    <h6 class="mb-2"><i class="bi bi-ticket"></i> Tickets This Month</h6>
                    <div class="summary-row">
                        <span>Total Tickets</span>
                        <span>${p.this_month.total_tickets}</span>
                    </div>
                    <div class="summary-row">
                        <span>Resolved</span>
                        <span class="text-success">${p.this_month.resolved_tickets}</span>
                    </div>
                    <div class="summary-row">
                        <span>SLA Breached</span>
                        <span class="text-danger">${p.this_month.sla_breached || 0}</span>
                    </div>
                </div>
                
                ${p.attendance_stats ? `
                <div class="performance-summary mt-3">
                    <h6 class="mb-2"><i class="bi bi-clock"></i> Attendance This Month</h6>
                    <div class="summary-row">
                        <span>Days Present</span>
                        <span class="text-success">${p.attendance_stats.days_present}</span>
                    </div>
                    <div class="summary-row">
                        <span>Days On Time</span>
                        <span class="text-success">${p.attendance_stats.days_on_time}</span>
                    </div>
                    <div class="summary-row">
                        <span>Days Late</span>
                        <span class="${p.attendance_stats.days_late > 0 ? 'text-warning' : ''}">${p.attendance_stats.days_late}</span>
                    </div>
                    <div class="summary-row">
                        <span>Total Hours Worked</span>
                        <span>${p.attendance_stats.total_hours}h</span>
                    </div>
                    ${p.attendance_stats.avg_clock_in ? `
                    <div class="summary-row">
                        <span>Avg Clock-in Time</span>
                        <span>${p.attendance_stats.avg_clock_in}</span>
                    </div>
                    ` : ''}
                </div>
                ` : ''}
            `;
        } else {
            const errorMsg = result.error || 'Could not load performance data';
            container.innerHTML = `<div class="empty-state"><i class="bi bi-exclamation-circle"></i><p>${errorMsg}</p></div>`;
        }
    },
    
    showNewTicket() {
        this.showScreen('new-ticket-screen');
        document.getElementById('new-ticket-form').reset();
        document.getElementById('ticket-customer-id').value = '';
        document.getElementById('customer-search-results').innerHTML = '';
    },
    
    async searchCustomers(query) {
        if (query.length < 2) {
            document.getElementById('customer-search-results').innerHTML = '';
            return;
        }
        
        const result = await this.api('search-customers&q=' + encodeURIComponent(query));
        const container = document.getElementById('customer-search-results');
        
        if (result.success && result.data.length > 0) {
            container.innerHTML = result.data.map(c => `
                <div class="search-result-item" onclick="app.selectCustomer(${c.id}, '${c.name.replace(/'/g, "\\'")}', '${c.phone}')">
                    <strong>${c.name}</strong>
                    <span class="text-muted">${c.phone}</span>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="search-result-item text-muted">No customers found</div>';
        }
    },
    
    selectCustomer(id, name, phone) {
        document.getElementById('ticket-customer-id').value = id;
        document.getElementById('ticket-customer-search').value = `${name} (${phone})`;
        document.getElementById('customer-search-results').innerHTML = '';
    },
    
    async createTicket() {
        const data = {
            customer_id: document.getElementById('ticket-customer-id').value || null,
            subject: document.getElementById('ticket-subject').value,
            category: document.getElementById('ticket-category').value,
            priority: document.getElementById('ticket-priority').value,
            description: document.getElementById('ticket-description').value
        };
        
        if (!data.subject) {
            this.showToast('Subject is required', 'danger');
            return;
        }
        
        const result = await this.api('create-ticket', 'POST', data);
        
        if (result.success) {
            this.showToast('Ticket created successfully!', 'success');
            document.getElementById('new-ticket-form').reset();
            document.getElementById('ticket-customer-id').value = '';
            this.goBack();
            this.loadTechnicianDashboard();
        } else {
            this.showToast(result.error || 'Failed to create ticket', 'danger');
        }
    },
    
    initTicketForm() {
        const searchInput = document.getElementById('ticket-customer-search');
        if (searchInput) {
            let timeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => this.searchCustomers(e.target.value), 300);
            });
        }
        
        const ticketForm = document.getElementById('new-ticket-form');
        if (ticketForm) {
            ticketForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createTicket();
            });
        }
    },
    
    setActiveNav(element) {
        const nav = element.closest('.bottom-nav');
        if (nav) {
            nav.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            element.classList.add('active');
        }
    },
    
    async showAvailableTickets() {
        this.showScreen('available-tickets-screen');
        this.loadAvailableTickets();
    },
    
    async loadAvailableTickets() {
        const container = document.getElementById('available-tickets-list');
        container.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-success"></div></div>';
        
        const result = await this.api('available-tickets');
        if (result.success && result.data.length > 0) {
            container.innerHTML = result.data.map(ticket => `
                <div class="list-item">
                    <div class="list-item-header">
                        <div>
                            <h6 class="list-item-title">${ticket.subject}</h6>
                            <p class="list-item-subtitle">${ticket.ticket_number} - ${ticket.customer_name || 'Unknown'}</p>
                        </div>
                        <span class="badge ${this.getStatusBadge(ticket.status)}">${ticket.status}</span>
                    </div>
                    <div class="list-item-meta">
                        <span class="priority-${ticket.priority}"><i class="bi bi-flag"></i> ${ticket.priority}</span>
                        <span><i class="bi bi-folder"></i> ${ticket.category}</span>
                    </div>
                    ${ticket.customer_phone ? `<div class="list-item-meta"><span><i class="bi bi-telephone"></i> ${ticket.customer_phone}</span></div>` : ''}
                    ${ticket.customer_address ? `<div class="list-item-meta"><span><i class="bi bi-geo-alt"></i> ${ticket.customer_address}</span></div>` : ''}
                    <div class="mt-2">
                        <button class="btn btn-success btn-sm w-100" onclick="app.claimTicket(${ticket.id})">
                            <i class="bi bi-hand-index"></i> Claim This Ticket
                        </button>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="empty-state"><i class="bi bi-inbox"></i><p>No available tickets</p><p class="text-muted small">All tickets are currently assigned</p></div>';
        }
    },
    
    async claimTicket(ticketId) {
        if (!confirm('Claim this ticket and assign it to yourself?')) return;
        
        const result = await this.api('claim-ticket', 'POST', { ticket_id: ticketId });
        
        if (result.success) {
            this.showToast('Ticket claimed successfully!', 'success');
            this.loadAvailableTickets();
            this.loadTechnicianDashboard();
        } else {
            this.showToast(result.error || 'Failed to claim ticket', 'danger');
        }
    },
    
    async showTechEquipment() {
        this.showScreen('tech-equipment-screen');
        const container = document.getElementById('tech-equipment-list');
        container.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-success"></div></div>';
        
        const result = await this.api('technician-equipment');
        if (result.success && result.data.length > 0) {
            container.innerHTML = result.data.map(eq => `
                <div class="list-item">
                    <div class="list-item-header">
                        <div>
                            <h6 class="list-item-title">${eq.name || eq.equipment_name || 'Equipment'}</h6>
                            <p class="list-item-subtitle">${eq.brand || ''} ${eq.model || ''}</p>
                        </div>
                        <span class="badge ${eq.assignment_status === 'assigned' ? 'bg-success' : 'bg-secondary'}">${eq.assignment_status || 'N/A'}</span>
                    </div>
                    <div class="list-item-meta">
                        <span><i class="bi bi-upc-scan"></i> ${eq.serial_number || 'N/A'}</span>
                        ${eq.mac_address ? `<span><i class="bi bi-ethernet"></i> ${eq.mac_address}</span>` : ''}
                    </div>
                    ${eq.customer_name ? `
                    <div class="list-item-meta">
                        <span><i class="bi bi-person"></i> ${eq.customer_name}</span>
                    </div>` : ''}
                    ${eq.customer_address ? `
                    <div class="list-item-meta">
                        <span><i class="bi bi-geo-alt"></i> ${eq.customer_address}</span>
                    </div>` : ''}
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="empty-state"><i class="bi bi-box-seam"></i><p>No equipment assigned</p></div>';
        }
    },
    
    async showTicketDetailAny(ticketId) {
        const result = await this.api('ticket-detail-any&id=' + ticketId);
        
        if (result.success) {
            const ticket = result.data;
            const container = document.getElementById('ticket-detail-content');
            
            let equipmentHtml = '';
            if (ticket.equipment && ticket.equipment.length > 0) {
                equipmentHtml = `
                    <div class="ticket-detail-card">
                        <h6><i class="bi bi-box-seam"></i> Customer Equipment</h6>
                        ${ticket.equipment.map(eq => `
                            <div class="equipment-item p-2 border-bottom">
                                <strong>${eq.name}</strong> - ${eq.serial_number || 'N/A'}
                                <div class="small text-muted">${eq.brand || ''} ${eq.model || ''}</div>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
            
            container.innerHTML = `
                <div class="ticket-detail-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5>${ticket.subject}</h5>
                            <p class="text-muted mb-0">${ticket.ticket_number}</p>
                        </div>
                        <span class="badge ${this.getStatusBadge(ticket.status)}">${ticket.status}</span>
                    </div>
                    ${ticket.assigned_name ? `<p class="small text-info mt-2 mb-0"><i class="bi bi-person-badge"></i> Assigned to: ${ticket.assigned_name}</p>` : '<p class="small text-warning mt-2 mb-0"><i class="bi bi-exclamation-triangle"></i> Unassigned</p>'}
                    <hr>
                    <p>${ticket.description || 'No description'}</p>
                    <div class="d-flex gap-3 text-muted small">
                        <span><i class="bi bi-flag"></i> ${ticket.priority}</span>
                        <span><i class="bi bi-folder"></i> ${ticket.category}</span>
                    </div>
                </div>
                
                <div class="ticket-detail-card">
                    <h6><i class="bi bi-person"></i> Customer</h6>
                    <p class="mb-1"><strong>${ticket.customer_name || 'Unknown'}</strong></p>
                    ${ticket.customer_phone ? `<p class="mb-1"><i class="bi bi-telephone"></i> ${ticket.customer_phone}</p>` : ''}
                    ${ticket.customer_address ? `<p class="mb-1"><i class="bi bi-geo-alt"></i> ${ticket.customer_address}</p>` : ''}
                    ${ticket.customer_phone ? `
                    <div class="customer-actions">
                        <a href="tel:${ticket.customer_phone}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-telephone"></i> Call
                        </a>
                        <a href="https://wa.me/${ticket.customer_phone.replace(/[^0-9]/g, '')}" class="btn btn-outline-success btn-sm" target="_blank">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                    </div>` : ''}
                </div>
                
                ${equipmentHtml}
                
                <div class="ticket-detail-card">
                    <h6><i class="bi bi-arrow-repeat"></i> Update Status</h6>
                    <div class="status-actions">
                        ${!ticket.assigned_to ? `<button class="btn btn-primary btn-sm" onclick="app.claimAndUpdate(${ticket.id})">Claim & Start</button>` : ''}
                        ${ticket.status !== 'in_progress' ? `<button class="btn btn-warning btn-sm" onclick="app.updateTicketStatusAny(${ticket.id}, 'in_progress')">Start Working</button>` : ''}
                        ${ticket.status !== 'resolved' ? `<button class="btn btn-success btn-sm" onclick="app.updateTicketStatusAny(${ticket.id}, 'resolved')">Mark Resolved</button>` : ''}
                        ${ticket.status !== 'on_hold' ? `<button class="btn btn-secondary btn-sm" onclick="app.updateTicketStatusAny(${ticket.id}, 'on_hold')">On Hold</button>` : ''}
                    </div>
                </div>
                
                <div class="ticket-detail-card">
                    <h6><i class="bi bi-chat-dots"></i> Comments</h6>
                    <div class="comment-list">
                        ${(ticket.comments || []).map(c => `
                            <div class="comment-item">
                                <div class="d-flex justify-content-between">
                                    <span class="author">${c.user_name || 'System'}</span>
                                    <span class="time">${this.formatDate(c.created_at)}</span>
                                </div>
                                <div class="text">${c.comment}</div>
                            </div>
                        `).join('') || '<p class="text-muted">No comments yet</p>'}
                    </div>
                    <div class="mt-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="new-comment-any" placeholder="Add a comment...">
                            <button class="btn btn-primary" onclick="app.addCommentAny(${ticket.id})">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            this.showScreen('ticket-detail-screen');
        } else {
            this.showToast('Failed to load ticket', 'danger');
        }
    },
    
    async claimAndUpdate(ticketId) {
        const claimResult = await this.api('claim-ticket', 'POST', { ticket_id: ticketId });
        if (claimResult.success) {
            await this.api('update-ticket-any', 'POST', { ticket_id: ticketId, status: 'in_progress' });
            this.showToast('Ticket claimed and started!', 'success');
            this.showTicketDetailAny(ticketId);
        } else {
            this.showToast(claimResult.error || 'Failed to claim', 'danger');
        }
    },
    
    async updateTicketStatusAny(ticketId, status) {
        const result = await this.api('update-ticket-any', 'POST', { ticket_id: ticketId, status });
        
        if (result.success) {
            this.showToast('Status updated', 'success');
            this.showTicketDetailAny(ticketId);
        } else {
            this.showToast(result.error || 'Failed to update', 'danger');
        }
    },
    
    async addCommentAny(ticketId) {
        const input = document.getElementById('new-comment-any');
        const comment = input.value.trim();
        
        if (!comment) return;
        
        const result = await this.api('add-comment-any', 'POST', { ticket_id: ticketId, comment });
        
        if (result.success) {
            input.value = '';
            this.showTicketDetailAny(ticketId);
        } else {
            this.showToast(result.error || 'Failed to add comment', 'danger');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    app.init();
    app.initTicketForm();
});
