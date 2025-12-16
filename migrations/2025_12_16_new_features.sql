-- Migration: 2025_12_16 New Features
-- 1. Announcements to Employees
-- 2. Bill Manager Reminders
-- 3. Commission SLA Requirement
-- 4. Ticket Service Fees

-- =====================================================
-- 1. ANNOUNCEMENTS SYSTEM
-- =====================================================
CREATE TABLE IF NOT EXISTS announcements (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priority VARCHAR(20) DEFAULT 'normal', -- low, normal, high, urgent
    target_audience VARCHAR(50) DEFAULT 'all', -- all, branch, team, individual
    target_branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL,
    target_team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL,
    send_sms BOOLEAN DEFAULT FALSE,
    send_notification BOOLEAN DEFAULT TRUE,
    scheduled_at TIMESTAMP,
    sent_at TIMESTAMP,
    status VARCHAR(20) DEFAULT 'draft', -- draft, scheduled, sent, cancelled
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

CREATE INDEX IF NOT EXISTS idx_announcements_status ON announcements(status);
CREATE INDEX IF NOT EXISTS idx_announcement_recipients_employee ON announcement_recipients(employee_id);
CREATE INDEX IF NOT EXISTS idx_announcement_recipients_announcement ON announcement_recipients(announcement_id);

-- =====================================================
-- 2. BILL MANAGER REMINDERS
-- =====================================================
ALTER TABLE vendor_bills ADD COLUMN IF NOT EXISTS reminder_enabled BOOLEAN DEFAULT FALSE;
ALTER TABLE vendor_bills ADD COLUMN IF NOT EXISTS reminder_days_before INTEGER DEFAULT 3;
ALTER TABLE vendor_bills ADD COLUMN IF NOT EXISTS last_reminder_sent TIMESTAMP;
ALTER TABLE vendor_bills ADD COLUMN IF NOT EXISTS reminder_count INTEGER DEFAULT 0;

CREATE TABLE IF NOT EXISTS bill_reminders (
    id SERIAL PRIMARY KEY,
    bill_id INTEGER NOT NULL REFERENCES vendor_bills(id) ON DELETE CASCADE,
    reminder_date DATE NOT NULL,
    sent_at TIMESTAMP,
    sent_to INTEGER REFERENCES users(id),
    notification_type VARCHAR(20) DEFAULT 'both', -- sms, notification, both
    is_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_bill_reminders_date ON bill_reminders(reminder_date);
CREATE INDEX IF NOT EXISTS idx_bill_reminders_bill ON bill_reminders(bill_id);

-- =====================================================
-- 3. COMMISSION SLA REQUIREMENT
-- =====================================================
ALTER TABLE ticket_commission_rates ADD COLUMN IF NOT EXISTS require_sla_compliance BOOLEAN DEFAULT FALSE;
ALTER TABLE ticket_earnings ADD COLUMN IF NOT EXISTS sla_compliant BOOLEAN DEFAULT TRUE;
ALTER TABLE ticket_earnings ADD COLUMN IF NOT EXISTS sla_note TEXT;

-- =====================================================
-- 4. TICKET SERVICE FEES
-- =====================================================
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

CREATE INDEX IF NOT EXISTS idx_ticket_service_fees_ticket ON ticket_service_fees(ticket_id);

-- Insert default service fee types
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
