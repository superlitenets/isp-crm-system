<?php

require_once __DIR__ . '/database.php';

function initializeDatabase(): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    
    $db = Database::getConnection();
    
    $checkTable = $db->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'users')");
    $tablesExist = $checkTable->fetchColumn();
    
    if ($tablesExist) {
        $initialized = true;
        return;
    }

    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20) NOT NULL,
        password_hash VARCHAR(255),
        role VARCHAR(20) NOT NULL DEFAULT 'technician',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS customers (
        id SERIAL PRIMARY KEY,
        account_number VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20) NOT NULL,
        address TEXT NOT NULL,
        service_plan VARCHAR(50) NOT NULL,
        connection_status VARCHAR(20) DEFAULT 'active',
        installation_date DATE,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS tickets (
        id SERIAL PRIMARY KEY,
        ticket_number VARCHAR(20) UNIQUE NOT NULL,
        customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE,
        assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
        subject VARCHAR(200) NOT NULL,
        description TEXT NOT NULL,
        category VARCHAR(50) NOT NULL,
        priority VARCHAR(20) DEFAULT 'medium',
        status VARCHAR(20) DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS ticket_comments (
        id SERIAL PRIMARY KEY,
        ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
        user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
        comment TEXT NOT NULL,
        is_internal BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS sms_logs (
        id SERIAL PRIMARY KEY,
        ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
        recipient_phone VARCHAR(20) NOT NULL,
        recipient_type VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS departments (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        manager_id INTEGER,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS employees (
        id SERIAL PRIMARY KEY,
        employee_id VARCHAR(20) UNIQUE NOT NULL,
        user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20) NOT NULL,
        department_id INTEGER REFERENCES departments(id) ON DELETE SET NULL,
        position VARCHAR(100) NOT NULL,
        salary DECIMAL(12, 2),
        hire_date DATE,
        employment_status VARCHAR(20) DEFAULT 'active',
        emergency_contact VARCHAR(100),
        emergency_phone VARCHAR(20),
        address TEXT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    ALTER TABLE departments DROP CONSTRAINT IF EXISTS fk_manager;
    DO $$ BEGIN
        IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_manager') THEN
            ALTER TABLE departments ADD CONSTRAINT fk_manager FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL;
        END IF;
    END $$;

    CREATE TABLE IF NOT EXISTS attendance (
        id SERIAL PRIMARY KEY,
        employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
        date DATE NOT NULL,
        clock_in TIME,
        clock_out TIME,
        status VARCHAR(20) DEFAULT 'present',
        hours_worked DECIMAL(5, 2),
        overtime_hours DECIMAL(5, 2) DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(employee_id, date)
    );

    CREATE TABLE IF NOT EXISTS payroll (
        id SERIAL PRIMARY KEY,
        employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
        pay_period_start DATE NOT NULL,
        pay_period_end DATE NOT NULL,
        base_salary DECIMAL(12, 2) NOT NULL,
        overtime_pay DECIMAL(12, 2) DEFAULT 0,
        bonuses DECIMAL(12, 2) DEFAULT 0,
        deductions DECIMAL(12, 2) DEFAULT 0,
        tax DECIMAL(12, 2) DEFAULT 0,
        net_pay DECIMAL(12, 2) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        payment_date DATE,
        payment_method VARCHAR(50),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS performance_reviews (
        id SERIAL PRIMARY KEY,
        employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
        reviewer_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
        review_period_start DATE NOT NULL,
        review_period_end DATE NOT NULL,
        overall_rating INTEGER CHECK (overall_rating >= 1 AND overall_rating <= 5),
        productivity_rating INTEGER CHECK (productivity_rating >= 1 AND productivity_rating <= 5),
        quality_rating INTEGER CHECK (quality_rating >= 1 AND quality_rating <= 5),
        teamwork_rating INTEGER CHECK (teamwork_rating >= 1 AND teamwork_rating <= 5),
        communication_rating INTEGER CHECK (communication_rating >= 1 AND communication_rating <= 5),
        goals_achieved TEXT,
        strengths TEXT,
        areas_for_improvement TEXT,
        goals_next_period TEXT,
        comments TEXT,
        status VARCHAR(20) DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS company_settings (
        id SERIAL PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type VARCHAR(20) DEFAULT 'text',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS ticket_templates (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        category VARCHAR(50),
        subject VARCHAR(200),
        content TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS whatsapp_logs (
        id SERIAL PRIMARY KEY,
        ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
        recipient_phone VARCHAR(20) NOT NULL,
        recipient_type VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS biometric_devices (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        device_type VARCHAR(20) NOT NULL CHECK (device_type IN ('zkteco', 'hikvision')),
        ip_address VARCHAR(45) NOT NULL,
        port INTEGER DEFAULT 4370,
        username VARCHAR(100),
        password_encrypted TEXT,
        sync_interval_minutes INTEGER DEFAULT 15,
        is_active BOOLEAN DEFAULT TRUE,
        last_sync_at TIMESTAMP,
        last_sync_status VARCHAR(50),
        last_sync_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS biometric_attendance_logs (
        id SERIAL PRIMARY KEY,
        device_id INTEGER REFERENCES biometric_devices(id) ON DELETE CASCADE,
        employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
        device_user_id VARCHAR(50) NOT NULL,
        log_time TIMESTAMP NOT NULL,
        direction VARCHAR(10) CHECK (direction IN ('in', 'out', 'unknown')),
        verification_type VARCHAR(20),
        raw_data JSONB,
        synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed BOOLEAN DEFAULT FALSE,
        UNIQUE(device_id, device_user_id, log_time)
    );

    CREATE TABLE IF NOT EXISTS device_user_mapping (
        id SERIAL PRIMARY KEY,
        device_id INTEGER REFERENCES biometric_devices(id) ON DELETE CASCADE,
        device_user_id VARCHAR(50) NOT NULL,
        employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
        device_user_name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(device_id, device_user_id)
    );

    CREATE TABLE IF NOT EXISTS late_rules (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        work_start_time TIME NOT NULL DEFAULT '09:00',
        grace_minutes INTEGER DEFAULT 15,
        deduction_tiers JSONB NOT NULL DEFAULT '[]',
        currency VARCHAR(10) DEFAULT 'KES',
        apply_to_department_id INTEGER REFERENCES departments(id) ON DELETE SET NULL,
        is_default BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS payroll_deductions (
        id SERIAL PRIMARY KEY,
        payroll_id INTEGER REFERENCES payroll(id) ON DELETE CASCADE,
        employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
        deduction_type VARCHAR(50) NOT NULL,
        description TEXT,
        amount DECIMAL(12, 2) NOT NULL,
        details JSONB,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS service_packages (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) UNIQUE NOT NULL,
        description TEXT,
        speed VARCHAR(50) NOT NULL,
        speed_unit VARCHAR(10) DEFAULT 'Mbps',
        price DECIMAL(10, 2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'KES',
        billing_cycle VARCHAR(20) DEFAULT 'monthly',
        features JSONB DEFAULT '[]',
        is_popular BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        display_order INTEGER DEFAULT 0,
        badge_text VARCHAR(50),
        badge_color VARCHAR(20),
        icon VARCHAR(50) DEFAULT 'wifi',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    ALTER TABLE attendance ADD COLUMN IF NOT EXISTS late_minutes INTEGER DEFAULT 0;
    ALTER TABLE attendance ADD COLUMN IF NOT EXISTS source VARCHAR(20) DEFAULT 'manual';
    ALTER TABLE attendance ADD COLUMN IF NOT EXISTS biometric_log_id INTEGER;

    CREATE INDEX IF NOT EXISTS idx_tickets_customer ON tickets(customer_id);
    CREATE INDEX IF NOT EXISTS idx_tickets_assigned ON tickets(assigned_to);
    CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);
    CREATE INDEX IF NOT EXISTS idx_tickets_created ON tickets(created_at DESC);
    CREATE INDEX IF NOT EXISTS idx_ticket_comments_ticket ON ticket_comments(ticket_id);
    CREATE INDEX IF NOT EXISTS idx_sms_logs_ticket ON sms_logs(ticket_id);
    CREATE INDEX IF NOT EXISTS idx_sms_logs_sent ON sms_logs(sent_at DESC);
    CREATE INDEX IF NOT EXISTS idx_customers_account ON customers(account_number);
    CREATE INDEX IF NOT EXISTS idx_customers_name ON customers(name);
    CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers(phone);
    CREATE INDEX IF NOT EXISTS idx_employees_department ON employees(department_id);
    CREATE INDEX IF NOT EXISTS idx_employees_status ON employees(employment_status);
    CREATE INDEX IF NOT EXISTS idx_employees_emp_id ON employees(employee_id);
    CREATE INDEX IF NOT EXISTS idx_attendance_employee ON attendance(employee_id);
    CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance(date);
    CREATE INDEX IF NOT EXISTS idx_payroll_employee ON payroll(employee_id);
    CREATE INDEX IF NOT EXISTS idx_payroll_period ON payroll(pay_period_start, pay_period_end);
    CREATE INDEX IF NOT EXISTS idx_payroll_status ON payroll(status);
    CREATE INDEX IF NOT EXISTS idx_performance_employee ON performance_reviews(employee_id);
    CREATE INDEX IF NOT EXISTS idx_ticket_templates_category ON ticket_templates(category);
    CREATE INDEX IF NOT EXISTS idx_company_settings_key ON company_settings(setting_key);
    CREATE INDEX IF NOT EXISTS idx_whatsapp_logs_ticket ON whatsapp_logs(ticket_id);
    CREATE INDEX IF NOT EXISTS idx_whatsapp_logs_sent ON whatsapp_logs(sent_at DESC);
    CREATE INDEX IF NOT EXISTS idx_biometric_devices_active ON biometric_devices(is_active);
    CREATE INDEX IF NOT EXISTS idx_biometric_logs_device ON biometric_attendance_logs(device_id);
    CREATE INDEX IF NOT EXISTS idx_biometric_logs_employee ON biometric_attendance_logs(employee_id);
    CREATE INDEX IF NOT EXISTS idx_biometric_logs_time ON biometric_attendance_logs(log_time);
    CREATE INDEX IF NOT EXISTS idx_biometric_logs_processed ON biometric_attendance_logs(processed);
    CREATE INDEX IF NOT EXISTS idx_device_mapping_device ON device_user_mapping(device_id);
    CREATE INDEX IF NOT EXISTS idx_device_mapping_employee ON device_user_mapping(employee_id);
    CREATE INDEX IF NOT EXISTS idx_late_rules_active ON late_rules(is_active);
    CREATE INDEX IF NOT EXISTS idx_payroll_deductions_payroll ON payroll_deductions(payroll_id);
    CREATE INDEX IF NOT EXISTS idx_payroll_deductions_employee ON payroll_deductions(employee_id);
    CREATE INDEX IF NOT EXISTS idx_payroll_deductions_type ON payroll_deductions(deduction_type);
    CREATE INDEX IF NOT EXISTS idx_service_packages_active ON service_packages(is_active);
    CREATE INDEX IF NOT EXISTS idx_service_packages_order ON service_packages(display_order);
    ";

    try {
        $db->exec($sql);
        
        $checkUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($checkUsers == 0) {
            $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
            $techPass = password_hash('tech123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                INSERT INTO users (name, email, phone, password_hash, role) VALUES
                ('Admin User', 'admin@isp.com', '+1234567890', ?, 'admin'),
                ('John Tech', 'john@isp.com', '+1234567891', ?, 'technician'),
                ('Jane Support', 'jane@isp.com', '+1234567892', ?, 'technician')
            ");
            $stmt->execute([$adminPass, $techPass, $techPass]);
        }
        
        $initialized = true;
        
        if (php_sapi_name() === 'cli') {
            echo "Database initialized successfully!\n";
        }
    } catch (PDOException $e) {
        if (php_sapi_name() === 'cli') {
            die("Database initialization failed: " . $e->getMessage());
        }
        error_log("Database initialization failed: " . $e->getMessage());
    }
}

if (php_sapi_name() === 'cli') {
    initializeDatabase();
}
