-- Migration: Fix missing tables and columns
-- Date: 2025-12-10

-- Add role_id column to users if missing
ALTER TABLE users ADD COLUMN IF NOT EXISTS role_id INTEGER REFERENCES roles(id) ON DELETE SET NULL;

-- Create vendors table if missing
CREATE TABLE IF NOT EXISTS vendors (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    contact_person VARCHAR(255),
    payment_terms VARCHAR(100),
    notes TEXT,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create vendor_bills table if missing
CREATE TABLE IF NOT EXISTS vendor_bills (
    id SERIAL PRIMARY KEY,
    vendor_id INTEGER REFERENCES vendors(id) ON DELETE CASCADE,
    bill_number VARCHAR(50) UNIQUE,
    bill_date DATE NOT NULL,
    due_date DATE,
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    amount_paid DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'draft',
    notes TEXT,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create vendor_bill_items table if missing
CREATE TABLE IF NOT EXISTS vendor_bill_items (
    id SERIAL PRIMARY KEY,
    bill_id INTEGER REFERENCES vendor_bills(id) ON DELETE CASCADE,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(12,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    amount DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_vendors_status ON vendors(status);
CREATE INDEX IF NOT EXISTS idx_vendor_bills_vendor ON vendor_bills(vendor_id);
CREATE INDEX IF NOT EXISTS idx_vendor_bills_status ON vendor_bills(status);
CREATE INDEX IF NOT EXISTS idx_vendor_bill_items_bill ON vendor_bill_items(bill_id);
