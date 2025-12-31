<?php

namespace App;

class Settings {
    private \PDO $db;
    private string $encryptionKey;
    private static array $cache = [];
    private static bool $cacheLoaded = false;

    public function __construct() {
        $this->db = \Database::getConnection();
        $this->encryptionKey = $this->getEncryptionKey();
        $this->loadCache();
    }

    private function loadCache(): void {
        if (self::$cacheLoaded) {
            return;
        }
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value, setting_type FROM company_settings");
            $results = $stmt->fetchAll();
            foreach ($results as $row) {
                self::$cache[$row['setting_key']] = [
                    'value' => $row['setting_value'],
                    'type' => $row['setting_type']
                ];
            }
            self::$cacheLoaded = true;
        } catch (\Exception $e) {
            self::$cacheLoaded = true;
        }
    }

    public static function clearCache(): void {
        self::$cache = [];
        self::$cacheLoaded = false;
    }

    private function getEncryptionKey(): string {
        $key = getenv('SESSION_SECRET') ?: getenv('ENCRYPTION_KEY');
        if (!$key) {
            $key = 'isp-crm-default-key-change-in-production';
        }
        return hash('sha256', $key, true);
    }

    private function encrypt(string $plaintext): string {
        if (empty($plaintext)) return '';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    private function decrypt(string $ciphertext): string {
        if (empty($ciphertext)) return '';
        $decoded = base64_decode($ciphertext);
        if ($decoded === false || strpos($decoded, '::') === false) {
            return $ciphertext;
        }
        list($iv, $encrypted) = explode('::', $decoded, 2);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return $decrypted !== false ? $decrypted : $ciphertext;
    }

    public function get(string $key, $default = null) {
        if (isset(self::$cache[$key])) {
            $cached = self::$cache[$key];
            if ($cached['type'] === 'secret') {
                return $this->decrypt($cached['value']);
            }
            return $cached['value'];
        }
        return $default;
    }
    
    public function getSetting(string $key, $default = null) {
        return $this->get($key, $default);
    }
    
    public function saveSetting(string $key, string $value, string $type = 'text'): void {
        $stmt = $this->db->prepare("
            INSERT INTO company_settings (setting_key, setting_value, setting_type)
            VALUES (?, ?, ?)
            ON CONFLICT (setting_key) DO UPDATE SET setting_value = ?, updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$key, $value, $type, $value]);
        self::$cache[$key] = ['value' => $value, 'type' => $type];
    }

    public function set(string $key, $value, string $type = 'text'): bool {
        $storedValue = $value;
        if ($type === 'secret' && !empty($value)) {
            $storedValue = $this->encrypt($value);
        }
        $stmt = $this->db->prepare("
            INSERT INTO company_settings (setting_key, setting_value, setting_type)
            VALUES (?, ?, ?)
            ON CONFLICT (setting_key) DO UPDATE SET
                setting_value = EXCLUDED.setting_value,
                setting_type = EXCLUDED.setting_type,
                updated_at = CURRENT_TIMESTAMP
        ");
        $result = $stmt->execute([$key, $storedValue, $type]);
        if ($result) {
            self::$cache[$key] = ['value' => $storedValue, 'type' => $type];
        }
        return $result;
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
            'whatsapp_enabled' => $this->get('whatsapp_enabled', '1'),
            'email_notifications' => $this->get('email_notifications', '0'),
        ];
    }

    public function saveCompanyInfo(array $data): bool {
        $fields = [
            'company_name', 'company_email', 'company_phone', 'company_address',
            'company_website', 'company_logo', 'timezone', 'currency', 'currency_symbol',
            'date_format', 'time_format', 'working_hours_start', 'working_hours_end',
            'ticket_prefix', 'customer_prefix', 'sms_enabled', 'whatsapp_enabled', 'email_notifications'
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
            VALUES (?, ?, ?, ?, ?::boolean, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['category'] ?? null,
            $data['subject'] ?? null,
            $data['content'],
            'true',
            $data['created_by'] ?? null
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateTemplate(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE ticket_templates SET 
                name = ?, category = ?, subject = ?, content = ?, is_active = ?::boolean, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['category'] ?? null,
            $data['subject'] ?? null,
            $data['content'],
            isset($data['is_active']) && !empty($data['is_active']) ? 'true' : 'false',
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
            'Africa/Nairobi' => 'Nairobi (East Africa)',
            'Africa/Lagos' => 'Lagos (West Africa)',
            'Africa/Johannesburg' => 'Johannesburg (South Africa)',
            'Africa/Cairo' => 'Cairo (Egypt)',
            'Africa/Casablanca' => 'Casablanca (Morocco)',
            'Africa/Accra' => 'Accra (Ghana)',
            'Africa/Addis_Ababa' => 'Addis Ababa (Ethiopia)',
            'Africa/Dar_es_Salaam' => 'Dar es Salaam (Tanzania)',
            'Africa/Kampala' => 'Kampala (Uganda)',
            'Africa/Kigali' => 'Kigali (Rwanda)',
            'America/New_York' => 'Eastern Time (US)',
            'America/Chicago' => 'Central Time (US)',
            'America/Denver' => 'Mountain Time (US)',
            'America/Los_Angeles' => 'Pacific Time (US)',
            'Europe/London' => 'London',
            'Europe/Paris' => 'Paris',
            'Asia/Dubai' => 'Dubai (UAE)',
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

    public function getSMSSettings(): array {
        return [
            'sms_provider' => $this->get('sms_provider', 'advanta'),
            'advanta_api_key' => $this->get('advanta_api_key', ''),
            'advanta_partner_id' => $this->get('advanta_partner_id', ''),
            'advanta_shortcode' => $this->get('advanta_shortcode', ''),
            'advanta_url' => $this->get('advanta_url', 'https://quicksms.advantasms.com/api/services/sendsms/'),
            'twilio_account_sid' => $this->get('twilio_account_sid', ''),
            'twilio_auth_token' => $this->get('twilio_auth_token', ''),
            'twilio_phone_number' => $this->get('twilio_phone_number', ''),
            'custom_sms_url' => $this->get('custom_sms_url', ''),
            'custom_sms_api_key' => $this->get('custom_sms_api_key', ''),
            'custom_sms_sender_id' => $this->get('custom_sms_sender_id', ''),
        ];
    }

    public function getWhatsAppSettings(): array {
        return [
            'whatsapp_enabled' => $this->get('whatsapp_enabled', '1'),
            'whatsapp_country_code' => $this->get('whatsapp_country_code', '254'),
            'whatsapp_default_message' => $this->get('whatsapp_default_message', ''),
            'whatsapp_provider' => $this->get('whatsapp_provider', 'web'),
        ];
    }
    
    public function getPrimaryNotificationGateway(): string {
        return $this->get('primary_notification_gateway', 'both');
    }
    
    public function setPrimaryNotificationGateway(string $gateway): bool {
        if (!in_array($gateway, ['sms', 'whatsapp', 'both'])) {
            $gateway = 'both';
        }
        return $this->set('primary_notification_gateway', $gateway);
    }

    public function saveWhatsAppSettings(array $data): bool {
        $fields = [
            'whatsapp_country_code', 
            'whatsapp_default_message',
            'whatsapp_provider',
            'whatsapp_session_url',
            'whatsapp_session_secret',
            'whatsapp_meta_token',
            'whatsapp_phone_number_id',
            'whatsapp_business_id',
            'whatsapp_waha_url',
            'whatsapp_waha_api_key',
            'whatsapp_ultramsg_instance',
            'whatsapp_ultramsg_token',
            'whatsapp_custom_url',
            'whatsapp_custom_api_key',
            'whatsapp_summary_groups',
            'whatsapp_daily_summary_groups',
            'daily_summary_morning_hour',
            'daily_summary_evening_hour',
            'whatsapp_operations_group_id',
            'wa_provisioning_group',
            'min_clock_out_hour'
        ];
        
        $this->set('whatsapp_enabled', $data['whatsapp_enabled'] ?? '1');
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $secretFields = ['whatsapp_meta_token', 'whatsapp_waha_api_key', 'whatsapp_ultramsg_token', 'whatsapp_custom_api_key', 'whatsapp_session_secret'];
                $type = in_array($field, $secretFields) ? 'secret' : 'text';
                $this->set($field, $data[$field], $type);
            }
        }
        
        // Save department-specific WhatsApp groups
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'whatsapp_group_dept_')) {
                $this->set($key, $value, 'text');
            }
        }
        
        return true;
    }

    public function saveSMSSettings(array $data): bool {
        $fields = [
            'sms_provider', 'advanta_api_key', 'advanta_partner_id', 'advanta_shortcode', 'advanta_url',
            'twilio_account_sid', 'twilio_auth_token', 'twilio_phone_number',
            'custom_sms_url', 'custom_sms_api_key', 'custom_sms_sender_id'
        ];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $this->set($field, $data[$field], 'secret');
            }
        }
        return true;
    }

    public function getAdvantaConfig(): array {
        $apiKey = getenv('ADVANTA_API_KEY') ?: $this->get('advanta_api_key', '');
        $partnerId = getenv('ADVANTA_PARTNER_ID') ?: $this->get('advanta_partner_id', '');
        $shortcode = getenv('ADVANTA_SHORTCODE') ?: $this->get('advanta_shortcode', '');
        $url = getenv('ADVANTA_URL') ?: $this->get('advanta_url', 'https://quicksms.advantasms.com/api/services/sendsms/');
        
        return [
            'api_key' => $apiKey,
            'partner_id' => $partnerId,
            'shortcode' => $shortcode,
            'url' => $url,
            'configured' => !empty($apiKey) && !empty($partnerId) && !empty($shortcode)
        ];
    }

    public function createPackage(array $data): int {
        $slug = $this->generateSlug($data['name']);
        $features = isset($data['features']) ? (is_array($data['features']) ? json_encode($data['features']) : $data['features']) : '[]';
        
        $stmt = $this->db->prepare("
            INSERT INTO service_packages (name, slug, description, speed, speed_unit, price, currency, billing_cycle, features, is_popular, is_active, display_order, badge_text, badge_color, icon)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::boolean, ?::boolean, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $slug,
            $data['description'] ?? '',
            $data['speed'],
            $data['speed_unit'] ?? 'Mbps',
            $data['price'],
            $data['currency'] ?? 'KES',
            $data['billing_cycle'] ?? 'monthly',
            $features,
            isset($data['is_popular']) && !empty($data['is_popular']) ? 'true' : 'false',
            'true',
            $data['display_order'] ?? 0,
            $data['badge_text'] ?? null,
            $data['badge_color'] ?? null,
            $data['icon'] ?? 'wifi'
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updatePackage(int $id, array $data): bool {
        $features = isset($data['features']) ? (is_array($data['features']) ? json_encode($data['features']) : $data['features']) : '[]';
        
        $stmt = $this->db->prepare("
            UPDATE service_packages SET
                name = ?, description = ?, speed = ?, speed_unit = ?, price = ?, currency = ?,
                billing_cycle = ?, features = ?, is_popular = ?::boolean, is_active = ?::boolean, display_order = ?,
                badge_text = ?, badge_color = ?, icon = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['speed'],
            $data['speed_unit'] ?? 'Mbps',
            $data['price'],
            $data['currency'] ?? 'KES',
            $data['billing_cycle'] ?? 'monthly',
            $features,
            isset($data['is_popular']) && !empty($data['is_popular']) ? 'true' : 'false',
            isset($data['is_active']) && !empty($data['is_active']) ? 'true' : 'false',
            $data['display_order'] ?? 0,
            $data['badge_text'] ?? null,
            $data['badge_color'] ?? null,
            $data['icon'] ?? 'wifi',
            $id
        ]);
    }

    public function deletePackage(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM service_packages WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getPackage(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM service_packages WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        if ($result && $result['features']) {
            $result['features'] = json_decode($result['features'], true) ?: [];
        }
        return $result ?: null;
    }

    public function getAllPackages(bool $activeOnly = false): array {
        $sql = "SELECT * FROM service_packages";
        if ($activeOnly) {
            $sql .= " WHERE is_active = true";
        }
        $sql .= " ORDER BY display_order ASC, price ASC";
        
        $stmt = $this->db->query($sql);
        $packages = $stmt->fetchAll();
        
        foreach ($packages as &$package) {
            if ($package['features']) {
                $package['features'] = json_decode($package['features'], true) ?: [];
            } else {
                $package['features'] = [];
            }
        }
        
        return $packages;
    }

    public function getActivePackagesForLanding(): array {
        return $this->getAllPackages(true);
    }

    private function generateSlug(string $name): string {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        $baseSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    private function slugExists(string $slug): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM service_packages WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetchColumn() > 0;
    }

    public function getLandingPageSettings(): array {
        return [
            'hero_title' => $this->get('landing_hero_title', 'Lightning Fast Internet'),
            'hero_subtitle' => $this->get('landing_hero_subtitle', 'Experience blazing fast fiber internet for your home and business'),
            'hero_cta_text' => $this->get('landing_hero_cta', 'Get Started'),
            'hero_cta_link' => $this->get('landing_hero_cta_link', '#packages'),
            'about_title' => $this->get('landing_about_title', 'Why Choose Us?'),
            'about_description' => $this->get('landing_about_description', 'We deliver reliable, high-speed internet with exceptional customer support.'),
            'contact_phone' => $this->get('company_phone', ''),
            'contact_email' => $this->get('company_email', ''),
            'contact_address' => $this->get('company_address', ''),
            'footer_text' => $this->get('landing_footer_text', ''),
            'primary_color' => $this->get('landing_primary_color', '#2563eb'),
            'show_testimonials' => $this->get('landing_show_testimonials', '1'),
        ];
    }

    public function saveLandingPageSettings(array $data): bool {
        $fields = [
            'landing_hero_title', 'landing_hero_subtitle', 'landing_hero_cta', 'landing_hero_cta_link',
            'landing_about_title', 'landing_about_description', 'landing_footer_text',
            'landing_primary_color', 'landing_show_testimonials'
        ];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $this->set($field, $data[$field]);
            }
        }
        return true;
    }

    public function getContactSettings(): array {
        return [
            'contact_phone' => $this->get('contact_phone', ''),
            'contact_phone_2' => $this->get('contact_phone_2', ''),
            'contact_email' => $this->get('contact_email', ''),
            'contact_email_support' => $this->get('contact_email_support', ''),
            'contact_address' => $this->get('contact_address', ''),
            'contact_city' => $this->get('contact_city', ''),
            'contact_country' => $this->get('contact_country', 'Kenya'),
            'contact_whatsapp' => $this->get('contact_whatsapp', ''),
            'social_facebook' => $this->get('social_facebook', ''),
            'social_twitter' => $this->get('social_twitter', ''),
            'social_instagram' => $this->get('social_instagram', ''),
            'social_linkedin' => $this->get('social_linkedin', ''),
            'social_youtube' => $this->get('social_youtube', ''),
            'social_tiktok' => $this->get('social_tiktok', ''),
            'map_embed_url' => $this->get('map_embed_url', ''),
            'working_days' => $this->get('working_days', 'Monday - Friday'),
            'working_hours' => $this->get('working_hours', '8:00 AM - 5:00 PM'),
            'support_hours' => $this->get('support_hours', '24/7'),
        ];
    }

    public function saveContactSettings(array $data): bool {
        $fields = [
            'contact_phone', 'contact_phone_2', 'contact_email', 'contact_email_support',
            'contact_address', 'contact_city', 'contact_country', 'contact_whatsapp',
            'social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin',
            'social_youtube', 'social_tiktok', 'map_embed_url',
            'working_days', 'working_hours', 'support_hours'
        ];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $this->set($field, $data[$field]);
            }
        }
        return true;
    }

    public function getBillingCycles(): array {
        return [
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'semi-annual' => 'Semi-Annual',
            'annual' => 'Annual'
        ];
    }

    public function getPackageIcons(): array {
        return [
            'wifi' => 'WiFi',
            'rocket' => 'Rocket',
            'lightning' => 'Lightning',
            'globe' => 'Globe',
            'building' => 'Building',
            'house' => 'House',
            'speedometer' => 'Speedometer',
            'star' => 'Star'
        ];
    }

    public function getMobileAppSettings(): array {
        return [
            'mobile_enabled' => $this->get('mobile_enabled', '1'),
            'mobile_salesperson_enabled' => $this->get('mobile_salesperson_enabled', '1'),
            'mobile_technician_enabled' => $this->get('mobile_technician_enabled', '1'),
            'mobile_token_expiry_days' => $this->get('mobile_token_expiry_days', '30'),
            'mobile_app_name' => $this->get('mobile_app_name', 'ISP Mobile'),
            'mobile_require_location' => $this->get('mobile_require_location', '0'),
            'mobile_allow_offline' => $this->get('mobile_allow_offline', '1'),
            'mobile_restrict_clockin_ip' => $this->get('mobile_restrict_clockin_ip', '0'),
            'mobile_allowed_ips' => $this->get('mobile_allowed_ips', ''),
        ];
    }

    public function saveMobileAppSettings(array $data): bool {
        $fields = [
            'mobile_enabled', 'mobile_salesperson_enabled', 'mobile_technician_enabled',
            'mobile_token_expiry_days', 'mobile_app_name', 'mobile_require_location', 'mobile_allow_offline',
            'mobile_restrict_clockin_ip', 'mobile_allowed_ips'
        ];
        
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $this->set($field, $data[$field]);
            }
        }
        return true;
    }
}
