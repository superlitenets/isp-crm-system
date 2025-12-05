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
        
        document.getElementById('new-lead-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.createLead();
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
            customer_email: document.getElementById('order-email').value,
            customer_address: document.getElementById('order-address').value,
            package_id: document.getElementById('order-package').value,
            amount: document.getElementById('order-amount').value,
            notes: document.getElementById('order-notes').value
        };
        
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
        this.showScreen('new-lead-screen');
    },
    
    async createLead() {
        const data = {
            customer_name: document.getElementById('lead-name').value,
            customer_phone: document.getElementById('lead-phone').value,
            location: document.getElementById('lead-location').value,
            description: document.getElementById('lead-description').value
        };
        
        if (!data.customer_name || !data.customer_phone || !data.location) {
            this.showToast('Please fill in all required fields', 'warning');
            return;
        }
        
        const result = await this.api('create-lead', 'POST', data);
        
        if (result.success) {
            this.showToast('Lead submitted successfully!', 'success');
            document.getElementById('new-lead-form').reset();
            this.goBack();
            this.loadSalespersonDashboard();
        } else {
            this.showToast(result.error || 'Failed to submit lead', 'danger');
        }
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
    
    async clockIn() {
        const result = await this.api('clock-in', 'POST');
        if (result.success) {
            this.showToast(result.message, 'success');
            this.loadAttendanceStatus();
        } else {
            this.showToast(result.message || result.error, 'warning');
        }
    },
    
    async clockOut() {
        const result = await this.api('clock-out', 'POST');
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
                        ${ticket.status !== 'resolved' ? `<button class="btn btn-success btn-sm" onclick="app.updateTicketStatus(${ticket.id}, 'resolved')">Mark Resolved</button>` : ''}
                        ${ticket.status !== 'on_hold' ? `<button class="btn btn-secondary btn-sm" onclick="app.updateTicketStatus(${ticket.id}, 'on_hold')">On Hold</button>` : ''}
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
        
        const result = await this.api('assigned-equipment');
        const container = document.getElementById('equipment-list');
        
        if (result.success && result.data.length > 0) {
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
                    <p>No equipment assigned</p>
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
            container.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-circle"></i><p>Could not load performance data</p></div>';
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
            `;
        } else {
            container.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-circle"></i><p>Could not load performance data</p></div>';
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
    }
};

document.addEventListener('DOMContentLoaded', () => {
    app.init();
    app.initTicketForm();
});
