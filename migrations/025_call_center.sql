-- Call Center Module for ISP CRM
-- Integrates with FreePBX via AMI

-- Extensions table (maps CRM users to PBX extensions)
CREATE TABLE IF NOT EXISTS call_center_extensions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    extension VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    secret VARCHAR(100),
    context VARCHAR(50) DEFAULT 'from-internal',
    caller_id VARCHAR(100),
    device_type VARCHAR(20) DEFAULT 'softphone',
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Call queues
CREATE TABLE IF NOT EXISTS call_center_queues (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    extension VARCHAR(20) NOT NULL UNIQUE,
    strategy VARCHAR(50) DEFAULT 'ringall',
    timeout INTEGER DEFAULT 30,
    wrapup_time INTEGER DEFAULT 5,
    max_wait_time INTEGER DEFAULT 300,
    announce_frequency INTEGER DEFAULT 60,
    announce_position BOOLEAN DEFAULT true,
    music_on_hold VARCHAR(100) DEFAULT 'default',
    join_empty BOOLEAN DEFAULT false,
    leave_when_empty BOOLEAN DEFAULT true,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Queue members (agents assigned to queues)
CREATE TABLE IF NOT EXISTS call_center_queue_members (
    id SERIAL PRIMARY KEY,
    queue_id INTEGER NOT NULL REFERENCES call_center_queues(id) ON DELETE CASCADE,
    extension_id INTEGER NOT NULL REFERENCES call_center_extensions(id) ON DELETE CASCADE,
    penalty INTEGER DEFAULT 0,
    paused BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(queue_id, extension_id)
);

-- Call logs (CDR from FreePBX)
CREATE TABLE IF NOT EXISTS call_center_calls (
    id SERIAL PRIMARY KEY,
    uniqueid VARCHAR(100) UNIQUE,
    linkedid VARCHAR(100),
    call_date TIMESTAMP NOT NULL,
    src VARCHAR(50),
    dst VARCHAR(50),
    src_channel VARCHAR(100),
    dst_channel VARCHAR(100),
    duration INTEGER DEFAULT 0,
    billsec INTEGER DEFAULT 0,
    disposition VARCHAR(50),
    recording_file VARCHAR(255),
    direction VARCHAR(20),
    queue_id INTEGER REFERENCES call_center_queues(id) ON DELETE SET NULL,
    extension_id INTEGER REFERENCES call_center_extensions(id) ON DELETE SET NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Agent status history
CREATE TABLE IF NOT EXISTS call_center_agent_status (
    id SERIAL PRIMARY KEY,
    extension_id INTEGER NOT NULL REFERENCES call_center_extensions(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL,
    status_reason VARCHAR(100),
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP,
    duration INTEGER
);

-- SIP Trunks configuration
CREATE TABLE IF NOT EXISTS call_center_trunks (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    trunk_type VARCHAR(20) DEFAULT 'peer',
    host VARCHAR(255) NOT NULL,
    port INTEGER DEFAULT 5060,
    username VARCHAR(100),
    secret VARCHAR(100),
    context VARCHAR(50) DEFAULT 'from-trunk',
    codecs VARCHAR(100) DEFAULT 'ulaw,alaw,g729',
    max_channels INTEGER DEFAULT 30,
    registration BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Outbound routes
CREATE TABLE IF NOT EXISTS call_center_outbound_routes (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    pattern VARCHAR(100) NOT NULL,
    trunk_id INTEGER REFERENCES call_center_trunks(id) ON DELETE CASCADE,
    prepend VARCHAR(20),
    prefix VARCHAR(20),
    priority INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- IVR menus
CREATE TABLE IF NOT EXISTS call_center_ivr (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    extension VARCHAR(20) NOT NULL UNIQUE,
    welcome_message VARCHAR(255),
    timeout INTEGER DEFAULT 10,
    timeout_retries INTEGER DEFAULT 3,
    invalid_retries INTEGER DEFAULT 3,
    invalid_destination VARCHAR(50),
    timeout_destination VARCHAR(50),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- IVR options
CREATE TABLE IF NOT EXISTS call_center_ivr_options (
    id SERIAL PRIMARY KEY,
    ivr_id INTEGER NOT NULL REFERENCES call_center_ivr(id) ON DELETE CASCADE,
    digit VARCHAR(5) NOT NULL,
    destination_type VARCHAR(50) NOT NULL,
    destination_id VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    UNIQUE(ivr_id, digit)
);

-- Speed dials / quick contacts
CREATE TABLE IF NOT EXISTS call_center_speed_dials (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    number VARCHAR(50) NOT NULL,
    category VARCHAR(50),
    is_global BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_calls_date ON call_center_calls(call_date);
CREATE INDEX IF NOT EXISTS idx_calls_customer ON call_center_calls(customer_id);
CREATE INDEX IF NOT EXISTS idx_calls_extension ON call_center_calls(extension_id);
CREATE INDEX IF NOT EXISTS idx_calls_uniqueid ON call_center_calls(uniqueid);
CREATE INDEX IF NOT EXISTS idx_agent_status_ext ON call_center_agent_status(extension_id, started_at);
