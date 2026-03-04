<?php

require_once __DIR__ . '/LicenseClient.php';

class LicenseMiddleware {
    private static $client = null;
    private static $validated = null;
    
    public static function getClient(): LicenseClient {
        if (self::$client === null) {
            self::$client = new LicenseClient();
        }
        return self::$client;
    }
    
    public static function check(): array {
        if (self::$validated !== null) {
            return self::$validated;
        }
        
        self::$validated = self::getClient()->validate();
        return self::$validated;
    }
    
    public static function isValid(): bool {
        $result = self::check();
        return $result['valid'] ?? false;
    }
    
    public static function hasFeature(string $feature): bool {
        if (!self::isValid()) return false;
        return self::getClient()->hasFeature($feature);
    }
    
    public static function getLimits(): array {
        return self::getClient()->getLimits();
    }
    
    public static function getLicenseInfo(): ?array {
        return self::getClient()->getLicenseInfo();
    }

    public static function getAppVersion(): string {
        return self::getClient()->getAppVersion();
    }

    public static function checkForUpdates(): ?array {
        return self::getClient()->checkForUpdates();
    }

    public static function getUpdateFromCache(): ?array {
        return self::getClient()->getUpdateFromCache();
    }
    
    public static function requireFeature(string $feature): void {
        if (!self::hasFeature($feature)) {
            http_response_code(403);
            echo self::renderUpgradeMessage($feature);
            exit;
        }
    }
    
    public static function enforceLimit(string $type, int $currentCount): bool {
        $limits = self::getLimits();
        $max = $limits["max_$type"] ?? 0;
        if ($max === 0) return true;
        return $currentCount < $max;
    }

    public static function enforce(): void {
        $result = self::check();
        if ($result['valid'] ?? false) {
            return;
        }

        $mode = $result['mode'] ?? '';
        $error = $result['error'] ?? '';
        $message = $result['message'] ?? 'License validation failed';

        echo self::renderLicenseActivationPage($mode, $error, $message);
        exit;
    }
    
    public static function renderLicenseStatus(): string {
        $result = self::check();
        
        $html = '';
        
        if (!$result['valid']) {
            $message = $result['message'] ?? 'License validation failed';
            $html .= '<div class="alert alert-danger m-3">
                <i class="bi bi-shield-exclamation me-2"></i>
                <strong>License Error:</strong> ' . htmlspecialchars($message) . '
                <a href="?page=settings&section=license" class="alert-link ms-2">Configure License</a>
            </div>';
        } elseif (!empty($result['grace_mode'])) {
            $html .= '<div class="alert alert-warning m-3">
                <i class="bi bi-clock me-2"></i>
                <strong>Offline Mode:</strong> Cannot connect to license server. Running in grace period.
            </div>';
        }

        $update = $result['update_available'] ?? null;
        if ($update) {
            $critical = !empty($update['is_critical']) ? ' <span class="badge bg-danger">Critical</span>' : '';
            $html .= '<div class="alert alert-info m-3 d-flex align-items-center justify-content-between">
                <div>
                    <i class="bi bi-cloud-arrow-down me-2"></i>
                    <strong>Update Available:</strong> v' . htmlspecialchars($update['version']) . ' — ' . htmlspecialchars($update['title']) . $critical . '
                </div>
                <a href="?page=settings&section=license" class="btn btn-sm btn-info">View Details</a>
            </div>';
        }
        
        return $html;
    }

