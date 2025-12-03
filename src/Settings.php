<?php

namespace App;

class Settings {
    private \PDO $db;

    public function __construct() {
        $this->db = \Database::getConnection();
    }

    public function get(string $key, $default = null) {
        $stmt = $this->db->prepare("SELECT setting_value FROM company_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    }

    public function set(string $key, $value, string $type = 'text'): bool {
        $stmt = $this->db->prepare("
            INSERT INTO company_settings (setting_key, setting_value, setting_type)
            VALUES (?, ?, ?)
            ON CONFLICT (setting_key) DO UPDATE SET
                setting_value = EXCLUDED.setting_value,
                setting_type = EXCLUDED.setting_type,
                updated_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$key, $value, $type]);
    }

    public function getAll(): array {
        $stmt = $this->db->query("SELECT * FROM company_settings ORDER BY setting_key");
        $results = $stmt->fetchAll();
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    public function getCompanyInfo(): array {
        return [
            'company_name' => $this->get('company_name', 'ISP Company'),
            'company_email' => $this->get('company_email', ''),
            'company_phone' => $this->get('company_phone', ''),
            'company_address' => $this->get('company_address', ''),
            'company_website' => $this->get('company_website', ''),
            'company_logo' => $this->get('company_logo', ''),
            'timezone' => $this->get('timezone', 'UTC'),
            'currency' => $this->get('currency', 'USD'),
            'currency_symbol' => $this->get('currency_symbol', '$'),
            'date_format' => $this->get('date_format', 'Y-m-d'),
            'time_format' => $this->get('time_format', 'H:i'),
            'working_hours_start' => $this->get('working_hours_start', '09:00'),
            'working_hours_end' => $this->get('working_hours_end', '17:00'),
            'ticket_prefix' => $this->get('ticket_prefix', 'TKT'),
            'customer_prefix' => $this->get('customer_prefix', 'CUS'),
            'sms_enabled' => $this->get('sms_enabled', '1'),
            'email_notifications' => $this->get('email_notifications', '0'),
        ];
    }

    public function saveCompanyInfo(array $data): bool {
        $fields = [
            'company_name', 'company_email', 'company_phone', 'company_address',
            'company_website', 'company_logo', 'timezone', 'currency', 'currency_symbol',
            'date_format', 'time_format', 'working_hours_start', 'working_hours_end',
            'ticket_prefix', 'customer_prefix', 'sms_enabled', 'email_notifications'
        ];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $this->set($field, $data[$field]);
            }
        }
        return true;
    }

    public function createTemplate(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_templates (name, category, subject, content, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['category'] ?? null,
            $data['subject'] ?? null,
            $data['content'],
            isset($data['is_active']) ? 1 : 1,
            $data['created_by'] ?? null
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateTemplate(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE ticket_templates SET 
                name = ?, category = ?, subject = ?, content = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['category'] ?? null,
            $data['subject'] ?? null,
            $data['content'],
            isset($data['is_active']) ? 1 : 0,
            $id
        ]);
    }

    public function deleteTemplate(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM ticket_templates WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getTemplate(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT t.*, u.name as created_by_name
            FROM ticket_templates t
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getAllTemplates(?string $category = null, bool $activeOnly = false): array {
        $sql = "SELECT t.*, u.name as created_by_name
                FROM ticket_templates t
                LEFT JOIN users u ON t.created_by = u.id
                WHERE 1=1";
        $params = [];

        if ($category) {
            $sql .= " AND t.category = ?";
            $params[] = $category;
        }

        if ($activeOnly) {
            $sql .= " AND t.is_active = true";
        }

        $sql .= " ORDER BY t.category, t.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getTemplateCategories(): array {
        $stmt = $this->db->query("SELECT DISTINCT category FROM ticket_templates WHERE category IS NOT NULL ORDER BY category");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getDefaultTemplateCategories(): array {
        return [
            'greeting' => 'Greeting',
            'acknowledgment' => 'Acknowledgment',
            'status_update' => 'Status Update',
            'resolution' => 'Resolution',
            'follow_up' => 'Follow Up',
            'escalation' => 'Escalation',
            'closure' => 'Closure',
            'general' => 'General'
        ];
    }

    public function getTimezones(): array {
        return [
            'UTC' => 'UTC',
            'America/New_York' => 'Eastern Time (US)',
            'America/Chicago' => 'Central Time (US)',
            'America/Denver' => 'Mountain Time (US)',
            'America/Los_Angeles' => 'Pacific Time (US)',
            'Europe/London' => 'London',
            'Europe/Paris' => 'Paris',
            'Asia/Tokyo' => 'Tokyo',
            'Asia/Singapore' => 'Singapore',
            'Australia/Sydney' => 'Sydney'
        ];
    }

    public function getCurrencies(): array {
        return [
            'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
            'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
            'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'CA$'],
            'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
            'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
            'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦'],
            'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh'],
            'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R']
        ];
    }

    public function getDateFormats(): array {
        return [
            'Y-m-d' => date('Y-m-d') . ' (2024-12-15)',
            'd/m/Y' => date('d/m/Y') . ' (15/12/2024)',
            'm/d/Y' => date('m/d/Y') . ' (12/15/2024)',
            'd-m-Y' => date('d-m-Y') . ' (15-12-2024)',
            'M j, Y' => date('M j, Y') . ' (Dec 15, 2024)',
            'F j, Y' => date('F j, Y') . ' (December 15, 2024)'
        ];
    }

    public function getTimeFormats(): array {
        return [
            'H:i' => date('H:i') . ' (24-hour)',
            'h:i A' => date('h:i A') . ' (12-hour)'
        ];
    }
}
