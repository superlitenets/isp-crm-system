-- Migration: Ticket Resolution with Photo Documentation
-- Date: 2026-01-30
-- Description: Adds tables for tracking ticket resolutions with mandatory photo documentation
--              including router serial, power levels, cables, and optional additional photos.

-- Table: ticket_resolutions
-- Stores resolution details including technical information captured during ticket completion
CREATE TABLE IF NOT EXISTS ticket_resolutions (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER UNIQUE REFERENCES tickets(id) ON DELETE CASCADE,
    resolved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    resolution_notes TEXT,
    router_serial VARCHAR(100),
    power_levels VARCHAR(100),
    cable_used VARCHAR(100),
    equipment_installed TEXT,
    additional_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: ticket_resolution_photos
-- Stores photo documentation for resolutions (serial, power_levels, cables, additional)
CREATE TABLE IF NOT EXISTS ticket_resolution_photos (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    resolution_id INTEGER REFERENCES ticket_resolutions(id) ON DELETE CASCADE,
    photo_type VARCHAR(50) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255),
    caption TEXT,
    uploaded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index for faster photo lookups by ticket
CREATE INDEX IF NOT EXISTS idx_resolution_photos_ticket ON ticket_resolution_photos(ticket_id);

-- Photo types used:
-- 'serial' - Router/ONU serial number photo (required)
-- 'power_levels' - ONU power levels photo (required)
-- 'cables' - Cable installation photo (required)
-- 'additional' - Any additional documentation (optional)
