-- Add stock management columns to equipment table for ISP inventory best practices
-- Run: docker exec -i isp_crm_db psql -U crm -d isp_crm < migrations/2025_12_16_add_equipment_stock_management.sql

-- Add min_stock_level column
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS min_stock_level INTEGER DEFAULT 0;

-- Add max_stock_level column
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS max_stock_level INTEGER DEFAULT 0;

-- Add reorder_point column
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS reorder_point INTEGER DEFAULT 0;

-- Add unit_cost column
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(12, 2) DEFAULT 0;

-- Add quantity column (for bulk items)
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS quantity INTEGER DEFAULT 1;

-- Create index for low stock queries
CREATE INDEX IF NOT EXISTS idx_equipment_stock_levels ON equipment(min_stock_level, reorder_point) WHERE min_stock_level > 0 OR reorder_point > 0;

-- Add comment for documentation
COMMENT ON COLUMN equipment.min_stock_level IS 'Minimum stock level - critical alert threshold';
COMMENT ON COLUMN equipment.max_stock_level IS 'Maximum stock capacity';
COMMENT ON COLUMN equipment.reorder_point IS 'Stock level at which to trigger reorder';
COMMENT ON COLUMN equipment.unit_cost IS 'Cost per unit for inventory valuation';
COMMENT ON COLUMN equipment.quantity IS 'Quantity of this item type in stock';
