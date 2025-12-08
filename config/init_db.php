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
        recipient_phone VARCHAR(20) NOT NULL,
        recipient_type VARCHAR(20) NOT NULL,
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

    ALTER TABLE attendance ADD COLUMN IF NOT EXISTS late_minutes INTEGER DEFAULT 0;
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
        ['equipment', 'brand', 'ALTER TABLE equipment ADD COLUMN brand VARCHAR(100)'],
        ['equipment', 'mac_address', 'ALTER TABLE equipment ADD COLUMN mac_address VARCHAR(50)'],
        ['equipment', 'warranty_expiry', 'ALTER TABLE equipment ADD COLUMN warranty_expiry DATE']
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
    
    seedRolesAndPermissions($db);
    seedSLADefaults($db);
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
        ['dashboard.view', 'View Dashboard', 'dashboard', 'Can view the main dashboard'],
        
        ['customers.view', 'View Customers', 'customers', 'Can view customer list and details'],
        ['customers.create', 'Create Customers', 'customers', 'Can create new customers'],
        ['customers.edit', 'Edit Customers', 'customers', 'Can edit existing customers'],
        ['customers.delete', 'Delete Customers', 'customers', 'Can delete customers'],
        
        ['tickets.view', 'View Tickets', 'tickets', 'Can view ticket list and details'],
        ['tickets.create', 'Create Tickets', 'tickets', 'Can create new tickets'],
        ['tickets.edit', 'Edit Tickets', 'tickets', 'Can edit and update tickets'],
        ['tickets.delete', 'Delete Tickets', 'tickets', 'Can delete tickets'],
        ['tickets.assign', 'Assign Tickets', 'tickets', 'Can assign tickets to technicians'],
        
        ['hr.view', 'View HR', 'hr', 'Can view employee records and HR data'],
        ['hr.manage', 'Manage HR', 'hr', 'Can create, edit, and manage employees'],
        ['hr.payroll', 'Manage Payroll', 'hr', 'Can process payroll and deductions'],
        ['hr.attendance', 'Manage Attendance', 'hr', 'Can view and edit attendance records'],
        
        ['inventory.view', 'View Inventory', 'inventory', 'Can view equipment and inventory'],
        ['inventory.manage', 'Manage Inventory', 'inventory', 'Can add, edit, and assign equipment'],
        
        ['orders.view', 'View Orders', 'orders', 'Can view orders list'],
        ['orders.create', 'Create Orders', 'orders', 'Can create new orders'],
        ['orders.manage', 'Manage Orders', 'orders', 'Can edit and process orders'],
        
        ['payments.view', 'View Payments', 'payments', 'Can view payment records'],
        ['payments.manage', 'Manage Payments', 'payments', 'Can process and manage payments'],
        
        ['settings.view', 'View Settings', 'settings', 'Can view system settings'],
        ['settings.manage', 'Manage Settings', 'settings', 'Can modify system settings'],
        ['settings.sms', 'Manage SMS Settings', 'settings', 'Can configure SMS gateway'],
        ['settings.biometric', 'Manage Biometric', 'settings', 'Can configure biometric devices'],
        
        ['users.view', 'View Users', 'users', 'Can view user accounts'],
        ['users.manage', 'Manage Users', 'users', 'Can create, edit, and delete users'],
        ['roles.manage', 'Manage Roles', 'users', 'Can manage roles and permissions'],
        
        ['reports.view', 'View Reports', 'reports', 'Can view reports and analytics'],
        ['reports.export', 'Export Reports', 'reports', 'Can export data and reports'],
        
        ['tickets.view_all', 'View All Tickets', 'tickets', 'View all tickets (not just assigned)'],
        ['customers.view_all', 'View All Customers', 'customers', 'View all customers (not just created by user)'],
        ['orders.view_all', 'View All Orders', 'orders', 'View all orders (not just owned by user)'],
        ['complaints.view_all', 'View All Complaints', 'complaints', 'View all complaints (not just assigned)']
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

if (php_sapi_name() === 'cli') {
    initializeDatabase();
}
