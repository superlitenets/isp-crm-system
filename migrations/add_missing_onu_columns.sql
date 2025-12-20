-- Migration: Add missing columns to huawei_onus table
-- Run this on your production database

-- Add distance column (in meters)
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS distance INTEGER;

-- Add vlan_id column
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS vlan_id INTEGER;

-- Add vlan_priority column  
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS vlan_priority INTEGER DEFAULT 0;

-- Add ip_mode column
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS ip_mode VARCHAR(20) DEFAULT 'dhcp';

-- Add password column for LOID/password auth
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS password VARCHAR(100);

-- Add line_profile_id column
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS line_profile_id INTEGER;

-- Add srv_profile_id column
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS srv_profile_id INTEGER;

-- Add tr069_profile_id column
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS tr069_profile_id INTEGER;

-- Add zone column
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS zone VARCHAR(100);

-- Add area column
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS area VARCHAR(100);

-- Add customer_name column
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS customer_name VARCHAR(200);

-- Add auth_date column
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS auth_date DATE;

-- Add line_profile column (text name)
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS line_profile VARCHAR(100);

-- Add srv_profile column (text name)
ALTER TABLE huawei_onus ADD COLUMN IF NOT EXISTS srv_profile VARCHAR(100);

-- Verify the columns were added
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'huawei_onus' 
ORDER BY ordinal_position;
