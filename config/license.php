<?php
/**
 * License Configuration
 * 
 * Priority: Database settings > Environment variables > Defaults
 * Set LICENSE_SERVER_URL or configure via Settings > License in the CRM.
 */

$dbServerUrl = '';
$dbLicenseKey = '';

try {
    if (class_exists('Database', false)) {
        $db = \Database::getConnection();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('license_server_url', 'license_key')");
        $stmt->execute();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'license_server_url' && !empty($row['setting_value'])) {
                $dbServerUrl = $row['setting_value'];
            }
            if ($row['setting_key'] === 'license_key' && !empty($row['setting_value'])) {
                $dbLicenseKey = $row['setting_value'];
            }
        }
    }
} catch (\Throwable $e) {
}

$serverUrl = $dbServerUrl ?: (getenv('LICENSE_SERVER_URL') ?: '');
$licenseKey = $dbLicenseKey ?: (getenv('LICENSE_KEY') ?: '');

return [
    'enabled' => !empty($serverUrl),
    
    'server_url' => $serverUrl,
    
    'license_key' => $licenseKey,
    
    'grace_period_days' => 7,
    
    'check_interval_hours' => 24,
    
    'cache_file' => __DIR__ . '/../storage/license_cache.json',
    
    'features' => [
        'crm' => true,
        'tickets' => true,
        'oms' => true,
        'hr' => true,
        'inventory' => true,
        'accounting' => true,
        'whitelabel' => false,
    ],
];
