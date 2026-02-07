<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Hotspot portal routing: /hotspot/{nas_ip}/{mac} or /hotspot/{nas_ip}
// MAC segment accepts actual MACs (AA:BB:CC:DD:EE:FF) and MikroTik variables like $(mac)
if (preg_match('#^/hotspot/([0-9.:]+)(?:/([^/?]+))?/?$#', $uri, $matches)) {
    $_GET['nas'] = $matches[1];
    $qs = 'nas=' . $matches[1];
    if (!empty($matches[2])) {
        $_GET['mac'] = $matches[2];
        $qs .= '&mac=' . $matches[2];
    }
    if (!empty($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $existingParams);
        foreach ($existingParams as $k => $v) {
            $_GET[$k] = $v;
        }
        $qs .= '&' . $_SERVER['QUERY_STRING'];
    }
    $_SERVER['QUERY_STRING'] = $qs;
    require __DIR__ . '/hotspot.php';
    return true;
}

if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

require __DIR__ . '/index.php';
return true;
