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
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS last_queue_rx_bytes BIGINT DEFAULT 0;
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS last_queue_tx_bytes BIGINT DEFAULT 0;
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS last_queue_poll_at TIMESTAMP;

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
    id SERIAL PRIMARY KEY, site_id INTEGER, rack_id INTEGER, olt_id INTEGER,
    name VARCHAR(100) NOT NULL, equipment_type VARCHAR(50) NOT NULL,
    manufacturer VARCHAR(100), model VARCHAR(100), serial_number VARCHAR(100),
    mac_address VARCHAR(17), management_ip VARCHAR(45), os_version VARCHAR(50),
    firmware_version VARCHAR(50), rack_position VARCHAR(20), capacity VARCHAR(50),
    purchase_date DATE, warranty_expiry DATE, supplier VARCHAR(100),
    purchase_price DECIMAL(10,2), notes TEXT,
    status VARCHAR(20) DEFAULT 'active', monitor_enabled BOOLEAN DEFAULT TRUE,
    ping_status VARCHAR(20) DEFAULT 'unknown', last_ping_at TIMESTAMP,
    last_seen_online TIMESTAMP, downtime_started TIMESTAMP,
    downtime_notified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS olt_id INTEGER;
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS manufacturer VARCHAR(100);
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS management_ip VARCHAR(45);
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS os_version VARCHAR(50);
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS capacity VARCHAR(50);
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS purchase_date DATE;
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS warranty_expiry DATE;
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS supplier VARCHAR(100);
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(10,2);
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS ping_status VARCHAR(20) DEFAULT 'unknown';
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS last_ping_at TIMESTAMP;
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS last_seen_online TIMESTAMP;
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS downtime_started TIMESTAMP;
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS downtime_notified BOOLEAN DEFAULT FALSE;
ALTER TABLE isp_core_equipment ADD COLUMN IF NOT EXISTS monitor_enabled BOOLEAN DEFAULT TRUE;

