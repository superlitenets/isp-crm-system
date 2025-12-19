-- ONU Configuration Enhancements Migration
-- Adds fields to capture complete ONU provisioning data from Huawei OLT
-- Created: 2025-12-19

-- Add missing ONU configuration fields
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS vlan_id INTEGER;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS vlan_priority INTEGER DEFAULT 0;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS ip_mode VARCHAR(20) DEFAULT 'dhcp';
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS line_profile_id INTEGER;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS srv_profile_id INTEGER;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS tr069_profile_id INTEGER;
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS zone VARCHAR(100);
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS area VARCHAR(100);
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS customer_name VARCHAR(255);
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS auth_date DATE;

-- Create indexes for common filters
CREATE INDEX IF NOT EXISTS idx_huawei_onus_vlan ON huawei_onus(vlan_id);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_zone ON huawei_onus(zone);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_area ON huawei_onus(area);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_line_profile ON huawei_onus(line_profile_id);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_srv_profile ON huawei_onus(srv_profile_id);

-- Add comment for documentation
COMMENT ON COLUMN huawei_onus.vlan_id IS 'VLAN ID assigned to ONU (from ont ipconfig)';
COMMENT ON COLUMN huawei_onus.vlan_priority IS 'VLAN priority 0-7 (from ont ipconfig priority)';
COMMENT ON COLUMN huawei_onus.ip_mode IS 'IP assignment mode: dhcp or static';
COMMENT ON COLUMN huawei_onus.line_profile_id IS 'Huawei ont-lineprofile-id number';
COMMENT ON COLUMN huawei_onus.srv_profile_id IS 'Huawei ont-srvprofile-id number';
COMMENT ON COLUMN huawei_onus.tr069_profile_id IS 'TR-069 server profile ID';
COMMENT ON COLUMN huawei_onus.zone IS 'Zone/region from ONU description';
COMMENT ON COLUMN huawei_onus.area IS 'Area/location within zone from description';
COMMENT ON COLUMN huawei_onus.customer_name IS 'Customer name from ONU description';
COMMENT ON COLUMN huawei_onus.auth_date IS 'Authorization date from ONU description';
