-- Add link column to user_notifications table
ALTER TABLE user_notifications ADD COLUMN IF NOT EXISTS link VARCHAR(500);
