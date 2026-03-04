<?php

class UpdateManager {
    private $storageDir;
    private $projectRoot;
    private $lockFile;
    private $historyFile;
    
    const LOCK_TIMEOUT = 600;
    
    public function __construct() {
        $this->projectRoot = realpath(__DIR__ . '/..');
        $this->storageDir = $this->projectRoot . '/storage';
        $this->lockFile = $this->storageDir . '/update_lock.json';
        $this->historyFile = $this->storageDir . '/update_history.json';
        
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }
    
    public function isRemoteUpdateAllowed(): bool {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT setting_value FROM company_settings WHERE setting_key = 'allow_remote_updates'");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return $val !== '0';
        } catch (\Throwable $e) {
            return true;
        }
    }
    
    public function isLocked(): bool {
        if (!file_exists($this->lockFile)) return false;
        $lock = json_decode(file_get_contents($this->lockFile), true);
        if (!$lock) return false;
        if (time() - ($lock['started_at'] ?? 0) > self::LOCK_TIMEOUT) {
            @unlink($this->lockFile);
            return false;
        }
        return true;
    }
    
    private function acquireLock(string $updateVersion): bool {
        if ($this->isLocked()) return false;
        file_put_contents($this->lockFile, json_encode([
            'version' => $updateVersion,
            'started_at' => time(),
            'pid' => getmypid()
        ]));
        return true;
    }
    
    private function releaseLock(): void {
        @unlink($this->lockFile);
    }
    
    public function applyUpdate(array $update): array {
        $version = $update['version'] ?? '';
        $downloadUrl = $update['download_url'] ?? '';
        $expectedHash = $update['download_hash'] ?? '';
        $updateId = $update['id'] ?? 0;
        
        if (empty($downloadUrl)) {
            return ['success' => false, 'error' => 'No download URL provided'];
        }
        
        if (!$this->acquireLock($version)) {
            return ['success' => false, 'error' => 'Update already in progress'];
        }
        
        $result = ['success' => false, 'error' => ''];
        $tempDir = sys_get_temp_dir() . '/isp_crm_update_' . time();
        $zipFile = $tempDir . '/update.zip';
        
        try {
            @mkdir($tempDir, 0755, true);
            
            $this->logHistory('started', $version, "Downloading update v{$version}");
            
            $ch = curl_init($downloadUrl);
            $fp = fopen($zipFile, 'w');
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            $curlResult = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            
            if (!$curlResult || $httpCode !== 200) {
                throw new \Exception("Download failed: HTTP {$httpCode} - {$curlError}");
            }
            
            if (!file_exists($zipFile) || filesize($zipFile) < 1000) {
                throw new \Exception("Downloaded file is too small or missing");
            }
            
            if (!empty($expectedHash)) {
                $actualHash = hash_file('sha256', $zipFile);
                if ($actualHash !== $expectedHash) {
                    throw new \Exception("Hash mismatch: expected {$expectedHash}, got {$actualHash}");
                }
            }
            
            $this->logHistory('progress', $version, "Creating backup");
            $backupFile = $this->createBackup();
            
            $this->logHistory('progress', $version, "Extracting update");
            $extractDir = $tempDir . '/extracted';
            @mkdir($extractDir, 0755, true);
            
            $zip = new \ZipArchive();
            if ($zip->open($zipFile) !== true) {
                throw new \Exception("Failed to open update package");
            }
            $zip->extractTo($extractDir);
            $zip->close();
            
            $sourceDir = $extractDir;
            $items = scandir($extractDir);
            $dirs = array_filter($items, function($item) use ($extractDir) {
                return $item !== '.' && $item !== '..' && is_dir($extractDir . '/' . $item);
            });
            if (count($dirs) === 1 && count($items) <= 3) {
                $sourceDir = $extractDir . '/' . reset($dirs);
            }
            
            $this->logHistory('progress', $version, "Applying files");
            $this->copyFiles($sourceDir, $this->projectRoot);
            
            $migrationFile = $this->projectRoot . '/database/fix_missing_columns.sql';
            if (file_exists($migrationFile)) {
                $this->logHistory('progress', $version, "Running database migrations");
                $this->runMigrations($migrationFile);
            }
            
            $this->logHistory('progress', $version, "Restarting services");
            $this->restartServices();
            
            $this->logHistory('completed', $version, "Update to v{$version} completed successfully", $backupFile);
            
            $this->reportToLicenseServer($updateId, $version, 'completed');
            
            $result = ['success' => true, 'message' => "Updated to v{$version} successfully", 'backup' => $backupFile];
            
        } catch (\Exception $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
            $this->logHistory('failed', $version, $e->getMessage());
            $this->reportToLicenseServer($updateId, $version, 'failed', $e->getMessage());
        } finally {
            $this->releaseLock();
            @$this->removeDir($tempDir);
        }
        
        return $result;
    }
    
    private function createBackup(): string {
        $backupDir = $this->storageDir . '/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . '/backup_' . date('Y-m-d_His') . '.tar.gz';
        
        $excludes = '--exclude=storage --exclude=node_modules --exclude=vendor --exclude=.git --exclude=*.tar.gz';
        $cmd = "cd " . escapeshellarg(dirname($this->projectRoot)) . " && tar czf " . escapeshellarg($backupFile) . " {$excludes} " . escapeshellarg(basename($this->projectRoot)) . " 2>/dev/null";
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($backupFile)) {
            $cmd2 = "cd " . escapeshellarg($this->projectRoot) . " && tar czf " . escapeshellarg($backupFile) . " {$excludes} . 2>/dev/null";
            exec($cmd2);
        }
        
        $oldBackups = glob($backupDir . '/backup_*.tar.gz');
        if (count($oldBackups) > 5) {
            usort($oldBackups, function($a, $b) { return filemtime($a) - filemtime($b); });
            for ($i = 0; $i < count($oldBackups) - 5; $i++) {
                @unlink($oldBackups[$i]);
            }
        }
        
        return $backupFile;
    }
    
    private function copyFiles(string $source, string $dest): void {
        $protectedPaths = [
            'storage', 'node_modules', 'vendor', '.git', '.env',
            'config/database.php', 'storage/license_cache.json',
            'storage/activation_token'
        ];
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            
            $skip = false;
            foreach ($protectedPaths as $protected) {
                if (strpos($relativePath, $protected) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;
            
            $targetPath = $dest . '/' . $relativePath;
            
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    @mkdir($targetPath, 0755, true);
                }
            } else {
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }
                @copy($item->getPathname(), $targetPath);
            }
        }
    }
    
    private function runMigrations(string $sqlFile): void {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = \Database::getConnection();
            $sql = file_get_contents($sqlFile);
            $db->exec($sql);
        } catch (\Throwable $e) {
            error_log("Migration warning: " . $e->getMessage());
        }
    }
    
    private function restartServices(): void {
        $services = ['isp-crm', 'php-fpm', 'php8.2-fpm', 'isp-olt', 'isp-whatsapp', 'isp-snmp'];
        foreach ($services as $service) {
            @exec("systemctl restart {$service} 2>/dev/null");
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }
    
    private function reportToLicenseServer(int $updateId, string $version, string $status, ?string $error = null): void {
        if ($updateId <= 0) return;
        
        try {
            require_once __DIR__ . '/LicenseClient.php';
            $client = new \LicenseClient();
            $client->reportUpdateResult($updateId, \LicenseClient::APP_VERSION, $version, $status, $error);
        } catch (\Throwable $e) {
        }
    }
    
    public function logHistory(string $status, string $version, string $message, ?string $backupFile = null): void {
        $history = $this->getHistory();
        
        if ($status === 'started' || $status === 'completed' || $status === 'failed') {
            $entry = [
                'version' => $version,
                'status' => $status,
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s'),
                'backup_file' => $backupFile
            ];
            array_unshift($history, $entry);
            $history = array_slice($history, 0, 50);
        }
        
        file_put_contents($this->historyFile, json_encode($history, JSON_PRETTY_PRINT));
    }
    
    public function getHistory(): array {
        if (!file_exists($this->historyFile)) return [];
        $data = json_decode(file_get_contents($this->historyFile), true);
        return is_array($data) ? $data : [];
    }
    
    private function removeDir(string $dir): void {
        if (!is_dir($dir)) return;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }
        @rmdir($dir);
    }
    
    public function validateActivationToken(string $token): bool {
        $storedToken = $this->getStoredActivationToken();
        return !empty($storedToken) && hash_equals($storedToken, $token);
    }
    
    private function getStoredActivationToken(): ?string {
        $tokenFile = $this->storageDir . '/activation_token';
        if (file_exists($tokenFile)) {
            return trim(file_get_contents($tokenFile));
        }
        $cacheFile = $this->storageDir . '/license_cache.json';
        if (file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), true);
            return $cache['activation_token'] ?? null;
        }
        return null;
    }
}
