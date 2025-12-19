-- OLT Inventory Enhancements
-- Adds firmware, hardware model, and enhanced configuration fields

-- Add firmware and hardware info to OLTs
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS firmware_version VARCHAR(100);
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS hardware_model VARCHAR(100);
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS software_version VARCHAR(100);
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS uptime VARCHAR(100);
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS cpu_usage INTEGER;
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS memory_usage INTEGER;
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS temperature INTEGER;
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS system_synced_at TIMESTAMP;

-- Enhanced board information
ALTER TABLE huawei_olt_boards ADD COLUMN IF NOT EXISTS hardware_version VARCHAR(50);
ALTER TABLE huawei_olt_boards ADD COLUMN IF NOT EXISTS software_version VARCHAR(50);
ALTER TABLE huawei_olt_boards ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100);
ALTER TABLE huawei_olt_boards ADD COLUMN IF NOT EXISTS board_type VARCHAR(20) DEFAULT 'unknown';
ALTER TABLE huawei_olt_boards ADD COLUMN IF NOT EXISTS is_enabled BOOLEAN DEFAULT TRUE;
ALTER TABLE huawei_olt_boards ADD COLUMN IF NOT EXISTS temperature INTEGER;

-- Enhanced PON port configuration
ALTER TABLE huawei_olt_pon_ports ADD COLUMN IF NOT EXISTS description VARCHAR(255);
ALTER TABLE huawei_olt_pon_ports ADD COLUMN IF NOT EXISTS service_profile_id INTEGER;
ALTER TABLE huawei_olt_pon_ports ADD COLUMN IF NOT EXISTS line_profile_id INTEGER;
ALTER TABLE huawei_olt_pon_ports ADD COLUMN IF NOT EXISTS native_vlan INTEGER;
ALTER TABLE huawei_olt_pon_ports ADD COLUMN IF NOT EXISTS allowed_vlans TEXT;
ALTER TABLE huawei_olt_pon_ports ADD COLUMN IF NOT EXISTS max_onus INTEGER DEFAULT 128;

-- Enhanced uplink configuration
ALTER TABLE huawei_olt_uplinks ADD COLUMN IF NOT EXISTS description VARCHAR(255);
ALTER TABLE huawei_olt_uplinks ADD COLUMN IF NOT EXISTS allowed_vlans TEXT;
ALTER TABLE huawei_olt_uplinks ADD COLUMN IF NOT EXISTS native_vlan INTEGER;
ALTER TABLE huawei_olt_uplinks ADD COLUMN IF NOT EXISTS is_enabled BOOLEAN DEFAULT TRUE;
ALTER TABLE huawei_olt_uplinks ADD COLUMN IF NOT EXISTS speed VARCHAR(20);
ALTER TABLE huawei_olt_uplinks ADD COLUMN IF NOT EXISTS duplex VARCHAR(20);
ALTER TABLE huawei_olt_uplinks ADD COLUMN IF NOT EXISTS mtu INTEGER DEFAULT 1500;
ALTER TABLE huawei_olt_uplinks ADD COLUMN IF NOT EXISTS rx_bytes BIGINT DEFAULT 0;
ALTER TABLE huawei_olt_uplinks ADD COLUMN IF NOT EXISTS tx_bytes BIGINT DEFAULT 0;
ALTER TABLE huawei_olt_uplinks ADD COLUMN IF NOT EXISTS rx_errors BIGINT DEFAULT 0;
ALTER TABLE huawei_olt_uplinks ADD COLUMN IF NOT EXISTS tx_errors BIGINT DEFAULT 0;
ALTER TABLE huawei_olt_uplinks ADD COLUMN IF NOT EXISTS stats_updated_at TIMESTAMP;

-- Service Templates table
CREATE TABLE IF NOT EXISTS huawei_service_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    downstream_bandwidth INTEGER DEFAULT 100,
    upstream_bandwidth INTEGER DEFAULT 50,
    bandwidth_unit VARCHAR(10) DEFAULT 'mbps',
    vlan_id INTEGER,
    vlan_mode VARCHAR(20) DEFAULT 'tag',
    qos_profile VARCHAR(100),
    line_profile_id INTEGER,
    service_profile_id INTEGER,
    iptv_enabled BOOLEAN DEFAULT FALSE,
    voip_enabled BOOLEAN DEFAULT FALSE,
    tr069_enabled BOOLEAN DEFAULT FALSE,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Port VLAN assignments (many-to-many)
CREATE TABLE IF NOT EXISTS huawei_port_vlans (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER NOT NULL REFERENCES huawei_olts(id) ON DELETE CASCADE,
    port_name VARCHAR(20) NOT NULL,
    port_type VARCHAR(10) NOT NULL DEFAULT 'pon',
    vlan_id INTEGER NOT NULL,
    vlan_mode VARCHAR(20) DEFAULT 'tag',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_port_vlans_olt ON huawei_port_vlans(olt_id);
CREATE INDEX IF NOT EXISTS idx_port_vlans_port ON huawei_port_vlans(port_name);
CREATE INDEX IF NOT EXISTS idx_service_templates_name ON huawei_service_templates(name);
