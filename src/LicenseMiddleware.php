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
        
        $client = self::getClient();
        
        if (!$client->isEnabled()) {
            self::$validated = ['valid' => true, 'mode' => 'unlicensed'];
            return self::$validated;
        }
        
        self::$validated = $client->validate();
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
    
    public static function renderLicenseStatus(): string {
        $result = self::check();
        $client = self::getClient();
        
        if (!$client->isEnabled()) {
            return '';
        }
        
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
