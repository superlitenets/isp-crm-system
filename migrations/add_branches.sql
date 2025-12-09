-- Multi-Branch Feature Migration
-- Run this on production: docker exec -i isp_crm_db psql -U crm -d isp_crm < migrations/add_branches.sql

-- Create branches table
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

-- Create employee_branches junction table (many-to-many)
CREATE TABLE IF NOT EXISTS employee_branches (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    branch_id INTEGER NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    is_primary BOOLEAN DEFAULT false,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE(employee_id, branch_id)
);

-- Add branch_id to tickets table
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL;

-- Add branch_id to teams table
ALTER TABLE teams ADD COLUMN IF NOT EXISTS branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_employee_branches_employee ON employee_branches(employee_id);
CREATE INDEX IF NOT EXISTS idx_employee_branches_branch ON employee_branches(branch_id);
CREATE INDEX IF NOT EXISTS idx_tickets_branch ON tickets(branch_id);
CREATE INDEX IF NOT EXISTS idx_teams_branch ON teams(branch_id);
CREATE INDEX IF NOT EXISTS idx_branches_active ON branches(is_active);

-- Insert a default "Head Office" branch if none exists
INSERT INTO branches (name, code, is_active) 
SELECT 'Head Office', 'HQ', true 
WHERE NOT EXISTS (SELECT 1 FROM branches LIMIT 1);
