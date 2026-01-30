<?php
// Temporary debug file - delete after use
header('Content-Type: application/json');
echo json_encode([
    'getenv' => getenv('ENCRYPTION_KEY') ? 'SET (' . strlen(getenv('ENCRYPTION_KEY')) . ' chars)' : 'NOT SET',
    '_ENV' => isset($_ENV['ENCRYPTION_KEY']) ? 'SET (' . strlen($_ENV['ENCRYPTION_KEY']) . ' chars)' : 'NOT SET',
    '_SERVER' => isset($_SERVER['ENCRYPTION_KEY']) ? 'SET' : 'NOT SET',
    'php_sapi' => php_sapi_name()
]);
