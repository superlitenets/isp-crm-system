-- Custom ONU Types table for OMS Settings
CREATE TABLE IF NOT EXISTS huawei_onu_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    model VARCHAR(100),
    model_aliases TEXT,
    vendor VARCHAR(100) DEFAULT 'Huawei',
    eth_ports INTEGER DEFAULT 1,
    pots_ports INTEGER DEFAULT 0,
    wifi_capable BOOLEAN DEFAULT FALSE,
    wifi_dual_band BOOLEAN DEFAULT FALSE,
    catv_port BOOLEAN DEFAULT FALSE,
    usb_port BOOLEAN DEFAULT FALSE,
    pon_type VARCHAR(20) DEFAULT 'GPON',
    default_mode VARCHAR(20) DEFAULT 'bridge',
    tcont_count INTEGER DEFAULT 1,
    gemport_count INTEGER DEFAULT 1,
    recommended_line_profile VARCHAR(100),
    recommended_srv_profile VARCHAR(100),
    omci_capable BOOLEAN DEFAULT TRUE,
    tr069_capable BOOLEAN DEFAULT TRUE,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add ONU type reference to ONUs table
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS onu_type_id INTEGER REFERENCES huawei_onu_types(id);

-- Add equipment ID and ONU type to discovery log
ALTER TABLE onu_discovery_log ADD COLUMN IF NOT EXISTS onu_type_id INTEGER REFERENCES huawei_onu_types(id);
ALTER TABLE onu_discovery_log ADD COLUMN IF NOT EXISTS equipment_id VARCHAR(100);

-- Seed comprehensive Huawei ONT models
INSERT INTO huawei_onu_types (name, model, model_aliases, eth_ports, pots_ports, wifi_capable, wifi_dual_band, usb_port, default_mode, description) VALUES
-- Single port bridge ONUs (SFU)
('HG8010H', 'HG8010H', 'HG8010,EchoLife-HG8010H', 1, 0, FALSE, FALSE, FALSE, 'bridge', 'Single GE port SFU bridge - most basic FTTH terminal'),
('HG8310M', 'HG8310M', 'HG8310,EchoLife-HG8310M', 1, 0, FALSE, FALSE, FALSE, 'bridge', 'Single GE port compact SFU bridge'),
('HG8012H', 'HG8012H', 'HG8012,EchoLife-HG8012H', 1, 1, FALSE, FALSE, FALSE, 'bridge', 'Single GE + 1 POTS bridge ONT'),

-- Multi-port bridge ONUs (MDU style)
('HG8040H', 'HG8040H', 'HG8040,EchoLife-HG8040H', 4, 0, FALSE, FALSE, FALSE, 'bridge', '4x GE bridge ONT without WiFi'),
('HG8240H', 'HG8240H', 'HG8240,EchoLife-HG8240H', 4, 2, FALSE, FALSE, FALSE, 'router', '4x GE + 2 POTS router without WiFi'),
('HG8040F', 'HG8040F', 'EchoLife-HG8040F', 4, 0, FALSE, FALSE, FALSE, 'bridge', '4x FE bridge ONT'),

-- HG8145 Series (Popular mid-range)
('HG8145V', 'HG8145V', 'HG8145,EchoLife-HG8145V', 4, 1, TRUE, FALSE, TRUE, 'router', '4x GE + 1 POTS + WiFi 2.4GHz + USB'),
('HG8145V5', 'HG8145V5', 'HG8145V5,EchoLife-HG8145V5,EG8145V5', 4, 1, TRUE, TRUE, TRUE, 'router', '4x GE + 1 POTS + Dual-band WiFi + USB - Popular model'),
('HG8145X6', 'HG8145X6', 'EchoLife-HG8145X6', 4, 1, TRUE, TRUE, TRUE, 'router', '4x GE + 1 POTS + WiFi 6 Dual-band + USB'),

-- HG8245 Series (High-end residential)
('HG8245H', 'HG8245H', 'HG8245,EchoLife-HG8245H', 4, 2, TRUE, FALSE, TRUE, 'router', '4x GE + 2 POTS + WiFi 2.4GHz + USB - Classic model'),
('HG8245H5', 'HG8245H5', 'HG8245H5,EchoLife-HG8245H5', 4, 2, TRUE, TRUE, TRUE, 'router', '4x GE + 2 POTS + Dual-band WiFi + USB'),
('HG8245Q', 'HG8245Q', 'HG8245Q2,EchoLife-HG8245Q', 4, 2, TRUE, FALSE, TRUE, 'router', '4x GE + 2 POTS + WiFi + CATV port'),
('HG8245W5', 'HG8245W5', 'EchoLife-HG8245W5', 4, 2, TRUE, TRUE, TRUE, 'router', '4x GE + 2 POTS + Dual-band WiFi AC + USB'),
('HG8245X6', 'HG8245X6', 'EchoLife-HG8245X6', 4, 2, TRUE, TRUE, TRUE, 'router', '4x GE + 2 POTS + WiFi 6 AX + USB - Latest high-end'),

