-- Migration to add dual SNMP community support (read/write)
-- Run this if the huawei_olts table already exists

-- Rename existing snmp_community column to snmp_read_community if it exists
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns 
               WHERE table_name = 'huawei_olts' AND column_name = 'snmp_community') THEN
        ALTER TABLE huawei_olts RENAME COLUMN snmp_community TO snmp_read_community;
    END IF;
END $$;

-- Add snmp_write_community column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'huawei_olts' AND column_name = 'snmp_write_community') THEN
        ALTER TABLE huawei_olts ADD COLUMN snmp_write_community VARCHAR(100) DEFAULT 'private';
    END IF;
END $$;

-- Add snmp_read_community column if it doesn't exist (for fresh installs without snmp_community)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'huawei_olts' AND column_name = 'snmp_read_community') THEN
        ALTER TABLE huawei_olts ADD COLUMN snmp_read_community VARCHAR(100) DEFAULT 'public';
    END IF;
END $$;
