<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/hotspot/([0-9.:]+)(?:/([0-9a-fA-F:.-]+))?/?$#', $uri, $matches)) {
    $_GET['nas'] = $matches[1];
    $qs = 'nas=' . $matches[1];
    if (!empty($matches[2])) {
        $_GET['mac'] = $matches[2];
        $qs .= '&mac=' . $matches[2];
    }
    $_SERVER['QUERY_STRING'] = $qs . (empty($_SERVER['QUERY_STRING']) ? '' : '&' . $_SERVER['QUERY_STRING']);
    require __DIR__ . '/hotspot.php';
    return true;
}

if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

require __DIR__ . '/index.php';
return true;
