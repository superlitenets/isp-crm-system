-- RADIUS Billing System Schema
-- MikroTik NAS devices, packages, sessions, vouchers, and billing

-- NAS Devices (MikroTik Routers)
CREATE TABLE IF NOT EXISTS radius_nas (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    secret VARCHAR(100) NOT NULL,
    nas_type VARCHAR(50) DEFAULT 'mikrotik',
    ports INTEGER DEFAULT 1812,
    description TEXT,
    api_enabled BOOLEAN DEFAULT FALSE,
    api_port INTEGER DEFAULT 8728,
    api_username VARCHAR(100),
    api_password_encrypted TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Service Packages / Plans
CREATE TABLE IF NOT EXISTS radius_packages (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    package_type VARCHAR(20) DEFAULT 'pppoe', -- pppoe, hotspot, static, dhcp
    billing_type VARCHAR(20) DEFAULT 'monthly', -- daily, weekly, monthly, quarterly, yearly, unlimited, quota
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    validity_days INTEGER DEFAULT 30,
    data_quota_mb BIGINT, -- NULL for unlimited
    download_speed VARCHAR(20), -- e.g., "10M"
    upload_speed VARCHAR(20), -- e.g., "5M"
    burst_download VARCHAR(20),
    burst_upload VARCHAR(20),
    burst_threshold VARCHAR(20),
    burst_time VARCHAR(20),
    priority INTEGER DEFAULT 8,
    address_pool VARCHAR(100),
    ip_binding BOOLEAN DEFAULT FALSE,
    simultaneous_sessions INTEGER DEFAULT 1,
    fup_enabled BOOLEAN DEFAULT FALSE,
    fup_quota_mb BIGINT,
    fup_download_speed VARCHAR(20),
    fup_upload_speed VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customer Subscriptions
CREATE TABLE IF NOT EXISTS radius_subscriptions (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE,
    package_id INTEGER REFERENCES radius_packages(id),
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(100) NOT NULL, -- Cleartext password for RADIUS CHAP/MS-CHAP
    password_encrypted TEXT NOT NULL,
    access_type VARCHAR(20) DEFAULT 'pppoe', -- pppoe, hotspot, static, dhcp
    static_ip VARCHAR(45),
    mac_address VARCHAR(17),
    status VARCHAR(20) DEFAULT 'active', -- active, suspended, expired, terminated
    start_date DATE,
    expiry_date DATE,
    data_used_mb BIGINT DEFAULT 0,
    last_session_start TIMESTAMP,
    last_session_end TIMESTAMP,
    auto_renew BOOLEAN DEFAULT TRUE,
    grace_period_days INTEGER DEFAULT 3,
    notes TEXT,
    nas_id INTEGER REFERENCES radius_nas(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- RADIUS Sessions (Accounting)
CREATE TABLE IF NOT EXISTS radius_sessions (
    id SERIAL PRIMARY KEY,
    subscription_id INTEGER REFERENCES radius_subscriptions(id) ON DELETE CASCADE,
    acct_session_id VARCHAR(64) NOT NULL,
    nas_id INTEGER REFERENCES radius_nas(id),
    nas_ip_address VARCHAR(45),
    nas_port_id VARCHAR(50),
    framed_ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    session_start TIMESTAMP NOT NULL,
    session_end TIMESTAMP,
    session_duration INTEGER DEFAULT 0, -- seconds
    input_octets BIGINT DEFAULT 0,
    output_octets BIGINT DEFAULT 0,
    input_packets BIGINT DEFAULT 0,
    output_packets BIGINT DEFAULT 0,
    terminate_cause VARCHAR(50),
    status VARCHAR(20) DEFAULT 'active', -- active, closed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Hotspot Vouchers
CREATE TABLE IF NOT EXISTS radius_vouchers (
    id SERIAL PRIMARY KEY,
    batch_id VARCHAR(50),
    code VARCHAR(20) NOT NULL UNIQUE,
    package_id INTEGER REFERENCES radius_packages(id),
    status VARCHAR(20) DEFAULT 'unused', -- unused, used, expired, revoked
    validity_minutes INTEGER DEFAULT 60,
    data_limit_mb INTEGER,
    used_by_subscription_id INTEGER REFERENCES radius_subscriptions(id),
    used_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- IP Address Pools
CREATE TABLE IF NOT EXISTS radius_ip_pools (
    id SERIAL PRIMARY KEY,
    pool_name VARCHAR(50) NOT NULL,
    nas_id INTEGER REFERENCES radius_nas(id),
    ip_address VARCHAR(45) NOT NULL,
    status VARCHAR(20) DEFAULT 'available', -- available, assigned, reserved
    assigned_to INTEGER REFERENCES radius_subscriptions(id),
    assigned_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(nas_id, ip_address)
);

-- Billing/Payment History (links to existing payments table)
CREATE TABLE IF NOT EXISTS radius_billing (
    id SERIAL PRIMARY KEY,
    subscription_id INTEGER REFERENCES radius_subscriptions(id) ON DELETE CASCADE,
    package_id INTEGER REFERENCES radius_packages(id),
    amount DECIMAL(10,2) NOT NULL,
    billing_type VARCHAR(20), -- renewal, upgrade, downgrade, topup
    period_start DATE,
    period_end DATE,
    payment_id INTEGER,
    status VARCHAR(20) DEFAULT 'pending', -- pending, paid, failed, refunded
    invoice_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Usage Logs (for FUP and quota tracking)
CREATE TABLE IF NOT EXISTS radius_usage_logs (
    id SERIAL PRIMARY KEY,
    subscription_id INTEGER REFERENCES radius_subscriptions(id) ON DELETE CASCADE,
    log_date DATE NOT NULL,
    download_mb DECIMAL(12,2) DEFAULT 0,
    upload_mb DECIMAL(12,2) DEFAULT 0,
    session_count INTEGER DEFAULT 0,
    session_time_seconds INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(subscription_id, log_date)
);

-- Redirect Rules (for expired/suspended users)
CREATE TABLE IF NOT EXISTS radius_redirect_rules (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    condition_type VARCHAR(30) NOT NULL, -- expired, suspended, quota_exhausted, grace_period
    redirect_url TEXT NOT NULL,
    walled_garden TEXT, -- comma-separated allowed domains
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Post-Auth Log (for FreeRADIUS)
CREATE TABLE IF NOT EXISTS radpostauth (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    pass VARCHAR(255),
    reply VARCHAR(32),
    authdate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    nasipaddress VARCHAR(45),
    calledstationid VARCHAR(50),
    callingstationid VARCHAR(50)
);

-- Authentication Logs (detailed auth history with accept/reject reasons)
CREATE TABLE IF NOT EXISTS radius_auth_logs (
    id SERIAL PRIMARY KEY,
    subscription_id INTEGER REFERENCES radius_subscriptions(id) ON DELETE SET NULL,
    username VARCHAR(100) NOT NULL,
    nas_ip_address VARCHAR(45),
    mac_address VARCHAR(50),
    auth_result VARCHAR(20) NOT NULL, -- Accept, Reject
    reject_reason VARCHAR(100), -- User not found, Invalid password, MAC mismatch, Expired, Suspended, Quota exhausted
    reply_message TEXT,
    attributes JSONB, -- Store RADIUS attributes returned
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_radius_auth_logs_subscription ON radius_auth_logs(subscription_id);
CREATE INDEX IF NOT EXISTS idx_radius_auth_logs_username ON radius_auth_logs(username);
CREATE INDEX IF NOT EXISTS idx_radius_auth_logs_created ON radius_auth_logs(created_at DESC);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_radpostauth_username ON radpostauth(username);
CREATE INDEX IF NOT EXISTS idx_radpostauth_authdate ON radpostauth(authdate);
CREATE INDEX IF NOT EXISTS idx_radius_sessions_subscription ON radius_sessions(subscription_id);
CREATE INDEX IF NOT EXISTS idx_radius_sessions_status ON radius_sessions(status);
CREATE INDEX IF NOT EXISTS idx_radius_sessions_start ON radius_sessions(session_start);
CREATE INDEX IF NOT EXISTS idx_radius_subscriptions_status ON radius_subscriptions(status);
CREATE INDEX IF NOT EXISTS idx_radius_subscriptions_expiry ON radius_subscriptions(expiry_date);
CREATE INDEX IF NOT EXISTS idx_radius_subscriptions_username ON radius_subscriptions(username);
CREATE INDEX IF NOT EXISTS idx_radius_vouchers_code ON radius_vouchers(code);
CREATE INDEX IF NOT EXISTS idx_radius_vouchers_status ON radius_vouchers(status);
CREATE INDEX IF NOT EXISTS idx_radius_usage_date ON radius_usage_logs(log_date);

-- Insert default redirect rules
INSERT INTO radius_redirect_rules (name, condition_type, redirect_url, walled_garden, is_active)
VALUES 
    ('Expired Users', 'expired', '/radius/payment', 'billing.local,mpesa.safaricom.co.ke', TRUE),
    ('Suspended Users', 'suspended', '/radius/suspended', 'billing.local', TRUE),
    ('Quota Exhausted', 'quota_exhausted', '/radius/topup', 'billing.local,mpesa.safaricom.co.ke', TRUE),
    ('Grace Period', 'grace_period', '/radius/grace', 'billing.local,mpesa.safaricom.co.ke', TRUE)
ON CONFLICT DO NOTHING;

-- Insert sample packages
INSERT INTO radius_packages (name, description, package_type, billing_type, price, validity_days, download_speed, upload_speed, simultaneous_sessions)
VALUES 
    ('Home Basic 5Mbps', 'Basic home internet package', 'pppoe', 'monthly', 1500.00, 30, '5M', '2M', 1),
    ('Home Standard 10Mbps', 'Standard home internet package', 'pppoe', 'monthly', 2500.00, 30, '10M', '5M', 2),
    ('Home Premium 20Mbps', 'Premium home internet package', 'pppoe', 'monthly', 4000.00, 30, '20M', '10M', 3),
    ('Business 50Mbps', 'Business internet with SLA', 'pppoe', 'monthly', 8000.00, 30, '50M', '25M', 5),
    ('Hotspot 1 Hour', 'Hotspot voucher - 1 hour', 'hotspot', 'daily', 50.00, 1, '2M', '1M', 1),
    ('Hotspot Daily', 'Hotspot voucher - 24 hours', 'hotspot', 'daily', 150.00, 1, '5M', '2M', 1)
ON CONFLICT DO NOTHING;

-- Locations for organizing NAS devices and subscribers
CREATE TABLE IF NOT EXISTS isp_locations (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sub-locations under main locations
CREATE TABLE IF NOT EXISTS isp_sub_locations (
    id SERIAL PRIMARY KEY,
    location_id INTEGER NOT NULL REFERENCES isp_locations(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add location_id to radius_nas if not exists
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'radius_nas' AND column_name = 'location_id') THEN
        ALTER TABLE radius_nas ADD COLUMN location_id INTEGER REFERENCES isp_locations(id) ON DELETE SET NULL;
    END IF;
END $$;

-- Add location and sub_location to radius_subscriptions if not exists
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'radius_subscriptions' AND column_name = 'location_id') THEN
        ALTER TABLE radius_subscriptions ADD COLUMN location_id INTEGER REFERENCES isp_locations(id) ON DELETE SET NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'radius_subscriptions' AND column_name = 'sub_location_id') THEN
        ALTER TABLE radius_subscriptions ADD COLUMN sub_location_id INTEGER REFERENCES isp_sub_locations(id) ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_isp_sub_locations_location ON isp_sub_locations(location_id);
CREATE INDEX IF NOT EXISTS idx_radius_nas_location ON radius_nas(location_id);
CREATE INDEX IF NOT EXISTS idx_radius_subscriptions_location ON radius_subscriptions(location_id);

-- Authentication Logs (detailed auth history with accept/reject reasons)
CREATE TABLE IF NOT EXISTS radius_auth_logs (
    id SERIAL PRIMARY KEY,
    subscription_id INTEGER REFERENCES radius_subscriptions(id) ON DELETE SET NULL,
    username VARCHAR(100) NOT NULL,
    nas_ip_address VARCHAR(45),
    mac_address VARCHAR(50),
    auth_result VARCHAR(20) NOT NULL,
    reject_reason VARCHAR(100),
    reply_message TEXT,
    attributes JSONB,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_radius_auth_logs_subscription ON radius_auth_logs(subscription_id);
CREATE INDEX IF NOT EXISTS idx_radius_auth_logs_username ON radius_auth_logs(username);
CREATE INDEX IF NOT EXISTS idx_radius_auth_logs_created ON radius_auth_logs(created_at DESC);

-- Timed Speed Overrides
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS speed_override VARCHAR(50);
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS override_expires_at TIMESTAMP WITH TIME ZONE;

-- Package Speed Schedules (time-based speed changes)
CREATE TABLE IF NOT EXISTS radius_package_schedules (
    id SERIAL PRIMARY KEY,
    package_id INTEGER NOT NULL REFERENCES radius_packages(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    days_of_week VARCHAR(20) DEFAULT '0123456',
    download_speed VARCHAR(20) NOT NULL,
    upload_speed VARCHAR(20) NOT NULL,
    priority INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_package_schedules_package ON radius_package_schedules(package_id);
CREATE INDEX IF NOT EXISTS idx_package_schedules_active ON radius_package_schedules(is_active) WHERE is_active = TRUE;