    private static function renderLicenseActivationPage(string $mode, string $error, string $message): string {
        $version = LicenseClient::APP_VERSION;
        $isUnconfigured = ($mode === 'unconfigured');
        $isExpired = ($error === 'expired');
        $isSuspended = ($error === 'suspended');

        if ($isUnconfigured) {
            $icon = 'bi-shield-lock';
            $iconColor = 'text-primary';
            $title = 'License Required';
            $subtitle = 'This installation requires a valid license to operate.';
        } elseif ($isExpired) {
            $icon = 'bi-clock-history';
            $iconColor = 'text-warning';
            $title = 'License Expired';
            $subtitle = 'Your license has expired. Please renew to continue.';
        } elseif ($isSuspended) {
            $icon = 'bi-shield-x';
            $iconColor = 'text-danger';
            $title = 'License Suspended';
            $subtitle = htmlspecialchars($message);
        } else {
            $icon = 'bi-exclamation-triangle';
            $iconColor = 'text-danger';
            $title = 'License Error';
            $subtitle = htmlspecialchars($message);
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . ' - ISP CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0d1b2a 0%, #1b263b 50%, #415a77 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .license-card { max-width: 520px; width: 100%; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .license-header { background: linear-gradient(135deg, #1a1a2e, #16213e); padding: 3rem 2rem 2rem; text-align: center; }
        .license-icon { font-size: 4rem; margin-bottom: 1rem; }
        .version-badge { position: absolute; top: 1rem; right: 1rem; font-size: 0.75rem; }
    </style>
</head>
<body>
    <div class="license-card card border-0 position-relative">
        <span class="version-badge badge bg-secondary">v' . htmlspecialchars($version) . '</span>
        <div class="license-header text-white">
            <i class="bi ' . $icon . ' license-icon ' . $iconColor . '"></i>
            <h2 class="fw-bold mb-2">' . $title . '</h2>
            <p class="text-white-50 mb-0">' . $subtitle . '</p>
        </div>
        <div class="card-body p-4">
            ' . ($isUnconfigured ? '
            <div class="mb-4">
                <div class="d-flex align-items-start mb-3">
                    <span class="badge bg-primary rounded-circle me-3 p-2"><i class="bi bi-1-circle-fill"></i></span>
                    <div>
                        <strong>Get a License Key</strong>
                        <p class="text-muted small mb-0">Contact your administrator or purchase a license from the license portal.</p>
                    </div>
                </div>
                <div class="d-flex align-items-start mb-3">
                    <span class="badge bg-primary rounded-circle me-3 p-2"><i class="bi bi-2-circle-fill"></i></span>
                    <div>
                        <strong>Configure License</strong>
                        <p class="text-muted small mb-0">Go to Settings and enter the License Server URL and your License Key.</p>
                    </div>
                </div>
                <div class="d-flex align-items-start">
                    <span class="badge bg-primary rounded-circle me-3 p-2"><i class="bi bi-3-circle-fill"></i></span>
                    <div>
                        <strong>Activate</strong>
                        <p class="text-muted small mb-0">Click Save & Activate to register this installation.</p>
                    </div>
                </div>
            </div>
            ' : '
            <div class="alert alert-' . ($isExpired ? 'warning' : 'danger') . ' mb-4">
                <i class="bi bi-info-circle me-2"></i>' . htmlspecialchars($message) . '
            </div>
            ') . '
            <a href="?page=settings&section=license" class="btn btn-primary btn-lg w-100 mb-3">
                <i class="bi bi-gear me-2"></i>Go to License Settings
            </a>
            <div class="text-center">
                <a href="?page=logout" class="text-muted small"><i class="bi bi-box-arrow-right me-1"></i>Sign Out</a>
            </div>
        </div>
        <div class="card-footer bg-light text-center py-3">
            <small class="text-muted">ISP CRM v' . htmlspecialchars($version) . ' &mdash; A valid license is required to use this application.</small>
        </div>
    </div>
</body>
</html>';
    }
    
    private static function renderUpgradeMessage(string $feature): string {
        return '<!DOCTYPE html>
        <html>
        <head>
            <title>Feature Unavailable</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
        </head>
        <body class="bg-light">
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card shadow">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-lock-fill text-warning" style="font-size: 4rem;"></i>
                                <h3 class="mt-4">Feature Not Available</h3>
                                <p class="text-muted">The <strong>' . htmlspecialchars($feature) . '</strong> feature is not included in your current license tier.</p>
                                <p>Please upgrade your license to access this feature.</p>
                                <a href="/" class="btn btn-primary">Go to Dashboard</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
}
