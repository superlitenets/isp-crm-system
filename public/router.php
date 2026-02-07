<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/hotspot/([0-9.:]+)/?$#', $uri, $matches)) {
    $_GET['nas'] = $matches[1];
    $_SERVER['QUERY_STRING'] = 'nas=' . $matches[1] . (empty($_SERVER['QUERY_STRING']) ? '' : '&' . $_SERVER['QUERY_STRING']);
    require __DIR__ . '/hotspot.php';
    return true;
}

if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

require __DIR__ . '/index.php';
return true;
