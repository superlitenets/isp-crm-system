-- Create attendance_notification_logs table if not exists
CREATE TABLE IF NOT EXISTS attendance_notification_logs (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    notification_template_id INTEGER REFERENCES hr_notification_templates(id) ON DELETE SET NULL,
    attendance_date DATE,
    clock_in_time TIME,
    late_minutes INTEGER DEFAULT 0,
    deduction_amount DECIMAL(10,2) DEFAULT 0,
    notification_type VARCHAR(50) DEFAULT 'sms',
    phone VARCHAR(20),
    message TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    response_data JSONB,
    sent_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add index for faster queries
CREATE INDEX IF NOT EXISTS idx_attendance_notification_logs_employee ON attendance_notification_logs(employee_id);
CREATE INDEX IF NOT EXISTS idx_attendance_notification_logs_date ON attendance_notification_logs(attendance_date);
CREATE INDEX IF NOT EXISTS idx_attendance_notification_logs_status ON attendance_notification_logs(status);
