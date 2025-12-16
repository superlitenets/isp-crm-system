-- Add missing columns to vendors table
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS city VARCHAR(100);
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS country VARCHAR(100) DEFAULT 'Kenya';
