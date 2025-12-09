-- Fix Missing Columns Migration
-- Run this on production: docker exec -i isp_crm_db psql -U crm -d isp_crm < migrations/fix_missing_columns.sql

-- SLA Policies table (required for tickets)
CREATE TABLE IF NOT EXISTS sla_policies (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    priority VARCHAR(50),
    response_time_hours INTEGER DEFAULT 4,
    resolution_time_hours INTEGER DEFAULT 24,
    business_hours_only BOOLEAN DEFAULT true,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teams table (required for tickets)
CREATE TABLE IF NOT EXISTS teams (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    leader_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT true,
    branch_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add missing columns to tickets table
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_policy_id INTEGER REFERENCES sla_policies(id) ON DELETE SET NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS first_response_at TIMESTAMP;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_response_due TIMESTAMP;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_resolution_due TIMESTAMP;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_response_breached BOOLEAN DEFAULT false;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_resolution_breached BOOLEAN DEFAULT false;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_paused_at TIMESTAMP;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_paused_duration INTEGER DEFAULT 0;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS source VARCHAR(50) DEFAULT 'crm';
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS created_by INTEGER REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS is_escalated BOOLEAN DEFAULT false;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS escalation_count INTEGER DEFAULT 0;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS satisfaction_rating INTEGER;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS branch_id INTEGER;

-- Add missing column to orders table
ALTER TABLE orders ADD COLUMN IF NOT EXISTS ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_tickets_team ON tickets(team_id);
CREATE INDEX IF NOT EXISTS idx_tickets_sla_policy ON tickets(sla_policy_id);
CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_priority ON tickets(priority);
CREATE INDEX IF NOT EXISTS idx_tickets_branch ON tickets(branch_id);

-- Branches table
CREATE TABLE IF NOT EXISTS branches (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(255),
    whatsapp_group VARCHAR(255),
    manager_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Employee branches junction table
CREATE TABLE IF NOT EXISTS employee_branches (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    branch_id INTEGER NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    is_primary BOOLEAN DEFAULT false,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE(employee_id, branch_id)
);

-- Add branch_id to teams if missing
ALTER TABLE teams ADD COLUMN IF NOT EXISTS branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL;

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_employee_branches_employee ON employee_branches(employee_id);
CREATE INDEX IF NOT EXISTS idx_employee_branches_branch ON employee_branches(branch_id);
CREATE INDEX IF NOT EXISTS idx_teams_branch ON teams(branch_id);
CREATE INDEX IF NOT EXISTS idx_branches_active ON branches(is_active);

-- Insert default SLA policy if none exists
INSERT INTO sla_policies (name, description, priority, response_time_hours, resolution_time_hours, business_hours_only, is_active)
SELECT 'Standard SLA', 'Default SLA policy', 'medium', 4, 24, true, true
WHERE NOT EXISTS (SELECT 1 FROM sla_policies LIMIT 1);

-- Insert default branch if none exists
INSERT INTO branches (name, code, is_active) 
SELECT 'Head Office', 'HQ', true 
WHERE NOT EXISTS (SELECT 1 FROM branches LIMIT 1);

SELECT 'Migration completed successfully!' as status;