CREATE TABLE IF NOT EXISTS isp_fiber_cores (
    id SERIAL PRIMARY KEY, cable_name VARCHAR(100), core_number INTEGER,
    core_color VARCHAR(50), tube_color VARCHAR(50),
    route_path TEXT, start_point VARCHAR(255), end_point VARCHAR(255),
    splice_points TEXT, distance_meters DECIMAL(10,2), attenuation_db DECIMAL(6,2),
    assigned_to VARCHAR(255), assignment_type VARCHAR(50),
    status VARCHAR(20) DEFAULT 'available', notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_fiber_cores ADD COLUMN IF NOT EXISTS tube_color VARCHAR(50);
ALTER TABLE isp_fiber_cores ADD COLUMN IF NOT EXISTS route_path TEXT;
ALTER TABLE isp_fiber_cores ADD COLUMN IF NOT EXISTS start_point VARCHAR(255);
ALTER TABLE isp_fiber_cores ADD COLUMN IF NOT EXISTS end_point VARCHAR(255);
ALTER TABLE isp_fiber_cores ADD COLUMN IF NOT EXISTS splice_points TEXT;
ALTER TABLE isp_fiber_cores ADD COLUMN IF NOT EXISTS distance_meters DECIMAL(10,2);
ALTER TABLE isp_fiber_cores ADD COLUMN IF NOT EXISTS attenuation_db DECIMAL(6,2);
ALTER TABLE isp_fiber_cores ADD COLUMN IF NOT EXISTS assigned_to VARCHAR(255);
ALTER TABLE isp_fiber_cores ADD COLUMN IF NOT EXISTS assignment_type VARCHAR(50);
ALTER TABLE isp_fiber_cores ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS isp_splice_closures (
    id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL, closure_type VARCHAR(50),
    location_description TEXT, pole_number VARCHAR(50),
    gps_lat DECIMAL(10,7), gps_lng DECIMAL(10,7),
    splice_diagram TEXT, core_mapping TEXT, fiber_core_id INTEGER,
    status VARCHAR(20) DEFAULT 'active', notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_splice_closures ADD COLUMN IF NOT EXISTS location_description TEXT;
ALTER TABLE isp_splice_closures ADD COLUMN IF NOT EXISTS pole_number VARCHAR(50);
ALTER TABLE isp_splice_closures ADD COLUMN IF NOT EXISTS splice_diagram TEXT;
ALTER TABLE isp_splice_closures ADD COLUMN IF NOT EXISTS core_mapping TEXT;
ALTER TABLE isp_splice_closures ADD COLUMN IF NOT EXISTS fiber_core_id INTEGER;
ALTER TABLE isp_splice_closures ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS isp_splitters (
    id SERIAL PRIMARY KEY, site_id INTEGER, name VARCHAR(100) NOT NULL,
    splitter_type VARCHAR(50), ratio VARCHAR(20) DEFAULT '1:8',
    total_ports INTEGER DEFAULT 8, used_ports INTEGER DEFAULT 0,
    location_description TEXT, pole_number VARCHAR(50),
    gps_lat DECIMAL(10,7), gps_lng DECIMAL(10,7),
    upstream_equipment_id INTEGER, upstream_port VARCHAR(50),
    upstream_fiber_core_id INTEGER,
    status VARCHAR(20) DEFAULT 'active', notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_splitters ADD COLUMN IF NOT EXISTS site_id INTEGER;
ALTER TABLE isp_splitters ADD COLUMN IF NOT EXISTS splitter_type VARCHAR(50);
ALTER TABLE isp_splitters ADD COLUMN IF NOT EXISTS total_ports INTEGER DEFAULT 8;
ALTER TABLE isp_splitters ADD COLUMN IF NOT EXISTS used_ports INTEGER DEFAULT 0;
ALTER TABLE isp_splitters ADD COLUMN IF NOT EXISTS location_description TEXT;
ALTER TABLE isp_splitters ADD COLUMN IF NOT EXISTS pole_number VARCHAR(50);
ALTER TABLE isp_splitters ADD COLUMN IF NOT EXISTS gps_lat DECIMAL(10,7);
ALTER TABLE isp_splitters ADD COLUMN IF NOT EXISTS gps_lng DECIMAL(10,7);
ALTER TABLE isp_splitters ADD COLUMN IF NOT EXISTS upstream_equipment_id INTEGER;
ALTER TABLE isp_splitters ADD COLUMN IF NOT EXISTS upstream_port VARCHAR(50);
ALTER TABLE isp_splitters ADD COLUMN IF NOT EXISTS upstream_fiber_core_id INTEGER;
ALTER TABLE isp_splitters ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS isp_distribution_boxes (
    id SERIAL PRIMARY KEY, site_id INTEGER, name VARCHAR(100) NOT NULL, box_type VARCHAR(50),
    capacity INTEGER DEFAULT 8, used_ports INTEGER DEFAULT 0,
    pole_number VARCHAR(50), gps_lat DECIMAL(10,7), gps_lng DECIMAL(10,7),
    location_description TEXT, splitter_id INTEGER,
    status VARCHAR(20) DEFAULT 'active', notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_distribution_boxes ADD COLUMN IF NOT EXISTS capacity INTEGER DEFAULT 8;
ALTER TABLE isp_distribution_boxes ADD COLUMN IF NOT EXISTS pole_number VARCHAR(50);
ALTER TABLE isp_distribution_boxes ADD COLUMN IF NOT EXISTS location_description TEXT;
ALTER TABLE isp_distribution_boxes ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS isp_drop_cables (
    id SERIAL PRIMARY KEY, distribution_box_id INTEGER, box_port INTEGER,
    customer_id INTEGER, cable_type VARCHAR(50) DEFAULT 'single-mode',
    length_meters DECIMAL(6,2), installation_date DATE,
    status VARCHAR(20) DEFAULT 'active', notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_drop_cables ADD COLUMN IF NOT EXISTS box_port INTEGER;
ALTER TABLE isp_drop_cables ADD COLUMN IF NOT EXISTS length_meters DECIMAL(6,2);
ALTER TABLE isp_drop_cables ADD COLUMN IF NOT EXISTS installation_date DATE;
ALTER TABLE isp_drop_cables ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS isp_cpe_devices (
    id SERIAL PRIMARY KEY, serial_number VARCHAR(100), mac_address VARCHAR(17),
    model VARCHAR(100), manufacturer VARCHAR(100), firmware_version VARCHAR(50),
    olt_id INTEGER, olt_port VARCHAR(50), splitter_id INTEGER, splitter_port VARCHAR(50),
    customer_id INTEGER, pppoe_account VARCHAR(100),
    installation_date DATE, warranty_expiry DATE,
    purchase_price DECIMAL(10,2), supplier VARCHAR(100),
    status VARCHAR(20) DEFAULT 'active', notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_cpe_devices ADD COLUMN IF NOT EXISTS manufacturer VARCHAR(100);
ALTER TABLE isp_cpe_devices ADD COLUMN IF NOT EXISTS olt_id INTEGER;
ALTER TABLE isp_cpe_devices ADD COLUMN IF NOT EXISTS olt_port VARCHAR(50);
ALTER TABLE isp_cpe_devices ADD COLUMN IF NOT EXISTS splitter_id INTEGER;
ALTER TABLE isp_cpe_devices ADD COLUMN IF NOT EXISTS splitter_port VARCHAR(50);
ALTER TABLE isp_cpe_devices ADD COLUMN IF NOT EXISTS pppoe_account VARCHAR(100);
ALTER TABLE isp_cpe_devices ADD COLUMN IF NOT EXISTS installation_date DATE;
ALTER TABLE isp_cpe_devices ADD COLUMN IF NOT EXISTS warranty_expiry DATE;
ALTER TABLE isp_cpe_devices ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(10,2);
ALTER TABLE isp_cpe_devices ADD COLUMN IF NOT EXISTS supplier VARCHAR(100);

CREATE TABLE IF NOT EXISTS isp_ip_addresses (
    id SERIAL PRIMARY KEY, ip_type VARCHAR(20) DEFAULT 'ipv4',
    ip_address VARCHAR(45) NOT NULL, subnet_mask VARCHAR(45),
    cidr INTEGER, gateway VARCHAR(45), block_name VARCHAR(100),
    vlan_id INTEGER, assigned_to VARCHAR(255), assignment_type VARCHAR(50) DEFAULT 'dynamic',
    customer_id INTEGER, device_id INTEGER, reverse_dns VARCHAR(255),
    status VARCHAR(20) DEFAULT 'available', notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_ip_addresses ADD COLUMN IF NOT EXISTS ip_type VARCHAR(20) DEFAULT 'ipv4';
ALTER TABLE isp_ip_addresses ADD COLUMN IF NOT EXISTS subnet_mask VARCHAR(45);
ALTER TABLE isp_ip_addresses ADD COLUMN IF NOT EXISTS cidr INTEGER;
ALTER TABLE isp_ip_addresses ADD COLUMN IF NOT EXISTS gateway VARCHAR(45);
ALTER TABLE isp_ip_addresses ADD COLUMN IF NOT EXISTS block_name VARCHAR(100);
ALTER TABLE isp_ip_addresses ADD COLUMN IF NOT EXISTS vlan_id INTEGER;
ALTER TABLE isp_ip_addresses ADD COLUMN IF NOT EXISTS reverse_dns VARCHAR(255);

CREATE TABLE IF NOT EXISTS isp_vlans (
    id SERIAL PRIMARY KEY, vlan_id INTEGER NOT NULL, name VARCHAR(100),
    purpose VARCHAR(50), subnet VARCHAR(50), gateway VARCHAR(45),
    site_id INTEGER, equipment_id INTEGER, description TEXT,
    status VARCHAR(20) DEFAULT 'active', notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_vlans ADD COLUMN IF NOT EXISTS equipment_id INTEGER;
ALTER TABLE isp_vlans ADD COLUMN IF NOT EXISTS notes TEXT;
ALTER TABLE isp_vlans ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS isp_warehouse_stock (
    id SERIAL PRIMARY KEY, site_id INTEGER, item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50), unit VARCHAR(20) DEFAULT 'piece',
    quantity INTEGER DEFAULT 0, min_threshold INTEGER DEFAULT 5,
    unit_cost DECIMAL(10,2), supplier VARCHAR(100), supplier_contact VARCHAR(100),
    storage_location VARCHAR(255), last_restocked TIMESTAMP, notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_warehouse_stock ADD COLUMN IF NOT EXISTS site_id INTEGER;
ALTER TABLE isp_warehouse_stock ADD COLUMN IF NOT EXISTS unit VARCHAR(20) DEFAULT 'piece';
ALTER TABLE isp_warehouse_stock ADD COLUMN IF NOT EXISTS min_threshold INTEGER DEFAULT 5;
ALTER TABLE isp_warehouse_stock ADD COLUMN IF NOT EXISTS supplier VARCHAR(100);
ALTER TABLE isp_warehouse_stock ADD COLUMN IF NOT EXISTS supplier_contact VARCHAR(100);
ALTER TABLE isp_warehouse_stock ADD COLUMN IF NOT EXISTS storage_location VARCHAR(255);
ALTER TABLE isp_warehouse_stock ADD COLUMN IF NOT EXISTS last_restocked TIMESTAMP;

CREATE TABLE IF NOT EXISTS isp_warehouse_serials (
    id SERIAL PRIMARY KEY, stock_id INTEGER, serial_number VARCHAR(100) NOT NULL,
    mac_address VARCHAR(17), status VARCHAR(20) DEFAULT 'in_stock', assigned_to INTEGER,
    site_id INTEGER, received_date DATE, notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_warehouse_serials ADD COLUMN IF NOT EXISTS site_id INTEGER;
ALTER TABLE isp_warehouse_serials ADD COLUMN IF NOT EXISTS received_date DATE;
ALTER TABLE isp_warehouse_serials ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS isp_stock_movements (
    id SERIAL PRIMARY KEY, stock_id INTEGER, movement_type VARCHAR(20) NOT NULL,
    quantity INTEGER NOT NULL, reference_number VARCHAR(100),
    from_location VARCHAR(255), to_location VARCHAR(255),
    performed_by INTEGER, reason TEXT, notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_stock_movements ADD COLUMN IF NOT EXISTS reference_number VARCHAR(100);
ALTER TABLE isp_stock_movements ADD COLUMN IF NOT EXISTS from_location VARCHAR(255);
ALTER TABLE isp_stock_movements ADD COLUMN IF NOT EXISTS to_location VARCHAR(255);
ALTER TABLE isp_stock_movements ADD COLUMN IF NOT EXISTS reason TEXT;

CREATE TABLE IF NOT EXISTS isp_field_assets (
    id SERIAL PRIMARY KEY, asset_type VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL,
    serial_number VARCHAR(100), model VARCHAR(100), manufacturer VARCHAR(100),
    site_id INTEGER, assigned_to INTEGER, assigned_to_name VARCHAR(100),
    assignment_date DATE, purchase_date DATE, purchase_price DECIMAL(10,2),
    warranty_expiry DATE, condition VARCHAR(20) DEFAULT 'good',
    next_maintenance DATE, last_maintenance DATE,
    notes TEXT, status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_field_assets ADD COLUMN IF NOT EXISTS model VARCHAR(100);
ALTER TABLE isp_field_assets ADD COLUMN IF NOT EXISTS manufacturer VARCHAR(100);
ALTER TABLE isp_field_assets ADD COLUMN IF NOT EXISTS assigned_to_name VARCHAR(100);
ALTER TABLE isp_field_assets ADD COLUMN IF NOT EXISTS assignment_date DATE;
ALTER TABLE isp_field_assets ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(10,2);
ALTER TABLE isp_field_assets ADD COLUMN IF NOT EXISTS next_maintenance DATE;
ALTER TABLE isp_field_assets ADD COLUMN IF NOT EXISTS last_maintenance DATE;

CREATE TABLE IF NOT EXISTS isp_maintenance_logs (
    id SERIAL PRIMARY KEY, asset_type VARCHAR(50), asset_id INTEGER,
    asset_name VARCHAR(255), maintenance_type VARCHAR(50), description TEXT,
    performed_by INTEGER, performed_by_name VARCHAR(255),
    cost DECIMAL(10,2), next_due DATE, notes TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE isp_maintenance_logs ADD COLUMN IF NOT EXISTS asset_type VARCHAR(50);
ALTER TABLE isp_maintenance_logs ADD COLUMN IF NOT EXISTS asset_id INTEGER;
ALTER TABLE isp_maintenance_logs ADD COLUMN IF NOT EXISTS asset_name VARCHAR(255);
ALTER TABLE isp_maintenance_logs ADD COLUMN IF NOT EXISTS performed_by_name VARCHAR(255);
ALTER TABLE isp_maintenance_logs ADD COLUMN IF NOT EXISTS next_due DATE;
ALTER TABLE isp_maintenance_logs ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending';

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

-- ==================== TR069 Tables ====================
CREATE TABLE IF NOT EXISTS tr069_provision_state (
    id SERIAL PRIMARY KEY, onu_id INTEGER,
    device_id VARCHAR(255), serial_number VARCHAR(255),
    state VARCHAR(50) DEFAULT 'pending',
    current_step INTEGER DEFAULT 0, total_steps INTEGER DEFAULT 0,
    step_name VARCHAR(255), error_message TEXT,
    config_profile TEXT, applied_config JSONB,
    last_inform_at TIMESTAMP, next_step_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tr069_provision_logs (
    id SERIAL PRIMARY KEY, onu_id INTEGER,
    log_type VARCHAR(50), message TEXT, details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==================== Employees ====================
ALTER TABLE employees ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active';
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'employees' AND column_name = 'employment_status')
       AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'employees' AND column_name = 'status') THEN
        UPDATE employees SET status = employment_status WHERE status IS NULL OR status = 'active';
    END IF;
END $$;

-- ==================== Fleet Management ====================
CREATE TABLE IF NOT EXISTS fleet_vehicles (
    id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL,
    plate_number VARCHAR(50), imei VARCHAR(50),
    vehicle_type VARCHAR(50) DEFAULT 'car',
    make VARCHAR(100), model VARCHAR(100), year INTEGER,
    color VARCHAR(50), fuel_rate DECIMAL(6,2) DEFAULT 0,
    assigned_employee_id INTEGER,
    last_latitude DECIMAL(10,7), last_longitude DECIMAL(10,7),
    last_speed DECIMAL(6,2) DEFAULT 0, last_acc_status INTEGER DEFAULT -1,
    last_battery INTEGER DEFAULT -1, last_mileage DECIMAL(12,2) DEFAULT 0,
    last_data_status INTEGER DEFAULT 0, last_update TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active', notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fleet_vehicle_assignments (
    id SERIAL PRIMARY KEY, vehicle_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL, notes TEXT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    returned_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fleet_geofences (
    id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL,
    geofence_type VARCHAR(20) DEFAULT 'circle',
    latitude DECIMAL(10,7), longitude DECIMAL(10,7),
    radius INTEGER DEFAULT 500, alarm_type INTEGER DEFAULT 2,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fleet_alarms (
    id SERIAL PRIMARY KEY, vehicle_id INTEGER,
    imei VARCHAR(50), alarm_type INTEGER DEFAULT 0,
    alarm_name VARCHAR(100), latitude DECIMAL(10,7),
    longitude DECIMAL(10,7), speed DECIMAL(6,2) DEFAULT 0,
    alarm_time TIMESTAMP, acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by INTEGER, acknowledged_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fleet_command_log (
    id SERIAL PRIMARY KEY, vehicle_id INTEGER,
    imei VARCHAR(50), command VARCHAR(255),
    command_id VARCHAR(100), status VARCHAR(20) DEFAULT 'sent',
    response TEXT, sent_by INTEGER,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fleet_mileage_reports (
    id SERIAL PRIMARY KEY, vehicle_id INTEGER,
    imei VARCHAR(50), report_date DATE,
    mileage_km DECIMAL(12,2) DEFAULT 0,
    fuel_consumed DECIMAL(8,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE fleet_vehicles ADD COLUMN IF NOT EXISTS last_data_status INTEGER DEFAULT 0;

-- ==================== Grant all permissions ====================
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'isp_crm') THEN
        EXECUTE 'GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO isp_crm';
        EXECUTE 'GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO isp_crm';
        EXECUTE 'ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO isp_crm';
        EXECUTE 'ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO isp_crm';
    END IF;
END
$$;

-- ==================== Fix ONUs authorized but still marked is_authorized=FALSE ====================
UPDATE huawei_onus 
SET is_authorized = TRUE 
WHERE is_authorized = FALSE 
AND authorized_at IS NOT NULL;

UPDATE onu_discovery_log 
SET authorized = true, authorized_at = CURRENT_TIMESTAMP
WHERE authorized = false
AND serial_number IN (
    SELECT sn FROM huawei_onus WHERE is_authorized = TRUE
);
