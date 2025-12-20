-- OMS Location Management Tables
-- Zones (top-level geographic areas)
CREATE TABLE IF NOT EXISTS huawei_zones (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Subzones (subdivisions within zones)
CREATE TABLE IF NOT EXISTS huawei_subzones (
    id SERIAL PRIMARY KEY,
    zone_id INTEGER NOT NULL REFERENCES huawei_zones(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Apartments/Buildings
CREATE TABLE IF NOT EXISTS huawei_apartments (
    id SERIAL PRIMARY KEY,
    zone_id INTEGER NOT NULL REFERENCES huawei_zones(id) ON DELETE CASCADE,
    subzone_id INTEGER REFERENCES huawei_subzones(id) ON DELETE SET NULL,
    name VARCHAR(150) NOT NULL,
    address TEXT,
    floors INTEGER,
    units_count INTEGER,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ODB (Optical Distribution Box) Units
CREATE TABLE IF NOT EXISTS huawei_odb_units (
    id SERIAL PRIMARY KEY,
    zone_id INTEGER NOT NULL REFERENCES huawei_zones(id) ON DELETE CASCADE,
    subzone_id INTEGER REFERENCES huawei_subzones(id) ON DELETE SET NULL,
    apartment_id INTEGER REFERENCES huawei_apartments(id) ON DELETE SET NULL,
    code VARCHAR(50) NOT NULL,
    capacity INTEGER DEFAULT 8,
    ports_used INTEGER DEFAULT 0,
    location_description TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add location columns to huawei_onus if they don't exist
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'huawei_onus' AND column_name = 'zone_id') THEN
        ALTER TABLE huawei_onus ADD COLUMN zone_id INTEGER REFERENCES huawei_zones(id) ON DELETE SET NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'huawei_onus' AND column_name = 'subzone_id') THEN
        ALTER TABLE huawei_onus ADD COLUMN subzone_id INTEGER REFERENCES huawei_subzones(id) ON DELETE SET NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'huawei_onus' AND column_name = 'apartment_id') THEN
        ALTER TABLE huawei_onus ADD COLUMN apartment_id INTEGER REFERENCES huawei_apartments(id) ON DELETE SET NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'huawei_onus' AND column_name = 'odb_id') THEN
        ALTER TABLE huawei_onus ADD COLUMN odb_id INTEGER REFERENCES huawei_odb_units(id) ON DELETE SET NULL;
    END IF;
END $$;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_huawei_subzones_zone ON huawei_subzones(zone_id);
CREATE INDEX IF NOT EXISTS idx_huawei_apartments_zone ON huawei_apartments(zone_id);
CREATE INDEX IF NOT EXISTS idx_huawei_apartments_subzone ON huawei_apartments(subzone_id);
CREATE INDEX IF NOT EXISTS idx_huawei_odb_units_zone ON huawei_odb_units(zone_id);
CREATE INDEX IF NOT EXISTS idx_huawei_odb_units_apartment ON huawei_odb_units(apartment_id);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_zone ON huawei_onus(zone_id);
CREATE INDEX IF NOT EXISTS idx_huawei_onus_odb ON huawei_onus(odb_id);
