-- Fix: Change onu_id to BIGINT to handle large SNMP index values
-- Some OLTs return ONU IDs that exceed INTEGER max (2,147,483,647)

ALTER TABLE huawei_onus ALTER COLUMN onu_id TYPE BIGINT;
