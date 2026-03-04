-- Comprehensive fix for all missing columns and tables
-- Safe to run multiple times (uses IF NOT EXISTS / ADD COLUMN IF NOT EXISTS)

-- ==================== radius_nas ====================
ALTER TABLE radius_nas ADD COLUMN IF NOT EXISTS wireguard_peer_id INTEGER;
ALTER TABLE radius_nas ADD COLUMN IF NOT EXISTS mpesa_account_id INTEGER;
ALTER TABLE radius_nas ADD COLUMN IF NOT EXISTS local_ip VARCHAR(255);
ALTER TABLE radius_nas ADD COLUMN IF NOT EXISTS location_id INTEGER;
ALTER TABLE radius_nas ADD COLUMN IF NOT EXISTS sub_location_id INTEGER;

-- ==================== radius_packages ====================
ALTER TABLE radius_packages ADD COLUMN IF NOT EXISTS package_type VARCHAR(20) DEFAULT 'pppoe';
ALTER TABLE radius_packages ADD COLUMN IF NOT EXISTS speed_unit VARCHAR(10) DEFAULT 'mbps';
ALTER TABLE radius_packages ADD COLUMN IF NOT EXISTS burst_download INTEGER;
ALTER TABLE radius_packages ADD COLUMN IF NOT EXISTS burst_upload INTEGER;
ALTER TABLE radius_packages ADD COLUMN IF NOT EXISTS burst_threshold_download INTEGER;
ALTER TABLE radius_packages ADD COLUMN IF NOT EXISTS burst_threshold_upload INTEGER;
ALTER TABLE radius_packages ADD COLUMN IF NOT EXISTS burst_time INTEGER;
ALTER TABLE radius_packages ADD COLUMN IF NOT EXISTS pool_name VARCHAR(100);
ALTER TABLE radius_packages ADD COLUMN IF NOT EXISTS address_list VARCHAR(100);
ALTER TABLE radius_packages ADD COLUMN IF NOT EXISTS parent_queue VARCHAR(100);
ALTER TABLE radius_packages ADD COLUMN IF NOT EXISTS priority INTEGER DEFAULT 8;
ALTER TABLE radius_packages ADD COLUMN IF NOT EXISTS service_type VARCHAR(50);

-- ==================== radius_subscriptions ====================
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS framed_ip_address VARCHAR(45);
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS online_status VARCHAR(20) DEFAULT 'offline';
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS last_session_start TIMESTAMP;
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS last_session_end TIMESTAMP;
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS password VARCHAR(100);
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS credit_balance DECIMAL(10,2) DEFAULT 0;
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS credit_limit DECIMAL(10,2) DEFAULT 0;
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS referral_code VARCHAR(20);
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS billing_type VARCHAR(20) DEFAULT 'prepaid';
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS grace_expires_at TIMESTAMP;
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS portal_password VARCHAR(255);
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS location_id INTEGER;
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS sub_location_id INTEGER;
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS speed_override VARCHAR(50);
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS override_expires_at TIMESTAMPTZ;
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS suspended_at TIMESTAMP;
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS days_remaining_at_suspension INTEGER;
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS huawei_onu_id INTEGER;

-- ==================== radius_sessions ====================
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active';
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS session_end TIMESTAMP;
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS session_start TIMESTAMP;
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS session_duration INTEGER DEFAULT 0;
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS acct_session_id VARCHAR(64);
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS nas_ip_address VARCHAR(45);
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS nas_port_id VARCHAR(50);
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS framed_ip_address VARCHAR(45);
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS mac_address VARCHAR(17);
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS input_packets BIGINT DEFAULT 0;
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS output_packets BIGINT DEFAULT 0;
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS started_at TIMESTAMP;
ALTER TABLE radius_sessions ADD COLUMN IF NOT EXISTS stopped_at TIMESTAMP;

-- ==================== radius_billing ====================
ALTER TABLE radius_billing ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'paid';
ALTER TABLE radius_billing ADD COLUMN IF NOT EXISTS package_id INTEGER;
ALTER TABLE radius_billing ADD COLUMN IF NOT EXISTS billing_type VARCHAR(20);
ALTER TABLE radius_billing ADD COLUMN IF NOT EXISTS period_start DATE;
ALTER TABLE radius_billing ADD COLUMN IF NOT EXISTS period_end DATE;
ALTER TABLE radius_billing ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(50);
ALTER TABLE radius_billing ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE radius_billing ADD COLUMN IF NOT EXISTS paid_at TIMESTAMP;

