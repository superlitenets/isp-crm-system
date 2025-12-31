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

if (!preg_match('/^https?:\/\/(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $target)) {
    http_response_code(403);
    exit('Only private IP addresses allowed');
}

$parsedUrl = parse_url($target);
$baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
if (isset($parsedUrl['port'])) {
    $baseUrl .= ':' . $parsedUrl['port'];
}
$basePath = isset($parsedUrl['path']) ? dirname($parsedUrl['path']) : '';
if ($basePath === '/' || $basePath === '.') $basePath = '';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $target);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_HEADER, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    $postData = file_get_contents('php://input');
    if (empty($postData) && !empty($_POST)) {
        $postData = http_build_query($_POST);
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
}

$headers = [];
foreach (getallheaders() as $name => $value) {
    $lowerName = strtolower($name);
    if (in_array($lowerName, ['content-type', 'accept', 'authorization', 'cookie'])) {
        $headers[] = "$name: $value";
    }
}
if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'text/html';

if (curl_errno($ch)) {
    http_response_code(502);
    echo 'Proxy Error: ' . curl_error($ch);
    curl_close($ch);
    exit;
}

curl_close($ch);

$responseHeaders = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

foreach (explode("\r\n", $responseHeaders) as $header) {
    if (stripos($header, 'Set-Cookie:') === 0) {
        header($header, false);
    }
}

function makeProxyUrl($url, $baseUrl, $basePath) {
    if (empty($url) || strpos($url, 'data:') === 0 || strpos($url, 'javascript:') === 0 || $url === '#') {
        return $url;
    }
    
    if (strpos($url, '//') === 0) {
        $url = 'http:' . $url;
    }
    
    if (preg_match('/^https?:\/\//', $url)) {
        return '/router-proxy.php?url=' . urlencode($url);
    }
    
    if (strpos($url, '/') === 0) {
        return '/router-proxy.php?url=' . urlencode($baseUrl . $url);
    }
    
    return '/router-proxy.php?url=' . urlencode($baseUrl . $basePath . '/' . $url);
}

if (strpos($contentType, 'text/html') !== false) {
    $body = preg_replace_callback(
        '/(href|src|action)=["\']([^"\']*?)["\']/i',
        function($matches) use ($baseUrl, $basePath) {
            $attr = $matches[1];
            $url = $matches[2];
            $newUrl = makeProxyUrl($url, $baseUrl, $basePath);
            return $attr . '="' . htmlspecialchars($newUrl) . '"';
        },
        $body
    );
    
    $body = preg_replace_callback(
        '/url\(["\']?([^"\'\)]+)["\']?\)/i',
        function($matches) use ($baseUrl, $basePath) {
            $url = $matches[1];
            $newUrl = makeProxyUrl($url, $baseUrl, $basePath);
            return 'url("' . $newUrl . '")';
        },
        $body
    );
    
    $body = preg_replace_callback(
        '/<form([^>]*)>/i',
        function($matches) use ($target) {
            $attrs = $matches[1];
            if (stripos($attrs, 'action=') === false) {
                return '<form' . $attrs . ' action="/router-proxy.php?url=' . urlencode($target) . '">';
            }
            return $matches[0];
        },
        $body
    );
    
    $proxyScript = '<script>
(function(){
    var baseUrl = ' . json_encode($baseUrl) . ';
    var basePath = ' . json_encode($basePath) . ';
    
    function proxyUrl(url) {
        if (!url || url.startsWith("data:") || url.startsWith("javascript:") || url === "#") return url;
        if (url.startsWith("/router-proxy.php")) return url;
        if (url.startsWith("//")) url = "http:" + url;
        if (/^https?:\/\//.test(url)) return "/router-proxy.php?url=" + encodeURIComponent(url);
        if (url.startsWith("/")) return "/router-proxy.php?url=" + encodeURIComponent(baseUrl + url);
        return "/router-proxy.php?url=" + encodeURIComponent(baseUrl + basePath + "/" + url);
    }
    
    var origXHROpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function(method, url, async, user, pass) {
        return origXHROpen.call(this, method, proxyUrl(url), async !== false, user, pass);
    };
    
    if (window.fetch) {
        var origFetch = window.fetch;
        window.fetch = function(input, init) {
            if (typeof input === "string") {
                input = proxyUrl(input);
            } else if (input && input.url) {
                input = new Request(proxyUrl(input.url), input);
            }
            return origFetch.call(this, input, init);
        };
    }
    
    var origImage = window.Image;
    window.Image = function(w, h) {
        var img = new origImage(w, h);
        var origSrc = Object.getOwnPropertyDescriptor(HTMLImageElement.prototype, "src");
        Object.defineProperty(img, "src", {
            set: function(v) { origSrc.set.call(this, proxyUrl(v)); },
            get: function() { return origSrc.get.call(this); }
        });
        return img;
    };
})();
</script>';
    
    $body = preg_replace('/(<head[^>]*>)/i', '$1' . $proxyScript, $body, 1);
    if (strpos($body, $proxyScript) === false) {
        $body = $proxyScript . $body;
    }
}

if (strpos($contentType, 'text/css') !== false) {
    $body = preg_replace_callback(
        '/url\(["\']?([^"\'\)]+)["\']?\)/i',
        function($matches) use ($baseUrl, $basePath) {
            $url = $matches[1];
            $newUrl = makeProxyUrl($url, $baseUrl, $basePath);
            return 'url("' . $newUrl . '")';
        },
        $body
    );
}

http_response_code($httpCode);
header('Content-Type: ' . $contentType);
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo $body;
