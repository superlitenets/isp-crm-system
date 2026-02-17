-- ISP Inventory Module - Complete Database Migration
-- All tables required by the ISP Inventory module
-- Run: docker exec -i isp_crm_db psql -U crm -d isp_crm < migration.sql

-- ============================================================
-- 1. Network Sites
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_network_sites (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    site_type VARCHAR(50) NOT NULL DEFAULT 'pop',
    address TEXT,
    gps_lat NUMERIC(10,7),
    gps_lng NUMERIC(10,7),
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
-- 2. Racks
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_racks (
    id SERIAL PRIMARY KEY,
    site_id INTEGER REFERENCES isp_network_sites(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    rack_units INTEGER DEFAULT 42,
    used_units INTEGER DEFAULT 0,
    location_detail VARCHAR(255),
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 3. Core Equipment
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_core_equipment (
    id SERIAL PRIMARY KEY,
    site_id INTEGER REFERENCES isp_network_sites(id) ON DELETE SET NULL,
    rack_id INTEGER REFERENCES isp_racks(id) ON DELETE SET NULL,
    olt_id INTEGER REFERENCES huawei_olts(id) ON DELETE SET NULL,
    equipment_type VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    manufacturer VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(255),
    mac_address VARCHAR(50),
    management_ip VARCHAR(45),
    os_version VARCHAR(100),
    firmware_version VARCHAR(100),
    rack_position VARCHAR(50),
    uplink_ports TEXT,
    sfp_usage TEXT,
    vlan_config TEXT,
    port_fiber_mapping TEXT,
    capacity VARCHAR(100),
    purchase_date DATE,
    warranty_expiry DATE,
    supplier VARCHAR(255),
    purchase_price NUMERIC(12,2),
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 4. Splitters
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_splitters (
    id SERIAL PRIMARY KEY,
    site_id INTEGER REFERENCES isp_network_sites(id) ON DELETE SET NULL,
    name VARCHAR(255) NOT NULL,
    splitter_type VARCHAR(50),
    ratio VARCHAR(20),
    input_fiber_core_id INTEGER,
    location_description TEXT,
    pole_number VARCHAR(50),
    gps_lat NUMERIC(10,7),
    gps_lng NUMERIC(10,7),
    total_ports INTEGER DEFAULT 8,
    used_ports INTEGER DEFAULT 0,
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 5. Fiber Cores
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_fiber_cores (
    id SERIAL PRIMARY KEY,
    cable_name VARCHAR(255) NOT NULL,
    core_number INTEGER NOT NULL,
    core_color VARCHAR(50),
    tube_color VARCHAR(50),
    source_site_id INTEGER REFERENCES isp_network_sites(id) ON DELETE SET NULL,
    dest_site_id INTEGER REFERENCES isp_network_sites(id) ON DELETE SET NULL,
    source_equipment_id INTEGER REFERENCES isp_core_equipment(id) ON DELETE SET NULL,
    source_port VARCHAR(50),
    dest_equipment_id INTEGER REFERENCES isp_core_equipment(id) ON DELETE SET NULL,
    dest_port VARCHAR(50),
    distance_km NUMERIC(8,2),
    attenuation NUMERIC(6,2),
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 6. Distribution Boxes
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_distribution_boxes (
    id SERIAL PRIMARY KEY,
    site_id INTEGER REFERENCES isp_network_sites(id) ON DELETE SET NULL,
    splitter_id INTEGER REFERENCES isp_splitters(id) ON DELETE SET NULL,
    name VARCHAR(255) NOT NULL,
    box_type VARCHAR(50),
    location_description TEXT,
    pole_number VARCHAR(50),
    gps_lat NUMERIC(10,7),
    gps_lng NUMERIC(10,7),
    total_ports INTEGER DEFAULT 8,
    used_ports INTEGER DEFAULT 0,
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 7. Drop Cables
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_drop_cables (
    id SERIAL PRIMARY KEY,
    distribution_box_id INTEGER REFERENCES isp_distribution_boxes(id) ON DELETE SET NULL,
    port_number INTEGER,
    cable_type VARCHAR(50),
    length_meters NUMERIC(8,2),
    customer_id INTEGER,
    customer_name VARCHAR(255),
    address TEXT,
    gps_lat NUMERIC(10,7),
    gps_lng NUMERIC(10,7),
    installation_date DATE,
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 8. Splice Closures
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_splice_closures (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    closure_type VARCHAR(50),
    location_description TEXT,
    pole_number VARCHAR(50),
    gps_lat NUMERIC(10,7),
    gps_lng NUMERIC(10,7),
    splice_diagram TEXT,
    core_mapping TEXT,
    fiber_core_id INTEGER REFERENCES isp_fiber_cores(id) ON DELETE SET NULL,
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 9. CPE Devices (legacy, ONT Inventory now uses huawei_onus)
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_cpe_devices (
    id SERIAL PRIMARY KEY,
    serial_number VARCHAR(255) NOT NULL,
    mac_address VARCHAR(50),
    model VARCHAR(100),
    manufacturer VARCHAR(100),
    firmware_version VARCHAR(100),
    olt_id INTEGER REFERENCES huawei_olts(id) ON DELETE SET NULL,
    olt_port VARCHAR(50),
    splitter_id INTEGER REFERENCES isp_splitters(id) ON DELETE SET NULL,
    splitter_port INTEGER,
    customer_id INTEGER,
    pppoe_account VARCHAR(100),
    installation_date DATE,
    warranty_expiry DATE,
    purchase_price NUMERIC(10,2),
    supplier VARCHAR(255),
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'in_stock',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 10. Field Assets
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_field_assets (
    id SERIAL PRIMARY KEY,
    asset_type VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    serial_number VARCHAR(255),
    model VARCHAR(100),
    manufacturer VARCHAR(100),
    purchase_date DATE,
    purchase_price NUMERIC(12,2),
    warranty_expiry DATE,
    condition VARCHAR(30) DEFAULT 'good',
    assigned_to INTEGER,
    assigned_to_name VARCHAR(255),
    assignment_date DATE,
    site_id INTEGER REFERENCES isp_network_sites(id) ON DELETE SET NULL,
    next_maintenance DATE,
    last_maintenance DATE,
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 11. Maintenance Logs
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_maintenance_logs (
    id SERIAL PRIMARY KEY,
    asset_type VARCHAR(50) NOT NULL,
    asset_id INTEGER NOT NULL,
    asset_name VARCHAR(255),
    maintenance_type VARCHAR(50) NOT NULL,
    description TEXT,
    performed_by INTEGER,
    performed_by_name VARCHAR(255),
    cost NUMERIC(12,2) DEFAULT 0,
    next_due DATE,
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 12. IP Addresses
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_ip_addresses (
    id SERIAL PRIMARY KEY,
    ip_type VARCHAR(20) DEFAULT 'public',
    ip_address VARCHAR(45) NOT NULL,
    subnet_mask VARCHAR(45),
    cidr INTEGER,
    gateway VARCHAR(45),
    dns_primary VARCHAR(45),
    dns_secondary VARCHAR(45),
    assigned_to VARCHAR(255),
    site_id INTEGER REFERENCES isp_network_sites(id) ON DELETE SET NULL,
    equipment_id INTEGER,
    purpose VARCHAR(255),
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 13. VLANs
-- ============================================================
CREATE TABLE IF NOT EXISTS isp_vlans (
    id SERIAL PRIMARY KEY,
    vlan_id INTEGER NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    site_id INTEGER REFERENCES isp_network_sites(id) ON DELETE SET NULL,
    ip_range VARCHAR(45),
    gateway VARCHAR(45),
    purpose VARCHAR(100),
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 14. Warehouse Stock
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
-- 15. Stock Movements
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

-- ============================================================
-- 16. Warehouse Serial Number Tracking
-- ============================================================
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

CREATE INDEX IF NOT EXISTS idx_warehouse_serials_stock_id ON isp_warehouse_serials(stock_id);
CREATE INDEX IF NOT EXISTS idx_warehouse_serials_serial ON isp_warehouse_serials(serial_number);
CREATE INDEX IF NOT EXISTS idx_warehouse_serials_status ON isp_warehouse_serials(status);
