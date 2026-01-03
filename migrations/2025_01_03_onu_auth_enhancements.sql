-- ONU Authorization Enhancements
-- Adds fields for customer linking, phone, GPS, installation date, and PPPoE credentials

-- Add phone number for quick contact
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS phone VARCHAR(50);

-- Add GPS coordinates for mapping
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8);
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8);

-- Add installation date (auto-set on authorization)
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS installation_date DATE;

-- Add PPPoE credentials for TR-069 Internet WAN configuration
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS pppoe_username VARCHAR(100);
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS pppoe_password VARCHAR(100);

-- Add ONU type override field
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS onu_type_id INTEGER REFERENCES huawei_onu_types(id) ON DELETE SET NULL;

-- Add address field if not exists
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS address TEXT;

-- Add zone_id reference if not exists
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS zone_id INTEGER REFERENCES huawei_zones(id) ON DELETE SET NULL;

-- Create index for zone lookups
CREATE INDEX IF NOT EXISTS idx_huawei_onus_zone ON huawei_onus(zone_id);

-- Create index for phone lookups
CREATE INDEX IF NOT EXISTS idx_huawei_onus_phone ON huawei_onus(phone);
