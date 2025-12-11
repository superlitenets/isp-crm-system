-- Migration: Add comprehensive permissions for all system modules
-- Run this on production to add missing permissions

INSERT INTO permissions (name, display_name, category, description) VALUES
-- Dashboard
('dashboard.view', 'View Dashboard', 'dashboard', 'Can view the main dashboard'),
('dashboard.stats', 'View Dashboard Stats', 'dashboard', 'Can view dashboard statistics and metrics'),

-- Customers
('customers.view', 'View Customers', 'customers', 'Can view customer list and details'),
('customers.create', 'Create Customers', 'customers', 'Can create new customers'),
('customers.edit', 'Edit Customers', 'customers', 'Can edit existing customers'),
('customers.delete', 'Delete Customers', 'customers', 'Can delete customers'),
('customers.import', 'Import Customers', 'customers', 'Can import customers from CSV/Excel'),
('customers.export', 'Export Customers', 'customers', 'Can export customer data'),
('customers.view_all', 'View All Customers', 'customers', 'View all customers (not just created by user)'),

-- Tickets
('tickets.view', 'View Tickets', 'tickets', 'Can view ticket list and details'),
('tickets.create', 'Create Tickets', 'tickets', 'Can create new tickets'),
('tickets.edit', 'Edit Tickets', 'tickets', 'Can edit and update tickets'),
('tickets.delete', 'Delete Tickets', 'tickets', 'Can delete tickets'),
('tickets.assign', 'Assign Tickets', 'tickets', 'Can assign tickets to technicians'),
('tickets.escalate', 'Escalate Tickets', 'tickets', 'Can escalate tickets to higher priority'),
('tickets.close', 'Close Tickets', 'tickets', 'Can close/resolve tickets'),
('tickets.reopen', 'Reopen Tickets', 'tickets', 'Can reopen closed tickets'),
('tickets.sla', 'Manage SLA', 'tickets', 'Can configure SLA policies'),
('tickets.commission', 'Manage Ticket Commission', 'tickets', 'Can configure ticket commission rates'),
('tickets.view_all', 'View All Tickets', 'tickets', 'View all tickets (not just assigned)'),

-- HR
('hr.view', 'View HR', 'hr', 'Can view employee records and HR data'),
('hr.manage', 'Manage HR', 'hr', 'Can create, edit, and manage employees'),
('hr.payroll', 'Manage Payroll', 'hr', 'Can process payroll and deductions'),
('hr.attendance', 'Manage Attendance', 'hr', 'Can view and edit attendance records'),
('hr.advances', 'Manage Salary Advances', 'hr', 'Can approve and manage salary advances'),
('hr.leave', 'Manage Leave', 'hr', 'Can approve and manage leave requests'),
('hr.overtime', 'Manage Overtime', 'hr', 'Can manage overtime and deductions'),

-- Inventory
('inventory.view', 'View Inventory', 'inventory', 'Can view equipment and inventory'),
('inventory.manage', 'Manage Inventory', 'inventory', 'Can add, edit, and assign equipment'),
('inventory.import', 'Import Inventory', 'inventory', 'Can import equipment from CSV/Excel'),
('inventory.export', 'Export Inventory', 'inventory', 'Can export inventory data'),
('inventory.assign', 'Assign Equipment', 'inventory', 'Can assign equipment to customers'),
('inventory.faults', 'Manage Faults', 'inventory', 'Can report and manage equipment faults'),

-- Orders
('orders.view', 'View Orders', 'orders', 'Can view orders list'),
('orders.create', 'Create Orders', 'orders', 'Can create new orders'),
('orders.manage', 'Manage Orders', 'orders', 'Can edit and process orders'),
('orders.delete', 'Delete Orders', 'orders', 'Can delete orders'),
('orders.convert', 'Convert Orders', 'orders', 'Can convert orders to tickets'),
('orders.view_all', 'View All Orders', 'orders', 'View all orders (not just owned by user)'),

-- Payments
('payments.view', 'View Payments', 'payments', 'Can view payment records'),
('payments.manage', 'Manage Payments', 'payments', 'Can process and manage payments'),
('payments.stk', 'Send STK Push', 'payments', 'Can send M-Pesa STK Push requests'),
('payments.refund', 'Process Refunds', 'payments', 'Can process payment refunds'),
('payments.export', 'Export Payments', 'payments', 'Can export payment data'),

