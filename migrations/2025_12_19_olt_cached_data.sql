-- OLT Cached Data Tables
-- Stores OLT configuration data fetched from devices to avoid repeated Telnet connections

-- Cached OLT Boards
CREATE TABLE IF NOT EXISTS huawei_olt_boards (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER NOT NULL REFERENCES huawei_olts(id) ON DELETE CASCADE,
    slot INTEGER NOT NULL,
    board_name VARCHAR(50),
    status VARCHAR(50),
    subtype VARCHAR(50),
    online_status VARCHAR(20),
    port_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, slot)
);

-- Cached OLT VLANs
CREATE TABLE IF NOT EXISTS huawei_olt_vlans (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER NOT NULL REFERENCES huawei_olts(id) ON DELETE CASCADE,
    vlan_id INTEGER NOT NULL,
    vlan_type VARCHAR(50) DEFAULT 'smart',
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, vlan_id)
);

-- Cached PON Ports
CREATE TABLE IF NOT EXISTS huawei_olt_pon_ports (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER NOT NULL REFERENCES huawei_olts(id) ON DELETE CASCADE,
    port_name VARCHAR(20) NOT NULL,
    port_type VARCHAR(20) DEFAULT 'GPON',
    admin_status VARCHAR(20) DEFAULT 'enable',
    oper_status VARCHAR(20),
    onu_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, port_name)
);

-- Cached Uplink Ports
CREATE TABLE IF NOT EXISTS huawei_olt_uplinks (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER NOT NULL REFERENCES huawei_olts(id) ON DELETE CASCADE,
    port_name VARCHAR(20) NOT NULL,
    port_type VARCHAR(20),
    admin_status VARCHAR(20) DEFAULT 'enable',
    oper_status VARCHAR(20),
    speed VARCHAR(20),
    duplex VARCHAR(20),
    vlan_mode VARCHAR(20),
    pvid INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, port_name)
);

-- ONU Management IPs
CREATE TABLE IF NOT EXISTS huawei_onu_mgmt_ips (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER NOT NULL REFERENCES huawei_olts(id) ON DELETE CASCADE,
    onu_id INTEGER REFERENCES huawei_onus(id) ON DELETE SET NULL,
    ip_address VARCHAR(45) NOT NULL,
    subnet_mask VARCHAR(45),
    gateway VARCHAR(45),
    vlan_id INTEGER,
    ip_type VARCHAR(20) DEFAULT 'static',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Track last sync times
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS boards_synced_at TIMESTAMP;
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS vlans_synced_at TIMESTAMP;
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS ports_synced_at TIMESTAMP;
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS uplinks_synced_at TIMESTAMP;

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_olt_boards_olt ON huawei_olt_boards(olt_id);
CREATE INDEX IF NOT EXISTS idx_olt_vlans_olt ON huawei_olt_vlans(olt_id);
CREATE INDEX IF NOT EXISTS idx_olt_pon_ports_olt ON huawei_olt_pon_ports(olt_id);
CREATE INDEX IF NOT EXISTS idx_olt_uplinks_olt ON huawei_olt_uplinks(olt_id);
