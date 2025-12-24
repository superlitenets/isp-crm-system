<?php
/**
 * License Configuration
 * 
 * Set LICENSE_SERVER_URL to enable license validation.
 * When disabled, the CRM runs without license restrictions.
 */
return [
    'enabled' => !empty(getenv('LICENSE_SERVER_URL')),
    
    'server_url' => getenv('LICENSE_SERVER_URL') ?: '',
    
    'license_key' => getenv('LICENSE_KEY') ?: '',
    
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
