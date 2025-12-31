<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$target = $_GET['url'] ?? '';
if (empty($target)) {
    http_response_code(400);
    exit('Missing URL parameter');
}

if (!preg_match('/^https?:\/\/[\d\.]+/', $target) && !preg_match('/^https?:\/\/10\./', $target) && !preg_match('/^https?:\/\/192\.168\./', $target) && !preg_match('/^https?:\/\/172\.(1[6-9]|2[0-9]|3[01])\./', $target)) {
    http_response_code(403);
    exit('Only private IP addresses allowed');
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $target);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
}

$headers = [];
foreach (getallheaders() as $name => $value) {
    if (in_array(strtolower($name), ['content-type', 'accept', 'authorization'])) {
        $headers[] = "$name: $value";
    }
}
if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

$response = curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    http_response_code(502);
    echo 'Proxy Error: ' . curl_error($ch);
    curl_close($ch);
    exit;
}

curl_close($ch);

$parsedUrl = parse_url($target);
$baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
if (isset($parsedUrl['port'])) {
    $baseUrl .= ':' . $parsedUrl['port'];
}

if (strpos($contentType, 'text/html') !== false) {
    $proxyBase = '/router-proxy.php?url=' . urlencode($baseUrl);
    
    $response = preg_replace('/(href|src|action)=["\'](?!https?:\/\/|\/\/|#|javascript:|data:)([^"\']+)["\']/i', '$1="' . $proxyBase . '/$2"', $response);
    
    $response = preg_replace('/(href|src|action)=["\']\/([^"\']+)["\']/i', '$1="' . $proxyBase . '/$2"', $response);
}

http_response_code($httpCode);
if ($contentType) {
    header('Content-Type: ' . $contentType);
}
header('X-Frame-Options: SAMEORIGIN');
echo $response;
