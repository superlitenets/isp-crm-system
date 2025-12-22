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
        runMigrations($db);
        $initialized = true;
        return;
    }

    $sql = "
    CREATE TABLE IF NOT EXISTS roles (
        id SERIAL PRIMARY KEY,
        name VARCHAR(50) UNIQUE NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        description TEXT,
        is_system BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS permissions (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        display_name VARCHAR(150) NOT NULL,
        category VARCHAR(50) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS role_permissions (
        id SERIAL PRIMARY KEY,
        role_id INTEGER REFERENCES roles(id) ON DELETE CASCADE,
        permission_id INTEGER REFERENCES permissions(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(role_id, permission_id)
    );

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
        created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS tickets (
        id SERIAL PRIMARY KEY,
        ticket_number VARCHAR(20) UNIQUE NOT NULL,
        customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE,
        assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
        created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
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
        clock_in_latitude DECIMAL(10, 8),
        clock_in_longitude DECIMAL(11, 8),
        clock_out_latitude DECIMAL(10, 8),
        clock_out_longitude DECIMAL(11, 8),
        clock_in_address TEXT,
        clock_out_address TEXT,
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

    CREATE TABLE IF NOT EXISTS settings (
        id SERIAL PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
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
        order_id INTEGER,
        complaint_id INTEGER,
        recipient_phone VARCHAR(100) NOT NULL,
        recipient_type VARCHAR(50) NOT NULL,
        message_type VARCHAR(50) DEFAULT 'custom',
        message TEXT,
        status VARCHAR(20) DEFAULT 'pending',
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS complaints (
        id SERIAL PRIMARY KEY,
        complaint_number VARCHAR(20) UNIQUE NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_email VARCHAR(100),
        customer_phone VARCHAR(20) NOT NULL,
        customer_address TEXT,
        subject VARCHAR(200) NOT NULL,
        description TEXT NOT NULL,
        category VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        priority VARCHAR(20) DEFAULT 'medium',
        customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
        converted_ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
        approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
        approved_at TIMESTAMP,
        rejection_reason TEXT,
        source VARCHAR(20) DEFAULT 'web',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

    CREATE TABLE IF NOT EXISTS mpesa_transactions (
        id SERIAL PRIMARY KEY,
        transaction_type VARCHAR(20) NOT NULL,
        merchant_request_id VARCHAR(100),
        checkout_request_id VARCHAR(100),
        result_code INTEGER,
        result_desc TEXT,
        mpesa_receipt_number VARCHAR(50),
        transaction_date TIMESTAMP,
        phone_number VARCHAR(20),
        amount DECIMAL(12, 2),
        account_reference VARCHAR(100),
        transaction_desc TEXT,
        customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
        invoice_id INTEGER,
        status VARCHAR(20) DEFAULT 'pending',
        raw_callback JSONB,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS orders (
        id SERIAL PRIMARY KEY,
        order_number VARCHAR(20) UNIQUE NOT NULL,
        package_id INTEGER REFERENCES service_packages(id) ON DELETE SET NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_email VARCHAR(100),
        customer_phone VARCHAR(20) NOT NULL,
        customer_address TEXT,
        customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
        payment_status VARCHAR(20) DEFAULT 'pending',
        payment_method VARCHAR(20),
        mpesa_transaction_id INTEGER REFERENCES mpesa_transactions(id) ON DELETE SET NULL,
        amount DECIMAL(12, 2),
        order_status VARCHAR(20) DEFAULT 'new',
        notes TEXT,
        assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
        converted_ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
        source VARCHAR(20) DEFAULT 'web',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS biometric_devices (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        device_type VARCHAR(20) NOT NULL CHECK (device_type IN ('zkteco', 'hikvision')),
        ip_address VARCHAR(45) NOT NULL,
        port INTEGER DEFAULT 4370,
        username VARCHAR(100),
        password_encrypted TEXT,
        serial_number VARCHAR(100),
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

    CREATE TABLE IF NOT EXISTS ticket_commission_rates (
        id SERIAL PRIMARY KEY,
        category VARCHAR(50) NOT NULL UNIQUE,
        rate DECIMAL(12, 2) NOT NULL DEFAULT 0,
        currency VARCHAR(10) DEFAULT 'KES',
        description TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS ticket_categories (
        id SERIAL PRIMARY KEY,
        key VARCHAR(50) NOT NULL UNIQUE,
        label VARCHAR(100) NOT NULL,
        description TEXT,
        color VARCHAR(20) DEFAULT 'primary',
        display_order INTEGER DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS teams (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        leader_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS team_members (
        id SERIAL PRIMARY KEY,
        team_id INTEGER REFERENCES teams(id) ON DELETE CASCADE,
        employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(team_id, employee_id)
    );

    CREATE TABLE IF NOT EXISTS ticket_earnings (
        id SERIAL PRIMARY KEY,
        ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
        employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
        team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL,
        category VARCHAR(50) NOT NULL,
        full_rate DECIMAL(12, 2) NOT NULL,
        earned_amount DECIMAL(12, 2) NOT NULL,
        share_count INTEGER DEFAULT 1,
        currency VARCHAR(10) DEFAULT 'KES',
        status VARCHAR(20) DEFAULT 'pending',
        payroll_id INTEGER REFERENCES payroll(id) ON DELETE SET NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS payroll_commissions (
        id SERIAL PRIMARY KEY,
        payroll_id INTEGER REFERENCES payroll(id) ON DELETE CASCADE,
        employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
        commission_type VARCHAR(50) NOT NULL DEFAULT 'ticket',
        description TEXT,
        amount DECIMAL(12, 2) NOT NULL,
        details JSONB,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    ALTER TABLE attendance ADD COLUMN IF NOT EXISTS late_minutes INTEGER DEFAULT 0;
    ALTER TABLE attendance ADD COLUMN IF NOT EXISTS deduction DECIMAL(10,2) DEFAULT 0;
    ALTER TABLE attendance ADD COLUMN IF NOT EXISTS source VARCHAR(20) DEFAULT 'manual';
    ALTER TABLE attendance ADD COLUMN IF NOT EXISTS biometric_log_id INTEGER;

    CREATE TABLE IF NOT EXISTS equipment_categories (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS equipment (
        id SERIAL PRIMARY KEY,
        category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
        name VARCHAR(100) NOT NULL,
        serial_number VARCHAR(100),
        model VARCHAR(100),
        manufacturer VARCHAR(100),
        purchase_date DATE,
        purchase_price DECIMAL(12, 2),
        status VARCHAR(20) DEFAULT 'available',
        condition VARCHAR(20) DEFAULT 'good',
        location VARCHAR(100),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    ALTER TABLE tickets ADD COLUMN IF NOT EXISTS closure_details JSONB DEFAULT '{}';
    ALTER TABLE tickets ADD COLUMN IF NOT EXISTS equipment_used_id INTEGER REFERENCES equipment(id);

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
        
        $checkRoles = $db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
        if ($checkRoles == 0) {
            $db->exec("
                INSERT INTO roles (name, display_name, description, is_system) VALUES
                ('admin', 'Administrator', 'Full system access', TRUE),
                ('manager', 'Manager', 'Management access', TRUE),
                ('technician', 'Technician', 'Field technician access', TRUE),
                ('support', 'Support Staff', 'Customer support access', TRUE),
                ('hr', 'HR Manager', 'Human resources access', TRUE),
                ('sales', 'Salesperson', 'Sales and marketing access', TRUE)
            ");
        }
        
        $checkUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($checkUsers == 0) {
            $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
            $techPass = password_hash('tech123', PASSWORD_DEFAULT);
            
            $adminRoleId = $db->query("SELECT id FROM roles WHERE name = 'admin' LIMIT 1")->fetchColumn() ?: null;
            $techRoleId = $db->query("SELECT id FROM roles WHERE name = 'technician' LIMIT 1")->fetchColumn() ?: null;
            
            $stmt = $db->prepare("
                INSERT INTO users (name, email, phone, password_hash, role, role_id) VALUES
                ('Admin User', 'admin@isp.com', '+1234567890', ?, 'admin', ?),
                ('John Tech', 'john@isp.com', '+1234567891', ?, 'technician', ?),
                ('Jane Support', 'jane@isp.com', '+1234567892', ?, 'technician', ?)
            ");
            $stmt->execute([$adminPass, $adminRoleId, $techPass, $techRoleId, $techPass, $techRoleId]);
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

function runMigrations(PDO $db): void {
    // Check if migrations have already been applied using a version hash
    // This reduces ~110 queries per page load to just 1-2 queries
    $migrationVersion = 'v2024122004'; // Increment this when adding new migrations
    
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            id SERIAL PRIMARY KEY,
            version VARCHAR(50) NOT NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $stmt = $db->prepare("SELECT version FROM schema_migrations ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $currentVersion = $stmt->fetchColumn();
        
        if ($currentVersion === $migrationVersion) {
            // Migrations are up to date, skip all checks
            return;
        }
    } catch (PDOException $e) {
        error_log("Migration version check failed: " . $e->getMessage());
    }
    
    $tables = [
        'biometric_devices' => "
            CREATE TABLE IF NOT EXISTS biometric_devices (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                device_type VARCHAR(20) NOT NULL,
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
            )",
        'biometric_attendance_logs' => "
            CREATE TABLE IF NOT EXISTS biometric_attendance_logs (
                id SERIAL PRIMARY KEY,
                device_id INTEGER,
                employee_id INTEGER,
                device_user_id VARCHAR(50),
                log_time TIMESTAMP NOT NULL,
                log_type VARCHAR(20) DEFAULT 'check',
                verify_mode VARCHAR(20),
                raw_data JSONB,
                processed BOOLEAN DEFAULT FALSE,
                attendance_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'device_user_mapping' => "
            CREATE TABLE IF NOT EXISTS device_user_mapping (
                id SERIAL PRIMARY KEY,
                device_id INTEGER,
                device_user_id VARCHAR(50) NOT NULL,
                employee_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(device_id, device_user_id)
            )",
        'late_rules' => "
            CREATE TABLE IF NOT EXISTS late_rules (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                work_start_time TIME NOT NULL DEFAULT '09:00',
                grace_minutes INTEGER DEFAULT 15,
                deduction_tiers JSONB NOT NULL DEFAULT '[]',
                currency VARCHAR(10) DEFAULT 'KES',
                apply_to_department_id INTEGER,
                is_default BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'service_packages' => "
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
            )",
        'mpesa_transactions' => "
            CREATE TABLE IF NOT EXISTS mpesa_transactions (
                id SERIAL PRIMARY KEY,
                transaction_type VARCHAR(20) NOT NULL,
                merchant_request_id VARCHAR(100),
                checkout_request_id VARCHAR(100),
                result_code INTEGER,
                result_desc TEXT,
                mpesa_receipt_number VARCHAR(50),
                transaction_date TIMESTAMP,
                phone_number VARCHAR(20),
                amount DECIMAL(12, 2),
                account_reference VARCHAR(100),
                transaction_desc TEXT,
                customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
                invoice_id INTEGER,
                status VARCHAR(20) DEFAULT 'pending',
                raw_callback JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'mpesa_c2b_transactions' => "
            CREATE TABLE IF NOT EXISTS mpesa_c2b_transactions (
                id SERIAL PRIMARY KEY,
                transaction_type VARCHAR(20),
                trans_id VARCHAR(50) UNIQUE,
                trans_time TIMESTAMP,
                trans_amount DECIMAL(12, 2),
                business_short_code VARCHAR(20),
                bill_ref_number VARCHAR(100),
                invoice_number VARCHAR(100),
                org_account_balance DECIMAL(12, 2),
                third_party_trans_id VARCHAR(100),
                msisdn VARCHAR(20),
                first_name VARCHAR(100),
                middle_name VARCHAR(100),
                last_name VARCHAR(100),
                customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
                status VARCHAR(20) DEFAULT 'received',
                raw_data JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'mpesa_config' => "
            CREATE TABLE IF NOT EXISTS mpesa_config (
                id SERIAL PRIMARY KEY,
                config_key VARCHAR(50) UNIQUE NOT NULL,
                config_value TEXT,
                is_encrypted BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'orders' => "
            CREATE TABLE IF NOT EXISTS orders (
                id SERIAL PRIMARY KEY,
                order_number VARCHAR(20) UNIQUE NOT NULL,
                package_id INTEGER REFERENCES service_packages(id) ON DELETE SET NULL,
                customer_name VARCHAR(100) NOT NULL,
                customer_email VARCHAR(100),
                customer_phone VARCHAR(20) NOT NULL,
                customer_address TEXT,
                customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
                payment_status VARCHAR(20) DEFAULT 'pending',
                payment_method VARCHAR(20),
                mpesa_transaction_id INTEGER REFERENCES mpesa_transactions(id) ON DELETE SET NULL,
                amount DECIMAL(12, 2),
                order_status VARCHAR(20) DEFAULT 'new',
                notes TEXT,
                ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
                created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'salespersons' => "
            CREATE TABLE IF NOT EXISTS salespersons (
                id SERIAL PRIMARY KEY,
                employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
                user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100),
                phone VARCHAR(20) NOT NULL,
                commission_type VARCHAR(20) DEFAULT 'percentage',
                commission_value DECIMAL(10, 2) DEFAULT 0,
                total_sales DECIMAL(12, 2) DEFAULT 0,
                total_commission DECIMAL(12, 2) DEFAULT 0,
                is_active BOOLEAN DEFAULT TRUE,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'sales_commissions' => "
            CREATE TABLE IF NOT EXISTS sales_commissions (
                id SERIAL PRIMARY KEY,
                salesperson_id INTEGER REFERENCES salespersons(id) ON DELETE CASCADE,
                order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
                order_amount DECIMAL(12, 2) NOT NULL,
                commission_type VARCHAR(20) NOT NULL,
                commission_rate DECIMAL(10, 2) NOT NULL,
                commission_amount DECIMAL(12, 2) NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                paid_at TIMESTAMP,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'equipment_categories' => "
            CREATE TABLE IF NOT EXISTS equipment_categories (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'equipment' => "
            CREATE TABLE IF NOT EXISTS equipment (
                id SERIAL PRIMARY KEY,
                category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
                name VARCHAR(100) NOT NULL,
                brand VARCHAR(100),
                model VARCHAR(100),
                serial_number VARCHAR(100),
                mac_address VARCHAR(50),
                purchase_date DATE,
                purchase_price DECIMAL(12, 2),
                warranty_expiry DATE,
                condition VARCHAR(20) DEFAULT 'new',
                status VARCHAR(20) DEFAULT 'available',
                location VARCHAR(200),
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'equipment_assignments' => "
            CREATE TABLE IF NOT EXISTS equipment_assignments (
                id SERIAL PRIMARY KEY,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
                employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
                customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
                assigned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                assignment_date DATE NOT NULL,
                return_date DATE,
                status VARCHAR(20) DEFAULT 'assigned',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'equipment_loans' => "
            CREATE TABLE IF NOT EXISTS equipment_loans (
                id SERIAL PRIMARY KEY,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
                customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
                loaned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                loan_date DATE NOT NULL,
                expected_return_date DATE,
                actual_return_date DATE,
                deposit_amount DECIMAL(12, 2) DEFAULT 0,
                status VARCHAR(20) DEFAULT 'on_loan',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'equipment_faults' => "
            CREATE TABLE IF NOT EXISTS equipment_faults (
                id SERIAL PRIMARY KEY,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
                reported_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                fault_date DATE NOT NULL,
                description TEXT NOT NULL,
                severity VARCHAR(20) DEFAULT 'medium',
                repair_status VARCHAR(20) DEFAULT 'pending',
                repair_date DATE,
                repair_cost DECIMAL(12, 2),
                repair_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_warehouses' => "
            CREATE TABLE IF NOT EXISTS inventory_warehouses (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(20) UNIQUE NOT NULL,
                type VARCHAR(30) NOT NULL DEFAULT 'depot',
                address TEXT,
                phone VARCHAR(20),
                manager_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                is_active BOOLEAN DEFAULT TRUE,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_locations' => "
            CREATE TABLE IF NOT EXISTS inventory_locations (
                id SERIAL PRIMARY KEY,
                warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE CASCADE,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(50),
                type VARCHAR(30) DEFAULT 'shelf',
                capacity INTEGER,
                notes TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_purchase_orders' => "
            CREATE TABLE IF NOT EXISTS inventory_purchase_orders (
                id SERIAL PRIMARY KEY,
                po_number VARCHAR(30) UNIQUE NOT NULL,
                supplier_name VARCHAR(200),
                supplier_contact VARCHAR(100),
                order_date DATE NOT NULL,
                expected_date DATE,
                status VARCHAR(20) DEFAULT 'pending',
                total_amount DECIMAL(12, 2) DEFAULT 0,
                notes TEXT,
                created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                approved_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_po_items' => "
            CREATE TABLE IF NOT EXISTS inventory_po_items (
                id SERIAL PRIMARY KEY,
                po_id INTEGER REFERENCES inventory_purchase_orders(id) ON DELETE CASCADE,
                category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
                item_name VARCHAR(200) NOT NULL,
                quantity INTEGER NOT NULL,
                unit_price DECIMAL(12, 2) DEFAULT 0,
                received_qty INTEGER DEFAULT 0,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_receipts' => "
            CREATE TABLE IF NOT EXISTS inventory_receipts (
                id SERIAL PRIMARY KEY,
                receipt_number VARCHAR(30) UNIQUE NOT NULL,
                po_id INTEGER REFERENCES inventory_purchase_orders(id) ON DELETE SET NULL,
                warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
                receipt_date DATE NOT NULL,
                supplier_name VARCHAR(200),
                delivery_note VARCHAR(100),
                status VARCHAR(20) DEFAULT 'pending',
                notes TEXT,
                received_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                verified_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                verified_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_receipt_items' => "
            CREATE TABLE IF NOT EXISTS inventory_receipt_items (
                id SERIAL PRIMARY KEY,
                receipt_id INTEGER REFERENCES inventory_receipts(id) ON DELETE CASCADE,
                po_item_id INTEGER REFERENCES inventory_po_items(id) ON DELETE SET NULL,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
                category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
                item_name VARCHAR(200) NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 1,
                serial_number VARCHAR(100),
                mac_address VARCHAR(50),
                condition VARCHAR(20) DEFAULT 'new',
                location_id INTEGER REFERENCES inventory_locations(id) ON DELETE SET NULL,
                unit_cost DECIMAL(12, 2) DEFAULT 0,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_stock_requests' => "
            CREATE TABLE IF NOT EXISTS inventory_stock_requests (
                id SERIAL PRIMARY KEY,
                request_number VARCHAR(30) UNIQUE NOT NULL,
                requested_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
                request_type VARCHAR(30) NOT NULL DEFAULT 'technician',
                ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
                customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
                priority VARCHAR(20) DEFAULT 'normal',
                status VARCHAR(20) DEFAULT 'pending',
                required_date DATE,
                notes TEXT,
                approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                approved_at TIMESTAMP,
                picked_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                picked_at TIMESTAMP,
                handed_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
                handover_at TIMESTAMP,
                handover_signature TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_stock_request_items' => "
            CREATE TABLE IF NOT EXISTS inventory_stock_request_items (
                id SERIAL PRIMARY KEY,
                request_id INTEGER REFERENCES inventory_stock_requests(id) ON DELETE CASCADE,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
                category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
                item_name VARCHAR(200),
                quantity_requested INTEGER NOT NULL DEFAULT 1,
                quantity_approved INTEGER DEFAULT 0,
                quantity_picked INTEGER DEFAULT 0,
                quantity_used INTEGER DEFAULT 0,
                quantity_returned INTEGER DEFAULT 0,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_usage' => "
            CREATE TABLE IF NOT EXISTS inventory_usage (
                id SERIAL PRIMARY KEY,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
                request_item_id INTEGER REFERENCES inventory_stock_request_items(id) ON DELETE SET NULL,
                ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
                customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
                employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
                job_type VARCHAR(50) NOT NULL DEFAULT 'installation',
                quantity INTEGER DEFAULT 1,
                usage_date DATE NOT NULL,
                notes TEXT,
                recorded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_returns' => "
            CREATE TABLE IF NOT EXISTS inventory_returns (
                id SERIAL PRIMARY KEY,
                return_number VARCHAR(30) UNIQUE NOT NULL,
                request_id INTEGER REFERENCES inventory_stock_requests(id) ON DELETE SET NULL,
                returned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
                return_date DATE NOT NULL,
                return_type VARCHAR(30) DEFAULT 'unused',
                status VARCHAR(20) DEFAULT 'pending',
                notes TEXT,
                received_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                received_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_return_items' => "
            CREATE TABLE IF NOT EXISTS inventory_return_items (
                id SERIAL PRIMARY KEY,
                return_id INTEGER REFERENCES inventory_returns(id) ON DELETE CASCADE,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
                request_item_id INTEGER REFERENCES inventory_stock_request_items(id) ON DELETE SET NULL,
                quantity INTEGER DEFAULT 1,
                condition VARCHAR(20) DEFAULT 'good',
                location_id INTEGER REFERENCES inventory_locations(id) ON DELETE SET NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_rma' => "
            CREATE TABLE IF NOT EXISTS inventory_rma (
                id SERIAL PRIMARY KEY,
                rma_number VARCHAR(30) UNIQUE NOT NULL,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
                fault_id INTEGER REFERENCES equipment_faults(id) ON DELETE SET NULL,
                vendor_name VARCHAR(200),
                vendor_contact VARCHAR(100),
                status VARCHAR(20) DEFAULT 'pending',
                shipped_date DATE,
                received_date DATE,
                resolution VARCHAR(50),
                resolution_notes TEXT,
                replacement_equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
                created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_loss_reports' => "
            CREATE TABLE IF NOT EXISTS inventory_loss_reports (
                id SERIAL PRIMARY KEY,
                report_number VARCHAR(30) UNIQUE NOT NULL,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
                reported_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
                loss_type VARCHAR(30) NOT NULL DEFAULT 'lost',
                loss_date DATE NOT NULL,
                description TEXT NOT NULL,
                estimated_value DECIMAL(12, 2),
                investigation_status VARCHAR(20) DEFAULT 'pending',
                investigation_notes TEXT,
                resolved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                resolved_at TIMESTAMP,
                resolution VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_stock_movements' => "
            CREATE TABLE IF NOT EXISTS inventory_stock_movements (
                id SERIAL PRIMARY KEY,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
                movement_type VARCHAR(30) NOT NULL,
                from_location_id INTEGER REFERENCES inventory_locations(id) ON DELETE SET NULL,
                to_location_id INTEGER REFERENCES inventory_locations(id) ON DELETE SET NULL,
                from_warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
                to_warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
                quantity INTEGER DEFAULT 1,
                reference_type VARCHAR(30),
                reference_id INTEGER,
                notes TEXT,
                performed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_stock_levels' => "
            CREATE TABLE IF NOT EXISTS inventory_stock_levels (
                id SERIAL PRIMARY KEY,
                category_id INTEGER REFERENCES equipment_categories(id) ON DELETE CASCADE,
                warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE CASCADE,
                min_quantity INTEGER DEFAULT 0,
                max_quantity INTEGER DEFAULT 100,
                reorder_point INTEGER DEFAULT 10,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(category_id, warehouse_id)
            )",
        'inventory_audits' => "
            CREATE TABLE IF NOT EXISTS inventory_audits (
                id SERIAL PRIMARY KEY,
                audit_number VARCHAR(30) UNIQUE NOT NULL,
                warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL,
                audit_type VARCHAR(30) DEFAULT 'full',
                scheduled_date DATE,
                completed_date DATE,
                status VARCHAR(20) DEFAULT 'pending',
                notes TEXT,
                created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                completed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_audit_items' => "
            CREATE TABLE IF NOT EXISTS inventory_audit_items (
                id SERIAL PRIMARY KEY,
                audit_id INTEGER REFERENCES inventory_audits(id) ON DELETE CASCADE,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
                category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
                expected_qty INTEGER DEFAULT 0,
                actual_qty INTEGER DEFAULT 0,
                variance INTEGER DEFAULT 0,
                notes TEXT,
                verified_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                verified_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'inventory_thresholds' => "
            CREATE TABLE IF NOT EXISTS inventory_thresholds (
                id SERIAL PRIMARY KEY,
                category_id INTEGER REFERENCES equipment_categories(id) ON DELETE CASCADE,
                warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE CASCADE,
                min_quantity INTEGER NOT NULL DEFAULT 5,
                max_quantity INTEGER DEFAULT 100,
                reorder_point INTEGER NOT NULL DEFAULT 10,
                reorder_quantity INTEGER DEFAULT 20,
                notify_on_low BOOLEAN DEFAULT TRUE,
                notify_on_excess BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(category_id, warehouse_id)
            )",
        'technician_kits' => "
            CREATE TABLE IF NOT EXISTS technician_kits (
                id SERIAL PRIMARY KEY,
                kit_number VARCHAR(30) UNIQUE NOT NULL,
                employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                status VARCHAR(20) DEFAULT 'active',
                issued_date DATE,
                issued_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                returned_date DATE,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'technician_kit_items' => "
            CREATE TABLE IF NOT EXISTS technician_kit_items (
                id SERIAL PRIMARY KEY,
                kit_id INTEGER REFERENCES technician_kits(id) ON DELETE CASCADE,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE SET NULL,
                category_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL,
                quantity INTEGER DEFAULT 1,
                issued_quantity INTEGER DEFAULT 0,
                returned_quantity INTEGER DEFAULT 0,
                status VARCHAR(20) DEFAULT 'issued',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'equipment_lifecycle_logs' => "
            CREATE TABLE IF NOT EXISTS equipment_lifecycle_logs (
                id SERIAL PRIMARY KEY,
                equipment_id INTEGER REFERENCES equipment(id) ON DELETE CASCADE,
                from_status VARCHAR(30),
                to_status VARCHAR(30) NOT NULL,
                changed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                reference_type VARCHAR(30),
                reference_id INTEGER,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'mobile_tokens' => "
            CREATE TABLE IF NOT EXISTS mobile_tokens (
                id SERIAL PRIMARY KEY,
                user_id INTEGER UNIQUE REFERENCES users(id) ON DELETE CASCADE,
                token VARCHAR(64) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'roles' => "
            CREATE TABLE IF NOT EXISTS roles (
                id SERIAL PRIMARY KEY,
                name VARCHAR(50) UNIQUE NOT NULL,
                display_name VARCHAR(100) NOT NULL,
                description TEXT,
                is_system BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'permissions' => "
            CREATE TABLE IF NOT EXISTS permissions (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) UNIQUE NOT NULL,
                display_name VARCHAR(150) NOT NULL,
                category VARCHAR(50) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'role_permissions' => "
            CREATE TABLE IF NOT EXISTS role_permissions (
                id SERIAL PRIMARY KEY,
                role_id INTEGER REFERENCES roles(id) ON DELETE CASCADE,
                permission_id INTEGER REFERENCES permissions(id) ON DELETE CASCADE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(role_id, permission_id)
            )",
        'teams' => "
            CREATE TABLE IF NOT EXISTS teams (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                leader_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'team_members' => "
            CREATE TABLE IF NOT EXISTS team_members (
                id SERIAL PRIMARY KEY,
                team_id INTEGER REFERENCES teams(id) ON DELETE CASCADE,
                employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
                joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(team_id, employee_id)
            )",
        'sla_policies' => "
            CREATE TABLE IF NOT EXISTS sla_policies (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                priority VARCHAR(20) NOT NULL,
                response_time_hours INTEGER NOT NULL DEFAULT 4,
                resolution_time_hours INTEGER NOT NULL DEFAULT 24,
                escalation_time_hours INTEGER,
                escalation_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
                notify_on_breach BOOLEAN DEFAULT TRUE,
                is_active BOOLEAN DEFAULT TRUE,
                is_default BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'sla_business_hours' => "
            CREATE TABLE IF NOT EXISTS sla_business_hours (
                id SERIAL PRIMARY KEY,
                day_of_week INTEGER NOT NULL CHECK (day_of_week >= 0 AND day_of_week <= 6),
                start_time TIME NOT NULL DEFAULT '08:00',
                end_time TIME NOT NULL DEFAULT '17:00',
                is_working_day BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(day_of_week)
            )",
        'sla_holidays' => "
            CREATE TABLE IF NOT EXISTS sla_holidays (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                holiday_date DATE NOT NULL,
                is_recurring BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(holiday_date)
            )",
        'ticket_sla_logs' => "
            CREATE TABLE IF NOT EXISTS ticket_sla_logs (
                id SERIAL PRIMARY KEY,
                ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
                event_type VARCHAR(50) NOT NULL,
                details TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'hr_notification_templates' => "
            CREATE TABLE IF NOT EXISTS hr_notification_templates (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                category VARCHAR(50) DEFAULT 'attendance',
                event_type VARCHAR(50) NOT NULL,
                subject VARCHAR(200),
                sms_template TEXT,
                email_template TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                send_sms BOOLEAN DEFAULT TRUE,
                send_email BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'attendance_notification_logs' => "
            CREATE TABLE IF NOT EXISTS attendance_notification_logs (
                id SERIAL PRIMARY KEY,
                employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
                notification_template_id INTEGER REFERENCES hr_notification_templates(id) ON DELETE SET NULL,
                notification_type VARCHAR(50) NOT NULL,
                recipient_phone VARCHAR(20),
                recipient_email VARCHAR(100),
                message_content TEXT,
                status VARCHAR(20) DEFAULT 'pending',
                error_message TEXT,
                sent_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'complaints' => "
            CREATE TABLE IF NOT EXISTS complaints (
                id SERIAL PRIMARY KEY,
                complaint_number VARCHAR(30) UNIQUE NOT NULL,
                customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
                customer_name VARCHAR(100) NOT NULL,
                customer_phone VARCHAR(20) NOT NULL,
                customer_email VARCHAR(100),
                customer_location TEXT,
                category VARCHAR(50) NOT NULL,
                subject VARCHAR(200) NOT NULL,
                description TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                priority VARCHAR(20) DEFAULT 'medium',
                reviewed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                reviewed_at TIMESTAMP,
                review_notes TEXT,
                converted_ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
                source VARCHAR(50) DEFAULT 'public',
                created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'network_devices' => "
            CREATE TABLE IF NOT EXISTS network_devices (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                device_type VARCHAR(50) NOT NULL DEFAULT 'olt',
                vendor VARCHAR(50),
                model VARCHAR(100),
                ip_address VARCHAR(45) NOT NULL,
                snmp_version VARCHAR(10) DEFAULT 'v2c',
                snmp_community VARCHAR(100) DEFAULT 'public',
                snmp_port INTEGER DEFAULT 161,
                snmpv3_username VARCHAR(100),
                snmpv3_auth_protocol VARCHAR(20),
                snmpv3_auth_password VARCHAR(255),
                snmpv3_priv_protocol VARCHAR(20),
                snmpv3_priv_password VARCHAR(255),
                telnet_username VARCHAR(100),
                telnet_password VARCHAR(255),
                telnet_port INTEGER DEFAULT 23,
                ssh_enabled BOOLEAN DEFAULT FALSE,
                ssh_port INTEGER DEFAULT 22,
                location VARCHAR(255),
                status VARCHAR(20) DEFAULT 'unknown',
                last_polled TIMESTAMP,
                poll_interval INTEGER DEFAULT 300,
                enabled BOOLEAN DEFAULT TRUE,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'device_interfaces' => "
            CREATE TABLE IF NOT EXISTS device_interfaces (
                id SERIAL PRIMARY KEY,
                device_id INTEGER REFERENCES network_devices(id) ON DELETE CASCADE,
                if_index INTEGER NOT NULL,
                if_name VARCHAR(100),
                if_descr VARCHAR(255),
                if_type VARCHAR(50),
                if_speed BIGINT,
                if_status VARCHAR(20),
                in_octets BIGINT DEFAULT 0,
                out_octets BIGINT DEFAULT 0,
                in_errors BIGINT DEFAULT 0,
                out_errors BIGINT DEFAULT 0,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(device_id, if_index)
            )",
        'device_onus' => "
            CREATE TABLE IF NOT EXISTS device_onus (
                id SERIAL PRIMARY KEY,
                device_id INTEGER REFERENCES network_devices(id) ON DELETE CASCADE,
                onu_id VARCHAR(50) NOT NULL,
                serial_number VARCHAR(50),
                mac_address VARCHAR(17),
                pon_port VARCHAR(20),
                slot INTEGER,
                port INTEGER,
                onu_index INTEGER,
                customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
                status VARCHAR(20) DEFAULT 'unknown',
                rx_power DECIMAL(10,2),
                tx_power DECIMAL(10,2),
                distance INTEGER,
                description VARCHAR(255),
                profile VARCHAR(100),
                last_online TIMESTAMP,
                last_offline TIMESTAMP,
                last_polled TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(device_id, onu_id)
            )",
        'device_monitoring_log' => "
            CREATE TABLE IF NOT EXISTS device_monitoring_log (
                id SERIAL PRIMARY KEY,
                device_id INTEGER REFERENCES network_devices(id) ON DELETE CASCADE,
                metric_type VARCHAR(50) NOT NULL,
                metric_name VARCHAR(100),
                metric_value TEXT,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'ticket_commission_rates' => "
            CREATE TABLE IF NOT EXISTS ticket_commission_rates (
                id SERIAL PRIMARY KEY,
                category VARCHAR(50) NOT NULL UNIQUE,
                rate DECIMAL(12, 2) NOT NULL DEFAULT 0,
                currency VARCHAR(10) DEFAULT 'KES',
                description TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'ticket_earnings' => "
            CREATE TABLE IF NOT EXISTS ticket_earnings (
                id SERIAL PRIMARY KEY,
                ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
                employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
                team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL,
                category VARCHAR(50) NOT NULL,
                full_rate DECIMAL(12, 2) NOT NULL,
                earned_amount DECIMAL(12, 2) NOT NULL,
                share_count INTEGER DEFAULT 1,
                currency VARCHAR(10) DEFAULT 'KES',
                status VARCHAR(20) DEFAULT 'pending',
                payroll_id INTEGER REFERENCES payroll(id) ON DELETE SET NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'payroll_commissions' => "
            CREATE TABLE IF NOT EXISTS payroll_commissions (
                id SERIAL PRIMARY KEY,
                payroll_id INTEGER REFERENCES payroll(id) ON DELETE CASCADE,
                employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
                commission_type VARCHAR(50) NOT NULL DEFAULT 'ticket',
                description TEXT,
                amount DECIMAL(12, 2) NOT NULL,
                details JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'ticket_satisfaction_ratings' => "
            CREATE TABLE IF NOT EXISTS ticket_satisfaction_ratings (
                id SERIAL PRIMARY KEY,
                ticket_id INTEGER UNIQUE REFERENCES tickets(id) ON DELETE CASCADE,
                customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE,
                rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
                feedback TEXT,
                rated_by_name VARCHAR(100),
                rated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'ticket_escalations' => "
            CREATE TABLE IF NOT EXISTS ticket_escalations (
                id SERIAL PRIMARY KEY,
                ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
                escalated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                escalated_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
                reason TEXT NOT NULL,
                previous_priority VARCHAR(20),
                new_priority VARCHAR(20),
                previous_assigned_to INTEGER,
                status VARCHAR(20) DEFAULT 'active',
                resolved_at TIMESTAMP,
                resolution_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'user_notifications' => "
            CREATE TABLE IF NOT EXISTS user_notifications (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                type VARCHAR(50) NOT NULL DEFAULT 'info',
                title VARCHAR(255) NOT NULL,
                message TEXT,
                reference_id INTEGER,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'branches' => "
            CREATE TABLE IF NOT EXISTS branches (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(20) UNIQUE,
                address TEXT,
                phone VARCHAR(20),
                email VARCHAR(100),
                manager_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                whatsapp_group VARCHAR(100),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'employee_branches' => "
            CREATE TABLE IF NOT EXISTS employee_branches (
                id SERIAL PRIMARY KEY,
                branch_id INTEGER REFERENCES branches(id) ON DELETE CASCADE,
                employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
                is_primary BOOLEAN DEFAULT FALSE,
                assigned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(employee_id, branch_id)
            )",
        'salary_advances' => "
            CREATE TABLE IF NOT EXISTS salary_advances (
                id SERIAL PRIMARY KEY,
                employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
                requested_amount DECIMAL(12, 2) NOT NULL,
                approved_amount DECIMAL(12, 2),
                repayment_schedule VARCHAR(20) DEFAULT 'monthly',
                installments INTEGER DEFAULT 1,
                outstanding_balance DECIMAL(12, 2),
                status VARCHAR(20) DEFAULT 'pending',
                requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                approved_at TIMESTAMP,
                disbursed_at TIMESTAMP,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'salary_advance_repayments' => "
            CREATE TABLE IF NOT EXISTS salary_advance_repayments (
                id SERIAL PRIMARY KEY,
                advance_id INTEGER REFERENCES salary_advances(id) ON DELETE CASCADE,
                amount DECIMAL(12, 2) NOT NULL,
                repayment_date DATE NOT NULL,
                payroll_id INTEGER REFERENCES payroll(id) ON DELETE SET NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'leave_types' => "
            CREATE TABLE IF NOT EXISTS leave_types (
                id SERIAL PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                code VARCHAR(20) UNIQUE NOT NULL,
                days_per_year INTEGER DEFAULT 0,
                is_paid BOOLEAN DEFAULT TRUE,
                requires_approval BOOLEAN DEFAULT TRUE,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'leave_balances' => "
            CREATE TABLE IF NOT EXISTS leave_balances (
                id SERIAL PRIMARY KEY,
                employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
                leave_type_id INTEGER REFERENCES leave_types(id) ON DELETE CASCADE,
                year INTEGER NOT NULL,
                entitled_days DECIMAL(5,2) DEFAULT 0,
                used_days DECIMAL(5,2) DEFAULT 0,
                pending_days DECIMAL(5,2) DEFAULT 0,
                carried_over DECIMAL(5,2) DEFAULT 0,
                carried_over_days DECIMAL(5,2) DEFAULT 0,
                adjusted_days DECIMAL(5,2) DEFAULT 0,
                accrued_days DECIMAL(5,2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(employee_id, leave_type_id, year)
            )",
        'leave_calendar' => "
            CREATE TABLE IF NOT EXISTS leave_calendar (
                id SERIAL PRIMARY KEY,
                date DATE NOT NULL,
                name VARCHAR(255) NOT NULL,
                is_public_holiday BOOLEAN DEFAULT FALSE,
                branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(date, branch_id)
            )",
        'leave_requests' => "
            CREATE TABLE IF NOT EXISTS leave_requests (
                id SERIAL PRIMARY KEY,
                employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
                leave_type_id INTEGER REFERENCES leave_types(id) ON DELETE CASCADE,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                days_requested DECIMAL(5,2) NOT NULL,
                reason TEXT,
                status VARCHAR(20) DEFAULT 'pending',
                approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                approved_at TIMESTAMP,
                rejection_reason TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'public_holidays' => "
            CREATE TABLE IF NOT EXISTS public_holidays (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                holiday_date DATE NOT NULL,
                is_recurring BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'quotes' => "
            CREATE TABLE IF NOT EXISTS quotes (
                id SERIAL PRIMARY KEY,
                quote_number VARCHAR(50) UNIQUE NOT NULL,
                customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
                issue_date DATE NOT NULL DEFAULT CURRENT_DATE,
                expiry_date DATE,
                status VARCHAR(20) DEFAULT 'draft',
                subtotal DECIMAL(12,2) DEFAULT 0,
                tax_amount DECIMAL(12,2) DEFAULT 0,
                discount_amount DECIMAL(12,2) DEFAULT 0,
                total_amount DECIMAL(12,2) DEFAULT 0,
                currency VARCHAR(10) DEFAULT 'KES',
                notes TEXT,
                terms TEXT,
                converted_to_invoice_id INTEGER,
                created_by INTEGER REFERENCES users(id),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'quote_items' => "
            CREATE TABLE IF NOT EXISTS quote_items (
                id SERIAL PRIMARY KEY,
                quote_id INTEGER REFERENCES quotes(id) ON DELETE CASCADE,
                product_id INTEGER,
                description TEXT NOT NULL,
                quantity DECIMAL(10,2) DEFAULT 1,
                unit_price DECIMAL(12,2) NOT NULL,
                tax_rate_id INTEGER,
                tax_amount DECIMAL(12,2) DEFAULT 0,
                discount_percent DECIMAL(5,2) DEFAULT 0,
                line_total DECIMAL(12,2) NOT NULL,
                sort_order INTEGER DEFAULT 0
            )",
        'vendor_bills' => "
            CREATE TABLE IF NOT EXISTS vendor_bills (
                id SERIAL PRIMARY KEY,
                bill_number VARCHAR(50) UNIQUE NOT NULL,
                vendor_id INTEGER REFERENCES vendors(id) ON DELETE SET NULL,
                purchase_order_id INTEGER,
                bill_date DATE NOT NULL DEFAULT CURRENT_DATE,
                due_date DATE NOT NULL,
                status VARCHAR(20) DEFAULT 'draft',
                subtotal DECIMAL(12,2) DEFAULT 0,
                tax_amount DECIMAL(12,2) DEFAULT 0,
                total_amount DECIMAL(12,2) DEFAULT 0,
                amount_paid DECIMAL(12,2) DEFAULT 0,
                balance_due DECIMAL(12,2) DEFAULT 0,
                currency VARCHAR(10) DEFAULT 'KES',
                reference VARCHAR(100),
                notes TEXT,
                created_by INTEGER REFERENCES users(id),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'vendor_bill_items' => "
            CREATE TABLE IF NOT EXISTS vendor_bill_items (
                id SERIAL PRIMARY KEY,
                bill_id INTEGER REFERENCES vendor_bills(id) ON DELETE CASCADE,
                account_id INTEGER,
                description TEXT NOT NULL,
                quantity DECIMAL(10,2) DEFAULT 1,
                unit_price DECIMAL(12,2) NOT NULL,
                tax_rate_id INTEGER,
                tax_amount DECIMAL(12,2) DEFAULT 0,
                line_total DECIMAL(12,2) NOT NULL,
                sort_order INTEGER DEFAULT 0
            )",
        'activity_logs' => "
            CREATE TABLE IF NOT EXISTS activity_logs (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                action_type VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50),
                entity_id INTEGER,
                entity_reference VARCHAR(100),
                details JSONB,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'whatsapp_conversations' => "
            CREATE TABLE IF NOT EXISTS whatsapp_conversations (
                id SERIAL PRIMARY KEY,
                chat_id VARCHAR(100) UNIQUE NOT NULL,
                phone VARCHAR(30) NOT NULL,
                contact_name VARCHAR(150),
                customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
                is_group BOOLEAN DEFAULT FALSE,
                unread_count INTEGER DEFAULT 0,
                last_message_at TIMESTAMP,
                last_message_preview TEXT,
                status VARCHAR(20) DEFAULT 'active',
                assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'whatsapp_messages' => "
            CREATE TABLE IF NOT EXISTS whatsapp_messages (
                id SERIAL PRIMARY KEY,
                conversation_id INTEGER REFERENCES whatsapp_conversations(id) ON DELETE CASCADE,
                message_id VARCHAR(150) UNIQUE,
                direction VARCHAR(10) NOT NULL DEFAULT 'incoming',
                sender_phone VARCHAR(30),
                sender_name VARCHAR(150),
                message_type VARCHAR(30) DEFAULT 'text',
                body TEXT,
                media_url TEXT,
                media_mime_type VARCHAR(100),
                media_filename VARCHAR(255),
                is_read BOOLEAN DEFAULT FALSE,
                is_delivered BOOLEAN DEFAULT FALSE,
                sent_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                raw_data JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'huawei_zones' => "
            CREATE TABLE IF NOT EXISTS huawei_zones (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'huawei_subzones' => "
            CREATE TABLE IF NOT EXISTS huawei_subzones (
                id SERIAL PRIMARY KEY,
                zone_id INTEGER REFERENCES huawei_zones(id) ON DELETE CASCADE,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'huawei_apartments' => "
            CREATE TABLE IF NOT EXISTS huawei_apartments (
                id SERIAL PRIMARY KEY,
                zone_id INTEGER REFERENCES huawei_zones(id) ON DELETE CASCADE,
                subzone_id INTEGER REFERENCES huawei_subzones(id) ON DELETE SET NULL,
                name VARCHAR(150) NOT NULL,
                address TEXT,
                floors INTEGER,
                units_count INTEGER,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        'huawei_odb_units' => "
            CREATE TABLE IF NOT EXISTS huawei_odb_units (
                id SERIAL PRIMARY KEY,
                zone_id INTEGER REFERENCES huawei_zones(id) ON DELETE CASCADE,
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
            )"
    ];
    
    foreach ($tables as $tableName => $createSql) {
        try {
            $check = $db->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '$tableName')");
            if (!$check->fetchColumn()) {
                $db->exec($createSql);
            }
        } catch (PDOException $e) {
            error_log("Migration error for $tableName: " . $e->getMessage());
        }
    }
    
    $columnMigrations = [
        ['orders', 'salesperson_id', 'ALTER TABLE orders ADD COLUMN salesperson_id INTEGER REFERENCES salespersons(id) ON DELETE SET NULL'],
        ['orders', 'commission_paid', 'ALTER TABLE orders ADD COLUMN commission_paid BOOLEAN DEFAULT FALSE'],
        ['orders', 'lead_source', "ALTER TABLE orders ADD COLUMN lead_source VARCHAR(50) DEFAULT 'web'"],
        ['orders', 'ticket_id', 'ALTER TABLE orders ADD COLUMN ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL'],
        ['orders', 'customer_id', 'ALTER TABLE orders ADD COLUMN customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL'],
        ['users', 'role_id', 'ALTER TABLE users ADD COLUMN role_id INTEGER REFERENCES roles(id) ON DELETE SET NULL'],
        ['tickets', 'team_id', 'ALTER TABLE tickets ADD COLUMN team_id INTEGER REFERENCES teams(id) ON DELETE SET NULL'],
        ['tickets', 'sla_policy_id', 'ALTER TABLE tickets ADD COLUMN sla_policy_id INTEGER REFERENCES sla_policies(id) ON DELETE SET NULL'],
        ['tickets', 'first_response_at', 'ALTER TABLE tickets ADD COLUMN first_response_at TIMESTAMP'],
        ['tickets', 'sla_response_due', 'ALTER TABLE tickets ADD COLUMN sla_response_due TIMESTAMP'],
        ['tickets', 'sla_resolution_due', 'ALTER TABLE tickets ADD COLUMN sla_resolution_due TIMESTAMP'],
        ['tickets', 'sla_response_breached', 'ALTER TABLE tickets ADD COLUMN sla_response_breached BOOLEAN DEFAULT FALSE'],
        ['tickets', 'sla_resolution_breached', 'ALTER TABLE tickets ADD COLUMN sla_resolution_breached BOOLEAN DEFAULT FALSE'],
        ['tickets', 'sla_paused_at', 'ALTER TABLE tickets ADD COLUMN sla_paused_at TIMESTAMP'],
        ['tickets', 'sla_paused_duration', 'ALTER TABLE tickets ADD COLUMN sla_paused_duration INTEGER DEFAULT 0'],
        ['equipment_assignments', 'employee_id', 'ALTER TABLE equipment_assignments ADD COLUMN employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL'],
        ['equipment_assignments', 'customer_id', 'ALTER TABLE equipment_assignments ADD COLUMN customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL'],
        ['equipment_assignments', 'assigned_by', 'ALTER TABLE equipment_assignments ADD COLUMN assigned_by INTEGER REFERENCES users(id) ON DELETE SET NULL'],
        ['equipment_assignments', 'return_date', 'ALTER TABLE equipment_assignments ADD COLUMN return_date DATE'],
        ['equipment_assignments', 'status', 'ALTER TABLE equipment_assignments ADD COLUMN status VARCHAR(20) DEFAULT \'assigned\''],
        ['equipment_assignments', 'notes', 'ALTER TABLE equipment_assignments ADD COLUMN notes TEXT'],
        ['tickets', 'source', "ALTER TABLE tickets ADD COLUMN source VARCHAR(50) DEFAULT 'internal'"],
        ['biometric_devices', 'serial_number', 'ALTER TABLE biometric_devices ADD COLUMN serial_number VARCHAR(100)'],
        ['whatsapp_logs', 'order_id', 'ALTER TABLE whatsapp_logs ADD COLUMN order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE'],
        ['whatsapp_logs', 'complaint_id', 'ALTER TABLE whatsapp_logs ADD COLUMN complaint_id INTEGER REFERENCES complaints(id) ON DELETE CASCADE'],
        ['whatsapp_logs', 'message_type', "ALTER TABLE whatsapp_logs ADD COLUMN message_type VARCHAR(50) DEFAULT 'custom'"],
        ['customers', 'created_by', 'ALTER TABLE customers ADD COLUMN created_by INTEGER REFERENCES users(id) ON DELETE SET NULL'],
        ['tickets', 'created_by', 'ALTER TABLE tickets ADD COLUMN created_by INTEGER REFERENCES users(id) ON DELETE SET NULL'],
        ['orders', 'created_by', 'ALTER TABLE orders ADD COLUMN created_by INTEGER REFERENCES users(id) ON DELETE SET NULL'],
        ['complaints', 'created_by', 'ALTER TABLE complaints ADD COLUMN created_by INTEGER REFERENCES users(id) ON DELETE SET NULL'],
        ['complaints', 'customer_location', 'ALTER TABLE complaints ADD COLUMN customer_location TEXT'],
        ['complaints', 'reviewed_by', 'ALTER TABLE complaints ADD COLUMN reviewed_by INTEGER REFERENCES users(id) ON DELETE SET NULL'],
        ['complaints', 'reviewed_at', 'ALTER TABLE complaints ADD COLUMN reviewed_at TIMESTAMP'],
        ['complaints', 'review_notes', 'ALTER TABLE complaints ADD COLUMN review_notes TEXT'],
        ['equipment', 'brand', 'ALTER TABLE equipment ADD COLUMN brand VARCHAR(100)'],
        ['equipment', 'mac_address', 'ALTER TABLE equipment ADD COLUMN mac_address VARCHAR(50)'],
        ['equipment', 'warranty_expiry', 'ALTER TABLE equipment ADD COLUMN warranty_expiry DATE'],
        ['tickets', 'is_escalated', 'ALTER TABLE tickets ADD COLUMN is_escalated BOOLEAN DEFAULT FALSE'],
        ['tickets', 'escalation_count', 'ALTER TABLE tickets ADD COLUMN escalation_count INTEGER DEFAULT 0'],
        ['tickets', 'satisfaction_rating', 'ALTER TABLE tickets ADD COLUMN satisfaction_rating INTEGER'],
        ['tickets', 'closed_at', 'ALTER TABLE tickets ADD COLUMN closed_at TIMESTAMP'],
        ['tickets', 'branch_id', 'ALTER TABLE tickets ADD COLUMN branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL'],
        ['teams', 'branch_id', 'ALTER TABLE teams ADD COLUMN branch_id INTEGER REFERENCES branches(id) ON DELETE SET NULL'],
        ['branches', 'whatsapp_group', 'ALTER TABLE branches ADD COLUMN whatsapp_group VARCHAR(100)'],
        ['customers', 'username', 'ALTER TABLE customers ADD COLUMN username VARCHAR(100)'],
        ['customers', 'billing_id', 'ALTER TABLE customers ADD COLUMN billing_id VARCHAR(100)'],
        ['equipment', 'warehouse_id', 'ALTER TABLE equipment ADD COLUMN warehouse_id INTEGER REFERENCES inventory_warehouses(id) ON DELETE SET NULL'],
        ['equipment', 'location_id', 'ALTER TABLE equipment ADD COLUMN location_id INTEGER REFERENCES inventory_locations(id) ON DELETE SET NULL'],
        ['equipment', 'quantity', 'ALTER TABLE equipment ADD COLUMN quantity INTEGER DEFAULT 1'],
        ['equipment', 'sku', 'ALTER TABLE equipment ADD COLUMN sku VARCHAR(50)'],
        ['equipment', 'barcode', 'ALTER TABLE equipment ADD COLUMN barcode VARCHAR(100)'],
        ['equipment_categories', 'parent_id', 'ALTER TABLE equipment_categories ADD COLUMN parent_id INTEGER REFERENCES equipment_categories(id) ON DELETE SET NULL'],
        ['equipment_categories', 'item_type', "ALTER TABLE equipment_categories ADD COLUMN item_type VARCHAR(30) DEFAULT 'serialized'"],
        ['equipment_loans', 'deposit_paid', 'ALTER TABLE equipment_loans ADD COLUMN deposit_paid BOOLEAN DEFAULT FALSE'],
        ['equipment', 'lifecycle_status', "ALTER TABLE equipment ADD COLUMN lifecycle_status VARCHAR(30) DEFAULT 'in_stock'"],
        ['equipment', 'last_lifecycle_change', 'ALTER TABLE equipment ADD COLUMN last_lifecycle_change TIMESTAMP'],
        ['equipment', 'installed_customer_id', 'ALTER TABLE equipment ADD COLUMN installed_customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL'],
        ['equipment', 'installed_at', 'ALTER TABLE equipment ADD COLUMN installed_at TIMESTAMP'],
        ['equipment', 'installed_by', 'ALTER TABLE equipment ADD COLUMN installed_by INTEGER REFERENCES users(id) ON DELETE SET NULL'],
        ['huawei_onus', 'zone_id', 'ALTER TABLE huawei_onus ADD COLUMN zone_id INTEGER REFERENCES huawei_zones(id) ON DELETE SET NULL'],
        ['huawei_onus', 'subzone_id', 'ALTER TABLE huawei_onus ADD COLUMN subzone_id INTEGER REFERENCES huawei_subzones(id) ON DELETE SET NULL'],
        ['huawei_onus', 'apartment_id', 'ALTER TABLE huawei_onus ADD COLUMN apartment_id INTEGER REFERENCES huawei_apartments(id) ON DELETE SET NULL'],
        ['huawei_onus', 'odb_id', 'ALTER TABLE huawei_onus ADD COLUMN odb_id INTEGER REFERENCES huawei_odb_units(id) ON DELETE SET NULL'],
        ['huawei_onus', 'optical_updated_at', 'ALTER TABLE huawei_onus ADD COLUMN optical_updated_at TIMESTAMP']
    ];
    
    foreach ($columnMigrations as $migration) {
        [$table, $column, $sql] = $migration;
        try {
            $check = $db->query("SELECT EXISTS (SELECT FROM information_schema.columns WHERE table_name = '$table' AND column_name = '$column')");
            if (!$check->fetchColumn()) {
                $db->exec($sql);
            }
        } catch (PDOException $e) {
            error_log("Column migration error for $table.$column: " . $e->getMessage());
        }
    }
    
    // Auto-fix: Convert billing_id from integer to varchar if needed (One-ISP uses string IDs)
    try {
        $typeCheck = $db->query("SELECT data_type FROM information_schema.columns WHERE table_name = 'customers' AND column_name = 'billing_id'");
        $dataType = $typeCheck->fetchColumn();
        if ($dataType === 'integer') {
            $db->exec("ALTER TABLE customers ALTER COLUMN billing_id TYPE VARCHAR(100) USING billing_id::VARCHAR");
            error_log("Auto-fix: Converted customers.billing_id from integer to varchar");
        }
    } catch (PDOException $e) {
        error_log("Auto-fix billing_id error: " . $e->getMessage());
    }
    
    seedRolesAndPermissions($db);
    seedSLADefaults($db);
    seedLeaveTypes($db);
    seedHRNotificationTemplates($db);
    seedISPEquipmentCategories($db);
    
    // Record that migrations are complete
    try {
        $stmt = $db->prepare("INSERT INTO schema_migrations (version) VALUES (?)");
        $stmt->execute([$migrationVersion]);
        error_log("Migrations completed and recorded: $migrationVersion");
    } catch (PDOException $e) {
        error_log("Failed to record migration version: " . $e->getMessage());
    }
}

function seedRolesAndPermissions(PDO $db): void {
    $checkRoles = $db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    if ($checkRoles > 0) {
        return;
    }
    
    $roles = [
        ['admin', 'Administrator', 'Full system access with all permissions', true],
        ['manager', 'Manager', 'Can manage most resources but limited system settings', true],
        ['technician', 'Technician', 'Can manage tickets, customers, and basic operations', true],
        ['salesperson', 'Salesperson', 'Can manage orders, leads, and view commissions', true],
        ['viewer', 'Viewer', 'Read-only access to most resources', true]
    ];
    
    $stmt = $db->prepare("INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)");
    foreach ($roles as $role) {
        try {
            $stmt->execute($role);
        } catch (PDOException $e) {
            error_log("Error seeding role {$role[0]}: " . $e->getMessage());
        }
    }
    
    $permissions = [
        // Dashboard
        ['dashboard.view', 'View Dashboard', 'dashboard', 'Can view the main dashboard'],
        ['dashboard.stats', 'View Dashboard Stats', 'dashboard', 'Can view dashboard statistics and metrics'],
        
        // Customers
        ['customers.view', 'View Customers', 'customers', 'Can view customer list and details'],
        ['customers.create', 'Create Customers', 'customers', 'Can create new customers'],
        ['customers.edit', 'Edit Customers', 'customers', 'Can edit existing customers'],
        ['customers.delete', 'Delete Customers', 'customers', 'Can delete customers'],
        ['customers.import', 'Import Customers', 'customers', 'Can import customers from CSV/Excel'],
        ['customers.export', 'Export Customers', 'customers', 'Can export customer data'],
        ['customers.view_all', 'View All Customers', 'customers', 'View all customers (not just created by user)'],
        
        // Tickets
        ['tickets.view', 'View Tickets', 'tickets', 'Can view ticket list and details'],
        ['tickets.create', 'Create Tickets', 'tickets', 'Can create new tickets'],
        ['tickets.edit', 'Edit Tickets', 'tickets', 'Can edit and update tickets'],
        ['tickets.delete', 'Delete Tickets', 'tickets', 'Can delete tickets'],
        ['tickets.assign', 'Assign Tickets', 'tickets', 'Can assign tickets to technicians'],
        ['tickets.escalate', 'Escalate Tickets', 'tickets', 'Can escalate tickets to higher priority'],
        ['tickets.close', 'Close Tickets', 'tickets', 'Can close/resolve tickets'],
        ['tickets.reopen', 'Reopen Tickets', 'tickets', 'Can reopen closed tickets'],
        ['tickets.sla', 'Manage SLA', 'tickets', 'Can configure SLA policies'],
        ['tickets.commission', 'Manage Ticket Commission', 'tickets', 'Can configure ticket commission rates'],
        ['tickets.view_all', 'View All Tickets', 'tickets', 'View all tickets (not just assigned)'],
        
        // HR
        ['hr.view', 'View HR', 'hr', 'Can view employee records and HR data'],
        ['hr.manage', 'Manage HR', 'hr', 'Can create, edit, and manage employees'],
        ['hr.payroll', 'Manage Payroll', 'hr', 'Can process payroll and deductions'],
        ['hr.attendance', 'Manage Attendance', 'hr', 'Can view and edit attendance records'],
        ['hr.advances', 'Manage Salary Advances', 'hr', 'Can approve and manage salary advances'],
        ['hr.leave', 'Manage Leave', 'hr', 'Can approve and manage leave requests'],
        ['hr.overtime', 'Manage Overtime', 'hr', 'Can manage overtime and deductions'],
        
        // Inventory
        ['inventory.view', 'View Inventory', 'inventory', 'Can view equipment and inventory'],
        ['inventory.manage', 'Manage Inventory', 'inventory', 'Can add, edit, and assign equipment'],
        ['inventory.import', 'Import Inventory', 'inventory', 'Can import equipment from CSV/Excel'],
        ['inventory.export', 'Export Inventory', 'inventory', 'Can export inventory data'],
        ['inventory.assign', 'Assign Equipment', 'inventory', 'Can assign equipment to customers'],
        ['inventory.faults', 'Manage Faults', 'inventory', 'Can report and manage equipment faults'],
        
        // Orders
        ['orders.view', 'View Orders', 'orders', 'Can view orders list'],
        ['orders.create', 'Create Orders', 'orders', 'Can create new orders'],
        ['orders.manage', 'Manage Orders', 'orders', 'Can edit and process orders'],
        ['orders.delete', 'Delete Orders', 'orders', 'Can delete orders'],
        ['orders.convert', 'Convert Orders', 'orders', 'Can convert orders to tickets'],
        ['orders.view_all', 'View All Orders', 'orders', 'View all orders (not just owned by user)'],
        
        // Payments
        ['payments.view', 'View Payments', 'payments', 'Can view payment records'],
        ['payments.manage', 'Manage Payments', 'payments', 'Can process and manage payments'],
        ['payments.stk', 'Send STK Push', 'payments', 'Can send M-Pesa STK Push requests'],
        ['payments.refund', 'Process Refunds', 'payments', 'Can process payment refunds'],
        ['payments.export', 'Export Payments', 'payments', 'Can export payment data'],
        
        // Complaints
        ['complaints.view', 'View Complaints', 'complaints', 'Can view complaints list'],
        ['complaints.create', 'Create Complaints', 'complaints', 'Can create new complaints'],
        ['complaints.edit', 'Edit Complaints', 'complaints', 'Can edit complaints'],
        ['complaints.approve', 'Approve Complaints', 'complaints', 'Can approve complaints'],
        ['complaints.reject', 'Reject Complaints', 'complaints', 'Can reject complaints'],
        ['complaints.convert', 'Convert to Ticket', 'complaints', 'Can convert complaints to tickets'],
        ['complaints.view_all', 'View All Complaints', 'complaints', 'View all complaints (not just assigned)'],
        
        // Sales
        ['sales.view', 'View Sales', 'sales', 'Can view sales dashboard'],
        ['sales.view_all', 'View All Sales', 'sales', 'Can view all salespersons data'],
        ['sales.manage', 'Manage Sales', 'sales', 'Can manage salesperson assignments'],
        ['sales.commission', 'View Commission', 'sales', 'Can view and manage commissions'],
        ['sales.leads', 'Manage Leads', 'sales', 'Can create and manage leads'],
        ['sales.targets', 'Manage Targets', 'sales', 'Can set and manage sales targets'],
        
        // Branches
        ['branches.view', 'View Branches', 'branches', 'Can view branch list'],
        ['branches.create', 'Create Branches', 'branches', 'Can create new branches'],
        ['branches.edit', 'Edit Branches', 'branches', 'Can edit branch details'],
        ['branches.delete', 'Delete Branches', 'branches', 'Can delete branches'],
        ['branches.assign', 'Assign Employees', 'branches', 'Can assign employees to branches'],
        
        // Network / SmartOLT
        ['network.view', 'View Network', 'network', 'Can view SmartOLT network status'],
        ['network.manage', 'Manage Network', 'network', 'Can manage ONUs and network devices'],
        ['network.provision', 'Provision Devices', 'network', 'Can provision new network devices'],
        
        // Accounting
        ['accounting.view', 'View Accounting', 'accounting', 'Can view accounting dashboard'],
        ['accounting.invoices', 'Manage Invoices', 'accounting', 'Can create and manage invoices'],
        ['accounting.quotes', 'Manage Quotes', 'accounting', 'Can create and manage quotes'],
        ['accounting.bills', 'Manage Bills', 'accounting', 'Can manage vendor bills'],
        ['accounting.expenses', 'Manage Expenses', 'accounting', 'Can record and manage expenses'],
        ['accounting.vendors', 'Manage Vendors', 'accounting', 'Can manage vendors/suppliers'],
        ['accounting.products', 'Manage Products', 'accounting', 'Can manage products/services catalog'],
        ['accounting.reports', 'View Financial Reports', 'accounting', 'Can view P&L, aging reports'],
        ['accounting.chart', 'Manage Chart of Accounts', 'accounting', 'Can manage chart of accounts'],
        
        // WhatsApp
        ['whatsapp.view', 'View WhatsApp', 'whatsapp', 'Can view WhatsApp conversations'],
        ['whatsapp.send', 'Send WhatsApp', 'whatsapp', 'Can send WhatsApp messages'],
        ['whatsapp.manage', 'Manage WhatsApp', 'whatsapp', 'Can configure WhatsApp settings'],
        
        // Devices / Biometric
        ['devices.view', 'View Devices', 'devices', 'Can view biometric devices'],
        ['devices.manage', 'Manage Devices', 'devices', 'Can add/edit biometric devices'],
        ['devices.sync', 'Sync Devices', 'devices', 'Can sync attendance from devices'],
        ['devices.enroll', 'Enroll Users', 'devices', 'Can enroll fingerprints on devices'],
        
        // Teams
        ['teams.view', 'View Teams', 'teams', 'Can view team list'],
        ['teams.manage', 'Manage Teams', 'teams', 'Can create and manage teams'],
        
        // Activity Logs
        ['logs.view', 'View Activity Logs', 'logs', 'Can view system activity logs'],
        ['logs.export', 'Export Logs', 'logs', 'Can export activity logs'],
        
        // Settings
        ['settings.view', 'View Settings', 'settings', 'Can view system settings'],
        ['settings.manage', 'Manage Settings', 'settings', 'Can modify system settings'],
        ['settings.sms', 'Manage SMS Settings', 'settings', 'Can configure SMS gateway'],
        ['settings.biometric', 'Manage Biometric', 'settings', 'Can configure biometric devices'],
        
        // Users
        ['users.view', 'View Users', 'users', 'Can view user accounts'],
        ['users.manage', 'Manage Users', 'users', 'Can create, edit, and delete users'],
        ['roles.manage', 'Manage Roles', 'users', 'Can manage roles and permissions'],
        
        // Reports
        ['reports.view', 'View Reports', 'reports', 'Can view reports and analytics'],
        ['reports.export', 'Export Reports', 'reports', 'Can export data and reports']
    ];
    
    $stmt = $db->prepare("INSERT INTO permissions (name, display_name, category, description) VALUES (?, ?, ?, ?)");
    foreach ($permissions as $permission) {
        try {
            $stmt->execute($permission);
        } catch (PDOException $e) {
            error_log("Error seeding permission {$permission[0]}: " . $e->getMessage());
        }
    }
    
    $rolePermissions = [
        'admin' => ['*'],
        'manager' => [
            'dashboard.view', 'customers.*', 'tickets.*', 'hr.view', 'hr.manage', 'hr.attendance',
            'inventory.*', 'orders.*', 'payments.view', 'settings.view', 'users.view', 'reports.*',
            'tickets.view_all', 'customers.view_all', 'orders.view_all', 'complaints.view_all'
        ],
        'technician' => [
            'dashboard.view', 'customers.view', 'customers.edit', 'tickets.view', 'tickets.create', 
            'tickets.edit', 'inventory.view', 'orders.view'
        ],
        'salesperson' => [
            'dashboard.view', 'customers.view', 'customers.create', 'orders.view', 'orders.create',
            'orders.manage', 'payments.view'
        ],
        'viewer' => [
            'dashboard.view', 'customers.view', 'tickets.view', 'inventory.view', 'orders.view',
            'payments.view', 'reports.view'
        ]
    ];
    
    $roleStmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
    $permStmt = $db->prepare("SELECT id FROM permissions WHERE name = ? OR name LIKE ?");
    $insertStmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
    
    foreach ($rolePermissions as $roleName => $perms) {
        $roleStmt->execute([$roleName]);
        $roleId = $roleStmt->fetchColumn();
        if (!$roleId) continue;
        
        if (in_array('*', $perms)) {
            $allPerms = $db->query("SELECT id FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($allPerms as $permId) {
                try {
                    $insertStmt->execute([$roleId, $permId]);
                } catch (PDOException $e) {}
            }
        } else {
            foreach ($perms as $perm) {
                if (str_ends_with($perm, '.*')) {
                    $category = str_replace('.*', '', $perm);
                    $catPerms = $db->prepare("SELECT id FROM permissions WHERE category = ?");
                    $catPerms->execute([$category]);
                    foreach ($catPerms->fetchAll(PDO::FETCH_COLUMN) as $permId) {
                        try {
                            $insertStmt->execute([$roleId, $permId]);
                        } catch (PDOException $e) {}
                    }
                } else {
                    $permStmt->execute([$perm, '']);
                    $permId = $permStmt->fetchColumn();
                    if ($permId) {
                        try {
                            $insertStmt->execute([$roleId, $permId]);
                        } catch (PDOException $e) {}
                    }
                }
            }
        }
    }
    
    $adminRole = $db->query("SELECT id FROM roles WHERE name = 'admin'")->fetchColumn();
    $techRole = $db->query("SELECT id FROM roles WHERE name = 'technician'")->fetchColumn();
    
    if ($adminRole) {
        $db->exec("UPDATE users SET role_id = $adminRole WHERE role = 'admin' AND role_id IS NULL");
    }
    if ($techRole) {
        $db->exec("UPDATE users SET role_id = $techRole WHERE role = 'technician' AND role_id IS NULL");
    }
}

function seedSLADefaults(PDO $db): void {
    $checkSLA = $db->query("SELECT COUNT(*) FROM sla_policies")->fetchColumn();
    if ($checkSLA > 0) {
        return;
    }
    
    $policies = [
        ['Critical Priority SLA', 'For critical/emergency issues requiring immediate attention', 'critical', 1, 4, 2, true, true],
        ['High Priority SLA', 'For high priority issues requiring quick response', 'high', 2, 8, 4, true, false],
        ['Medium Priority SLA', 'Standard SLA for regular issues', 'medium', 4, 24, 12, true, true],
        ['Low Priority SLA', 'For low priority issues that can wait', 'low', 8, 48, 24, true, false]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO sla_policies (name, description, priority, response_time_hours, resolution_time_hours, escalation_time_hours, notify_on_breach, is_default)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($policies as $policy) {
        try {
            $stmt->execute($policy);
        } catch (PDOException $e) {
            error_log("Error seeding SLA policy: " . $e->getMessage());
        }
    }
    
    $checkHours = $db->query("SELECT COUNT(*) FROM sla_business_hours")->fetchColumn();
    if ($checkHours > 0) {
        return;
    }
    
    $businessHours = [
        [0, '00:00', '00:00', false],
        [1, '08:00', '17:00', true],
        [2, '08:00', '17:00', true],
        [3, '08:00', '17:00', true],
        [4, '08:00', '17:00', true],
        [5, '08:00', '17:00', true],
        [6, '09:00', '13:00', true]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO sla_business_hours (day_of_week, start_time, end_time, is_working_day)
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($businessHours as $hours) {
        try {
            $stmt->execute($hours);
        } catch (PDOException $e) {
            error_log("Error seeding business hours: " . $e->getMessage());
        }
    }
}

function seedLeaveTypes(PDO $db): void {
    $checkLeave = $db->query("SELECT COUNT(*) FROM leave_types")->fetchColumn();
    if ($checkLeave > 0) {
        return;
    }
    
    $leaveTypes = [
        ['Annual Leave', 'ANNUAL', 21, true, true, true],
        ['Sick Leave', 'SICK', 14, true, true, true],
        ['Unpaid Leave', 'UNPAID', 0, false, true, true],
        ['Maternity Leave', 'MATERNITY', 90, true, true, true],
        ['Paternity Leave', 'PATERNITY', 14, true, true, true],
        ['Compassionate Leave', 'COMPASSION', 5, true, true, true]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO leave_types (name, code, days_per_year, is_paid, requires_approval, is_active)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($leaveTypes as $type) {
        try {
            $stmt->execute($type);
        } catch (PDOException $e) {
            error_log("Error seeding leave type: " . $e->getMessage());
        }
    }
}

function seedHRNotificationTemplates(PDO $db): void {
    $templates = [
        ['Salary Advance Approved', 'salary_advance', 'advance_approved', 'Your salary advance has been approved', 'Dear {employee_name}, your salary advance request of {currency} {amount} has been APPROVED. It will be disbursed soon. - {company_name}', true],
        ['Salary Advance Rejected', 'salary_advance', 'advance_rejected', 'Your salary advance has been rejected', 'Dear {employee_name}, your salary advance request of {currency} {amount} has been REJECTED. Reason: {rejection_reason}. - {company_name}', true],
        ['Salary Advance Disbursed', 'salary_advance', 'advance_disbursed', 'Your salary advance has been disbursed', 'Dear {employee_name}, your salary advance of {currency} {amount} has been DISBURSED. Repayment: {repayment_installments} installments of {currency} {repayment_amount}. - {company_name}', true],
        ['Leave Request Approved', 'leave', 'leave_approved', 'Your leave request has been approved', 'Dear {employee_name}, your {leave_type} request from {start_date} to {end_date} ({total_days} days) has been APPROVED. - {company_name}', true],
        ['Leave Request Rejected', 'leave', 'leave_rejected', 'Your leave request has been rejected', 'Dear {employee_name}, your {leave_type} request from {start_date} to {end_date} has been REJECTED. Reason: {rejection_reason}. - {company_name}', true]
    ];
    
    foreach ($templates as $template) {
        try {
            $checkStmt = $db->prepare("SELECT id FROM hr_notification_templates WHERE event_type = ?");
            $checkStmt->execute([$template[2]]);
            if (!$checkStmt->fetch()) {
                $stmt = $db->prepare("
                    INSERT INTO hr_notification_templates (name, category, event_type, subject, sms_template, send_sms)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute($template);
            }
        } catch (PDOException $e) {
            error_log("Error seeding HR notification template: " . $e->getMessage());
        }
    }
}

function seedISPEquipmentCategories(PDO $db): void {
    $checkCategories = $db->query("SELECT COUNT(*) FROM equipment_categories")->fetchColumn();
    if ($checkCategories > 0) {
        return;
    }
    
    $categories = [
        // ONT/ONU Devices
        ['ONT/ONU Devices', 'Optical Network Terminals and Optical Network Units for FTTH connections', 'serialized', null],
        
        // Routers
        ['Routers', 'WiFi routers and indoor CPE devices', 'serialized', null],
        
        // Fiber Optic Materials
        ['Fiber Optic', 'Fiber optic cables, splitters and accessories', 'consumable', null],
        ['Fiber Splitters', 'PLC splitters (1x2, 1x4, 1x8, 1x16, 1x32)', 'serialized', 3],
        ['Fiber Patch Cords', 'SC/APC, SC/UPC, LC patch cords', 'consumable', 3],
        ['Drop Cables', 'FTTH drop cables with pre-terminated connectors', 'consumable', 3],
        ['Fiber Pigtails', 'SC/APC, SC/UPC, LC pigtails for splicing', 'consumable', 3],
        
        // PON Equipment
        ['PON Equipment', 'OLT cards, SFP modules and PON components', 'serialized', null],
        ['OLT Cards', 'GPON/EPON line cards for OLT', 'serialized', 8],
        ['SFP Modules', 'Class B+, C+ SFP modules for OLT/ONU', 'serialized', 8],
        
        // Network Equipment
        ['Network Equipment', 'Switches, media converters and network devices', 'serialized', null],
        ['Switches', 'Managed and unmanaged network switches', 'serialized', 11],
        ['Media Converters', 'Fiber to ethernet media converters', 'serialized', 11],
        
        // Tools
        ['Tools', 'Installation and testing equipment', 'reusable', null],
        ['Fiber Cleavers', 'High precision fiber optic cleavers', 'reusable', 14],
        ['Power Meters', 'Optical power meters for signal testing', 'reusable', 14],
        ['Visual Fault Locators', 'VFL for fiber break detection', 'reusable', 14],
        ['OTDR', 'Optical Time Domain Reflectometers', 'reusable', 14],
        ['Fusion Splicers', 'Fiber optic fusion splicing machines', 'reusable', 14],
        ['Stripping Tools', 'Fiber cable strippers and cutters', 'reusable', 14],
        
        // Consumables
        ['Consumables', 'Connectors, adapters and installation materials', 'consumable', null],
        ['Fiber Connectors', 'SC/APC, SC/UPC, LC connectors for field termination', 'consumable', 22],
        ['Fiber Adapters', 'SC/APC, SC/UPC adapter couplers', 'consumable', 22],
        ['Cable Ties', 'Cable ties and fasteners', 'consumable', 22],
        ['Heat Shrink Tubes', 'Splice protection sleeves', 'consumable', 22],
        ['Cleaning Supplies', 'Fiber cleaning wipes and swabs', 'consumable', 22],
        
        // Enclosures
        ['Enclosures', 'Splice closures, distribution boxes and cabinets', 'serialized', null],
        ['Splice Closures', 'Fiber optic splice closures (dome, inline)', 'serialized', 28],
        ['Distribution Boxes', 'FTTH distribution and termination boxes', 'serialized', 28],
        ['ODF Cabinets', 'Optical distribution frames', 'serialized', 28],
        ['FAT Boxes', 'Fiber Access Terminals for multi-port distribution', 'serialized', 28]
    ];
    
    $stmt = $db->prepare("INSERT INTO equipment_categories (name, description, item_type, parent_id) VALUES (?, ?, ?, ?)");
    foreach ($categories as $cat) {
        try {
            $stmt->execute($cat);
        } catch (PDOException $e) {
            error_log("Error seeding equipment category {$cat[0]}: " . $e->getMessage());
        }
    }
}

if (php_sapi_name() === 'cli') {
    initializeDatabase();
}

function initializeAccountingTables(\PDO $db) {
    $sql = "
    -- Tax Rates
    CREATE TABLE IF NOT EXISTS tax_rates (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        rate DECIMAL(5,2) NOT NULL DEFAULT 16.00,
        type VARCHAR(20) DEFAULT 'percentage',
        is_inclusive BOOLEAN DEFAULT FALSE,
        is_default BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Chart of Accounts
    CREATE TABLE IF NOT EXISTS chart_of_accounts (
        id SERIAL PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(50) NOT NULL,
        category VARCHAR(50),
        description TEXT,
        parent_id INTEGER REFERENCES chart_of_accounts(id),
        is_system BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        balance DECIMAL(15,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Products/Services Catalog
    CREATE TABLE IF NOT EXISTS products_services (
        id SERIAL PRIMARY KEY,
        code VARCHAR(50),
        name VARCHAR(200) NOT NULL,
        description TEXT,
        type VARCHAR(20) DEFAULT 'service',
        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        cost_price DECIMAL(12,2) DEFAULT 0,
        tax_rate_id INTEGER REFERENCES tax_rates(id),
        income_account_id INTEGER REFERENCES chart_of_accounts(id),
        expense_account_id INTEGER REFERENCES chart_of_accounts(id),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Vendors/Suppliers
    CREATE TABLE IF NOT EXISTS vendors (
        id SERIAL PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        contact_person VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(50),
        address TEXT,
        city VARCHAR(100),
        country VARCHAR(100) DEFAULT 'Kenya',
        tax_pin VARCHAR(50),
        payment_terms INTEGER DEFAULT 30,
        currency VARCHAR(10) DEFAULT 'KES',
        notes TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Invoices
    CREATE TABLE IF NOT EXISTS invoices (
        id SERIAL PRIMARY KEY,
        invoice_number VARCHAR(50) UNIQUE NOT NULL,
        customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
        order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
        ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
        issue_date DATE NOT NULL DEFAULT CURRENT_DATE,
        due_date DATE NOT NULL,
        status VARCHAR(20) DEFAULT 'draft',
        subtotal DECIMAL(12,2) DEFAULT 0,
        tax_amount DECIMAL(12,2) DEFAULT 0,
        discount_amount DECIMAL(12,2) DEFAULT 0,
        total_amount DECIMAL(12,2) DEFAULT 0,
        amount_paid DECIMAL(12,2) DEFAULT 0,
        balance_due DECIMAL(12,2) DEFAULT 0,
        currency VARCHAR(10) DEFAULT 'KES',
        notes TEXT,
        terms TEXT,
        is_recurring BOOLEAN DEFAULT FALSE,
        recurring_interval VARCHAR(20),
        next_recurring_date DATE,
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Invoice Items
    CREATE TABLE IF NOT EXISTS invoice_items (
        id SERIAL PRIMARY KEY,
        invoice_id INTEGER REFERENCES invoices(id) ON DELETE CASCADE,
        product_id INTEGER REFERENCES products_services(id),
        description TEXT NOT NULL,
        quantity DECIMAL(10,2) DEFAULT 1,
        unit_price DECIMAL(12,2) NOT NULL,
        tax_rate_id INTEGER REFERENCES tax_rates(id),
        tax_amount DECIMAL(12,2) DEFAULT 0,
        discount_percent DECIMAL(5,2) DEFAULT 0,
        line_total DECIMAL(12,2) NOT NULL,
        sort_order INTEGER DEFAULT 0
    );
    
    -- Quotes/Estimates
    CREATE TABLE IF NOT EXISTS quotes (
        id SERIAL PRIMARY KEY,
        quote_number VARCHAR(50) UNIQUE NOT NULL,
        customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
        issue_date DATE NOT NULL DEFAULT CURRENT_DATE,
        expiry_date DATE,
        status VARCHAR(20) DEFAULT 'draft',
        subtotal DECIMAL(12,2) DEFAULT 0,
        tax_amount DECIMAL(12,2) DEFAULT 0,
        discount_amount DECIMAL(12,2) DEFAULT 0,
        total_amount DECIMAL(12,2) DEFAULT 0,
        currency VARCHAR(10) DEFAULT 'KES',
        notes TEXT,
        terms TEXT,
        converted_to_invoice_id INTEGER REFERENCES invoices(id),
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Quote Items
    CREATE TABLE IF NOT EXISTS quote_items (
        id SERIAL PRIMARY KEY,
        quote_id INTEGER REFERENCES quotes(id) ON DELETE CASCADE,
        product_id INTEGER REFERENCES products_services(id),
        description TEXT NOT NULL,
        quantity DECIMAL(10,2) DEFAULT 1,
        unit_price DECIMAL(12,2) NOT NULL,
        tax_rate_id INTEGER REFERENCES tax_rates(id),
        tax_amount DECIMAL(12,2) DEFAULT 0,
        discount_percent DECIMAL(5,2) DEFAULT 0,
        line_total DECIMAL(12,2) NOT NULL,
        sort_order INTEGER DEFAULT 0
    );
    
    -- Purchase Orders
    CREATE TABLE IF NOT EXISTS purchase_orders (
        id SERIAL PRIMARY KEY,
        po_number VARCHAR(50) UNIQUE NOT NULL,
        vendor_id INTEGER REFERENCES vendors(id) ON DELETE SET NULL,
        order_date DATE NOT NULL DEFAULT CURRENT_DATE,
        expected_date DATE,
        status VARCHAR(20) DEFAULT 'draft',
        subtotal DECIMAL(12,2) DEFAULT 0,
        tax_amount DECIMAL(12,2) DEFAULT 0,
        total_amount DECIMAL(12,2) DEFAULT 0,
        currency VARCHAR(10) DEFAULT 'KES',
        notes TEXT,
        approved_by INTEGER REFERENCES users(id),
        approved_at TIMESTAMP,
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Purchase Order Items
    CREATE TABLE IF NOT EXISTS purchase_order_items (
        id SERIAL PRIMARY KEY,
        purchase_order_id INTEGER REFERENCES purchase_orders(id) ON DELETE CASCADE,
        product_id INTEGER REFERENCES products_services(id),
        equipment_id INTEGER REFERENCES equipment(id),
        description TEXT NOT NULL,
        quantity DECIMAL(10,2) DEFAULT 1,
        received_quantity DECIMAL(10,2) DEFAULT 0,
        unit_price DECIMAL(12,2) NOT NULL,
        tax_rate_id INTEGER REFERENCES tax_rates(id),
        tax_amount DECIMAL(12,2) DEFAULT 0,
        line_total DECIMAL(12,2) NOT NULL,
        sort_order INTEGER DEFAULT 0
    );
    
    -- Vendor Bills
    CREATE TABLE IF NOT EXISTS vendor_bills (
        id SERIAL PRIMARY KEY,
        bill_number VARCHAR(50) NOT NULL,
        vendor_id INTEGER REFERENCES vendors(id) ON DELETE SET NULL,
        purchase_order_id INTEGER REFERENCES purchase_orders(id),
        bill_date DATE NOT NULL DEFAULT CURRENT_DATE,
        due_date DATE NOT NULL,
        status VARCHAR(20) DEFAULT 'unpaid',
        subtotal DECIMAL(12,2) DEFAULT 0,
        tax_amount DECIMAL(12,2) DEFAULT 0,
        total_amount DECIMAL(12,2) DEFAULT 0,
        amount_paid DECIMAL(12,2) DEFAULT 0,
        balance_due DECIMAL(12,2) DEFAULT 0,
        currency VARCHAR(10) DEFAULT 'KES',
        reference VARCHAR(100),
        notes TEXT,
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Vendor Bill Items
    CREATE TABLE IF NOT EXISTS vendor_bill_items (
        id SERIAL PRIMARY KEY,
        bill_id INTEGER REFERENCES vendor_bills(id) ON DELETE CASCADE,
        account_id INTEGER REFERENCES chart_of_accounts(id),
        description TEXT NOT NULL,
        quantity DECIMAL(10,2) DEFAULT 1,
        unit_price DECIMAL(12,2) NOT NULL,
        tax_rate_id INTEGER REFERENCES tax_rates(id),
        tax_amount DECIMAL(12,2) DEFAULT 0,
        line_total DECIMAL(12,2) NOT NULL,
        sort_order INTEGER DEFAULT 0
    );
    
    -- Expense Categories
    CREATE TABLE IF NOT EXISTS expense_categories (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        account_id INTEGER REFERENCES chart_of_accounts(id),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Expenses
    CREATE TABLE IF NOT EXISTS expenses (
        id SERIAL PRIMARY KEY,
        expense_number VARCHAR(50),
        category_id INTEGER REFERENCES expense_categories(id),
        vendor_id INTEGER REFERENCES vendors(id),
        expense_date DATE NOT NULL DEFAULT CURRENT_DATE,
        amount DECIMAL(12,2) NOT NULL,
        tax_amount DECIMAL(12,2) DEFAULT 0,
        total_amount DECIMAL(12,2) NOT NULL,
        payment_method VARCHAR(50),
        reference VARCHAR(100),
        description TEXT,
        receipt_url TEXT,
        status VARCHAR(20) DEFAULT 'pending',
        approved_by INTEGER REFERENCES users(id),
        approved_at TIMESTAMP,
        employee_id INTEGER REFERENCES employees(id),
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Customer Payments (Received)
    CREATE TABLE IF NOT EXISTS customer_payments (
        id SERIAL PRIMARY KEY,
        payment_number VARCHAR(50) UNIQUE NOT NULL,
        customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
        invoice_id INTEGER REFERENCES invoices(id) ON DELETE SET NULL,
        payment_date DATE NOT NULL DEFAULT CURRENT_DATE,
        amount DECIMAL(12,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        mpesa_transaction_id INTEGER REFERENCES mpesa_transactions(id),
        mpesa_receipt VARCHAR(50),
        reference VARCHAR(100),
        notes TEXT,
        status VARCHAR(20) DEFAULT 'completed',
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Vendor Payments (Made)
    CREATE TABLE IF NOT EXISTS vendor_payments (
        id SERIAL PRIMARY KEY,
        payment_number VARCHAR(50) UNIQUE NOT NULL,
        vendor_id INTEGER REFERENCES vendors(id) ON DELETE SET NULL,
        bill_id INTEGER REFERENCES vendor_bills(id) ON DELETE SET NULL,
        payment_date DATE NOT NULL DEFAULT CURRENT_DATE,
        amount DECIMAL(12,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        reference VARCHAR(100),
        notes TEXT,
        status VARCHAR(20) DEFAULT 'completed',
        created_by INTEGER REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Accounting Settings
    CREATE TABLE IF NOT EXISTS accounting_settings (
        id SERIAL PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Indexes
    CREATE INDEX IF NOT EXISTS idx_invoices_customer ON invoices(customer_id);
    CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices(status);
    CREATE INDEX IF NOT EXISTS idx_invoices_due_date ON invoices(due_date);
    CREATE INDEX IF NOT EXISTS idx_quotes_customer ON quotes(customer_id);
    CREATE INDEX IF NOT EXISTS idx_vendor_bills_vendor ON vendor_bills(vendor_id);
    CREATE INDEX IF NOT EXISTS idx_vendor_bills_status ON vendor_bills(status);
    CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses(category_id);
    CREATE INDEX IF NOT EXISTS idx_customer_payments_invoice ON customer_payments(invoice_id);
    CREATE INDEX IF NOT EXISTS idx_vendor_payments_bill ON vendor_payments(bill_id);
    ";
    
    $db->exec($sql);
    
    // Seed default tax rate
    $checkTax = $db->query("SELECT COUNT(*) FROM tax_rates")->fetchColumn();
    if ($checkTax == 0) {
        $db->exec("INSERT INTO tax_rates (name, rate, is_default, is_active) VALUES ('VAT 16%', 16.00, true, true)");
        $db->exec("INSERT INTO tax_rates (name, rate, is_default, is_active) VALUES ('Exempt', 0.00, false, true)");
    }
    
    // Seed basic chart of accounts
    $checkAccounts = $db->query("SELECT COUNT(*) FROM chart_of_accounts")->fetchColumn();
    if ($checkAccounts == 0) {
        $accounts = [
            ['1000', 'Assets', 'asset', 'Assets', true],
            ['1100', 'Cash', 'asset', 'Current Assets', true],
            ['1110', 'M-Pesa', 'asset', 'Current Assets', true],
            ['1120', 'Bank Account', 'asset', 'Current Assets', true],
            ['1200', 'Accounts Receivable', 'asset', 'Current Assets', true],
            ['1300', 'Inventory', 'asset', 'Current Assets', true],
            ['2000', 'Liabilities', 'liability', 'Liabilities', true],
            ['2100', 'Accounts Payable', 'liability', 'Current Liabilities', true],
            ['2200', 'VAT Payable', 'liability', 'Current Liabilities', true],
            ['3000', 'Equity', 'equity', 'Equity', true],
            ['3100', 'Owners Equity', 'equity', 'Equity', true],
            ['3200', 'Retained Earnings', 'equity', 'Equity', true],
            ['4000', 'Revenue', 'revenue', 'Revenue', true],
            ['4100', 'Service Revenue', 'revenue', 'Revenue', true],
            ['4200', 'Installation Revenue', 'revenue', 'Revenue', true],
            ['4300', 'Equipment Sales', 'revenue', 'Revenue', true],
            ['5000', 'Expenses', 'expense', 'Expenses', true],
            ['5100', 'Salaries & Wages', 'expense', 'Operating Expenses', true],
            ['5200', 'Rent', 'expense', 'Operating Expenses', true],
            ['5300', 'Utilities', 'expense', 'Operating Expenses', true],
            ['5400', 'Internet & Bandwidth', 'expense', 'Operating Expenses', true],
            ['5500', 'Equipment & Supplies', 'expense', 'Operating Expenses', true],
            ['5600', 'Marketing', 'expense', 'Operating Expenses', true],
            ['5700', 'Transport', 'expense', 'Operating Expenses', true]
        ];
        
        $stmt = $db->prepare("INSERT INTO chart_of_accounts (code, name, type, category, is_system) VALUES (?, ?, ?, ?, ?)");
        foreach ($accounts as $acc) {
            try { $stmt->execute($acc); } catch (PDOException $e) {}
        }
    }
    
    // Seed expense categories
    $checkCats = $db->query("SELECT COUNT(*) FROM expense_categories")->fetchColumn();
    if ($checkCats == 0) {
        $categories = ['Salaries', 'Rent', 'Utilities', 'Internet & Bandwidth', 'Equipment', 'Office Supplies', 'Transport', 'Marketing', 'Repairs & Maintenance', 'Other'];
        $stmt = $db->prepare("INSERT INTO expense_categories (name) VALUES (?)");
        foreach ($categories as $cat) {
            try { $stmt->execute([$cat]); } catch (PDOException $e) {}
        }
    }
    
    // Seed accounting settings
    $checkSettings = $db->query("SELECT COUNT(*) FROM accounting_settings")->fetchColumn();
    if ($checkSettings == 0) {
        $settings = [
            ['invoice_prefix', 'INV-'],
            ['invoice_next_number', '1001'],
            ['quote_prefix', 'QUO-'],
            ['quote_next_number', '1001'],
            ['po_prefix', 'PO-'],
            ['po_next_number', '1001'],
            ['payment_prefix', 'PAY-'],
            ['payment_next_number', '1001'],
            ['default_payment_terms', '30'],
            ['default_currency', 'KES'],
            ['company_tax_pin', '']
        ];
        $stmt = $db->prepare("INSERT INTO accounting_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($settings as $s) {
            try { $stmt->execute($s); } catch (PDOException $e) {}
        }
    }
    
    // Seed default ticket categories
    $checkTicketCats = $db->query("SELECT COUNT(*) FROM ticket_categories")->fetchColumn();
    if ($checkTicketCats == 0) {
        $ticketCategories = [
            ['connectivity', 'Connectivity Issue', 1],
            ['speed', 'Speed Issue', 2],
            ['installation', 'New Installation', 3],
            ['billing', 'Billing Inquiry', 4],
            ['equipment', 'Equipment Problem', 5],
            ['outage', 'Service Outage', 6],
            ['service', 'Service Quality', 7],
            ['upgrade', 'Plan Upgrade', 8],
            ['other', 'Other', 9]
        ];
        $stmt = $db->prepare("INSERT INTO ticket_categories (key, label, display_order, is_active) VALUES (?, ?, ?, true)");
        foreach ($ticketCategories as $cat) {
            try { $stmt->execute($cat); } catch (PDOException $e) {}
        }
    }
    
    // Seed default ticket commission rates
    $checkCommRates = $db->query("SELECT COUNT(*) FROM ticket_commission_rates")->fetchColumn();
    if ($checkCommRates == 0) {
        $commissionRates = [
            ['connectivity', 100, 'KES', 'Commission for connectivity issue tickets'],
            ['speed', 100, 'KES', 'Commission for speed issue tickets'],
            ['installation', 500, 'KES', 'Commission for new installation tickets'],
            ['billing', 50, 'KES', 'Commission for billing inquiry tickets'],
            ['equipment', 150, 'KES', 'Commission for equipment problem tickets'],
            ['outage', 100, 'KES', 'Commission for service outage tickets'],
            ['service', 100, 'KES', 'Commission for service quality tickets'],
            ['upgrade', 200, 'KES', 'Commission for plan upgrade tickets'],
            ['other', 50, 'KES', 'Commission for other tickets']
        ];
        $stmt = $db->prepare("INSERT INTO ticket_commission_rates (category, rate, currency, description, is_active) VALUES (?, ?, ?, ?, true)");
        foreach ($commissionRates as $rate) {
            try { $stmt->execute($rate); } catch (PDOException $e) {}
        }
    }
}