-- ==================== radius_usage_logs ====================
ALTER TABLE radius_usage_logs ADD COLUMN IF NOT EXISTS log_date DATE;
ALTER TABLE radius_usage_logs ADD COLUMN IF NOT EXISTS upload_mb DECIMAL(12,2) DEFAULT 0;
ALTER TABLE radius_usage_logs ADD COLUMN IF NOT EXISTS download_mb DECIMAL(12,2) DEFAULT 0;

-- ==================== radius_vouchers ====================
ALTER TABLE radius_vouchers ADD COLUMN IF NOT EXISTS created_by INTEGER;

-- ==================== huawei_onu_types ====================
ALTER TABLE huawei_onu_types ADD COLUMN IF NOT EXISTS name VARCHAR(100);
ALTER TABLE huawei_onu_types ADD COLUMN IF NOT EXISTS equipment_id VARCHAR(100);
ALTER TABLE huawei_onu_types ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;

-- ==================== mpesa_accounts ====================
CREATE TABLE IF NOT EXISTS mpesa_accounts (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    shortcode VARCHAR(20) NOT NULL,
    consumer_key TEXT NOT NULL,
    consumer_secret TEXT NOT NULL,
    passkey TEXT NOT NULL,
    account_type VARCHAR(20) DEFAULT 'paybill',
    environment VARCHAR(20) DEFAULT 'production',
    callback_url TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==================== ISP Inventory tables ====================
CREATE TABLE IF NOT EXISTS isp_network_sites (
    id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL, site_type VARCHAR(50) DEFAULT 'tower',
    address TEXT, gps_lat DECIMAL(10,7), gps_lng DECIMAL(10,7), contact_person VARCHAR(100),
    contact_phone VARCHAR(20), power_source VARCHAR(50), ups_capacity VARCHAR(50),
    ups_battery_health VARCHAR(50), notes TEXT, status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_racks (
    id SERIAL PRIMARY KEY, site_id INTEGER, name VARCHAR(100) NOT NULL,
    rack_units INTEGER DEFAULT 42, used_units INTEGER DEFAULT 0,
    location_detail VARCHAR(255), status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_core_equipment (
    id SERIAL PRIMARY KEY, site_id INTEGER, rack_id INTEGER,
    name VARCHAR(100) NOT NULL, equipment_type VARCHAR(50) NOT NULL,
    brand VARCHAR(50), model VARCHAR(100), serial_number VARCHAR(100),
    ip_address VARCHAR(45), mac_address VARCHAR(17), firmware_version VARCHAR(50),
    rack_position VARCHAR(20), power_consumption INTEGER, notes TEXT,
    status VARCHAR(20) DEFAULT 'active', monitor_enabled BOOLEAN DEFAULT TRUE,
    ping_status VARCHAR(20) DEFAULT 'unknown', last_ping_at TIMESTAMP,
    last_seen_online TIMESTAMP, downtime_started TIMESTAMP,
    downtime_notified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_fiber_cores (
    id SERIAL PRIMARY KEY, site_from INTEGER, site_to INTEGER, cable_name VARCHAR(100),
    core_number INTEGER, core_color VARCHAR(50), purpose VARCHAR(100),
    status VARCHAR(20) DEFAULT 'available', notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_splice_closures (
    id SERIAL PRIMARY KEY, site_id INTEGER, name VARCHAR(100) NOT NULL, closure_type VARCHAR(50),
    location_detail VARCHAR(255), gps_lat DECIMAL(10,7), gps_lng DECIMAL(10,7),
    capacity INTEGER, used_ports INTEGER DEFAULT 0, status VARCHAR(20) DEFAULT 'active',
    notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_splitters (
    id SERIAL PRIMARY KEY, splice_closure_id INTEGER, name VARCHAR(100) NOT NULL,
    ratio VARCHAR(20) DEFAULT '1:8', input_core_id INTEGER, status VARCHAR(20) DEFAULT 'active',
    notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_distribution_boxes (
    id SERIAL PRIMARY KEY, site_id INTEGER, name VARCHAR(100) NOT NULL, box_type VARCHAR(50),
    location_detail VARCHAR(255), gps_lat DECIMAL(10,7), gps_lng DECIMAL(10,7),
    total_ports INTEGER DEFAULT 8, used_ports INTEGER DEFAULT 0, splitter_id INTEGER,
    status VARCHAR(20) DEFAULT 'active', notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_drop_cables (
    id SERIAL PRIMARY KEY, distribution_box_id INTEGER, customer_id INTEGER, port_number INTEGER,
    cable_length DECIMAL(6,2), cable_type VARCHAR(50) DEFAULT 'single-mode',
    status VARCHAR(20) DEFAULT 'active', installed_at TIMESTAMP, notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_cpe_devices (
    id SERIAL PRIMARY KEY, customer_id INTEGER, device_type VARCHAR(50) DEFAULT 'ONU',
    brand VARCHAR(50), model VARCHAR(100), serial_number VARCHAR(100), mac_address VARCHAR(17),
    ip_address VARCHAR(45), firmware_version VARCHAR(50), status VARCHAR(20) DEFAULT 'active',
    installed_at TIMESTAMP, notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_ip_addresses (
    id SERIAL PRIMARY KEY, subnet VARCHAR(50) NOT NULL, ip_address VARCHAR(45) NOT NULL,
    assignment_type VARCHAR(20) DEFAULT 'dynamic', assigned_to VARCHAR(100), customer_id INTEGER,
    device_id INTEGER, description TEXT, status VARCHAR(20) DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_vlans (
    id SERIAL PRIMARY KEY, vlan_id INTEGER NOT NULL, name VARCHAR(100), description TEXT,
    subnet VARCHAR(50), gateway VARCHAR(45), purpose VARCHAR(50),
    site_id INTEGER, status VARCHAR(20) DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_warehouse_stock (
    id SERIAL PRIMARY KEY, item_name VARCHAR(100) NOT NULL, category VARCHAR(50), brand VARCHAR(50),
    model VARCHAR(100), quantity INTEGER DEFAULT 0, min_quantity INTEGER DEFAULT 5,
    unit_cost DECIMAL(10,2), location VARCHAR(100), notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_warehouse_serials (
    id SERIAL PRIMARY KEY, stock_id INTEGER, serial_number VARCHAR(100) NOT NULL,
    mac_address VARCHAR(17), status VARCHAR(20) DEFAULT 'in_stock', assigned_to INTEGER,
    notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_stock_movements (
    id SERIAL PRIMARY KEY, stock_id INTEGER, movement_type VARCHAR(20) NOT NULL,
    quantity INTEGER NOT NULL, reference VARCHAR(100), performed_by INTEGER,
    notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_field_assets (
    id SERIAL PRIMARY KEY, asset_type VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL,
    serial_number VARCHAR(100), site_id INTEGER, assigned_to INTEGER,
    condition VARCHAR(20) DEFAULT 'good', purchase_date DATE, warranty_expiry DATE,
    notes TEXT, status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_maintenance_logs (
    id SERIAL PRIMARY KEY, equipment_type VARCHAR(50), equipment_id INTEGER,
    maintenance_type VARCHAR(50), description TEXT, performed_by INTEGER,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, next_maintenance DATE,
    cost DECIMAL(10,2), notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_equipment_uptime_log (
    id SERIAL PRIMARY KEY, equipment_id INTEGER NOT NULL, status VARCHAR(20) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, duration_seconds INTEGER, notes TEXT
);

-- ==================== ISP Locations ====================
CREATE TABLE IF NOT EXISTS isp_locations (
    id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL, description TEXT,
    parent_id INTEGER, is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS isp_sub_locations (
    id SERIAL PRIMARY KEY, location_id INTEGER, name VARCHAR(100) NOT NULL,
    description TEXT, is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==================== Radius Addon Services ====================
CREATE TABLE IF NOT EXISTS radius_addon_services (
    id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL, description TEXT,
    service_type VARCHAR(50), price DECIMAL(10,2) DEFAULT 0,
    is_recurring BOOLEAN DEFAULT FALSE, is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==================== Radius package schedules ====================
CREATE TABLE IF NOT EXISTS radius_package_schedules (
    id SERIAL PRIMARY KEY, package_id INTEGER, schedule_name VARCHAR(100),
    day_of_week INTEGER, start_time TIME, end_time TIME,
    download_speed INTEGER, upload_speed INTEGER, is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==================== Grant all permissions ====================
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO isp_crm;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO isp_crm;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO isp_crm;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO isp_crm;
