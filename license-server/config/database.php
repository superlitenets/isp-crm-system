<?php
/**
 * License Server Configuration
 * 
 * IMPORTANT: Set these environment variables on your license server:
 * - LICENSE_DB_HOST, LICENSE_DB_PORT, LICENSE_DB_NAME, LICENSE_DB_USER, LICENSE_DB_PASSWORD
 * - LICENSE_JWT_SECRET (use a long random string)
 * - LICENSE_ADMIN_PASSWORD (use a strong password)
 * 
 * Never commit real credentials to version control!
 */

$jwtSecret = getenv('LICENSE_JWT_SECRET');
$adminPassword = getenv('LICENSE_ADMIN_PASSWORD');

if (empty($jwtSecret) || $jwtSecret === 'change-this-in-production') {
    error_log('WARNING: LICENSE_JWT_SECRET not set or using default. Set a secure secret in production!');
}
if (empty($adminPassword) || $adminPassword === 'change-this-in-production') {
    error_log('WARNING: LICENSE_ADMIN_PASSWORD not set or using default. Set a secure password in production!');
}

return [
    'host' => getenv('LICENSE_DB_HOST') ?: getenv('PGHOST') ?: 'localhost',
    'port' => getenv('LICENSE_DB_PORT') ?: getenv('PGPORT') ?: '5432',
    'database' => getenv('LICENSE_DB_NAME') ?: getenv('PGDATABASE') ?: 'license_server',
    'username' => getenv('LICENSE_DB_USER') ?: getenv('PGUSER') ?: 'postgres',
    'password' => getenv('LICENSE_DB_PASSWORD') ?: getenv('PGPASSWORD') ?: '',
    
    'jwt_secret' => $jwtSecret ?: 'change-this-in-production',
    'admin_password' => $adminPassword ?: 'change-this-in-production',
];
