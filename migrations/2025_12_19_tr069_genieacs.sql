-- TR-069/GenieACS Integration Tables
-- Run after huawei_olt_module migration

-- TR-069 devices linked to ONUs
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

CREATE INDEX IF NOT EXISTS idx_tr069_devices_serial ON tr069_devices(serial_number);
CREATE INDEX IF NOT EXISTS idx_tr069_devices_onu ON tr069_devices(onu_id);
CREATE INDEX IF NOT EXISTS idx_tr069_devices_status ON tr069_devices(status);

-- TR-069 configuration profiles (WiFi, VoIP templates)
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

CREATE INDEX IF NOT EXISTS idx_tr069_profiles_type ON tr069_profiles(profile_type);

-- TR-069 task queue for pending operations
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

CREATE INDEX IF NOT EXISTS idx_tr069_tasks_device ON tr069_tasks(device_id);
CREATE INDEX IF NOT EXISTS idx_tr069_tasks_status ON tr069_tasks(status);

-- TR-069 logs
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

CREATE INDEX IF NOT EXISTS idx_tr069_logs_device ON tr069_logs(device_id);
CREATE INDEX IF NOT EXISTS idx_tr069_logs_action ON tr069_logs(action);

-- Insert default WiFi profile template
INSERT INTO tr069_profiles (name, description, profile_type, parameters, is_default) VALUES
('Default WiFi', 'Standard WiFi configuration template', 'wifi', 
 '{"ssid_2g": "", "password_2g": "", "ssid_5g": "", "password_5g": "", "channel_2g": 0, "channel_5g": 0}', 
 true)
ON CONFLICT DO NOTHING;

-- GenieACS settings
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('genieacs_url', 'http://localhost:7557', 'TR-069'),
('genieacs_username', '', 'TR-069'),
('genieacs_password', '', 'TR-069'),
('genieacs_timeout', '30', 'TR-069'),
('genieacs_enabled', '0', 'TR-069')
ON CONFLICT (setting_key) DO NOTHING;
