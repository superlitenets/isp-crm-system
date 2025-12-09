-- HR Advance Salary and Leave Management Migration
-- Created: 2025-12-09

-- =====================================================
-- SALARY ADVANCES MODULE
-- =====================================================

-- Salary Advances table - tracks advance requests
CREATE TABLE IF NOT EXISTS salary_advances (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'disbursed', 'repaying', 'completed', 'cancelled')),
    repayment_type VARCHAR(20) DEFAULT 'monthly' CHECK (repayment_type IN ('weekly', 'bi-weekly', 'monthly', 'custom')),
    repayment_installments INTEGER DEFAULT 1,
    repayment_amount DECIMAL(12,2),
    total_repaid DECIMAL(12,2) DEFAULT 0,
    balance DECIMAL(12,2),
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP,
    disbursed_at TIMESTAMP,
    completed_at TIMESTAMP,
    next_deduction_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Salary Advance Payments/Deductions - tracks each repayment
CREATE TABLE IF NOT EXISTS salary_advance_payments (
    id SERIAL PRIMARY KEY,
    advance_id INTEGER NOT NULL REFERENCES salary_advances(id) ON DELETE CASCADE,
    amount DECIMAL(12,2) NOT NULL,
    payment_type VARCHAR(20) DEFAULT 'payroll_deduction' CHECK (payment_type IN ('payroll_deduction', 'cash', 'bank_transfer', 'other')),
    payroll_id INTEGER REFERENCES payroll(id) ON DELETE SET NULL,
    payment_date DATE NOT NULL,
    reference_number VARCHAR(100),
    notes TEXT,
    recorded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- LEAVE MANAGEMENT MODULE
-- =====================================================

-- Leave Types table
CREATE TABLE IF NOT EXISTS leave_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    days_per_year DECIMAL(5,2) DEFAULT 0,
    is_paid BOOLEAN DEFAULT TRUE,
    requires_approval BOOLEAN DEFAULT TRUE,
    allow_negative_balance BOOLEAN DEFAULT FALSE,
    max_carryover_days DECIMAL(5,2) DEFAULT 0,
    accrual_type VARCHAR(20) DEFAULT 'monthly' CHECK (accrual_type IN ('annual', 'monthly', 'none')),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Leave Balances - tracks employee leave balances
CREATE TABLE IF NOT EXISTS leave_balances (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    leave_type_id INTEGER NOT NULL REFERENCES leave_types(id) ON DELETE CASCADE,
    year INTEGER NOT NULL,
    entitled_days DECIMAL(5,2) DEFAULT 0,
    accrued_days DECIMAL(5,2) DEFAULT 0,
    used_days DECIMAL(5,2) DEFAULT 0,
    pending_days DECIMAL(5,2) DEFAULT 0,
    carried_over_days DECIMAL(5,2) DEFAULT 0,
    adjusted_days DECIMAL(5,2) DEFAULT 0,
    available_days DECIMAL(5,2) GENERATED ALWAYS AS (accrued_days + carried_over_days + adjusted_days - used_days - pending_days) STORED,
    last_accrual_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(employee_id, leave_type_id, year)
);

-- Leave Requests - employee leave applications
CREATE TABLE IF NOT EXISTS leave_requests (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    leave_type_id INTEGER NOT NULL REFERENCES leave_types(id) ON DELETE CASCADE,
    branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(5,2) NOT NULL,
    is_half_day BOOLEAN DEFAULT FALSE,
    half_day_type VARCHAR(10) CHECK (half_day_type IN ('morning', 'afternoon')),
    reason TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled')),
    approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP,
    rejection_reason TEXT,
    attachment_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Leave Calendar - for tracking public holidays and blocked dates
CREATE TABLE IF NOT EXISTS leave_calendar (
    id SERIAL PRIMARY KEY,
    date DATE NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_public_holiday BOOLEAN DEFAULT TRUE,
    branch_id INTEGER REFERENCES branches(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(date, branch_id)
);

-- =====================================================
-- DEFAULT LEAVE TYPES
-- =====================================================
INSERT INTO leave_types (name, code, description, days_per_year, is_paid, accrual_type) VALUES
('Annual Leave', 'ANNUAL', 'Standard annual leave entitlement', 21, TRUE, 'monthly'),
('Sick Leave', 'SICK', 'Medical sick leave', 14, TRUE, 'annual'),
('Unpaid Leave', 'UNPAID', 'Leave without pay', 0, FALSE, 'none'),
('Maternity Leave', 'MATERNITY', 'Maternity leave for new mothers', 90, TRUE, 'none'),
('Paternity Leave', 'PATERNITY', 'Paternity leave for new fathers', 14, TRUE, 'none'),
('Compassionate Leave', 'COMPASSIONATE', 'Leave for family emergencies', 5, TRUE, 'annual')
ON CONFLICT (code) DO NOTHING;

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_salary_advances_employee ON salary_advances(employee_id);
CREATE INDEX IF NOT EXISTS idx_salary_advances_status ON salary_advances(status);
CREATE INDEX IF NOT EXISTS idx_salary_advances_branch ON salary_advances(branch_id);
CREATE INDEX IF NOT EXISTS idx_salary_advance_payments_advance ON salary_advance_payments(advance_id);
CREATE INDEX IF NOT EXISTS idx_leave_balances_employee ON leave_balances(employee_id);
CREATE INDEX IF NOT EXISTS idx_leave_balances_year ON leave_balances(year);
CREATE INDEX IF NOT EXISTS idx_leave_requests_employee ON leave_requests(employee_id);
CREATE INDEX IF NOT EXISTS idx_leave_requests_status ON leave_requests(status);
CREATE INDEX IF NOT EXISTS idx_leave_requests_dates ON leave_requests(start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_leave_calendar_date ON leave_calendar(date);

-- =====================================================
-- ADD PERMISSIONS FOR NEW MODULES
-- =====================================================
INSERT INTO permissions (name, description, category) VALUES
('manage_advances', 'Manage salary advances', 'HR'),
('approve_advances', 'Approve salary advance requests', 'HR'),
('view_advances', 'View salary advances', 'HR'),
('manage_leave', 'Manage leave types and policies', 'HR'),
('approve_leave', 'Approve leave requests', 'HR'),
('view_leave', 'View leave requests and balances', 'HR'),
('request_advance', 'Request salary advance', 'Employee'),
('request_leave', 'Request leave', 'Employee')
ON CONFLICT (name) DO NOTHING;

-- Grant permissions to admin role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'admin' AND p.name IN ('manage_advances', 'approve_advances', 'view_advances', 'manage_leave', 'approve_leave', 'view_leave', 'request_advance', 'request_leave')
ON CONFLICT DO NOTHING;
