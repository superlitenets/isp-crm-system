-- ISP Inventory Module - Database Migration
-- Warehouse Serial Number Tracking for ONU/ONT Inventory Management
-- Run this migration against your PostgreSQL database

-- ============================================================
-- 1. Warehouse Serial Number Tracking Table
-- ============================================================
-- Tracks individual items by serial number, linked to aggregate warehouse stock.
-- When an ONU is provisioned on the OLT, its serial is automatically marked
-- as 'deployed' and the warehouse stock quantity is reduced.

CREATE TABLE IF NOT EXISTS isp_warehouse_serials (
    id SERIAL PRIMARY KEY,
    stock_id INTEGER NOT NULL REFERENCES isp_warehouse_stock(id) ON DELETE CASCADE,
    serial_number VARCHAR(100) NOT NULL UNIQUE,
    status VARCHAR(20) NOT NULL DEFAULT 'in_stock',
    site_id INTEGER REFERENCES isp_network_sites(id),
    assigned_to VARCHAR(255),
    received_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Valid status values: 'in_stock', 'deployed', 'faulty', 'returned', 'lost'

-- ============================================================
-- 2. Indexes for Performance
-- ============================================================

CREATE INDEX IF NOT EXISTS idx_warehouse_serials_stock_id ON isp_warehouse_serials(stock_id);
CREATE INDEX IF NOT EXISTS idx_warehouse_serials_serial ON isp_warehouse_serials(serial_number);
CREATE INDEX IF NOT EXISTS idx_warehouse_serials_status ON isp_warehouse_serials(status);
