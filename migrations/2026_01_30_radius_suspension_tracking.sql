-- Migration: RADIUS Subscription Suspension Tracking
-- Date: 2026-01-30
-- Description: Adds columns to track when a subscription was suspended
--              and how many days remained at the time of suspension.

-- Add suspended_at column to track when subscription was suspended
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS suspended_at TIMESTAMP;

-- Add days_remaining_at_suspension to preserve remaining validity when suspended
ALTER TABLE radius_subscriptions ADD COLUMN IF NOT EXISTS days_remaining_at_suspension INTEGER;
