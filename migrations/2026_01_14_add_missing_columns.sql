-- Migration: Add missing tables and columns for GenieACS integration
-- Run this on production database

-- 1. Create genieacs_config table if not exists
CREATE TABLE IF NOT EXISTS genieacs_config (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Add last_provision_at column to huawei_onus if not exists
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'huawei_onus' AND column_name = 'last_provision_at'
    ) THEN
        ALTER TABLE huawei_onus ADD COLUMN last_provision_at TIMESTAMP;
    END IF;
END $$;

-- 3. Add any other missing columns that may be needed
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'huawei_onus' AND column_name = 'provision_status'
    ) THEN
        ALTER TABLE huawei_onus ADD COLUMN provision_status VARCHAR(50);
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'huawei_onus' AND column_name = 'provision_error'
    ) THEN
        ALTER TABLE huawei_onus ADD COLUMN provision_error TEXT;
    END IF;
END $$;

-- Success message
SELECT 'Migration completed successfully' as status;
