-- Migration for ticket status and customer view link tokens
-- Created: 2025-12-19

-- Technician ticket status tokens table
-- Used for secure links that allow technicians to update ticket status without logging in
CREATE TABLE IF NOT EXISTS ticket_status_tokens (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    token_lookup VARCHAR(64) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    usage_count INTEGER DEFAULT 0,
    max_uses INTEGER DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE INDEX IF NOT EXISTS idx_ticket_status_tokens_lookup ON ticket_status_tokens(token_lookup);
CREATE INDEX IF NOT EXISTS idx_ticket_status_tokens_ticket ON ticket_status_tokens(ticket_id);
CREATE INDEX IF NOT EXISTS idx_ticket_status_tokens_expires ON ticket_status_tokens(expires_at);
CREATE INDEX IF NOT EXISTS idx_ticket_status_tokens_active ON ticket_status_tokens(is_active);

-- Customer ticket view tokens table
-- Used for secure links that allow customers to view ticket progress and submit ratings
CREATE TABLE IF NOT EXISTS customer_ticket_tokens (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    token_lookup VARCHAR(64) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    usage_count INTEGER DEFAULT 0,
    max_uses INTEGER DEFAULT 50,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE INDEX IF NOT EXISTS idx_customer_ticket_tokens_lookup ON customer_ticket_tokens(token_lookup);
CREATE INDEX IF NOT EXISTS idx_customer_ticket_tokens_ticket ON customer_ticket_tokens(ticket_id);
CREATE INDEX IF NOT EXISTS idx_customer_ticket_tokens_expires ON customer_ticket_tokens(expires_at);
CREATE INDEX IF NOT EXISTS idx_customer_ticket_tokens_active ON customer_ticket_tokens(is_active);