-- Complaints
('complaints.view', 'View Complaints', 'complaints', 'Can view complaints list'),
('complaints.create', 'Create Complaints', 'complaints', 'Can create new complaints'),
('complaints.edit', 'Edit Complaints', 'complaints', 'Can edit complaints'),
('complaints.approve', 'Approve Complaints', 'complaints', 'Can approve complaints'),
('complaints.reject', 'Reject Complaints', 'complaints', 'Can reject complaints'),
('complaints.convert', 'Convert to Ticket', 'complaints', 'Can convert complaints to tickets'),
('complaints.view_all', 'View All Complaints', 'complaints', 'View all complaints (not just assigned)'),

-- Sales
('sales.view', 'View Sales', 'sales', 'Can view sales dashboard'),
('sales.manage', 'Manage Sales', 'sales', 'Can manage salesperson assignments'),
('sales.commission', 'View Commission', 'sales', 'Can view and manage commissions'),
('sales.leads', 'Manage Leads', 'sales', 'Can create and manage leads'),
('sales.targets', 'Manage Targets', 'sales', 'Can set and manage sales targets'),

-- Branches
('branches.view', 'View Branches', 'branches', 'Can view branch list'),
('branches.create', 'Create Branches', 'branches', 'Can create new branches'),
('branches.edit', 'Edit Branches', 'branches', 'Can edit branch details'),
('branches.delete', 'Delete Branches', 'branches', 'Can delete branches'),
('branches.assign', 'Assign Employees', 'branches', 'Can assign employees to branches'),

-- Network / SmartOLT
('network.view', 'View Network', 'network', 'Can view SmartOLT network status'),
('network.manage', 'Manage Network', 'network', 'Can manage ONUs and network devices'),
('network.provision', 'Provision Devices', 'network', 'Can provision new network devices'),

-- Accounting
('accounting.view', 'View Accounting', 'accounting', 'Can view accounting dashboard'),
('accounting.invoices', 'Manage Invoices', 'accounting', 'Can create and manage invoices'),
('accounting.quotes', 'Manage Quotes', 'accounting', 'Can create and manage quotes'),
('accounting.bills', 'Manage Bills', 'accounting', 'Can manage vendor bills'),
('accounting.expenses', 'Manage Expenses', 'accounting', 'Can record and manage expenses'),
('accounting.vendors', 'Manage Vendors', 'accounting', 'Can manage vendors/suppliers'),
('accounting.products', 'Manage Products', 'accounting', 'Can manage products/services catalog'),
('accounting.reports', 'View Financial Reports', 'accounting', 'Can view P&L, aging reports'),
('accounting.chart', 'Manage Chart of Accounts', 'accounting', 'Can manage chart of accounts'),

-- WhatsApp
('whatsapp.view', 'View WhatsApp', 'whatsapp', 'Can view WhatsApp conversations'),
('whatsapp.send', 'Send WhatsApp', 'whatsapp', 'Can send WhatsApp messages'),
('whatsapp.manage', 'Manage WhatsApp', 'whatsapp', 'Can configure WhatsApp settings'),

-- Devices / Biometric
('devices.view', 'View Devices', 'devices', 'Can view biometric devices'),
('devices.manage', 'Manage Devices', 'devices', 'Can add/edit biometric devices'),
('devices.sync', 'Sync Devices', 'devices', 'Can sync attendance from devices'),
('devices.enroll', 'Enroll Users', 'devices', 'Can enroll fingerprints on devices'),

-- Teams
('teams.view', 'View Teams', 'teams', 'Can view team list'),
('teams.manage', 'Manage Teams', 'teams', 'Can create and manage teams'),

-- Activity Logs
('logs.view', 'View Activity Logs', 'logs', 'Can view system activity logs'),
('logs.export', 'Export Logs', 'logs', 'Can export activity logs'),

-- Settings
('settings.view', 'View Settings', 'settings', 'Can view system settings'),
('settings.manage', 'Manage Settings', 'settings', 'Can modify system settings'),
('settings.sms', 'Manage SMS Settings', 'settings', 'Can configure SMS gateway'),
('settings.biometric', 'Manage Biometric', 'settings', 'Can configure biometric devices'),

-- Users
('users.view', 'View Users', 'users', 'Can view user accounts'),
('users.manage', 'Manage Users', 'users', 'Can create, edit, and delete users'),
('roles.manage', 'Manage Roles', 'users', 'Can manage roles and permissions'),

-- Reports
('reports.view', 'View Reports', 'reports', 'Can view reports and analytics'),
('reports.export', 'Export Reports', 'reports', 'Can export data and reports')

ON CONFLICT (name) DO NOTHING;

-- Fix category case (ensure lowercase)
UPDATE permissions SET category = LOWER(category);
