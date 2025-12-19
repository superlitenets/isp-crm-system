-- Huawei OLT Module Database Schema
-- Created: 2025-12-19

-- OLT devices table
CREATE TABLE IF NOT EXISTS huawei_olts (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    port INTEGER DEFAULT 23,
    connection_type VARCHAR(20) DEFAULT 'telnet',
    username VARCHAR(100),
    password_encrypted TEXT,
    snmp_community VARCHAR(100) DEFAULT 'public',
    snmp_version VARCHAR(10) DEFAULT 'v2c',
    snmp_port INTEGER DEFAULT 161,
    vendor VARCHAR(50) DEFAULT 'Huawei',
    model VARCHAR(100),
    location VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_sync_at TIMESTAMP,
    last_status VARCHAR(20) DEFAULT 'unknown',
    uptime VARCHAR(100),
    temperature VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_huawei_olts_active ON huawei_olts(is_active);
CREATE INDEX IF NOT EXISTS idx_huawei_olts_ip ON huawei_olts(ip_address);

-- Service profiles table
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

CREATE INDEX IF NOT EXISTS idx_huawei_profiles_active ON huawei_service_profiles(is_active);
CREATE INDEX IF NOT EXISTS idx_huawei_profiles_type ON huawei_service_profiles(profile_type);

-- ONUs inventory table
CREATE TABLE IF NOT EXISTS huawei_onus (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER REFERENCES huawei_olts(id) ON DELETE CASCADE,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    sn VARCHAR(100) NOT NULL,
    name VARCHAR(100),
    description TEXT,
    frame INTEGER DEFAULT 0,
    slot INTEGER,
    port INTEGER,
    onu_id INTEGER,
    onu_type VARCHAR(100),
    mac_address VARCHAR(17),
    status VARCHAR(30) DEFAULT 'offline',
    rx_power DECIMAL(10,2),
    tx_power DECIMAL(10,2),
    distance INTEGER,
    last_down_cause VARCHAR(100),
    last_down_time TIMESTAMP,
    last_up_time TIMESTAMP,
    service_profile_id INTEGER REFERENCES huawei_service_profiles(id) ON DELETE SET NULL,
    line_profile VARCHAR(100),
    srv_profile VARCHAR(100),
    is_authorized BOOLEAN DEFAULT FALSE,
    firmware_version VARCHAR(100),
    hardware_version VARCHAR(100),
    software_version VARCHAR(100),
    ip_address VARCHAR(45),
    config_state VARCHAR(50),
    run_state VARCHAR(50),
    auth_type VARCHAR(20) DEFAULT 'sn',
    password VARCHAR(100),
    additional_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, sn)
);

CREATE INDEX IF NOT EXISTS idx_huawei_onus_olt ON huawei_onus(olt_id);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_sn ON huawei_onus(sn);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_status ON huawei_onus(status);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_customer ON huawei_onus(customer_id);

-- Provisioning rules for auto-provisioning
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

CREATE INDEX IF NOT EXISTS idx_huawei_rules_olt ON huawei_provisioning_rules(olt_id);
CREATE INDEX IF NOT EXISTS idx_huawei_rules_active ON huawei_provisioning_rules(is_active);

-- Provisioning logs table
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

CREATE INDEX IF NOT EXISTS idx_huawei_logs_olt ON huawei_provisioning_logs(olt_id);
CREATE INDEX IF NOT EXISTS idx_huawei_logs_onu ON huawei_provisioning_logs(onu_id);
CREATE INDEX IF NOT EXISTS idx_huawei_logs_action ON huawei_provisioning_logs(action);
CREATE INDEX IF NOT EXISTS idx_huawei_logs_status ON huawei_provisioning_logs(status);
CREATE INDEX IF NOT EXISTS idx_huawei_logs_created ON huawei_provisioning_logs(created_at DESC);

-- Alerts table
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

CREATE INDEX IF NOT EXISTS idx_huawei_alerts_olt ON huawei_alerts(olt_id);
CREATE INDEX IF NOT EXISTS idx_huawei_alerts_unread ON huawei_alerts(is_read) WHERE is_read = FALSE;
CREATE INDEX IF NOT EXISTS idx_huawei_alerts_severity ON huawei_alerts(severity);

-- PON ports table for detailed port info
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

CREATE INDEX IF NOT EXISTS idx_huawei_ports_olt ON huawei_pon_ports(olt_id);

-- Command templates for common operations
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

-- Insert default command templates for Huawei
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
