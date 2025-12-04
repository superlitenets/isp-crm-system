<?php
$file = __DIR__ . '/downloads/isp-crm-docker.zip';

if (!file_exists($file)) {
    http_response_code(404);
    die('File not found');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="isp-crm-docker.zip"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

readfile($file);
exit;
