-- Fix: Change numeric columns to BIGINT to handle large SNMP index values
-- Huawei OLTs use large port index values that exceed INTEGER max (2,147,483,647)
-- Run this on your VPS database

-- huawei_onus table - these columns receive large SNMP values
ALTER TABLE huawei_onus ALTER COLUMN onu_id TYPE BIGINT;
ALTER TABLE huawei_onus ALTER COLUMN frame TYPE BIGINT;
ALTER TABLE huawei_onus ALTER COLUMN slot TYPE BIGINT;
ALTER TABLE huawei_onus ALTER COLUMN port TYPE BIGINT;

-- Verify the changes
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'huawei_onus' 
AND column_name IN ('onu_id', 'frame', 'slot', 'port');
