-- License Server Database Schema
-- Run this on your license server database

CREATE TABLE IF NOT EXISTS license_customers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    company VARCHAR(255),
    phone VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS license_products (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    features JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS license_tiers (
    id SERIAL PRIMARY KEY,
    product_id INTEGER REFERENCES license_products(id),
    code VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    max_users INTEGER DEFAULT 0,
    max_customers INTEGER DEFAULT 0,
    max_onus INTEGER DEFAULT 0,
    features JSONB DEFAULT '{}',
    price_monthly DECIMAL(10,2) DEFAULT 0,
    price_yearly DECIMAL(10,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(product_id, code)
);

CREATE TABLE IF NOT EXISTS licenses (
    id SERIAL PRIMARY KEY,
    license_key VARCHAR(64) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES license_customers(id),
    product_id INTEGER REFERENCES license_products(id),
    tier_id INTEGER REFERENCES license_tiers(id),
    
    domain_restriction VARCHAR(255),
    max_activations INTEGER DEFAULT 1,
    
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    is_suspended BOOLEAN DEFAULT FALSE,
    suspension_reason TEXT,
    
    notes TEXT,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS license_activations (
    id SERIAL PRIMARY KEY,
    license_id INTEGER REFERENCES licenses(id),
    activation_token VARCHAR(64) UNIQUE NOT NULL,
    
    domain VARCHAR(255),
    server_ip VARCHAR(45),
    server_hostname VARCHAR(255),
    hardware_id VARCHAR(255),
    
    php_version VARCHAR(20),
    os_info VARCHAR(255),
    
    first_activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_validated_at TIMESTAMP,
    
    is_active BOOLEAN DEFAULT TRUE,
    deactivated_at TIMESTAMP,
    deactivation_reason TEXT,
    
    metadata JSONB DEFAULT '{}'
);

CREATE TABLE IF NOT EXISTS license_validation_logs (
    id SERIAL PRIMARY KEY,
    license_id INTEGER REFERENCES licenses(id),
    activation_id INTEGER REFERENCES license_activations(id),
    
    action VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_data JSONB,
    response_status VARCHAR(20),
    response_message TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS license_subscriptions (
    id SERIAL PRIMARY KEY,
    license_id INTEGER REFERENCES licenses(id),
    tier_id INTEGER REFERENCES license_tiers(id),
    billing_cycle VARCHAR(20) DEFAULT 'monthly',
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'KES',
    status VARCHAR(20) DEFAULT 'pending',
    next_billing_date DATE,
    auto_renew BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS license_payments (
    id SERIAL PRIMARY KEY,
    license_id INTEGER REFERENCES licenses(id),
    subscription_id INTEGER REFERENCES license_subscriptions(id),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'KES',
    payment_method VARCHAR(50) DEFAULT 'mpesa',
    transaction_id VARCHAR(100),
    mpesa_receipt VARCHAR(50),
    phone_number VARCHAR(20),
    status VARCHAR(20) DEFAULT 'pending',
    paid_at TIMESTAMP,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_licenses_key ON licenses(license_key);
CREATE INDEX idx_licenses_customer ON licenses(customer_id);
CREATE INDEX idx_licenses_active ON licenses(is_active, is_suspended);
CREATE INDEX idx_activations_license ON license_activations(license_id);
CREATE INDEX idx_activations_token ON license_activations(activation_token);
CREATE INDEX idx_activations_domain ON license_activations(domain);
CREATE INDEX idx_validation_logs_license ON license_validation_logs(license_id);
CREATE INDEX idx_validation_logs_created ON license_validation_logs(created_at);
CREATE INDEX idx_subscriptions_license ON license_subscriptions(license_id);
CREATE INDEX idx_payments_license ON license_payments(license_id);
CREATE INDEX idx_payments_mpesa ON license_payments(mpesa_receipt);

-- Insert default product
INSERT INTO license_products (code, name, description, features) VALUES 
('isp-crm', 'ISP CRM & OMS System', 'Complete ISP management with CRM, Ticketing, OMS, and more', 
 '{"crm": true, "tickets": true, "oms": true, "hr": true, "inventory": true, "accounting": true}')
ON CONFLICT (code) DO NOTHING;

-- Insert default tiers
INSERT INTO license_tiers (product_id, code, name, max_users, max_customers, max_onus, features, price_monthly, price_yearly) VALUES
((SELECT id FROM license_products WHERE code = 'isp-crm'), 'starter', 'Starter', 3, 100, 50, 
 '{"crm": true, "tickets": true, "oms": false}', 29.99, 299.99),
((SELECT id FROM license_products WHERE code = 'isp-crm'), 'professional', 'Professional', 10, 500, 200, 
 '{"crm": true, "tickets": true, "oms": true, "hr": true}', 79.99, 799.99),
((SELECT id FROM license_products WHERE code = 'isp-crm'), 'enterprise', 'Enterprise', 0, 0, 0, 
 '{"crm": true, "tickets": true, "oms": true, "hr": true, "inventory": true, "accounting": true, "whitelabel": true}', 199.99, 1999.99)
ON CONFLICT (product_id, code) DO NOTHING;
