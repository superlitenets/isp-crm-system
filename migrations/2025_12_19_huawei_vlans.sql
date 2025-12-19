-- Huawei OLT VLANs Table
-- Stores VLAN configuration synced from OLT devices
-- Created: 2025-12-19

CREATE TABLE IF NOT EXISTS huawei_vlans (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER REFERENCES huawei_olts(id) ON DELETE CASCADE,
    vlan_id INTEGER NOT NULL,
    vlan_type VARCHAR(20) DEFAULT 'smart',
    attribute VARCHAR(50) DEFAULT 'common',
    standard_port_count INTEGER DEFAULT 0,
    service_port_count INTEGER DEFAULT 0,
    vlan_connect_count INTEGER DEFAULT 0,
    description VARCHAR(255),
    is_management BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, vlan_id)
);

CREATE INDEX IF NOT EXISTS idx_huawei_vlans_olt ON huawei_vlans(olt_id);
CREATE INDEX IF NOT EXISTS idx_huawei_vlans_vlan_id ON huawei_vlans(vlan_id);
CREATE INDEX IF NOT EXISTS idx_huawei_vlans_active ON huawei_vlans(is_active);

COMMENT ON TABLE huawei_vlans IS 'VLANs configured on Huawei OLT devices';
COMMENT ON COLUMN huawei_vlans.vlan_type IS 'VLAN type: smart, standard, mux, super';
COMMENT ON COLUMN huawei_vlans.attribute IS 'VLAN attribute: common, stacking, etc';
COMMENT ON COLUMN huawei_vlans.standard_port_count IS 'Number of standard ports using this VLAN';
COMMENT ON COLUMN huawei_vlans.service_port_count IS 'Number of service virtual ports (ONUs) using this VLAN';
COMMENT ON COLUMN huawei_vlans.is_management IS 'True if this is a management VLAN';