-- HG8546 Series (Cost-effective)
('HG8546M', 'HG8546M', 'HG8546,EchoLife-HG8546M', 4, 1, TRUE, FALSE, TRUE, 'router', '1x GE + 3x FE + 1 POTS + WiFi 2.4GHz - Popular budget model'),
('HG8546V', 'HG8546V', 'EchoLife-HG8546V', 4, 1, TRUE, FALSE, TRUE, 'router', '1x GE + 3x FE + 1 POTS + WiFi'),
('HG8546V5', 'HG8546V5', 'EchoLife-HG8546V5', 4, 1, TRUE, TRUE, TRUE, 'router', '1x GE + 3x FE + 1 POTS + Dual-band WiFi'),

-- EG Series (Enhanced Gateway)
('EG8145V5', 'EG8145V5', 'EchoLife-EG8145V5', 4, 1, TRUE, TRUE, TRUE, 'router', '4x GE + 1 POTS + Dual-band WiFi + USB - Advanced gateway'),
('EG8245H5', 'EG8245H5', 'EchoLife-EG8245H5', 4, 2, TRUE, TRUE, TRUE, 'router', '4x GE + 2 POTS + Dual-band WiFi - Premium gateway'),
('EG8247H5', 'EG8247H5', 'EchoLife-EG8247H5', 4, 2, TRUE, TRUE, TRUE, 'router', '4x GE + 2 POTS + Dual-band WiFi + CATV'),

-- HN Series (Business/Enterprise)
('HN8245Q', 'HN8245Q', 'EchoLife-HN8245Q', 4, 2, TRUE, FALSE, TRUE, 'router', '4x GE + 2 POTS + WiFi + CATV - Business grade'),
('HN8346Q', 'HN8346Q', 'EchoLife-HN8346Q', 4, 2, TRUE, TRUE, TRUE, 'router', '4x 2.5GE + 2 POTS + WiFi 6 - Enterprise ONT'),

-- HS Series (Smart Home)
('HS8145V', 'HS8145V', 'EchoLife-HS8145V', 4, 1, TRUE, FALSE, TRUE, 'router', '4x GE + 1 POTS + WiFi - Smart home ready'),
('HS8145V5', 'HS8145V5', 'EchoLife-HS8145V5', 4, 1, TRUE, TRUE, TRUE, 'router', '4x GE + 1 POTS + Dual-band WiFi - Smart home'),
('HS8546V', 'HS8546V', 'EchoLife-HS8546V', 4, 1, TRUE, FALSE, TRUE, 'router', '1x GE + 3x FE + 1 POTS + WiFi - Smart home budget'),
('HS8546V5', 'HS8546V5', 'EchoLife-HS8546V5', 4, 1, TRUE, TRUE, TRUE, 'router', '1x GE + 3x FE + 1 POTS + Dual-band - Smart home budget'),

-- OptiXstar Series (Next-gen 10G PON ready)
('OptiXstar HN8255Ws', 'HN8255Ws', 'OptiXstar-HN8255Ws', 4, 2, TRUE, TRUE, TRUE, 'router', '4x 2.5GE + 2 POTS + WiFi 6E - Premium 10G ready'),
('OptiXstar K662c', 'K662c', 'OptiXstar-K662c', 4, 2, TRUE, TRUE, TRUE, 'router', '4x GE + 2 POTS + WiFi 6 - Next-gen ONT')

ON CONFLICT DO NOTHING;

-- WireGuard VPN Network Subnets
CREATE TABLE IF NOT EXISTS wireguard_subnets (
    id SERIAL PRIMARY KEY,
    vpn_peer_id INTEGER REFERENCES wireguard_peers(id) ON DELETE CASCADE,
    network_cidr VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    subnet_type VARCHAR(50) DEFAULT 'management',
    is_olt_management BOOLEAN DEFAULT FALSE,
    is_tr069_range BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add indexes
CREATE INDEX IF NOT EXISTS idx_wireguard_subnets_peer ON wireguard_subnets(vpn_peer_id);
CREATE INDEX IF NOT EXISTS idx_wireguard_subnets_type ON wireguard_subnets(subnet_type);
CREATE INDEX IF NOT EXISTS idx_huawei_onu_types_model ON huawei_onu_types(model);
CREATE INDEX IF NOT EXISTS idx_onu_discovery_equipment ON onu_discovery_log(equipment_id);
