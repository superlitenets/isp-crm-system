<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('X-Frame-Options: SAMEORIGIN');

if (!isset($_SESSION['portal_subscription_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$db = Database::getConnection();
$subscriptionId = $_SESSION['portal_subscription_id'];

$stmt = $db->prepare("
    SELECT rs.customer_id, o.ip_address 
    FROM radius_subscriptions rs 
    JOIN huawei_onus o ON o.customer_id = rs.customer_id 
    WHERE rs.id = ? AND o.ip_address IS NOT NULL
    ORDER BY o.id DESC LIMIT 1
");
$stmt->execute([$subscriptionId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data || empty($data['ip_address'])) {
    http_response_code(404);
    die('Router not found');
}

$routerIp = $data['ip_address'];
$requestPath = $_GET['path'] ?? '/';
$requestPath = '/' . ltrim($requestPath, '/');

$routerUrl = 'http://' . $routerIp . $requestPath;

if (!empty($_SERVER['QUERY_STRING'])) {
    $queryString = preg_replace('/^path=[^&]*&?/', '', $_SERVER['QUERY_STRING']);
    if (!empty($queryString)) {
        $routerUrl .= '?' . $queryString;
    }
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $routerUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_HEADER, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
}

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0 && $key !== 'HTTP_HOST') {
        $headerName = str_replace('_', '-', substr($key, 5));
        if (!in_array($headerName, ['CONNECTION', 'ACCEPT-ENCODING'])) {
            $headers[] = $headerName . ': ' . $value;
        }
    }
}
if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Router Unreachable</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
            .error-box { background: white; padding: 40px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h2 { color: #e74c3c; }
            p { color: #666; }
            .ip { font-family: monospace; background: #f0f0f0; padding: 5px 10px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h2>Router Unreachable</h2>
            <p>Unable to connect to your router at <span class="ip"><?= htmlspecialchars($routerIp) ?></span></p>
            <p>Please check that your router is powered on and connected to the network.</p>
            <p><small>Error: <?= htmlspecialchars($error) ?></small></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$responseHeaders = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

http_response_code($httpCode);

$headerLines = explode("\r\n", $responseHeaders);
foreach ($headerLines as $headerLine) {
    if (empty($headerLine)) continue;
    if (stripos($headerLine, 'HTTP/') === 0) continue;
    if (stripos($headerLine, 'Transfer-Encoding:') === 0) continue;
    if (stripos($headerLine, 'Content-Length:') === 0) continue;
    if (stripos($headerLine, 'Connection:') === 0) continue;
    if (stripos($headerLine, 'X-Frame-Options:') === 0) continue;
    
    if (stripos($headerLine, 'Content-Type:') === 0) {
        header($headerLine);
    }
}

$contentType = '';
foreach ($headerLines as $line) {
    if (stripos($line, 'Content-Type:') === 0) {
        $contentType = strtolower(trim(substr($line, 13)));
        break;
    }
}

if (strpos($contentType, 'text/html') !== false) {
    $baseUrl = '/portal/router-proxy.php?path=';
    
    $body = preg_replace_callback(
        '/(href|src|action)=["\'](?!(?:https?:|javascript:|data:|#|mailto:))([^"\']*)["\']/',
        function($matches) use ($baseUrl, $routerIp) {
            $attr = $matches[1];
            $url = $matches[2];
            
            if (strpos($url, '/') === 0) {
                $newUrl = $baseUrl . urlencode($url);
            } else {
                $newUrl = $baseUrl . urlencode('/' . $url);
            }
            
            return $attr . '="' . $newUrl . '"';
        },
        $body
    );
    
    $body = preg_replace_callback(
        '/url\(["\']?(?!(?:https?:|data:))([^"\'\)]+)["\']?\)/',
        function($matches) use ($baseUrl) {
            $url = $matches[1];
            if (strpos($url, '/') === 0) {
                return 'url("' . $baseUrl . urlencode($url) . '")';
            }
            return 'url("' . $baseUrl . urlencode('/' . $url) . '")';
        },
        $body
    );
}

echo $body;
