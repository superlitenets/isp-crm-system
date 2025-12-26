-- Fix TR-069 settings and OLT profiles
-- Run this on your production database

-- Add setting_group column to settings table if missing
ALTER TABLE settings ADD COLUMN IF NOT EXISTS setting_group VARCHAR(50);

-- Create OLT Line Profiles table
CREATE TABLE IF NOT EXISTS huawei_olt_line_profiles (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER REFERENCES huawei_olts(id) ON DELETE CASCADE,
    profile_id INTEGER NOT NULL,
    profile_name VARCHAR(64),
    tcont_count INTEGER DEFAULT 0,
    gem_count INTEGER DEFAULT 0,
    tr069_enabled BOOLEAN DEFAULT FALSE,
    raw_config TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, profile_id)
);

-- Create OLT Service Profiles table
CREATE TABLE IF NOT EXISTS huawei_olt_srv_profiles (
    id SERIAL PRIMARY KEY,
    olt_id INTEGER REFERENCES huawei_olts(id) ON DELETE CASCADE,
    profile_id INTEGER NOT NULL,
    profile_name VARCHAR(64),
    eth_ports INTEGER DEFAULT 0,
    pots_ports INTEGER DEFAULT 0,
    wifi_enabled BOOLEAN DEFAULT FALSE,
    raw_config TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(olt_id, profile_id)
);

-- Insert default TR-069 OMCI settings
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
    ('tr069_acs_url', '', 'TR-069'),
    ('tr069_periodic_interval', '300', 'TR-069'),
    ('tr069_default_gem_port', '2', 'TR-069'),
    ('tr069_acs_username', '', 'TR-069'),
    ('tr069_acs_password', '', 'TR-069'),
    ('tr069_cpe_username', '', 'TR-069'),
    ('tr069_cpe_password', '', 'TR-069')
ON CONFLICT (setting_key) DO NOTHING;
