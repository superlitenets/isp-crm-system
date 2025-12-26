-- Add VLAN feature columns to huawei_vlans table
-- Run this on your production database

ALTER TABLE huawei_vlans ADD COLUMN IF NOT EXISTS is_multicast BOOLEAN DEFAULT FALSE;
ALTER TABLE huawei_vlans ADD COLUMN IF NOT EXISTS is_voip BOOLEAN DEFAULT FALSE;
ALTER TABLE huawei_vlans ADD COLUMN IF NOT EXISTS is_tr069 BOOLEAN DEFAULT FALSE;
ALTER TABLE huawei_vlans ADD COLUMN IF NOT EXISTS dhcp_snooping BOOLEAN DEFAULT FALSE;
ALTER TABLE huawei_vlans ADD COLUMN IF NOT EXISTS lan_to_lan BOOLEAN DEFAULT FALSE;

-- Add comment for documentation
COMMENT ON COLUMN huawei_vlans.is_multicast IS 'Multicast VLAN for IPTV services';
COMMENT ON COLUMN huawei_vlans.is_voip IS 'Management/VoIP VLAN';
COMMENT ON COLUMN huawei_vlans.is_tr069 IS 'TR-069 Management VLAN for remote ONU config';
COMMENT ON COLUMN huawei_vlans.dhcp_snooping IS 'DHCP Snooping enabled on this VLAN';
COMMENT ON COLUMN huawei_vlans.lan_to_lan IS 'LAN-to-LAN direct ONU communication enabled';
