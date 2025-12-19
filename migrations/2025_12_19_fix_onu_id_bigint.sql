-- Fix: Change numeric columns to BIGINT to handle large SNMP index values
-- Huawei OLTs use large port index values that exceed INTEGER max (2,147,483,647)

ALTER TABLE huawei_onus ALTER COLUMN onu_id TYPE BIGINT;
ALTER TABLE huawei_onus ALTER COLUMN frame TYPE BIGINT;
ALTER TABLE huawei_onus ALTER COLUMN slot TYPE BIGINT;
ALTER TABLE huawei_onus ALTER COLUMN port TYPE BIGINT;
