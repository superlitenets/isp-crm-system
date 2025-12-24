-- Safe Migration Script - Only adds missing columns/tables
-- Run: docker exec -i isp_crm_db psql -U crm -d isp_crm < safe_migration.sql

-- Payroll table additions
ALTER TABLE payroll ADD COLUMN IF NOT EXISTS allowances NUMERIC DEFAULT 0;

-- Huawei ONUs additions
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS discovered_eqid VARCHAR(100);
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS port_config JSONB;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS smartolt_external_id VARCHAR(100);
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS onu_type_id INTEGER;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS tr069_device_id VARCHAR(100);
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS tr069_serial VARCHAR(100);
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS tr069_ip VARCHAR(50);
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS tr069_status VARCHAR(50);
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS tr069_last_inform TIMESTAMP;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS zone_id INTEGER;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS subzone_id INTEGER;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS apartment_id INTEGER;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS odb_id INTEGER;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS olt_sync_pending BOOLEAN DEFAULT FALSE;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS optical_updated_at TIMESTAMP;

-- Salary advance repayments table
CREATE TABLE IF NOT EXISTS salary_advance_repayments (
    id SERIAL PRIMARY KEY,
    advance_id INTEGER NOT NULL REFERENCES salary_advances(id),
    payroll_id INTEGER REFERENCES payroll(id),
    amount NUMERIC(10,2) NOT NULL,
    repayment_date DATE DEFAULT CURRENT_DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Attendance additions
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS late_minutes INTEGER DEFAULT 0;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS source VARCHAR(50) DEFAULT 'manual';
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_in_latitude NUMERIC(10,8);
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_in_longitude NUMERIC(11,8);
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_out_latitude NUMERIC(10,8);
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_out_longitude NUMERIC(11,8);
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_in_address TEXT;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_out_address TEXT;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS deduction NUMERIC(10,2) DEFAULT 0;

-- Employees additions
ALTER TABLE employees ADD COLUMN IF NOT EXISTS user_id INTEGER;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS branch_id INTEGER;

-- Tickets additions
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_response_breached BOOLEAN DEFAULT FALSE;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_resolution_breached BOOLEAN DEFAULT FALSE;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS team_id INTEGER;

-- Ticket earnings table
CREATE TABLE IF NOT EXISTS ticket_earnings (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    team_id INTEGER,
    category VARCHAR(100),
    full_rate NUMERIC(10,2) DEFAULT 0,
    earned_amount NUMERIC(10,2) DEFAULT 0,
    share_count INTEGER DEFAULT 1,
    currency VARCHAR(10) DEFAULT 'KES',
    status VARCHAR(20) DEFAULT 'pending',
    payroll_id INTEGER,
    sla_compliant BOOLEAN DEFAULT TRUE,
    sla_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ticket commission rates table
CREATE TABLE IF NOT EXISTS ticket_commission_rates (
    id SERIAL PRIMARY KEY,
    category VARCHAR(100) UNIQUE NOT NULL,
    rate NUMERIC(10,2) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'KES',
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    require_sla_compliance BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Payroll commissions table
CREATE TABLE IF NOT EXISTS payroll_commissions (
    id SERIAL PRIMARY KEY,
    payroll_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    commission_type VARCHAR(50),
    description TEXT,
    amount NUMERIC(10,2) DEFAULT 0,
    details JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teams table
CREATE TABLE IF NOT EXISTS teams (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    leader_id INTEGER,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Team members table
CREATE TABLE IF NOT EXISTS team_members (
    id SERIAL PRIMARY KEY,
    team_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    role VARCHAR(50) DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(team_id, employee_id)
);

-- Salary advances additions
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS outstanding_balance NUMERIC(10,2);
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS next_deduction_date DATE;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS disbursed_at TIMESTAMP;

-- Branches table
CREATE TABLE IF NOT EXISTS branches (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    manager_id INTEGER,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Branch employees table
CREATE TABLE IF NOT EXISTS branch_employees (
    id SERIAL PRIMARY KEY,
    branch_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(branch_id, employee_id)
);

-- Leave types table
CREATE TABLE IF NOT EXISTS leave_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    days_per_year INTEGER DEFAULT 0,
    is_paid BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Leave requests table
CREATE TABLE IF NOT EXISTS leave_requests (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL,
    leave_type_id INTEGER,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_requested INTEGER,
    reason TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    approved_by INTEGER,
    approved_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Leave balances table
CREATE TABLE IF NOT EXISTS leave_balances (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL,
    leave_type_id INTEGER NOT NULL,
    year INTEGER NOT NULL,
    entitled_days INTEGER DEFAULT 0,
    used_days INTEGER DEFAULT 0,
    carried_over INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(employee_id, leave_type_id, year)
);

-- Huawei ONU types table
CREATE TABLE IF NOT EXISTS huawei_onu_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    equipment_id VARCHAR(100),
    vendor VARCHAR(100),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ONU discovery log table
CREATE TABLE IF NOT EXISTS onu_discovery_log (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER NOT NULL,
    sn VARCHAR(100),
    equipment_id VARCHAR(100),
    onu_type_id INTEGER,
    frame INTEGER,
    slot INTEGER,
    port INTEGER,
    status VARCHAR(50) DEFAULT 'discovered',
    discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    authorized_at TIMESTAMP,
    authorized_by INTEGER
);

RAISE NOTICE 'Migration completed successfully!';
