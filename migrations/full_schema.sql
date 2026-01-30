-- ISP CRM Full Database Schema Migration
-- Generated: 2026-01-30
-- Run this script on your PostgreSQL database to create all required tables

-- ==========================================
-- Core Tables (No Dependencies)
-- ==========================================

CREATE TABLE IF NOT EXISTS roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS permissions (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS role_permissions (
    id SERIAL PRIMARY KEY,
    role_id INTEGER REFERENCES roles(id) ON DELETE CASCADE,
    permission_id INTEGER REFERENCES permissions(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(role_id, permission_id)
);

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255),
    role VARCHAR(20) NOT NULL DEFAULT 'technician',
    role_id INTEGER REFERENCES roles(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS departments (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    manager_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS employees (
    id SERIAL PRIMARY KEY,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    office_phone VARCHAR(20),
    department_id INTEGER REFERENCES departments(id) ON DELETE SET NULL,
    position VARCHAR(100) NOT NULL,
    salary DECIMAL(12, 2),
    hire_date DATE,
    employment_status VARCHAR(20) DEFAULT 'active',
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    address TEXT,
    notes TEXT,
    passport_photo VARCHAR(500),
    id_number VARCHAR(50),
    passport_number VARCHAR(50),
    date_of_birth DATE,
    gender VARCHAR(20),
    nationality VARCHAR(50),
    marital_status VARCHAR(20),
    next_of_kin_name VARCHAR(100),
    next_of_kin_relationship VARCHAR(50),
    next_of_kin_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE departments DROP CONSTRAINT IF EXISTS fk_manager;
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_manager') THEN
        ALTER TABLE departments ADD CONSTRAINT fk_manager FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS branches (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    manager_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    whatsapp_group VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    id SERIAL PRIMARY KEY,
    account_number VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    service_plan VARCHAR(50) NOT NULL,
    connection_status VARCHAR(20) DEFAULT 'active',
    installation_date DATE,
    notes TEXT,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- Ticketing & Support
-- ==========================================

CREATE TABLE IF NOT EXISTS sla_policies (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    priority VARCHAR(20) NOT NULL,
    response_time_hours INTEGER NOT NULL DEFAULT 4,
    resolution_time_hours INTEGER NOT NULL DEFAULT 24,
    escalation_time_hours INTEGER,
    escalation_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
    notify_on_breach BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS teams (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    leader_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS team_members (
    id SERIAL PRIMARY KEY,
    team_id INTEGER REFERENCES teams(id) ON DELETE CASCADE,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(team_id, employee_id)
);

CREATE TABLE IF NOT EXISTS equipment_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    item_type VARCHAR(30) DEFAULT 'serialized',
    parent_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS equipment (
    id SERIAL PRIMARY KEY,
    category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
    name VARCHAR(100) NOT NULL,
    brand VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    mac_address VARCHAR(50),
    purchase_date DATE,
    purchase_price DECIMAL(12, 2),
    warranty_expiry DATE,
    condition VARCHAR(20) DEFAULT 'new',
    status VARCHAR(20) DEFAULT 'available',
    location VARCHAR(200),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tickets (
    id SERIAL PRIMARY KEY,
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE,
    assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    priority VARCHAR(20) DEFAULT 'medium',
    status VARCHAR(20) DEFAULT 'open',
    source VARCHAR(50) DEFAULT 'internal',
    team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL,
    branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL,
    sla_policy_id INTEGER REFERENCES sla_policies(id) ON DELETE SET NULL,
    first_response_at TIMESTAMP,
    sla_response_due TIMESTAMP,
    sla_resolution_due TIMESTAMP,
    sla_response_breached BOOLEAN DEFAULT FALSE,
    sla_resolution_breached BOOLEAN DEFAULT FALSE,
    sla_paused_at TIMESTAMP,
    sla_paused_duration INTEGER DEFAULT 0,
    is_escalated BOOLEAN DEFAULT FALSE,
    escalation_count INTEGER DEFAULT 0,
    satisfaction_rating INTEGER,
    closure_details JSONB DEFAULT '{}',
    equipment_used_id INTEGER REFERENCES equipment(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP,
    closed_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_comments (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    comment TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_categories (
    id SERIAL PRIMARY KEY,
    key VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(20) DEFAULT 'primary',
    display_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    subject VARCHAR(200),
    content TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_satisfaction_ratings (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER UNIQUE REFERENCES tickets(id) ON DELETE CASCADE,
    customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE,
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    feedback TEXT,
    rated_by_name VARCHAR(100),
    rated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_escalations (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    escalated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    escalated_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
    reason TEXT NOT NULL,
    previous_priority VARCHAR(20),
    new_priority VARCHAR(20),
    previous_assigned_to INTEGER,
    status VARCHAR(20) DEFAULT 'active',
    resolved_at TIMESTAMP,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_sla_logs (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    event_type VARCHAR(50) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sla_business_hours (
    id SERIAL PRIMARY KEY,
    day_of_week INTEGER NOT NULL CHECK (day_of_week >= 0 AND day_of_week <= 6),
    start_time TIME NOT NULL DEFAULT '08:00',
    end_time TIME NOT NULL DEFAULT '17:00',
    is_working_day BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(day_of_week)
);

CREATE TABLE IF NOT EXISTS sla_holidays (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    holiday_date DATE NOT NULL,
    is_recurring BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(holiday_date)
);

-- ==========================================
-- Communications (SMS, WhatsApp)
-- ==========================================

CREATE TABLE IF NOT EXISTS sms_logs (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_type VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS whatsapp_logs (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    order_id INTEGER,
    complaint_id INTEGER,
    recipient_phone VARCHAR(100) NOT NULL,
    recipient_type VARCHAR(50) NOT NULL,
    message_type VARCHAR(50) DEFAULT 'custom',
    message TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS whatsapp_conversations (
    id SERIAL PRIMARY KEY,
    chat_id VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(30) NOT NULL,
    contact_name VARCHAR(150),
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    is_group BOOLEAN DEFAULT FALSE,
    unread_count INTEGER DEFAULT 0,
    last_message_at TIMESTAMP,
    last_message_preview TEXT,
    status VARCHAR(20) DEFAULT 'active',
    assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id SERIAL PRIMARY KEY,
    conversation_id INTEGER REFERENCES whatsapp_conversations(id) ON DELETE CASCADE,
    message_id VARCHAR(150) UNIQUE,
    direction VARCHAR(10) NOT NULL DEFAULT 'incoming',
    sender_phone VARCHAR(30),
    sender_name VARCHAR(150),
    message_type VARCHAR(30) DEFAULT 'text',
    body TEXT,
    media_url TEXT,
    media_mime_type VARCHAR(100),
    media_filename VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    is_delivered BOOLEAN DEFAULT FALSE,
    sent_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    raw_data JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- Complaints
-- ==========================================

CREATE TABLE IF NOT EXISTS complaints (
    id SERIAL PRIMARY KEY,
    complaint_number VARCHAR(30) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_email VARCHAR(100),
    customer_location TEXT,
    category VARCHAR(50) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    priority VARCHAR(20) DEFAULT 'medium',
    reviewed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TIMESTAMP,
    review_notes TEXT,
    converted_ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
    source VARCHAR(50) DEFAULT 'public',
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- M-Pesa & Payments
-- ==========================================

CREATE TABLE IF NOT EXISTS mpesa_config (
    id SERIAL PRIMARY KEY,
    config_key VARCHAR(50) UNIQUE NOT NULL,
    config_value TEXT,
    is_encrypted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id SERIAL PRIMARY KEY,
    transaction_type VARCHAR(20) NOT NULL,
    merchant_request_id VARCHAR(100),
    checkout_request_id VARCHAR(100),
    result_code INTEGER,
    result_desc TEXT,
    mpesa_receipt_number VARCHAR(50),
    transaction_date TIMESTAMP,
    phone_number VARCHAR(20),
    amount DECIMAL(12, 2),
    account_reference VARCHAR(100),
    transaction_desc TEXT,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    invoice_id INTEGER,
    status VARCHAR(20) DEFAULT 'pending',
    raw_callback JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS mpesa_c2b_transactions (
    id SERIAL PRIMARY KEY,
    transaction_type VARCHAR(20),
    trans_id VARCHAR(50) UNIQUE,
    trans_time TIMESTAMP,
    trans_amount DECIMAL(12, 2),
    business_short_code VARCHAR(20),
    bill_ref_number VARCHAR(100),
    invoice_number VARCHAR(100),
    org_account_balance DECIMAL(12, 2),
    third_party_trans_id VARCHAR(100),
    msisdn VARCHAR(20),
    first_name VARCHAR(100),
    middle_name VARCHAR(100),
    last_name VARCHAR(100),
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'received',
    raw_data JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS mpesa_b2c_transactions (
    id SERIAL PRIMARY KEY,
    conversation_id VARCHAR(100),
    originator_conversation_id VARCHAR(100),
    command_id VARCHAR(50),
    phone VARCHAR(20) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    remarks TEXT,
    occasion VARCHAR(100),
    purpose VARCHAR(50),
    reference_id INTEGER,
    reference_type VARCHAR(50),
    initiated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'pending',
    result_code INTEGER,
    result_desc TEXT,
    transaction_id VARCHAR(100),
    transaction_receipt VARCHAR(100),
    receiver_party_public_name VARCHAR(255),
    callback_payload JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS mpesa_b2b_transactions (
    id SERIAL PRIMARY KEY,
    conversation_id VARCHAR(100),
    originator_conversation_id VARCHAR(100),
    command_id VARCHAR(50),
    receiver_shortcode VARCHAR(20) NOT NULL,
    receiver_type VARCHAR(10),
    amount DECIMAL(12, 2) NOT NULL,
    account_reference VARCHAR(100),
    remarks TEXT,
    reference_id INTEGER,
    reference_type VARCHAR(50),
    initiated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'pending',
    result_code INTEGER,
    result_desc TEXT,
    transaction_id VARCHAR(100),
    debit_party_name VARCHAR(255),
    credit_party_name VARCHAR(255),
    callback_payload JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP
);

-- ==========================================
-- Service Packages & Orders
-- ==========================================

CREATE TABLE IF NOT EXISTS service_packages (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    speed VARCHAR(50) NOT NULL,
    speed_unit VARCHAR(10) DEFAULT 'Mbps',
    price DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'KES',
    billing_cycle VARCHAR(20) DEFAULT 'monthly',
    features JSONB DEFAULT '[]',
    is_popular BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INTEGER DEFAULT 0,
    badge_text VARCHAR(50),
    badge_color VARCHAR(20),
    icon VARCHAR(50) DEFAULT 'wifi',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS salespersons (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    commission_type VARCHAR(20) DEFAULT 'percentage',
    commission_value DECIMAL(10, 2) DEFAULT 0,
    total_sales DECIMAL(12, 2) DEFAULT 0,
    total_commission DECIMAL(12, 2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    package_id INTEGER REFERENCES service_packages(id) ON DELETE SET NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20) NOT NULL,
    customer_address TEXT,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    payment_status VARCHAR(20) DEFAULT 'pending',
    payment_method VARCHAR(20),
    mpesa_transaction_id INTEGER REFERENCES mpesa_transactions(id) ON DELETE SET NULL,
    amount DECIMAL(12, 2),
    order_status VARCHAR(20) DEFAULT 'new',
    notes TEXT,
    assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
    converted_ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
    salesperson_id INTEGER REFERENCES salespersons(id) ON DELETE SET NULL,
    commission_paid BOOLEAN DEFAULT FALSE,
    lead_source VARCHAR(50) DEFAULT 'web',
    source VARCHAR(20) DEFAULT 'web',
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sales_commissions (
    id SERIAL PRIMARY KEY,
    salesperson_id INTEGER REFERENCES salespersons(id) ON DELETE CASCADE,
    order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
    order_amount DECIMAL(12, 2) NOT NULL,
    commission_type VARCHAR(20) NOT NULL,
    commission_rate DECIMAL(10, 2) NOT NULL,
    commission_amount DECIMAL(12, 2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    paid_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- HR & Payroll
-- ==========================================

CREATE TABLE IF NOT EXISTS attendance (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    date DATE NOT NULL,
    clock_in TIME,
    clock_out TIME,
    clock_in_latitude DECIMAL(10, 8),
    clock_in_longitude DECIMAL(11, 8),
    clock_out_latitude DECIMAL(10, 8),
    clock_out_longitude DECIMAL(11, 8),
    clock_in_address TEXT,
    clock_out_address TEXT,
    status VARCHAR(20) DEFAULT 'present',
    hours_worked DECIMAL(5, 2),
    overtime_hours DECIMAL(5, 2) DEFAULT 0,
    late_minutes INTEGER DEFAULT 0,
    deduction DECIMAL(10,2) DEFAULT 0,
    source VARCHAR(20) DEFAULT 'manual',
    biometric_log_id INTEGER,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(employee_id, date)
);

CREATE TABLE IF NOT EXISTS employee_kyc_documents (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    document_type VARCHAR(50) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP,
    verified_by INTEGER REFERENCES users(id),
    notes TEXT
);

CREATE TABLE IF NOT EXISTS employee_branches (
    id SERIAL PRIMARY KEY,
    branch_id INTEGER REFERENCES branches(id) ON DELETE CASCADE,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    is_primary BOOLEAN DEFAULT FALSE,
    assigned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(employee_id, branch_id)
);

CREATE TABLE IF NOT EXISTS payroll (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    pay_period_start DATE NOT NULL,
    pay_period_end DATE NOT NULL,
    base_salary DECIMAL(12, 2) NOT NULL,
    overtime_pay DECIMAL(12, 2) DEFAULT 0,
    bonuses DECIMAL(12, 2) DEFAULT 0,
    deductions DECIMAL(12, 2) DEFAULT 0,
    tax DECIMAL(12, 2) DEFAULT 0,
    net_pay DECIMAL(12, 2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    payment_date DATE,
    payment_method VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payroll_deductions (
    id SERIAL PRIMARY KEY,
    payroll_id INTEGER REFERENCES payroll(id) ON DELETE CASCADE,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    deduction_type VARCHAR(50) NOT NULL,
    description TEXT,
    amount DECIMAL(12, 2) NOT NULL,
    details JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payroll_commissions (
    id SERIAL PRIMARY KEY,
    payroll_id INTEGER REFERENCES payroll(id) ON DELETE CASCADE,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    commission_type VARCHAR(50) NOT NULL DEFAULT 'ticket',
    description TEXT,
    amount DECIMAL(12, 2) NOT NULL,
    details JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_commission_rates (
    id SERIAL PRIMARY KEY,
    category VARCHAR(50) NOT NULL UNIQUE,
    rate DECIMAL(12, 2) NOT NULL DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'KES',
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_earnings (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL,
    category VARCHAR(50) NOT NULL,
    full_rate DECIMAL(12, 2) NOT NULL,
    earned_amount DECIMAL(12, 2) NOT NULL,
    share_count INTEGER DEFAULT 1,
    currency VARCHAR(10) DEFAULT 'KES',
    status VARCHAR(20) DEFAULT 'pending',
    payroll_id INTEGER REFERENCES payroll(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS performance_reviews (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    reviewer_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    review_period_start DATE NOT NULL,
    review_period_end DATE NOT NULL,
    overall_rating INTEGER CHECK (overall_rating >= 1 AND overall_rating <= 5),
    productivity_rating INTEGER CHECK (productivity_rating >= 1 AND productivity_rating <= 5),
    quality_rating INTEGER CHECK (quality_rating >= 1 AND quality_rating <= 5),
    teamwork_rating INTEGER CHECK (teamwork_rating >= 1 AND teamwork_rating <= 5),
    communication_rating INTEGER CHECK (communication_rating >= 1 AND communication_rating <= 5),
    goals_achieved TEXT,
    strengths TEXT,
    areas_for_improvement TEXT,
    goals_next_period TEXT,
    comments TEXT,
    status VARCHAR(20) DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS salary_advances (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    requested_amount DECIMAL(12, 2) NOT NULL,
    approved_amount DECIMAL(12, 2),
    repayment_schedule VARCHAR(20) DEFAULT 'monthly',
    installments INTEGER DEFAULT 1,
    outstanding_balance DECIMAL(12, 2),
    status VARCHAR(20) DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP,
    disbursed_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS salary_advance_repayments (
    id SERIAL PRIMARY KEY,
    advance_id INTEGER REFERENCES salary_advances(id) ON DELETE CASCADE,
    amount DECIMAL(12, 2) NOT NULL,
    repayment_date DATE NOT NULL,
    payroll_id INTEGER REFERENCES payroll(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS leave_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    days_per_year INTEGER DEFAULT 0,
    is_paid BOOLEAN DEFAULT TRUE,
    requires_approval BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS leave_balances (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    leave_type_id INTEGER REFERENCES leave_types(id) ON DELETE CASCADE,
    year INTEGER NOT NULL,
    entitled_days DECIMAL(5,2) DEFAULT 0,
    used_days DECIMAL(5,2) DEFAULT 0,
    pending_days DECIMAL(5,2) DEFAULT 0,
    carried_over DECIMAL(5,2) DEFAULT 0,
    carried_over_days DECIMAL(5,2) DEFAULT 0,
    adjusted_days DECIMAL(5,2) DEFAULT 0,
    accrued_days DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(employee_id, leave_type_id, year)
);

CREATE TABLE IF NOT EXISTS leave_requests (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    leave_type_id INTEGER REFERENCES leave_types(id) ON DELETE CASCADE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_requested DECIMAL(5,2) NOT NULL,
    reason TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS leave_calendar (
    id SERIAL PRIMARY KEY,
    date DATE NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_public_holiday BOOLEAN DEFAULT FALSE,
    branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(date, branch_id)
);

CREATE TABLE IF NOT EXISTS public_holidays (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    holiday_date DATE NOT NULL,
    is_recurring BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS late_rules (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    work_start_time TIME NOT NULL DEFAULT '09:00',
    grace_minutes INTEGER DEFAULT 15,
    deduction_tiers JSONB NOT NULL DEFAULT '[]',
    currency VARCHAR(10) DEFAULT 'KES',
    apply_to_department_id INTEGER REFERENCES departments(id) ON DELETE SET NULL,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS absent_deduction_rules (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL DEFAULT 'Default Absent Rule',
    deduction_type VARCHAR(20) NOT NULL DEFAULT 'daily_rate',
    deduction_amount DECIMAL(12, 2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hr_notification_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) DEFAULT 'attendance',
    event_type VARCHAR(50) NOT NULL,
    subject VARCHAR(200),
    sms_template TEXT,
    email_template TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    send_sms BOOLEAN DEFAULT TRUE,
    send_email BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS attendance_notification_logs (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    notification_template_id INTEGER REFERENCES hr_notification_templates(id) ON DELETE SET NULL,
    notification_type VARCHAR(50) NOT NULL,
    recipient_phone VARCHAR(20),
    recipient_email VARCHAR(100),
    message_content TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    error_message TEXT,
    sent_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- Biometric Devices
-- ==========================================

CREATE TABLE IF NOT EXISTS biometric_devices (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    device_type VARCHAR(20) NOT NULL CHECK (device_type IN ('zkteco', 'hikvision')),
    ip_address VARCHAR(45) NOT NULL,
    port INTEGER DEFAULT 4370,
    username VARCHAR(100),
    password_encrypted TEXT,
    serial_number VARCHAR(100),
    sync_interval_minutes INTEGER DEFAULT 15,
    is_active BOOLEAN DEFAULT TRUE,
    last_sync_at TIMESTAMP,
    last_sync_status VARCHAR(50),
    last_sync_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS biometric_attendance_logs (
    id SERIAL PRIMARY KEY,
    device_id INTEGER REFERENCES biometric_devices(id) ON DELETE CASCADE,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    device_user_id VARCHAR(50) NOT NULL,
    log_time TIMESTAMP NOT NULL,
    direction VARCHAR(10) CHECK (direction IN ('in', 'out', 'unknown')),
    verification_type VARCHAR(20),
    raw_data JSONB,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed BOOLEAN DEFAULT FALSE,
    UNIQUE(device_id, device_user_id, log_time)
);

CREATE TABLE IF NOT EXISTS device_user_mapping (
    id SERIAL PRIMARY KEY,
    device_id INTEGER REFERENCES biometric_devices(id) ON DELETE CASCADE,
    device_user_id VARCHAR(50) NOT NULL,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    device_user_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(device_id, device_user_id)
);

-- ==========================================
-- Inventory Management
-- ==========================================

CREATE TABLE IF NOT EXISTS inventory_warehouses (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    manager_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_locations (
    id SERIAL PRIMARY KEY,
    warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50),
    type VARCHAR(30) DEFAULT 'shelf',
    capacity INTEGER,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_purchase_orders (
    id SERIAL PRIMARY KEY,
    po_number VARCHAR(30) UNIQUE NOT NULL,
    supplier_name VARCHAR(200),
    supplier_contact VARCHAR(100),
    order_date DATE NOT NULL,
    expected_date DATE,
    status VARCHAR(20) DEFAULT 'pending',
    total_amount DECIMAL(12, 2) DEFAULT 0,
    notes TEXT,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_po_items (
    id SERIAL PRIMARY KEY,
    po_id INTEGER REFERENCES inventory_purchase_orders(id) ON DELETE CASCADE,
    category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
    item_name VARCHAR(200) NOT NULL,
    quantity INTEGER NOT NULL,
    unit_price DECIMAL(12, 2) DEFAULT 0,
    received_qty INTEGER DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_receipts (
    id SERIAL PRIMARY KEY,
    receipt_number VARCHAR(30) UNIQUE NOT NULL,
    po_id INTEGER REFERENCES inventory_purchase_orders(id) ON DELETE SET NULL,
    warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
    receipt_date DATE NOT NULL,
    supplier_name VARCHAR(200),
    delivery_note VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending',
    notes TEXT,
    received_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    verified_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    verified_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_receipt_items (
    id SERIAL PRIMARY KEY,
    receipt_id INTEGER REFERENCES inventory_receipts(id) ON DELETE CASCADE,
    po_item_id INTEGER REFERENCES inventory_po_items(id) ON DELETE SET NULL,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
    category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
    item_name VARCHAR(200) NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    serial_number VARCHAR(100),
    mac_address VARCHAR(50),
    condition VARCHAR(20) DEFAULT 'new',
    location_id INTEGER REFERENCES inventory_locations(id) ON DELETE SET NULL,
    unit_cost DECIMAL(12, 2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_stock_requests (
    id SERIAL PRIMARY KEY,
    request_number VARCHAR(30) UNIQUE NOT NULL,
    requested_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
    request_type VARCHAR(30) NOT NULL DEFAULT 'technician',
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    priority VARCHAR(20) DEFAULT 'normal',
    status VARCHAR(20) DEFAULT 'pending',
    required_date DATE,
    notes TEXT,
    approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP,
    picked_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    picked_at TIMESTAMP,
    handed_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
    handover_at TIMESTAMP,
    handover_signature TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_stock_request_items (
    id SERIAL PRIMARY KEY,
    request_id INTEGER REFERENCES inventory_stock_requests(id) ON DELETE CASCADE,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
    category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
    item_name VARCHAR(200),
    quantity_requested INTEGER NOT NULL DEFAULT 1,
    quantity_approved INTEGER DEFAULT 0,
    quantity_picked INTEGER DEFAULT 0,
    quantity_used INTEGER DEFAULT 0,
    quantity_returned INTEGER DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_usage (
    id SERIAL PRIMARY KEY,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
    request_item_id INTEGER REFERENCES inventory_stock_request_items(id) ON DELETE SET NULL,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    job_type VARCHAR(50) NOT NULL DEFAULT 'installation',
    quantity INTEGER DEFAULT 1,
    usage_date DATE NOT NULL,
    notes TEXT,
    recorded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_returns (
    id SERIAL PRIMARY KEY,
    return_number VARCHAR(30) UNIQUE NOT NULL,
    request_id INTEGER REFERENCES inventory_stock_requests(id) ON DELETE SET NULL,
    returned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
    return_date DATE NOT NULL,
    return_type VARCHAR(30) DEFAULT 'unused',
    status VARCHAR(20) DEFAULT 'pending',
    notes TEXT,
    received_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    received_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_return_items (
    id SERIAL PRIMARY KEY,
    return_id INTEGER REFERENCES inventory_returns(id) ON DELETE CASCADE,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
    request_item_id INTEGER REFERENCES inventory_stock_request_items(id) ON DELETE SET NULL,
    quantity INTEGER DEFAULT 1,
    condition VARCHAR(20) DEFAULT 'good',
    location_id INTEGER REFERENCES inventory_locations(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_rma (
    id SERIAL PRIMARY KEY,
    rma_number VARCHAR(30) UNIQUE NOT NULL,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
    fault_id INTEGER,
    vendor_name VARCHAR(200),
    vendor_contact VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending',
    shipped_date DATE,
    received_date DATE,
    resolution VARCHAR(50),
    resolution_notes TEXT,
    replacement_equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_loss_reports (
    id SERIAL PRIMARY KEY,
    report_number VARCHAR(30) UNIQUE NOT NULL,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
    reported_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    loss_type VARCHAR(30) NOT NULL DEFAULT 'lost',
    loss_date DATE NOT NULL,
    description TEXT NOT NULL,
    estimated_value DECIMAL(12, 2),
    investigation_status VARCHAR(20) DEFAULT 'pending',
    investigation_notes TEXT,
    resolved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    resolved_at TIMESTAMP,
    resolution VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_stock_movements (
    id SERIAL PRIMARY KEY,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
    movement_type VARCHAR(30) NOT NULL,
    from_location_id INTEGER REFERENCES inventory_locations(id) ON DELETE SET NULL,
    to_location_id INTEGER REFERENCES inventory_locations(id) ON DELETE SET NULL,
    from_warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
    to_warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
    quantity INTEGER DEFAULT 1,
    reference_type VARCHAR(30),
    reference_id INTEGER,
    notes TEXT,
    performed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_stock_levels (
    id SERIAL PRIMARY KEY,
    category_id INTEGER REFERENCES equipment_categories(id) ON DELETE CASCADE,
    warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE CASCADE,
    min_quantity INTEGER DEFAULT 0,
    max_quantity INTEGER DEFAULT 100,
    reorder_point INTEGER DEFAULT 10,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(category_id, warehouse_id)
);

CREATE TABLE IF NOT EXISTS inventory_thresholds (
    id SERIAL PRIMARY KEY,
    category_id INTEGER REFERENCES equipment_categories(id) ON DELETE CASCADE,
    warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE CASCADE,
    min_quantity INTEGER NOT NULL DEFAULT 5,
    max_quantity INTEGER DEFAULT 100,
    reorder_point INTEGER NOT NULL DEFAULT 10,
    reorder_quantity INTEGER DEFAULT 20,
    notify_on_low BOOLEAN DEFAULT TRUE,
    notify_on_excess BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(category_id, warehouse_id)
);

CREATE TABLE IF NOT EXISTS inventory_audits (
    id SERIAL PRIMARY KEY,
    audit_number VARCHAR(30) UNIQUE NOT NULL,
    warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
    audit_type VARCHAR(30) DEFAULT 'full',
    scheduled_date DATE,
    completed_date DATE,
    status VARCHAR(20) DEFAULT 'pending',
    notes TEXT,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    completed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_audit_items (
    id SERIAL PRIMARY KEY,
    audit_id INTEGER REFERENCES inventory_audits(id) ON DELETE CASCADE,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
    category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
    expected_qty INTEGER DEFAULT 0,
    actual_qty INTEGER DEFAULT 0,
    variance INTEGER DEFAULT 0,
    notes TEXT,
    verified_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    verified_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS technician_kits (
    id SERIAL PRIMARY KEY,
    kit_number VARCHAR(30) UNIQUE NOT NULL,
    employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'active',
    issued_date DATE,
    issued_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    returned_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS technician_kit_items (
    id SERIAL PRIMARY KEY,
    kit_id INTEGER REFERENCES technician_kits(id) ON DELETE CASCADE,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
    category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
    quantity INTEGER DEFAULT 1,
    issued_quantity INTEGER DEFAULT 0,
    returned_quantity INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'issued',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS equipment_assignments (
    id SERIAL PRIMARY KEY,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
    employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    assigned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    assignment_date DATE NOT NULL,
    return_date DATE,
    status VARCHAR(20) DEFAULT 'assigned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS equipment_loans (
    id SERIAL PRIMARY KEY,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    loaned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    loan_date DATE NOT NULL,
    expected_return_date DATE,
    actual_return_date DATE,
    deposit_amount DECIMAL(12, 2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'on_loan',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS equipment_faults (
    id SERIAL PRIMARY KEY,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
    reported_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    fault_date DATE NOT NULL,
    description TEXT NOT NULL,
    severity VARCHAR(20) DEFAULT 'medium',
    repair_status VARCHAR(20) DEFAULT 'pending',
    repair_date DATE,
    repair_cost DECIMAL(12, 2),
    repair_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS equipment_lifecycle_logs (
    id SERIAL PRIMARY KEY,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
    from_status VARCHAR(30),
    to_status VARCHAR(30) NOT NULL,
    changed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    reference_type VARCHAR(30),
    reference_id INTEGER,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- Network & OLT Devices
-- ==========================================

CREATE TABLE IF NOT EXISTS network_devices (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    device_type VARCHAR(50) NOT NULL DEFAULT 'olt',
    vendor VARCHAR(50),
    model VARCHAR(100),
    ip_address VARCHAR(45) NOT NULL,
    snmp_version VARCHAR(10) DEFAULT 'v2c',
    snmp_community VARCHAR(100) DEFAULT 'public',
    snmp_port INTEGER DEFAULT 161,
    snmpv3_username VARCHAR(100),
    snmpv3_auth_password_encrypted TEXT,
    snmpv3_priv_password_encrypted TEXT,
    snmpv3_auth_protocol VARCHAR(10) DEFAULT 'MD5',
    snmpv3_priv_protocol VARCHAR(10) DEFAULT 'DES',
    is_active BOOLEAN DEFAULT TRUE,
    last_poll TIMESTAMP,
    poll_status VARCHAR(50),
    poll_message TEXT,
    uptime_seconds BIGINT DEFAULT 0,
    total_onus INTEGER DEFAULT 0,
    online_onus INTEGER DEFAULT 0,
    offline_onus INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS device_onus (
    id SERIAL PRIMARY KEY,
    device_id INTEGER REFERENCES network_devices(id) ON DELETE CASCADE,
    onu_id VARCHAR(50) NOT NULL,
    serial_number VARCHAR(50),
    mac_address VARCHAR(17),
    pon_port VARCHAR(20),
    slot INTEGER,
    port INTEGER,
    onu_index INTEGER,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'unknown',
    rx_power DECIMAL(10,2),
    tx_power DECIMAL(10,2),
    distance INTEGER,
    description VARCHAR(255),
    profile VARCHAR(100),
    last_online TIMESTAMP,
    last_offline TIMESTAMP,
    last_polled TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(device_id, onu_id)
);

CREATE TABLE IF NOT EXISTS device_monitoring_log (
    id SERIAL PRIMARY KEY,
    device_id INTEGER REFERENCES network_devices(id) ON DELETE CASCADE,
    metric_type VARCHAR(50) NOT NULL,
    metric_name VARCHAR(100),
    metric_value TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- Huawei OLT Management
-- ==========================================

CREATE TABLE IF NOT EXISTS huawei_zones (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS huawei_subzones (
    id SERIAL PRIMARY KEY,
    zone_id INTEGER REFERENCES huawei_zones(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS huawei_apartments (
    id SERIAL PRIMARY KEY,
    zone_id INTEGER REFERENCES huawei_zones(id) ON DELETE CASCADE,
    subzone_id INTEGER REFERENCES huawei_subzones(id) ON DELETE SET NULL,
    name VARCHAR(150) NOT NULL,
    address TEXT,
    floors INTEGER,
    units_count INTEGER,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS huawei_odb_units (
    id SERIAL PRIMARY KEY,
    zone_id INTEGER REFERENCES huawei_zones(id) ON DELETE CASCADE,
    subzone_id INTEGER REFERENCES huawei_subzones(id) ON DELETE SET NULL,
    apartment_id INTEGER REFERENCES huawei_apartments(id) ON DELETE SET NULL,
    code VARCHAR(50) NOT NULL,
    capacity INTEGER DEFAULT 8,
    ports_used INTEGER DEFAULT 0,
    location_description TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS huawei_dba_profiles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    profile_id INTEGER,
    bandwidth_type VARCHAR(50) DEFAULT 'type3',
    assured_bandwidth INTEGER DEFAULT 0,
    max_bandwidth INTEGER DEFAULT 0,
    fixed_bandwidth INTEGER DEFAULT 0,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS huawei_onu_types (
    id SERIAL PRIMARY KEY,
    type_name VARCHAR(100) UNIQUE NOT NULL,
    vendor VARCHAR(100),
    model VARCHAR(100),
    ports_eth INTEGER DEFAULT 1,
    ports_pots INTEGER DEFAULT 0,
    ports_wifi BOOLEAN DEFAULT FALSE,
    tr069_support BOOLEAN DEFAULT FALSE,
    default_service_profile VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- WireGuard VPN
-- ==========================================

CREATE TABLE IF NOT EXISTS wireguard_servers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    enabled BOOLEAN DEFAULT TRUE,
    interface_name VARCHAR(20) DEFAULT 'wg0',
    interface_addr VARCHAR(50) NOT NULL,
    listen_port INTEGER DEFAULT 51820,
    public_key TEXT,
    private_key_encrypted TEXT,
    preshared_key_encrypted TEXT,
    mtu INTEGER DEFAULT 1420,
    dns_servers VARCHAR(255),
    post_up_cmd TEXT,
    post_down_cmd TEXT,
    health_status VARCHAR(50) DEFAULT 'unknown',
    last_handshake_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wireguard_peers (
    id SERIAL PRIMARY KEY,
    server_id INTEGER REFERENCES wireguard_servers(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    public_key TEXT NOT NULL,
    private_key_encrypted TEXT,
    preshared_key_encrypted TEXT,
    allowed_ips TEXT NOT NULL,
    endpoint VARCHAR(255),
    persistent_keepalive INTEGER DEFAULT 25,
    last_handshake_at TIMESTAMP,
    rx_bytes BIGINT DEFAULT 0,
    tx_bytes BIGINT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    is_olt_site BOOLEAN DEFAULT FALSE,
    olt_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wireguard_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- RADIUS / ISP Billing
-- ==========================================

CREATE TABLE IF NOT EXISTS isp_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string',
    category VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS radius_nas (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    secret VARCHAR(100) NOT NULL,
    type VARCHAR(50) DEFAULT 'mikrotik',
    coa_port INTEGER DEFAULT 3799,
    api_enabled BOOLEAN DEFAULT FALSE,
    api_port INTEGER DEFAULT 8728,
    api_username VARCHAR(100),
    api_password_encrypted TEXT,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS radius_packages (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    download_speed INTEGER,
    upload_speed INTEGER,
    data_quota BIGINT,
    fup_download_speed INTEGER,
    fup_upload_speed INTEGER,
    validity_days INTEGER DEFAULT 30,
    billing_cycle VARCHAR(20) DEFAULT 'monthly',
    price DECIMAL(10,2) NOT NULL,
    simultaneous_sessions INTEGER DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS radius_subscriptions (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    package_id INTEGER REFERENCES radius_packages(id) ON DELETE SET NULL,
    nas_id INTEGER REFERENCES radius_nas(id) ON DELETE SET NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_encrypted TEXT NOT NULL,
    access_type VARCHAR(20) DEFAULT 'pppoe',
    static_ip VARCHAR(45),
    mac_address VARCHAR(17),
    ip_pool VARCHAR(100),
    status VARCHAR(20) DEFAULT 'active',
    start_date DATE,
    expiry_date DATE,
    data_used BIGINT DEFAULT 0,
    is_postpaid BOOLEAN DEFAULT FALSE,
    auto_renew BOOLEAN DEFAULT FALSE,
    suspended_at TIMESTAMP,
    days_remaining_at_suspension INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS radius_sessions (
    id SERIAL PRIMARY KEY,
    subscription_id INTEGER REFERENCES radius_subscriptions(id) ON DELETE SET NULL,
    nas_id INTEGER REFERENCES radius_nas(id) ON DELETE SET NULL,
    session_id VARCHAR(100),
    username VARCHAR(100),
    framed_ip VARCHAR(45),
    calling_station_id VARCHAR(50),
    called_station_id VARCHAR(50),
    start_time TIMESTAMP,
    stop_time TIMESTAMP,
    session_time INTEGER DEFAULT 0,
    input_octets BIGINT DEFAULT 0,
    output_octets BIGINT DEFAULT 0,
    terminate_cause VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS radius_vouchers (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    package_id INTEGER REFERENCES radius_packages(id) ON DELETE SET NULL,
    batch_id VARCHAR(50),
    status VARCHAR(20) DEFAULT 'unused',
    used_by INTEGER,
    used_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS radius_invoices (
    id SERIAL PRIMARY KEY,
    subscription_id INTEGER REFERENCES radius_subscriptions(id) ON DELETE SET NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_reference VARCHAR(100),
    paid_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS radius_ip_pools (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    start_ip VARCHAR(45) NOT NULL,
    end_ip VARCHAR(45) NOT NULL,
    subnet_mask VARCHAR(45) DEFAULT '255.255.255.0',
    gateway VARCHAR(45),
    dns_primary VARCHAR(45),
    dns_secondary VARCHAR(45),
    nas_id INTEGER REFERENCES radius_nas(id) ON DELETE SET NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS radius_billing (
    id SERIAL PRIMARY KEY,
    subscription_id INTEGER REFERENCES radius_subscriptions(id) ON DELETE SET NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_type VARCHAR(20) NOT NULL,
    payment_method VARCHAR(50),
    reference VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS radius_usage_logs (
    id SERIAL PRIMARY KEY,
    subscription_id INTEGER REFERENCES radius_subscriptions(id) ON DELETE SET NULL,
    date DATE NOT NULL,
    upload_bytes BIGINT DEFAULT 0,
    download_bytes BIGINT DEFAULT 0,
    session_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(subscription_id, date)
);

-- ==========================================
-- MikroTik VLAN & Static IP Provisioning (NEW)
-- ==========================================

CREATE TABLE IF NOT EXISTS mikrotik_vlans (
    id SERIAL PRIMARY KEY,
    nas_id INTEGER REFERENCES radius_nas(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    vlan_id INTEGER NOT NULL,
    interface VARCHAR(100) NOT NULL,
    gateway_ip VARCHAR(45),
    network_cidr VARCHAR(45),
    dhcp_pool_start VARCHAR(45),
    dhcp_pool_end VARCHAR(45),
    dhcp_server_name VARCHAR(100),
    dns_servers VARCHAR(255),
    lease_time VARCHAR(20) DEFAULT '1d',
    description TEXT,
    is_synced BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(nas_id, vlan_id)
);

CREATE TABLE IF NOT EXISTS mikrotik_provisioned_ips (
    id SERIAL PRIMARY KEY,
    subscription_id INTEGER REFERENCES radius_subscriptions(id) ON DELETE SET NULL,
    nas_id INTEGER REFERENCES radius_nas(id) ON DELETE CASCADE,
    vlan_id INTEGER REFERENCES mikrotik_vlans(id) ON DELETE SET NULL,
    ip_address VARCHAR(45) NOT NULL,
    mac_address VARCHAR(17),
    dhcp_lease_id VARCHAR(50),
    arp_entry_id VARCHAR(50),
    provision_type VARCHAR(20) DEFAULT 'dhcp_lease',
    comment TEXT,
    is_synced BOOLEAN DEFAULT FALSE,
    synced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- Accounting Module
-- ==========================================

CREATE TABLE IF NOT EXISTS tax_rates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    rate DECIMAL(5,2) NOT NULL DEFAULT 16.00,
    type VARCHAR(20) DEFAULT 'percentage',
    is_inclusive BOOLEAN DEFAULT FALSE,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS chart_of_accounts (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    category VARCHAR(50),
    description TEXT,
    parent_id INTEGER REFERENCES chart_of_accounts(id),
    is_system BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    balance DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products_services (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50),
    name VARCHAR(200) NOT NULL,
    description TEXT,
    type VARCHAR(20) DEFAULT 'service',
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    cost_price DECIMAL(12,2) DEFAULT 0,
    tax_rate_id INTEGER REFERENCES tax_rates(id),
    income_account_id INTEGER REFERENCES chart_of_accounts(id),
    expense_account_id INTEGER REFERENCES chart_of_accounts(id),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vendors (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Kenya',
    tax_pin VARCHAR(50),
    payment_terms INTEGER DEFAULT 30,
    currency VARCHAR(10) DEFAULT 'KES',
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS invoices (
    id SERIAL PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
    issue_date DATE NOT NULL DEFAULT CURRENT_DATE,
    due_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'draft',
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    amount_paid DECIMAL(12,2) DEFAULT 0,
    balance_due DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'KES',
    notes TEXT,
    terms TEXT,
    is_recurring BOOLEAN DEFAULT FALSE,
    recurring_interval VARCHAR(20),
    next_recurring_date DATE,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS invoice_items (
    id SERIAL PRIMARY KEY,
    invoice_id INTEGER REFERENCES invoices(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products_services(id),
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    tax_rate_id INTEGER REFERENCES tax_rates(id),
    tax_amount DECIMAL(12,2) DEFAULT 0,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL,
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS quotes (
    id SERIAL PRIMARY KEY,
    quote_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    issue_date DATE NOT NULL DEFAULT CURRENT_DATE,
    expiry_date DATE,
    status VARCHAR(20) DEFAULT 'draft',
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'KES',
    notes TEXT,
    terms TEXT,
    converted_to_invoice_id INTEGER REFERENCES invoices(id),
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quote_items (
    id SERIAL PRIMARY KEY,
    quote_id INTEGER REFERENCES quotes(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products_services(id),
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    tax_rate_id INTEGER REFERENCES tax_rates(id),
    tax_amount DECIMAL(12,2) DEFAULT 0,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL,
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    id SERIAL PRIMARY KEY,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    vendor_id INTEGER REFERENCES vendors(id) ON DELETE SET NULL,
    order_date DATE NOT NULL DEFAULT CURRENT_DATE,
    expected_date DATE,
    status VARCHAR(20) DEFAULT 'draft',
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'KES',
    notes TEXT,
    approved_by INTEGER REFERENCES users(id),
    approved_at TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id SERIAL PRIMARY KEY,
    purchase_order_id INTEGER REFERENCES purchase_orders(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products_services(id),
    equipment_id INTEGER REFERENCES equipment(id),
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    received_quantity DECIMAL(10,2) DEFAULT 0,
    unit_price DECIMAL(12,2) NOT NULL,
    tax_rate_id INTEGER REFERENCES tax_rates(id),
    tax_amount DECIMAL(12,2) DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL,
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS vendor_bills (
    id SERIAL PRIMARY KEY,
    bill_number VARCHAR(50) NOT NULL,
    vendor_id INTEGER REFERENCES vendors(id) ON DELETE SET NULL,
    purchase_order_id INTEGER REFERENCES purchase_orders(id),
    bill_date DATE NOT NULL DEFAULT CURRENT_DATE,
    due_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'unpaid',
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    amount_paid DECIMAL(12,2) DEFAULT 0,
    balance_due DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'KES',
    reference VARCHAR(100),
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vendor_bill_items (
    id SERIAL PRIMARY KEY,
    bill_id INTEGER REFERENCES vendor_bills(id) ON DELETE CASCADE,
    account_id INTEGER REFERENCES chart_of_accounts(id),
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    tax_rate_id INTEGER REFERENCES tax_rates(id),
    tax_amount DECIMAL(12,2) DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL,
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS expense_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    account_id INTEGER REFERENCES chart_of_accounts(id),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS expenses (
    id SERIAL PRIMARY KEY,
    expense_number VARCHAR(50),
    category_id INTEGER REFERENCES expense_categories(id),
    vendor_id INTEGER REFERENCES vendors(id),
    expense_date DATE NOT NULL DEFAULT CURRENT_DATE,
    amount DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50),
    reference VARCHAR(100),
    description TEXT,
    receipt_url TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    approved_by INTEGER REFERENCES users(id),
    approved_at TIMESTAMP,
    employee_id INTEGER REFERENCES employees(id),
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customer_payments (
    id SERIAL PRIMARY KEY,
    payment_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    invoice_id INTEGER REFERENCES invoices(id) ON DELETE SET NULL,
    payment_date DATE NOT NULL DEFAULT CURRENT_DATE,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    mpesa_transaction_id INTEGER REFERENCES mpesa_transactions(id),
    mpesa_receipt VARCHAR(50),
    reference VARCHAR(100),
    notes TEXT,
    status VARCHAR(20) DEFAULT 'completed',
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vendor_payments (
    id SERIAL PRIMARY KEY,
    payment_number VARCHAR(50) UNIQUE NOT NULL,
    vendor_id INTEGER REFERENCES vendors(id) ON DELETE SET NULL,
    bill_id INTEGER REFERENCES vendor_bills(id) ON DELETE SET NULL,
    payment_date DATE NOT NULL DEFAULT CURRENT_DATE,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    reference VARCHAR(100),
    notes TEXT,
    status VARCHAR(20) DEFAULT 'completed',
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS accounting_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- System & Miscellaneous
-- ==========================================

CREATE TABLE IF NOT EXISTS settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS company_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT,
    reference_id INTEGER,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INTEGER,
    entity_reference VARCHAR(100),
    details JSONB,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS mobile_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS schema_migrations (
    id SERIAL PRIMARY KEY,
    version VARCHAR(50) NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- Indexes for Performance
-- ==========================================

CREATE INDEX IF NOT EXISTS idx_tickets_customer ON tickets(customer_id);
CREATE INDEX IF NOT EXISTS idx_tickets_assigned ON tickets(assigned_to);
CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_created ON tickets(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_ticket_comments_ticket ON ticket_comments(ticket_id);
CREATE INDEX IF NOT EXISTS idx_sms_logs_ticket ON sms_logs(ticket_id);
CREATE INDEX IF NOT EXISTS idx_sms_logs_sent ON sms_logs(sent_at DESC);
CREATE INDEX IF NOT EXISTS idx_customers_account ON customers(account_number);
CREATE INDEX IF NOT EXISTS idx_customers_name ON customers(name);
CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers(phone);
CREATE INDEX IF NOT EXISTS idx_employees_department ON employees(department_id);
CREATE INDEX IF NOT EXISTS idx_employees_status ON employees(employment_status);
CREATE INDEX IF NOT EXISTS idx_employees_emp_id ON employees(employee_id);
CREATE INDEX IF NOT EXISTS idx_attendance_employee ON attendance(employee_id);
CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance(date);
CREATE INDEX IF NOT EXISTS idx_payroll_employee ON payroll(employee_id);
CREATE INDEX IF NOT EXISTS idx_payroll_period ON payroll(pay_period_start, pay_period_end);
CREATE INDEX IF NOT EXISTS idx_payroll_status ON payroll(status);
CREATE INDEX IF NOT EXISTS idx_performance_employee ON performance_reviews(employee_id);
CREATE INDEX IF NOT EXISTS idx_ticket_templates_category ON ticket_templates(category);
CREATE INDEX IF NOT EXISTS idx_company_settings_key ON company_settings(setting_key);
CREATE INDEX IF NOT EXISTS idx_whatsapp_logs_ticket ON whatsapp_logs(ticket_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_logs_sent ON whatsapp_logs(sent_at DESC);
CREATE INDEX IF NOT EXISTS idx_biometric_devices_active ON biometric_devices(is_active);
CREATE INDEX IF NOT EXISTS idx_biometric_logs_device ON biometric_attendance_logs(device_id);
CREATE INDEX IF NOT EXISTS idx_biometric_logs_employee ON biometric_attendance_logs(employee_id);
CREATE INDEX IF NOT EXISTS idx_biometric_logs_time ON biometric_attendance_logs(log_time);
CREATE INDEX IF NOT EXISTS idx_biometric_logs_processed ON biometric_attendance_logs(processed);
CREATE INDEX IF NOT EXISTS idx_device_mapping_device ON device_user_mapping(device_id);
CREATE INDEX IF NOT EXISTS idx_device_mapping_employee ON device_user_mapping(employee_id);
CREATE INDEX IF NOT EXISTS idx_late_rules_active ON late_rules(is_active);
CREATE INDEX IF NOT EXISTS idx_payroll_deductions_payroll ON payroll_deductions(payroll_id);
CREATE INDEX IF NOT EXISTS idx_payroll_deductions_employee ON payroll_deductions(employee_id);
CREATE INDEX IF NOT EXISTS idx_payroll_deductions_type ON payroll_deductions(deduction_type);
CREATE INDEX IF NOT EXISTS idx_service_packages_active ON service_packages(is_active);
CREATE INDEX IF NOT EXISTS idx_service_packages_order ON service_packages(display_order);
CREATE INDEX IF NOT EXISTS idx_radius_subscriptions_username ON radius_subscriptions(username);
CREATE INDEX IF NOT EXISTS idx_radius_subscriptions_status ON radius_subscriptions(status);
CREATE INDEX IF NOT EXISTS idx_radius_sessions_username ON radius_sessions(username);
CREATE INDEX IF NOT EXISTS idx_radius_sessions_active ON radius_sessions(is_active);
CREATE INDEX IF NOT EXISTS idx_radius_vouchers_code ON radius_vouchers(code);
CREATE INDEX IF NOT EXISTS idx_radius_vouchers_status ON radius_vouchers(status);
CREATE INDEX IF NOT EXISTS idx_isp_settings_category ON isp_settings(category);
CREATE INDEX IF NOT EXISTS idx_isp_settings_key ON isp_settings(setting_key);
CREATE INDEX IF NOT EXISTS idx_invoices_customer ON invoices(customer_id);
CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices(status);
CREATE INDEX IF NOT EXISTS idx_invoices_due_date ON invoices(due_date);
CREATE INDEX IF NOT EXISTS idx_quotes_customer ON quotes(customer_id);
CREATE INDEX IF NOT EXISTS idx_vendor_bills_vendor ON vendor_bills(vendor_id);
CREATE INDEX IF NOT EXISTS idx_vendor_bills_status ON vendor_bills(status);
CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses(category_id);
CREATE INDEX IF NOT EXISTS idx_customer_payments_invoice ON customer_payments(invoice_id);
CREATE INDEX IF NOT EXISTS idx_vendor_payments_bill ON vendor_payments(bill_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_user ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_entity ON activity_logs(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_created ON activity_logs(created_at DESC);

-- ==========================================
-- End of Schema
-- ==========================================
