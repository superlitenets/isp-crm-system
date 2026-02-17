-- ISP Inventory Module - Database Migration
-- Network Sites, Warehouse Stock, Stock Movements, and Serial Number Tracking
-- Run: docker exec -i isp_crm_db psql -U crm -d isp_crm < migration.sql

-- ============================================================
-- 1. Network Sites Table
-- ============================================================

CREATE TABLE IF NOT EXISTS isp_network_sites (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    site_type VARCHAR(50) NOT NULL DEFAULT 'pop',
    address TEXT,
    gps_lat NUMERIC,
    gps_lng NUMERIC,
    contact_person VARCHAR(255),
    contact_phone VARCHAR(50),
    power_source VARCHAR(100),
    ups_capacity VARCHAR(100),
    ups_battery_health VARCHAR(50),
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 2. Warehouse Stock Table
-- ============================================================

CREATE TABLE IF NOT EXISTS isp_warehouse_stock (
    id SERIAL PRIMARY KEY,
    site_id INTEGER REFERENCES isp_network_sites(id) ON DELETE SET NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    unit VARCHAR(30) DEFAULT 'pcs',
    quantity NUMERIC NOT NULL DEFAULT 0,
    min_threshold NUMERIC DEFAULT 0,
    unit_cost NUMERIC DEFAULT 0,
    supplier VARCHAR(255),
    supplier_contact VARCHAR(100),
    storage_location VARCHAR(255),
    last_restocked DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_isp_stock_category ON isp_warehouse_stock(category);

-- ============================================================
-- 3. Stock Movements Table
-- ============================================================

CREATE TABLE IF NOT EXISTS isp_stock_movements (
    id SERIAL PRIMARY KEY,
    stock_id INTEGER REFERENCES isp_warehouse_stock(id) ON DELETE CASCADE,
    movement_type VARCHAR(30) NOT NULL,
    quantity NUMERIC NOT NULL,
    reference_number VARCHAR(100),
    from_location VARCHAR(255),
    to_location VARCHAR(255),
    performed_by INTEGER,
    reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Valid movement_type values: 'intake', 'dispatch', 'return', 'usage', 'loss',
--                             'adjustment_add', 'adjustment_remove'

-- ============================================================
-- 4. Warehouse Serial Number Tracking Table
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

CREATE INDEX IF NOT EXISTS idx_warehouse_serials_stock_id ON isp_warehouse_serials(stock_id);
CREATE INDEX IF NOT EXISTS idx_warehouse_serials_serial ON isp_warehouse_serials(serial_number);
CREATE INDEX IF NOT EXISTS idx_warehouse_serials_status ON isp_warehouse_serials(status);
