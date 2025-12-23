-- Custom ONU Types table for OMS Settings
CREATE TABLE IF NOT EXISTS huawei_onu_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    model VARCHAR(100),
    vendor VARCHAR(100) DEFAULT 'Huawei',
    eth_ports INTEGER DEFAULT 1,
    pots_ports INTEGER DEFAULT 0,
    wifi_capable BOOLEAN DEFAULT FALSE,
    catv_port BOOLEAN DEFAULT FALSE,
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

-- Insert some common ONU types
INSERT INTO huawei_onu_types (name, model, eth_ports, pots_ports, wifi_capable, default_mode, description) VALUES
('Bridge ONU (1 ETH)', 'HG8010H', 1, 0, FALSE, 'bridge', 'Single port bridge ONU for dedicated connections'),
('Router ONU (4 ETH)', 'HG8245H', 4, 2, TRUE, 'router', '4-port router with WiFi and 2 POTS ports'),
('Router ONU (4 ETH, No WiFi)', 'HG8240H', 4, 2, FALSE, 'router', '4-port router without WiFi'),
('Bridge ONU (4 ETH)', 'HG8040H', 4, 0, FALSE, 'bridge', '4-port bridge ONU without POTS'),
('SFU Bridge', 'HG8310M', 1, 0, FALSE, 'bridge', 'Single-Family Unit bridge ONU')
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

-- Add index for subnet lookups
CREATE INDEX IF NOT EXISTS idx_wireguard_subnets_peer ON wireguard_subnets(vpn_peer_id);
CREATE INDEX IF NOT EXISTS idx_wireguard_subnets_type ON wireguard_subnets(subnet_type);
