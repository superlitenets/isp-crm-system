-- Add default timezone setting if not exists
INSERT INTO company_settings (setting_key, setting_value, setting_type) 
VALUES ('timezone', 'Africa/Nairobi', 'text') 
ON CONFLICT (setting_key) DO NOTHING;
