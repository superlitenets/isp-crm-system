-- ============================================================================
-- ISP CRM & OMS - Complete Database Initialization Script
-- Generated: 2025-12-23
-- Description: Full PostgreSQL schema including all tables, indexes, and seed data
-- Usage: psql -U crm -d isp_crm < db_init.sql
-- Docker: docker exec -i isp_crm_db psql -U crm -d isp_crm < db_init.sql
-- ============================================================================

-- ============================================================================
-- SECTION 1: CORE SYSTEM TABLES (Roles, Users, Settings)
-- ============================================================================

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

CREATE TABLE IF NOT EXISTS schema_migrations (
    id SERIAL PRIMARY KEY,
    version VARCHAR(100) NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_log (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INTEGER,
    details JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SECTION 2: BRANCHES & DEPARTMENTS
-- ============================================================================

CREATE TABLE IF NOT EXISTS branches (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(255),
    whatsapp_group VARCHAR(255),
    manager_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS departments (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    manager_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SECTION 3: CUSTOMERS
-- ============================================================================

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
    username VARCHAR(100),
    billing_id VARCHAR(100),
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SECTION 4: EMPLOYEES & HR
-- ============================================================================

CREATE TABLE IF NOT EXISTS employees (
    id SERIAL PRIMARY KEY,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    department_id INTEGER REFERENCES departments(id) ON DELETE SET NULL,
    position VARCHAR(100) NOT NULL,
    salary DECIMAL(12, 2),
    hire_date DATE,
    employment_status VARCHAR(20) DEFAULT 'active',
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE departments DROP CONSTRAINT IF EXISTS fk_manager;
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_manager') THEN
        ALTER TABLE departments ADD CONSTRAINT fk_manager FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS employee_branches (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    branch_id INTEGER NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    is_primary BOOLEAN DEFAULT FALSE,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE(employee_id, branch_id)
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
    notes TEXT,
    late_minutes INTEGER DEFAULT 0,
    deduction DECIMAL(10,2) DEFAULT 0,
    source VARCHAR(20) DEFAULT 'manual',
    biometric_log_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(employee_id, date)
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

-- ============================================================================
-- SECTION 5: SALARY ADVANCES & LEAVE MANAGEMENT
-- ============================================================================

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

CREATE TABLE IF NOT EXISTS leave_calendar (
    id SERIAL PRIMARY KEY,
    date DATE NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_public_holiday BOOLEAN DEFAULT TRUE,
    branch_id INTEGER REFERENCES branches(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(date, branch_id)
);

-- ============================================================================
-- SECTION 6: BIOMETRIC DEVICES
-- ============================================================================

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

-- ============================================================================
-- SECTION 7: EQUIPMENT & INVENTORY
-- ============================================================================

CREATE TABLE IF NOT EXISTS equipment_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
    item_type VARCHAR(30) DEFAULT 'serialized',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_warehouses (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    address TEXT,
    phone VARCHAR(20),
    manager_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_locations (
    id SERIAL PRIMARY KEY,
    warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS equipment (
    id SERIAL PRIMARY KEY,
    category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
    name VARCHAR(100) NOT NULL,
    serial_number VARCHAR(100),
    model VARCHAR(100),
    manufacturer VARCHAR(100),
    brand VARCHAR(100),
    mac_address VARCHAR(50),
    sku VARCHAR(50),
    barcode VARCHAR(100),
    purchase_date DATE,
    purchase_price DECIMAL(12, 2),
    warranty_expiry DATE,
    status VARCHAR(20) DEFAULT 'available',
    condition VARCHAR(20) DEFAULT 'good',
    location VARCHAR(100),
    warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
    location_id INTEGER REFERENCES inventory_locations(id) ON DELETE SET NULL,
    quantity INTEGER DEFAULT 1,
    lifecycle_status VARCHAR(30) DEFAULT 'in_stock',
    last_lifecycle_change TIMESTAMP,
    installed_customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    installed_at TIMESTAMP,
    installed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS equipment_assignments (
    id SERIAL PRIMARY KEY,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
    employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    assigned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    assigned_date DATE NOT NULL,
    return_date DATE,
    status VARCHAR(20) DEFAULT 'assigned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS equipment_faults (
    id SERIAL PRIMARY KEY,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
    reported_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    fault_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    severity VARCHAR(20) DEFAULT 'medium',
    status VARCHAR(20) DEFAULT 'reported',
    resolution TEXT,
    resolved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    resolved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS equipment_loans (
    id SERIAL PRIMARY KEY,
    loan_number VARCHAR(30) UNIQUE NOT NULL,
    equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    loan_date DATE NOT NULL,
    expected_return_date DATE,
    actual_return_date DATE,
    deposit_amount DECIMAL(12, 2) DEFAULT 0,
    deposit_paid BOOLEAN DEFAULT FALSE,
    rental_fee DECIMAL(12, 2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    condition_on_loan VARCHAR(20),
    condition_on_return VARCHAR(20),
    notes TEXT,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_vendors (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(30) UNIQUE,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(50),
    address TEXT,
    payment_terms VARCHAR(100),
    tax_id VARCHAR(50),
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_purchase_orders (
    id SERIAL PRIMARY KEY,
    po_number VARCHAR(30) UNIQUE NOT NULL,
    vendor_id INTEGER REFERENCES inventory_vendors(id) ON DELETE SET NULL,
    warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
    order_date DATE NOT NULL,
    expected_delivery DATE,
    status VARCHAR(20) DEFAULT 'draft',
    subtotal DECIMAL(12, 2) DEFAULT 0,
    tax_amount DECIMAL(12, 2) DEFAULT 0,
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
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_cost DECIMAL(12, 2) DEFAULT 0,
    total_cost DECIMAL(12, 2) DEFAULT 0,
    received_qty INTEGER DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_receipts (
    id SERIAL PRIMARY KEY,
    receipt_number VARCHAR(30) UNIQUE NOT NULL,
    po_id INTEGER REFERENCES inventory_purchase_orders(id) ON DELETE SET NULL,
    warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
    vendor_id INTEGER REFERENCES inventory_vendors(id) ON DELETE SET NULL,
    receipt_date DATE NOT NULL,
    receipt_type VARCHAR(30) DEFAULT 'purchase',
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
    ticket_id INTEGER,
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
    ticket_id INTEGER,
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
    fault_id INTEGER REFERENCES equipment_faults(id) ON DELETE SET NULL,
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

-- ============================================================================
-- SECTION 8: TICKETS & SLA
-- ============================================================================

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

CREATE TABLE IF NOT EXISTS ticket_commission_rates (
    id SERIAL PRIMARY KEY,
    category VARCHAR(50) NOT NULL UNIQUE,
    rate DECIMAL(12, 2) NOT NULL DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'KES',
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    require_sla_compliance BOOLEAN DEFAULT FALSE,
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

CREATE TABLE IF NOT EXISTS tickets (
    id SERIAL PRIMARY KEY,
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE,
    assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL,
    branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL,
    sla_policy_id INTEGER REFERENCES sla_policies(id) ON DELETE SET NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    priority VARCHAR(20) DEFAULT 'medium',
    status VARCHAR(20) DEFAULT 'open',
    source VARCHAR(50) DEFAULT 'internal',
    is_escalated BOOLEAN DEFAULT FALSE,
    escalation_count INTEGER DEFAULT 0,
    satisfaction_rating INTEGER,
    closure_details JSONB DEFAULT '{}',
    equipment_used_id INTEGER REFERENCES equipment(id),
    first_response_at TIMESTAMP,
    sla_response_due TIMESTAMP,
    sla_resolution_due TIMESTAMP,
    sla_response_breached BOOLEAN DEFAULT FALSE,
    sla_resolution_breached BOOLEAN DEFAULT FALSE,
    sla_paused_at TIMESTAMP,
    sla_paused_duration INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP,
    closed_at TIMESTAMP
);

ALTER TABLE inventory_stock_requests ADD CONSTRAINT fk_stock_request_ticket 
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL;
ALTER TABLE inventory_usage ADD CONSTRAINT fk_usage_ticket 
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS ticket_comments (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    comment TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_sla_logs (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    event_type VARCHAR(50) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    sla_compliant BOOLEAN DEFAULT TRUE,
    sla_note TEXT,
    payroll_id INTEGER REFERENCES payroll(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_status_tokens (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    token_lookup VARCHAR(64) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    usage_count INTEGER DEFAULT 0,
    max_uses INTEGER DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS customer_ticket_tokens (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    token_lookup VARCHAR(64) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    usage_count INTEGER DEFAULT 0,
    max_uses INTEGER DEFAULT 50,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS service_fee_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    default_amount DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'KES',
    is_active BOOLEAN DEFAULT TRUE,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_service_fees (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    fee_type_id INTEGER REFERENCES service_fee_types(id) ON DELETE SET NULL,
    fee_name VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'KES',
    notes TEXT,
    is_paid BOOLEAN DEFAULT FALSE,
    paid_at TIMESTAMP,
    payment_reference VARCHAR(100),
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SECTION 9: COMPLAINTS & ORDERS
-- ============================================================================

CREATE TABLE IF NOT EXISTS complaints (
    id SERIAL PRIMARY KEY,
    complaint_number VARCHAR(30) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_email VARCHAR(100),
    customer_address TEXT,
    customer_location TEXT,
    category VARCHAR(50) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    priority VARCHAR(20) DEFAULT 'medium',
    source VARCHAR(50) DEFAULT 'public',
    reviewed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TIMESTAMP,
    review_notes TEXT,
    approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP,
    rejection_reason TEXT,
    converted_ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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

CREATE TABLE IF NOT EXISTS salespersons (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    code VARCHAR(20) UNIQUE,
    commission_rate DECIMAL(5,2) DEFAULT 10,
    sales_target DECIMAL(12,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
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
    salesperson_id INTEGER REFERENCES salespersons(id) ON DELETE SET NULL,
    commission_paid BOOLEAN DEFAULT FALSE,
    lead_source VARCHAR(50) DEFAULT 'web',
    converted_ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
    source VARCHAR(20) DEFAULT 'web',
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SECTION 10: MESSAGING (SMS, WhatsApp)
-- ============================================================================

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
    order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
    complaint_id INTEGER REFERENCES complaints(id) ON DELETE CASCADE,
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
    phone_number VARCHAR(20),
    contact_name VARCHAR(255),
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    last_message_preview TEXT,
    last_message_time TIMESTAMP,
    unread_count INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id SERIAL PRIMARY KEY,
    conversation_id INTEGER REFERENCES whatsapp_conversations(id) ON DELETE CASCADE,
    message_id VARCHAR(100) UNIQUE,
    direction VARCHAR(10) NOT NULL,
    message_type VARCHAR(20) DEFAULT 'text',
    content TEXT,
    media_url TEXT,
    media_mime_type VARCHAR(100),
    sender_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'sent',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

CREATE TABLE IF NOT EXISTS announcements (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priority VARCHAR(20) DEFAULT 'normal',
    target_audience VARCHAR(50) DEFAULT 'all',
    target_branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL,
    target_team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL,
    send_sms BOOLEAN DEFAULT FALSE,
    send_notification BOOLEAN DEFAULT TRUE,
    scheduled_at TIMESTAMP,
    sent_at TIMESTAMP,
    status VARCHAR(20) DEFAULT 'draft',
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS announcement_recipients (
    id SERIAL PRIMARY KEY,
    announcement_id INTEGER NOT NULL REFERENCES announcements(id) ON DELETE CASCADE,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    sms_sent BOOLEAN DEFAULT FALSE,
    sms_sent_at TIMESTAMP,
    notification_sent BOOLEAN DEFAULT FALSE,
    notification_read BOOLEAN DEFAULT FALSE,
    notification_read_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SECTION 11: ACCOUNTING & FINANCE
-- ============================================================================

CREATE TABLE IF NOT EXISTS accounting_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tax_rates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    rate DECIMAL(5,2) NOT NULL,
    description TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS chart_of_accounts (
    id SERIAL PRIMARY KEY,
    account_code VARCHAR(20) UNIQUE NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_type VARCHAR(50) NOT NULL,
    parent_id INTEGER REFERENCES chart_of_accounts(id) ON DELETE SET NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products_services (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    sku VARCHAR(50) UNIQUE,
    description TEXT,
    product_type VARCHAR(20) DEFAULT 'service',
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    cost_price DECIMAL(12,2) DEFAULT 0,
    tax_rate_id INTEGER REFERENCES tax_rates(id) ON DELETE SET NULL,
    income_account_id INTEGER REFERENCES chart_of_accounts(id) ON DELETE SET NULL,
    expense_account_id INTEGER REFERENCES chart_of_accounts(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vendors (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(50),
    address TEXT,
    tax_id VARCHAR(50),
    payment_terms INTEGER DEFAULT 30,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customer_invoices (
    id SERIAL PRIMARY KEY,
    invoice_number VARCHAR(30) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    amount_paid DECIMAL(12,2) DEFAULT 0,
    balance_due DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'draft',
    notes TEXT,
    terms TEXT,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customer_invoice_items (
    id SERIAL PRIMARY KEY,
    invoice_id INTEGER REFERENCES customer_invoices(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products_services(id) ON DELETE SET NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    tax_rate_id INTEGER REFERENCES tax_rates(id) ON DELETE SET NULL,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS invoice_payments (
    id SERIAL PRIMARY KEY,
    invoice_id INTEGER REFERENCES customer_invoices(id) ON DELETE CASCADE,
    payment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50),
    reference_number VARCHAR(100),
    mpesa_transaction_id INTEGER REFERENCES mpesa_transactions(id) ON DELETE SET NULL,
    notes TEXT,
    recorded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quotes (
    id SERIAL PRIMARY KEY,
    quote_number VARCHAR(30) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    quote_date DATE NOT NULL,
    expiry_date DATE,
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'draft',
    notes TEXT,
    terms TEXT,
    converted_to_invoice_id INTEGER REFERENCES customer_invoices(id) ON DELETE SET NULL,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quote_items (
    id SERIAL PRIMARY KEY,
    quote_id INTEGER REFERENCES quotes(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products_services(id) ON DELETE SET NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    tax_rate_id INTEGER REFERENCES tax_rates(id) ON DELETE SET NULL,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vendor_bills (
    id SERIAL PRIMARY KEY,
    bill_number VARCHAR(30) UNIQUE NOT NULL,
    vendor_id INTEGER REFERENCES vendors(id) ON DELETE SET NULL,
    bill_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    amount_paid DECIMAL(12,2) DEFAULT 0,
    balance_due DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'draft',
    notes TEXT,
    reminder_enabled BOOLEAN DEFAULT FALSE,
    reminder_days_before INTEGER DEFAULT 3,
    last_reminder_sent TIMESTAMP,
    reminder_count INTEGER DEFAULT 0,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vendor_bill_items (
    id SERIAL PRIMARY KEY,
    bill_id INTEGER REFERENCES vendor_bills(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products_services(id) ON DELETE SET NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    tax_rate_id INTEGER REFERENCES tax_rates(id) ON DELETE SET NULL,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL,
    account_id INTEGER REFERENCES chart_of_accounts(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bill_payments (
    id SERIAL PRIMARY KEY,
    bill_id INTEGER REFERENCES vendor_bills(id) ON DELETE CASCADE,
    payment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50),
    reference_number VARCHAR(100),
    notes TEXT,
    recorded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bill_reminders (
    id SERIAL PRIMARY KEY,
    bill_id INTEGER NOT NULL REFERENCES vendor_bills(id) ON DELETE CASCADE,
    reminder_date DATE NOT NULL,
    sent_at TIMESTAMP,
    sent_to INTEGER REFERENCES users(id),
    notification_type VARCHAR(20) DEFAULT 'both',
    is_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS expenses (
    id SERIAL PRIMARY KEY,
    expense_number VARCHAR(30) UNIQUE NOT NULL,
    expense_date DATE NOT NULL,
    vendor_id INTEGER REFERENCES vendors(id) ON DELETE SET NULL,
    account_id INTEGER REFERENCES chart_of_accounts(id) ON DELETE SET NULL,
    amount DECIMAL(12,2) NOT NULL,
    tax_rate_id INTEGER REFERENCES tax_rates(id) ON DELETE SET NULL,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    description TEXT,
    reference_number VARCHAR(100),
    payment_method VARCHAR(50),
    is_billable BOOLEAN DEFAULT FALSE,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'recorded',
    receipt_path VARCHAR(500),
    recorded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SECTION 12: NETWORK DEVICES & MONITORING
-- ============================================================================

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
    snmpv3_auth_protocol VARCHAR(20),
    snmpv3_auth_password VARCHAR(255),
    snmpv3_priv_protocol VARCHAR(20),
    snmpv3_priv_password VARCHAR(255),
    telnet_username VARCHAR(100),
    telnet_password VARCHAR(255),
    telnet_port INTEGER DEFAULT 23,
    ssh_enabled BOOLEAN DEFAULT FALSE,
    ssh_port INTEGER DEFAULT 22,
    location VARCHAR(255),
    status VARCHAR(20) DEFAULT 'unknown',
    last_polled TIMESTAMP,
    poll_interval INTEGER DEFAULT 300,
    enabled BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS device_interfaces (
    id SERIAL PRIMARY KEY,
    device_id INTEGER REFERENCES network_devices(id) ON DELETE CASCADE,
    if_index INTEGER NOT NULL,
    if_name VARCHAR(100),
    if_descr VARCHAR(255),
    if_type VARCHAR(50),
    if_speed BIGINT,
    if_status VARCHAR(20),
    in_octets BIGINT DEFAULT 0,
    out_octets BIGINT DEFAULT 0,
    in_errors BIGINT DEFAULT 0,
    out_errors BIGINT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(device_id, if_index)
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SECTION 13: HUAWEI OLT MODULE
-- ============================================================================

CREATE TABLE IF NOT EXISTS huawei_olts (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    port INTEGER DEFAULT 23,
    connection_type VARCHAR(20) DEFAULT 'telnet',
    username VARCHAR(100),
    password_encrypted TEXT,
    snmp_read_community VARCHAR(100) DEFAULT 'public',
    snmp_write_community VARCHAR(100) DEFAULT 'private',
    snmp_version VARCHAR(10) DEFAULT 'v2c',
    snmp_port INTEGER DEFAULT 161,
    snmp_status VARCHAR(50) DEFAULT 'unknown',
    snmp_last_poll TIMESTAMP,
    snmp_sys_name VARCHAR(255),
    snmp_sys_descr TEXT,
    snmp_sys_uptime VARCHAR(100),
    snmp_sys_location VARCHAR(255),
    vendor VARCHAR(50) DEFAULT 'Huawei',
    model VARCHAR(100),
    location VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_sync_at TIMESTAMP,
    last_status VARCHAR(20) DEFAULT 'unknown',
    uptime VARCHAR(100),
    temperature VARCHAR(50),
    software_version VARCHAR(100),
    firmware_version VARCHAR(100),
    boards_synced_at TIMESTAMP,
    vlans_synced_at TIMESTAMP,
    ports_synced_at TIMESTAMP,
    uplinks_synced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS huawei_service_profiles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    profile_type VARCHAR(50) DEFAULT 'internet',
    vlan_id INTEGER,
    vlan_mode VARCHAR(20) DEFAULT 'tag',
    speed_profile_up VARCHAR(50),
    speed_profile_down VARCHAR(50),
    qos_profile VARCHAR(100),
    gem_port INTEGER,
    tcont_profile VARCHAR(100),
    line_profile VARCHAR(100),
    srv_profile VARCHAR(100),
    native_vlan INTEGER,
    additional_config TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- OMS Location Management
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
    zone_id INTEGER NOT NULL REFERENCES huawei_zones(id) ON DELETE CASCADE,
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

CREATE TABLE IF NOT EXISTS huawei_onu_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    model VARCHAR(100),
    model_aliases TEXT,
    vendor VARCHAR(100) DEFAULT 'Huawei',
    eth_ports INTEGER DEFAULT 1,
    pots_ports INTEGER DEFAULT 0,
    wifi_capable BOOLEAN DEFAULT FALSE,
    wifi_dual_band BOOLEAN DEFAULT FALSE,
    catv_port BOOLEAN DEFAULT FALSE,
    usb_port BOOLEAN DEFAULT FALSE,
    pon_type VARCHAR(20) DEFAULT 'GPON',
    default_mode VARCHAR(20) DEFAULT 'bridge',
    tcont_count INTEGER DEFAULT 1,
    gemport_count INTEGER DEFAULT 1,
    recommended_line_profile VARCHAR(100),
    recommended_srv_profile VARCHAR(100),
    omci_capable BOOLEAN DEFAULT TRUE,
    tr069_capable BOOLEAN DEFAULT TRUE,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS huawei_onus (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER REFERENCES huawei_olts(id) ON DELETE CASCADE,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    onu_type_id INTEGER REFERENCES huawei_onu_types(id),
    zone_id INTEGER REFERENCES huawei_zones(id) ON DELETE SET NULL,
    subzone_id INTEGER REFERENCES huawei_subzones(id) ON DELETE SET NULL,
    apartment_id INTEGER REFERENCES huawei_apartments(id) ON DELETE SET NULL,
    odb_id INTEGER REFERENCES huawei_odb_units(id) ON DELETE SET NULL,
    sn VARCHAR(100) NOT NULL,
    name VARCHAR(100),
    description TEXT,
    frame INTEGER DEFAULT 0,
    slot INTEGER,
    port INTEGER,
    onu_id BIGINT,
    onu_type VARCHAR(100),
    mac_address VARCHAR(17),
    status VARCHAR(30) DEFAULT 'offline',
    rx_power DECIMAL(10,2),
    tx_power DECIMAL(10,2),
    olt_rx_power DECIMAL(10,2),
    distance INTEGER,
    last_down_cause VARCHAR(100),
    last_down_time TIMESTAMP,
    last_up_time TIMESTAMP,
    service_profile_id INTEGER REFERENCES huawei_service_profiles(id) ON DELETE SET NULL,
    line_profile VARCHAR(100),
    srv_profile VARCHAR(100),
    line_profile_id INTEGER,
    srv_profile_id INTEGER,
    tr069_profile_id INTEGER,
    is_authorized BOOLEAN DEFAULT FALSE,
    firmware_version VARCHAR(100),
    hardware_version VARCHAR(100),
    software_version VARCHAR(100),
    ip_address VARCHAR(45),
    config_state VARCHAR(50),
    run_state VARCHAR(50),
    auth_type VARCHAR(20) DEFAULT 'sn',
    auth_date TIMESTAMP,
    password VARCHAR(100),
    vlan_id INTEGER,
    vlan_priority INTEGER,
    ip_mode VARCHAR(20),
    zone VARCHAR(100),
    area VARCHAR(100),
    customer_name VARCHAR(100),
    optical_updated_at TIMESTAMP,
    additional_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, sn)
);

CREATE TABLE IF NOT EXISTS onu_discovery_log (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER REFERENCES huawei_olts(id) ON DELETE CASCADE,
    serial_number VARCHAR(100) NOT NULL,
    equipment_id VARCHAR(100),
    onu_type_id INTEGER REFERENCES huawei_onu_types(id),
    frame INTEGER,
    slot INTEGER,
    port INTEGER,
    discovery_method VARCHAR(50),
    discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP,
    UNIQUE(olt_id, serial_number)
);

CREATE TABLE IF NOT EXISTS huawei_provisioning_rules (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    olt_id INTEGER REFERENCES huawei_olts(id) ON DELETE CASCADE,
    match_type VARCHAR(50) DEFAULT 'onu_type',
    match_value VARCHAR(255),
    service_profile_id INTEGER REFERENCES huawei_service_profiles(id) ON DELETE CASCADE,
    auto_authorize BOOLEAN DEFAULT FALSE,
    priority INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS huawei_provisioning_logs (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER REFERENCES huawei_olts(id) ON DELETE SET NULL,
    onu_id INTEGER REFERENCES huawei_onus(id) ON DELETE SET NULL,
    action VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    message TEXT,
    details TEXT,
    command_sent TEXT,
    command_response TEXT,
    user_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS huawei_alerts (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER REFERENCES huawei_olts(id) ON DELETE CASCADE,
    onu_id INTEGER REFERENCES huawei_onus(id) ON DELETE CASCADE,
    alert_type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP,
    resolved_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS huawei_pon_ports (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER REFERENCES huawei_olts(id) ON DELETE CASCADE,
    frame INTEGER DEFAULT 0,
    slot INTEGER NOT NULL,
    port INTEGER NOT NULL,
    port_type VARCHAR(20) DEFAULT 'gpon',
    status VARCHAR(20) DEFAULT 'unknown',
    rx_power DECIMAL(10,2),
    tx_power DECIMAL(10,2),
    onu_count INTEGER DEFAULT 0,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, frame, slot, port)
);

CREATE TABLE IF NOT EXISTS huawei_command_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    command_type VARCHAR(50) NOT NULL,
    command_template TEXT NOT NULL,
    vendor VARCHAR(50) DEFAULT 'Huawei',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- OLT Cached Data Tables
CREATE TABLE IF NOT EXISTS huawei_olt_boards (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER NOT NULL REFERENCES huawei_olts(id) ON DELETE CASCADE,
    slot INTEGER NOT NULL,
    board_name VARCHAR(50),
    status VARCHAR(50),
    subtype VARCHAR(50),
    online_status VARCHAR(20),
    port_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, slot)
);

CREATE TABLE IF NOT EXISTS huawei_olt_vlans (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER NOT NULL REFERENCES huawei_olts(id) ON DELETE CASCADE,
    vlan_id INTEGER NOT NULL,
    vlan_type VARCHAR(50) DEFAULT 'smart',
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, vlan_id)
);

CREATE TABLE IF NOT EXISTS huawei_olt_pon_ports (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER NOT NULL REFERENCES huawei_olts(id) ON DELETE CASCADE,
    port_name VARCHAR(20) NOT NULL,
    port_type VARCHAR(20) DEFAULT 'GPON',
    admin_status VARCHAR(20) DEFAULT 'enable',
    oper_status VARCHAR(20),
    onu_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, port_name)
);

CREATE TABLE IF NOT EXISTS huawei_olt_uplinks (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER NOT NULL REFERENCES huawei_olts(id) ON DELETE CASCADE,
    port_name VARCHAR(20) NOT NULL,
    port_type VARCHAR(20),
    admin_status VARCHAR(20) DEFAULT 'enable',
    oper_status VARCHAR(20),
    speed VARCHAR(20),
    duplex VARCHAR(20),
    vlan_mode VARCHAR(20),
    pvid INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, port_name)
);

CREATE TABLE IF NOT EXISTS huawei_onu_mgmt_ips (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER NOT NULL REFERENCES huawei_olts(id) ON DELETE CASCADE,
    onu_id INTEGER REFERENCES huawei_onus(id) ON DELETE SET NULL,
    ip_address VARCHAR(45) NOT NULL,
    subnet_mask VARCHAR(45),
    gateway VARCHAR(45),
    vlan_id INTEGER,
    ip_type VARCHAR(20) DEFAULT 'static',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SECTION 14: TR-069/GenieACS INTEGRATION
-- ============================================================================

CREATE TABLE IF NOT EXISTS tr069_devices (
    id SERIAL PRIMARY KEY,
    onu_id INTEGER REFERENCES huawei_onus(id) ON DELETE SET NULL,
    device_id VARCHAR(255) NOT NULL,
    serial_number VARCHAR(100) NOT NULL UNIQUE,
    manufacturer VARCHAR(100),
    model VARCHAR(100),
    software_version VARCHAR(100),
    hardware_version VARCHAR(100),
    last_inform TIMESTAMP,
    last_boot TIMESTAMP,
    ip_address VARCHAR(45),
    status VARCHAR(20) DEFAULT 'unknown',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tr069_profiles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    profile_type VARCHAR(50) DEFAULT 'wifi',
    parameters JSONB NOT NULL DEFAULT '{}',
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tr069_tasks (
    id SERIAL PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL,
    task_type VARCHAR(50) NOT NULL,
    parameters JSONB,
    status VARCHAR(20) DEFAULT 'pending',
    result TEXT,
    genieacs_task_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tr069_logs (
    id SERIAL PRIMARY KEY,
    device_id VARCHAR(255),
    action VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'success',
    message TEXT,
    details JSONB,
    user_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SECTION 15: WIREGUARD VPN
-- ============================================================================

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
    routed_networks TEXT,
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

CREATE TABLE IF NOT EXISTS wireguard_subnets (
    id SERIAL PRIMARY KEY,
    vpn_peer_id INTEGER REFERENCES wireguard_peers(id) ON DELETE CASCADE,
    network_cidr VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    subnet_type VARCHAR(50) DEFAULT 'management',
    is_olt_management BOOLEAN DEFAULT FALSE,
    is_tr069_range BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SECTION 16: MOBILE & AUTH TOKENS
-- ============================================================================

CREATE TABLE IF NOT EXISTS mobile_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SECTION 17: INDEXES
-- ============================================================================

-- Core tables
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_activity_log_user ON activity_log(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_log_action ON activity_log(action);
CREATE INDEX IF NOT EXISTS idx_activity_log_created ON activity_log(created_at DESC);

-- Customers
CREATE INDEX IF NOT EXISTS idx_customers_account ON customers(account_number);
CREATE INDEX IF NOT EXISTS idx_customers_name ON customers(name);
CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers(phone);

-- Employees & HR
CREATE INDEX IF NOT EXISTS idx_employees_department ON employees(department_id);
CREATE INDEX IF NOT EXISTS idx_employees_status ON employees(employment_status);
CREATE INDEX IF NOT EXISTS idx_employees_emp_id ON employees(employee_id);
CREATE INDEX IF NOT EXISTS idx_employee_branches_employee ON employee_branches(employee_id);
CREATE INDEX IF NOT EXISTS idx_employee_branches_branch ON employee_branches(branch_id);
CREATE INDEX IF NOT EXISTS idx_attendance_employee ON attendance(employee_id);
CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance(date);
CREATE INDEX IF NOT EXISTS idx_payroll_employee ON payroll(employee_id);
CREATE INDEX IF NOT EXISTS idx_payroll_period ON payroll(pay_period_start, pay_period_end);
CREATE INDEX IF NOT EXISTS idx_payroll_status ON payroll(status);
CREATE INDEX IF NOT EXISTS idx_performance_employee ON performance_reviews(employee_id);
CREATE INDEX IF NOT EXISTS idx_late_rules_active ON late_rules(is_active);
CREATE INDEX IF NOT EXISTS idx_payroll_deductions_payroll ON payroll_deductions(payroll_id);
CREATE INDEX IF NOT EXISTS idx_payroll_deductions_employee ON payroll_deductions(employee_id);
CREATE INDEX IF NOT EXISTS idx_payroll_deductions_type ON payroll_deductions(deduction_type);

-- Salary advances & Leave
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

-- Biometric devices
CREATE INDEX IF NOT EXISTS idx_biometric_devices_active ON biometric_devices(is_active);
CREATE INDEX IF NOT EXISTS idx_biometric_logs_device ON biometric_attendance_logs(device_id);
CREATE INDEX IF NOT EXISTS idx_biometric_logs_employee ON biometric_attendance_logs(employee_id);
CREATE INDEX IF NOT EXISTS idx_biometric_logs_time ON biometric_attendance_logs(log_time);
CREATE INDEX IF NOT EXISTS idx_biometric_logs_processed ON biometric_attendance_logs(processed);
CREATE INDEX IF NOT EXISTS idx_device_mapping_device ON device_user_mapping(device_id);
CREATE INDEX IF NOT EXISTS idx_device_mapping_employee ON device_user_mapping(employee_id);

-- Tickets
CREATE INDEX IF NOT EXISTS idx_tickets_customer ON tickets(customer_id);
CREATE INDEX IF NOT EXISTS idx_tickets_assigned ON tickets(assigned_to);
CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_created ON tickets(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_tickets_branch ON tickets(branch_id);
CREATE INDEX IF NOT EXISTS idx_ticket_comments_ticket ON ticket_comments(ticket_id);
CREATE INDEX IF NOT EXISTS idx_ticket_templates_category ON ticket_templates(category);
CREATE INDEX IF NOT EXISTS idx_ticket_status_tokens_lookup ON ticket_status_tokens(token_lookup);
CREATE INDEX IF NOT EXISTS idx_ticket_status_tokens_ticket ON ticket_status_tokens(ticket_id);
CREATE INDEX IF NOT EXISTS idx_ticket_status_tokens_expires ON ticket_status_tokens(expires_at);
CREATE INDEX IF NOT EXISTS idx_ticket_status_tokens_active ON ticket_status_tokens(is_active);
CREATE INDEX IF NOT EXISTS idx_customer_ticket_tokens_lookup ON customer_ticket_tokens(token_lookup);
CREATE INDEX IF NOT EXISTS idx_customer_ticket_tokens_ticket ON customer_ticket_tokens(ticket_id);
CREATE INDEX IF NOT EXISTS idx_customer_ticket_tokens_expires ON customer_ticket_tokens(expires_at);
CREATE INDEX IF NOT EXISTS idx_customer_ticket_tokens_active ON customer_ticket_tokens(is_active);
CREATE INDEX IF NOT EXISTS idx_ticket_service_fees_ticket ON ticket_service_fees(ticket_id);

-- Messaging
CREATE INDEX IF NOT EXISTS idx_sms_logs_ticket ON sms_logs(ticket_id);
CREATE INDEX IF NOT EXISTS idx_sms_logs_sent ON sms_logs(sent_at DESC);
CREATE INDEX IF NOT EXISTS idx_whatsapp_logs_ticket ON whatsapp_logs(ticket_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_logs_sent ON whatsapp_logs(sent_at DESC);
CREATE INDEX IF NOT EXISTS idx_whatsapp_conversations_customer ON whatsapp_conversations(customer_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_conversations_phone ON whatsapp_conversations(phone_number);
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_conversation ON whatsapp_messages(conversation_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_timestamp ON whatsapp_messages(timestamp);
CREATE INDEX IF NOT EXISTS idx_announcements_status ON announcements(status);
CREATE INDEX IF NOT EXISTS idx_announcement_recipients_employee ON announcement_recipients(employee_id);
CREATE INDEX IF NOT EXISTS idx_announcement_recipients_announcement ON announcement_recipients(announcement_id);

-- Orders & Payments
CREATE INDEX IF NOT EXISTS idx_service_packages_active ON service_packages(is_active);
CREATE INDEX IF NOT EXISTS idx_service_packages_order ON service_packages(display_order);
CREATE INDEX IF NOT EXISTS idx_company_settings_key ON company_settings(setting_key);
CREATE INDEX IF NOT EXISTS idx_branches_active ON branches(is_active);
CREATE INDEX IF NOT EXISTS idx_teams_branch ON teams(branch_id);

-- Bill reminders
CREATE INDEX IF NOT EXISTS idx_bill_reminders_date ON bill_reminders(reminder_date);
CREATE INDEX IF NOT EXISTS idx_bill_reminders_bill ON bill_reminders(bill_id);

-- Huawei OLT
CREATE INDEX IF NOT EXISTS idx_huawei_olts_active ON huawei_olts(is_active);
CREATE INDEX IF NOT EXISTS idx_huawei_olts_ip ON huawei_olts(ip_address);
CREATE INDEX IF NOT EXISTS idx_huawei_profiles_active ON huawei_service_profiles(is_active);
CREATE INDEX IF NOT EXISTS idx_huawei_profiles_type ON huawei_service_profiles(profile_type);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_olt ON huawei_onus(olt_id);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_sn ON huawei_onus(sn);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_status ON huawei_onus(status);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_customer ON huawei_onus(customer_id);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_zone ON huawei_onus(zone_id);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_odb ON huawei_onus(odb_id);
CREATE INDEX IF NOT EXISTS idx_huawei_rules_olt ON huawei_provisioning_rules(olt_id);
CREATE INDEX IF NOT EXISTS idx_huawei_rules_active ON huawei_provisioning_rules(is_active);
CREATE INDEX IF NOT EXISTS idx_huawei_logs_olt ON huawei_provisioning_logs(olt_id);
CREATE INDEX IF NOT EXISTS idx_huawei_logs_onu ON huawei_provisioning_logs(onu_id);
CREATE INDEX IF NOT EXISTS idx_huawei_logs_action ON huawei_provisioning_logs(action);
CREATE INDEX IF NOT EXISTS idx_huawei_logs_status ON huawei_provisioning_logs(status);
CREATE INDEX IF NOT EXISTS idx_huawei_logs_created ON huawei_provisioning_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_huawei_alerts_olt ON huawei_alerts(olt_id);
CREATE INDEX IF NOT EXISTS idx_huawei_alerts_unread ON huawei_alerts(is_read) WHERE is_read = FALSE;
CREATE INDEX IF NOT EXISTS idx_huawei_alerts_severity ON huawei_alerts(severity);
CREATE INDEX IF NOT EXISTS idx_huawei_ports_olt ON huawei_pon_ports(olt_id);
CREATE INDEX IF NOT EXISTS idx_huawei_subzones_zone ON huawei_subzones(zone_id);
CREATE INDEX IF NOT EXISTS idx_huawei_apartments_zone ON huawei_apartments(zone_id);
CREATE INDEX IF NOT EXISTS idx_huawei_apartments_subzone ON huawei_apartments(subzone_id);
CREATE INDEX IF NOT EXISTS idx_huawei_odb_units_zone ON huawei_odb_units(zone_id);
CREATE INDEX IF NOT EXISTS idx_huawei_odb_units_apartment ON huawei_odb_units(apartment_id);
CREATE INDEX IF NOT EXISTS idx_huawei_onu_types_model ON huawei_onu_types(model);
CREATE INDEX IF NOT EXISTS idx_onu_discovery_equipment ON onu_discovery_log(equipment_id);
CREATE INDEX IF NOT EXISTS idx_olt_boards_olt ON huawei_olt_boards(olt_id);
CREATE INDEX IF NOT EXISTS idx_olt_vlans_olt ON huawei_olt_vlans(olt_id);
CREATE INDEX IF NOT EXISTS idx_olt_pon_ports_olt ON huawei_olt_pon_ports(olt_id);
CREATE INDEX IF NOT EXISTS idx_olt_uplinks_olt ON huawei_olt_uplinks(olt_id);

-- TR-069
CREATE INDEX IF NOT EXISTS idx_tr069_devices_serial ON tr069_devices(serial_number);
CREATE INDEX IF NOT EXISTS idx_tr069_devices_onu ON tr069_devices(onu_id);
CREATE INDEX IF NOT EXISTS idx_tr069_devices_status ON tr069_devices(status);
CREATE INDEX IF NOT EXISTS idx_tr069_profiles_type ON tr069_profiles(profile_type);
CREATE INDEX IF NOT EXISTS idx_tr069_tasks_device ON tr069_tasks(device_id);
CREATE INDEX IF NOT EXISTS idx_tr069_tasks_status ON tr069_tasks(status);
CREATE INDEX IF NOT EXISTS idx_tr069_logs_device ON tr069_logs(device_id);
CREATE INDEX IF NOT EXISTS idx_tr069_logs_action ON tr069_logs(action);

-- WireGuard
CREATE INDEX IF NOT EXISTS idx_wireguard_subnets_peer ON wireguard_subnets(vpn_peer_id);
CREATE INDEX IF NOT EXISTS idx_wireguard_subnets_type ON wireguard_subnets(subnet_type);

-- ============================================================================
-- SECTION 18: SEED DATA - Roles & Permissions
-- ============================================================================

INSERT INTO roles (name, display_name, description, is_system) VALUES
('admin', 'Administrator', 'Full system access with all permissions', TRUE),
('manager', 'Manager', 'Can manage most resources but limited system settings', TRUE),
('technician', 'Technician', 'Can manage tickets, customers, and basic operations', TRUE),
('salesperson', 'Salesperson', 'Can manage orders, leads, and view commissions', TRUE),
('viewer', 'Viewer', 'Read-only access to most resources', TRUE),
('support', 'Support Staff', 'Customer support access', TRUE),
('hr', 'HR Manager', 'Human resources access', TRUE),
('sales', 'Sales Manager', 'Sales and marketing access', TRUE)
ON CONFLICT (name) DO NOTHING;

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
('customers.view_all', 'View All Customers', 'customers', 'View all customers'),
-- Tickets
('tickets.view', 'View Tickets', 'tickets', 'Can view ticket list and details'),
('tickets.create', 'Create Tickets', 'tickets', 'Can create new tickets'),
('tickets.edit', 'Edit Tickets', 'tickets', 'Can edit and update tickets'),
('tickets.delete', 'Delete Tickets', 'tickets', 'Can delete tickets'),
('tickets.assign', 'Assign Tickets', 'tickets', 'Can assign tickets to technicians'),
('tickets.escalate', 'Escalate Tickets', 'tickets', 'Can escalate tickets'),
('tickets.close', 'Close Tickets', 'tickets', 'Can close/resolve tickets'),
('tickets.reopen', 'Reopen Tickets', 'tickets', 'Can reopen closed tickets'),
('tickets.sla', 'Manage SLA', 'tickets', 'Can configure SLA policies'),
('tickets.commission', 'Manage Ticket Commission', 'tickets', 'Can configure ticket commission rates'),
('tickets.view_all', 'View All Tickets', 'tickets', 'View all tickets'),
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
('orders.view_all', 'View All Orders', 'orders', 'View all orders'),
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
('complaints.view_all', 'View All Complaints', 'complaints', 'View all complaints'),
-- Sales
('sales.view', 'View Sales', 'sales', 'Can view sales dashboard'),
('sales.view_all', 'View All Sales', 'sales', 'Can view all salespersons data'),
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
-- Network
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
-- Devices
('devices.view', 'View Devices', 'devices', 'Can view biometric devices'),
('devices.manage', 'Manage Devices', 'devices', 'Can add/edit biometric devices'),
('devices.sync', 'Sync Devices', 'devices', 'Can sync attendance from devices'),
('devices.enroll', 'Enroll Users', 'devices', 'Can enroll fingerprints on devices'),
-- Teams
('teams.view', 'View Teams', 'teams', 'Can view team list'),
('teams.manage', 'Manage Teams', 'teams', 'Can create and manage teams'),
-- Settings
('settings.view', 'View Settings', 'settings', 'Can view system settings'),
('settings.manage', 'Manage Settings', 'settings', 'Can modify system settings'),
-- Huawei OLT
('huawei_olt.view', 'View Huawei OLT', 'huawei_olt', 'Can view OLT devices and ONUs'),
('huawei_olt.manage', 'Manage Huawei OLT', 'huawei_olt', 'Can manage OLT devices'),
('huawei_olt.provision', 'Provision ONUs', 'huawei_olt', 'Can authorize and provision ONUs'),
('huawei_olt.delete', 'Delete ONUs', 'huawei_olt', 'Can delete ONUs from OLT'),
-- VPN
('vpn.view', 'View VPN', 'vpn', 'Can view WireGuard VPN status'),
('vpn.manage', 'Manage VPN', 'vpn', 'Can manage VPN servers and peers')
ON CONFLICT (name) DO NOTHING;

-- ============================================================================
-- SECTION 19: SEED DATA - Leave Types
-- ============================================================================

INSERT INTO leave_types (name, code, description, days_per_year, is_paid, accrual_type) VALUES
('Annual Leave', 'ANNUAL', 'Standard annual leave entitlement', 21, TRUE, 'monthly'),
('Sick Leave', 'SICK', 'Medical sick leave', 14, TRUE, 'annual'),
('Unpaid Leave', 'UNPAID', 'Leave without pay', 0, FALSE, 'none'),
('Maternity Leave', 'MATERNITY', 'Maternity leave for new mothers', 90, TRUE, 'none'),
('Paternity Leave', 'PATERNITY', 'Paternity leave for new fathers', 14, TRUE, 'none'),
('Compassionate Leave', 'COMPASSIONATE', 'Leave for family emergencies', 5, TRUE, 'annual')
ON CONFLICT (code) DO NOTHING;

-- ============================================================================
-- SECTION 20: SEED DATA - Service Fee Types
-- ============================================================================

INSERT INTO service_fee_types (name, description, default_amount, display_order) VALUES
('Installation Fee', 'Fee for new installation of services', 1500.00, 1),
('Reconnection Fee', 'Fee for reconnecting suspended service', 500.00, 2),
('Relocation Fee', 'Fee for relocating customer equipment', 2000.00, 3),
('Equipment Rental', 'Monthly equipment rental fee', 300.00, 4),
('Router Configuration', 'Fee for router setup and configuration', 500.00, 5),
('Cable Extension', 'Fee for additional cabling work', 1000.00, 6),
('Site Survey', 'Fee for site survey and feasibility check', 500.00, 7),
('Express Service', 'Premium fee for expedited service', 1000.00, 8)
ON CONFLICT DO NOTHING;

-- ============================================================================
-- SECTION 21: SEED DATA - SLA Business Hours
-- ============================================================================

INSERT INTO sla_business_hours (day_of_week, start_time, end_time, is_working_day) VALUES
(0, '08:00', '17:00', FALSE),
(1, '08:00', '17:00', TRUE),
(2, '08:00', '17:00', TRUE),
(3, '08:00', '17:00', TRUE),
(4, '08:00', '17:00', TRUE),
(5, '08:00', '17:00', TRUE),
(6, '08:00', '13:00', TRUE)
ON CONFLICT (day_of_week) DO NOTHING;

-- ============================================================================
-- SECTION 22: SEED DATA - WireGuard Settings
-- ============================================================================

INSERT INTO wireguard_settings (setting_key, setting_value) VALUES
('vpn_enabled', 'false'),
('tr069_use_vpn_gateway', 'false'),
('tr069_acs_url', 'http://localhost:7547'),
('vpn_gateway_ip', '10.200.0.1'),
('vpn_network', '10.200.0.0/24')
ON CONFLICT (setting_key) DO NOTHING;

-- ============================================================================
-- SECTION 23: SEED DATA - GenieACS Settings
-- ============================================================================

INSERT INTO settings (setting_key, setting_value) VALUES
('genieacs_url', 'http://localhost:7557'),
('genieacs_username', ''),
('genieacs_password', ''),
('genieacs_timeout', '30'),
('genieacs_enabled', '0'),
('wa_provisioning_group', '')
ON CONFLICT (setting_key) DO NOTHING;

-- ============================================================================
-- SECTION 24: SEED DATA - TR-069 Default Profile
-- ============================================================================

INSERT INTO tr069_profiles (name, description, profile_type, parameters, is_default) VALUES
('Default WiFi', 'Standard WiFi configuration template', 'wifi', 
 '{"ssid_2g": "", "password_2g": "", "ssid_5g": "", "password_5g": "", "channel_2g": 0, "channel_5g": 0}', 
 true)
ON CONFLICT DO NOTHING;

-- ============================================================================
-- SECTION 25: SEED DATA - Huawei Command Templates
-- ============================================================================

INSERT INTO huawei_command_templates (name, description, command_type, command_template, vendor) VALUES
('Display ONU Info', 'Get ONU information', 'display_onu', 'display ont info {frame}/{slot}/{port} {onu_id}', 'Huawei'),
('Display ONU Status', 'Get ONU online status', 'display_status', 'display ont info {frame}/{slot}/{port} {onu_id} ontstate', 'Huawei'),
('Display ONU Optical', 'Get ONU optical power', 'display_optical', 'display ont optical-info {frame}/{slot}/{port} {onu_id}', 'Huawei'),
('Display Unconfigured ONT', 'List unconfigured ONTs', 'display_unconfigured', 'display ont autofind all', 'Huawei'),
('Authorize ONU SN', 'Authorize ONU by SN', 'authorize_sn', 'ont add {frame}/{slot}/{port} {onu_id} sn-auth {sn} omci ont-lineprofile-id {line_profile} ont-srvprofile-id {srv_profile} desc "{description}"', 'Huawei'),
('Authorize ONU Password', 'Authorize ONU by password', 'authorize_password', 'ont add {frame}/{slot}/{port} {onu_id} password-auth {password} omci ont-lineprofile-id {line_profile} ont-srvprofile-id {srv_profile} desc "{description}"', 'Huawei'),
('Delete ONU', 'Delete/Remove ONU', 'delete_onu', 'ont delete {frame}/{slot}/{port} {onu_id}', 'Huawei'),
('Reboot ONU', 'Reboot ONU', 'reboot_onu', 'ont reboot {frame}/{slot}/{port} {onu_id}', 'Huawei'),
('Reset ONU', 'Factory reset ONU', 'reset_onu', 'ont reset {frame}/{slot}/{port} {onu_id}', 'Huawei'),
('Disable ONU Port', 'Administratively disable ONU', 'disable_onu', 'ont port native-vlan {frame}/{slot}/{port} {onu_id} eth 1 vlan {vlan} priority 0', 'Huawei'),
('Configure Service Port', 'Add service port to ONU', 'config_service_port', 'service-port vlan {vlan} gpon {frame}/{slot}/{port} ont {onu_id} gemport {gem_port} multi-service user-vlan {user_vlan}', 'Huawei'),
('Display PON Port', 'Display PON port status', 'display_pon_port', 'display interface gpon {frame}/{slot}/{port}', 'Huawei'),
('Display All ONT', 'Display all ONTs on port', 'display_all_ont', 'display ont info {frame}/{slot}/{port} all', 'Huawei'),
('Display Board', 'Display board info', 'display_board', 'display board {frame}/{slot}', 'Huawei')
ON CONFLICT DO NOTHING;

-- ============================================================================
-- SECTION 26: SEED DATA - Huawei ONU Types
-- ============================================================================

INSERT INTO huawei_onu_types (name, model, model_aliases, eth_ports, pots_ports, wifi_capable, wifi_dual_band, usb_port, default_mode, description) VALUES
-- Single port bridge ONUs (SFU)
('HG8010H', 'HG8010H', 'HG8010,EchoLife-HG8010H', 1, 0, FALSE, FALSE, FALSE, 'bridge', 'Single GE port SFU bridge - most basic FTTH terminal'),
('HG8310M', 'HG8310M', 'HG8310,EchoLife-HG8310M', 1, 0, FALSE, FALSE, FALSE, 'bridge', 'Single GE port compact SFU bridge'),
('HG8012H', 'HG8012H', 'HG8012,EchoLife-HG8012H', 1, 1, FALSE, FALSE, FALSE, 'bridge', 'Single GE + 1 POTS bridge ONT'),
-- Multi-port bridge ONUs
('HG8040H', 'HG8040H', 'HG8040,EchoLife-HG8040H', 4, 0, FALSE, FALSE, FALSE, 'bridge', '4x GE bridge ONT without WiFi'),
('HG8240H', 'HG8240H', 'HG8240,EchoLife-HG8240H', 4, 2, FALSE, FALSE, FALSE, 'router', '4x GE + 2 POTS router without WiFi'),
('HG8040F', 'HG8040F', 'EchoLife-HG8040F', 4, 0, FALSE, FALSE, FALSE, 'bridge', '4x FE bridge ONT'),
-- HG8145 Series
('HG8145V', 'HG8145V', 'HG8145,EchoLife-HG8145V', 4, 1, TRUE, FALSE, TRUE, 'router', '4x GE + 1 POTS + WiFi 2.4GHz + USB'),
('HG8145V5', 'HG8145V5', 'HG8145V5,EchoLife-HG8145V5,EG8145V5', 4, 1, TRUE, TRUE, TRUE, 'router', '4x GE + 1 POTS + Dual-band WiFi + USB - Popular model'),
('HG8145X6', 'HG8145X6', 'EchoLife-HG8145X6', 4, 1, TRUE, TRUE, TRUE, 'router', '4x GE + 1 POTS + WiFi 6 Dual-band + USB'),
-- HG8245 Series
('HG8245H', 'HG8245H', 'HG8245,EchoLife-HG8245H', 4, 2, TRUE, FALSE, TRUE, 'router', '4x GE + 2 POTS + WiFi 2.4GHz + USB - Classic model'),
('HG8245H5', 'HG8245H5', 'HG8245H5,EchoLife-HG8245H5', 4, 2, TRUE, TRUE, TRUE, 'router', '4x GE + 2 POTS + Dual-band WiFi + USB'),
('HG8245Q', 'HG8245Q', 'HG8245Q2,EchoLife-HG8245Q', 4, 2, TRUE, FALSE, TRUE, 'router', '4x GE + 2 POTS + WiFi + CATV port'),
('HG8245W5', 'HG8245W5', 'EchoLife-HG8245W5', 4, 2, TRUE, TRUE, TRUE, 'router', '4x GE + 2 POTS + Dual-band WiFi AC + USB'),
('HG8245X6', 'HG8245X6', 'EchoLife-HG8245X6', 4, 2, TRUE, TRUE, TRUE, 'router', '4x GE + 2 POTS + WiFi 6 AX + USB - Latest high-end'),
-- HG8546 Series
('HG8546M', 'HG8546M', 'HG8546,EchoLife-HG8546M', 4, 1, TRUE, FALSE, TRUE, 'router', '1x GE + 3x FE + 1 POTS + WiFi 2.4GHz - Popular budget model'),
('HG8546V', 'HG8546V', 'EchoLife-HG8546V', 4, 1, TRUE, FALSE, TRUE, 'router', '1x GE + 3x FE + 1 POTS + WiFi'),
('HG8546V5', 'HG8546V5', 'EchoLife-HG8546V5', 4, 1, TRUE, TRUE, TRUE, 'router', '1x GE + 3x FE + 1 POTS + Dual-band WiFi'),
-- EG Series
('EG8145V5', 'EG8145V5', 'EchoLife-EG8145V5', 4, 1, TRUE, TRUE, TRUE, 'router', '4x GE + 1 POTS + Dual-band WiFi + USB - Advanced gateway'),
('EG8245H5', 'EG8245H5', 'EchoLife-EG8245H5', 4, 2, TRUE, TRUE, TRUE, 'router', '4x GE + 2 POTS + Dual-band WiFi - Premium gateway'),
('EG8247H5', 'EG8247H5', 'EchoLife-EG8247H5', 4, 2, TRUE, TRUE, TRUE, 'router', '4x GE + 2 POTS + Dual-band WiFi + CATV'),
-- HN Series
('HN8245Q', 'HN8245Q', 'EchoLife-HN8245Q', 4, 2, TRUE, FALSE, TRUE, 'router', '4x GE + 2 POTS + WiFi + CATV - Business grade'),
('HN8346Q', 'HN8346Q', 'EchoLife-HN8346Q', 4, 2, TRUE, TRUE, TRUE, 'router', '4x 2.5GE + 2 POTS + WiFi 6 - Enterprise ONT'),
-- HS Series
('HS8145V', 'HS8145V', 'EchoLife-HS8145V', 4, 1, TRUE, FALSE, TRUE, 'router', '4x GE + 1 POTS + WiFi - Smart home ready'),
('HS8145V5', 'HS8145V5', 'EchoLife-HS8145V5', 4, 1, TRUE, TRUE, TRUE, 'router', '4x GE + 1 POTS + Dual-band WiFi - Smart home'),
('HS8546V', 'HS8546V', 'EchoLife-HS8546V', 4, 1, TRUE, FALSE, TRUE, 'router', '1x GE + 3x FE + 1 POTS + WiFi - Smart home budget'),
('HS8546V5', 'HS8546V5', 'EchoLife-HS8546V5', 4, 1, TRUE, TRUE, TRUE, 'router', '1x GE + 3x FE + 1 POTS + Dual-band - Smart home budget'),
-- OptiXstar Series
('OptiXstar HN8255Ws', 'HN8255Ws', 'OptiXstar-HN8255Ws', 4, 2, TRUE, TRUE, TRUE, 'router', '4x 2.5GE + 2 POTS + WiFi 6E - Premium 10G ready'),
('OptiXstar K662c', 'K662c', 'OptiXstar-K662c', 4, 2, TRUE, TRUE, TRUE, 'router', '4x GE + 2 POTS + WiFi 6 - Next-gen ONT')
ON CONFLICT DO NOTHING;

-- ============================================================================
-- SECTION 27: SEED DATA - ISP Equipment Categories
-- ============================================================================

INSERT INTO equipment_categories (name, description, item_type) VALUES
('ONUs/ONTs', 'Optical Network Units/Terminals for FTTH connections', 'serialized'),
('Routers', 'Customer premise routers and access points', 'serialized'),
('Fiber Cables', 'Fiber optic cables and patch cords', 'consumable'),
('Drop Cables', 'Customer drop cables', 'consumable'),
('Connectors', 'Fiber connectors and adapters (SC/UPC, SC/APC)', 'consumable'),
('Splitters', 'Fiber optic splitters (1:2, 1:4, 1:8, 1:16, 1:32)', 'serialized'),
('ODBs', 'Optical Distribution Boxes', 'serialized'),
('Closure Boxes', 'Fiber closure and splice boxes', 'serialized'),
('SFP Modules', 'Small Form-factor Pluggable transceivers', 'serialized'),
('Power Supplies', 'Power adapters and UPS units', 'serialized'),
('Tools', 'Fiber splicing and testing tools', 'serialized'),
('Consumables', 'General consumable items', 'consumable')
ON CONFLICT DO NOTHING;

-- ============================================================================
-- SECTION 28: SEED DATA - Default Branch
-- ============================================================================

INSERT INTO branches (name, code, is_active) 
SELECT 'Head Office', 'HQ', true 
WHERE NOT EXISTS (SELECT 1 FROM branches LIMIT 1);

-- ============================================================================
-- SECTION 29: SEED DATA - Default Tax Rates
-- ============================================================================

INSERT INTO tax_rates (name, code, rate, description, is_default) VALUES
('VAT 16%', 'VAT16', 16.00, 'Standard VAT rate in Kenya', TRUE),
('Zero Rated', 'ZERO', 0.00, 'Zero-rated goods and services', FALSE),
('Exempt', 'EXEMPT', 0.00, 'VAT exempt items', FALSE)
ON CONFLICT (code) DO NOTHING;

-- ============================================================================
-- Complete!
-- ============================================================================

SELECT 'Database initialization complete!' as status;
