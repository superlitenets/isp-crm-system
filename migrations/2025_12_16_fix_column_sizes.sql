-- Migration: Fix column sizes for WhatsApp group IDs
-- Date: 2025-12-16
-- Description: Increase recipient_phone column size to accommodate WhatsApp group IDs

-- Increase recipient_phone to handle group IDs like "120363395097732236@g.us"
ALTER TABLE whatsapp_logs ALTER COLUMN recipient_phone TYPE VARCHAR(100);

-- Also increase recipient_type just in case
ALTER TABLE whatsapp_logs ALTER COLUMN recipient_type TYPE VARCHAR(50);
