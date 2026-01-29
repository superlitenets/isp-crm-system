<?php
/**
 * Public Ticket Wallboard
 * Accessible without authentication at /wallboard.php
 */

date_default_timezone_set('Africa/Nairobi');

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

// Apply timezone from settings
try {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT setting_value FROM company_settings WHERE setting_key = 'timezone'");
    $stmt->execute();
    $tz = $stmt->fetchColumn();
    if ($tz && in_array($tz, timezone_identifiers_list())) {
        date_default_timezone_set($tz);
    }
} catch (Exception $e) {
    // Use default timezone
}

// Include the wallboard template
include __DIR__ . '/../templates/ticket_wallboard.php';
