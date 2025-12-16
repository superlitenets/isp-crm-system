<?php

namespace App;

abstract class BiometricDevice {
    protected int $deviceId;
    protected string $ip;
    protected int $port;
    protected ?string $username;
    protected ?string $password;
    protected $connection = null;
    protected array $lastError = [];
    
    public function __construct(int $deviceId, string $ip, int $port = 4370, ?string $username = null, ?string $password = null) {
        $this->deviceId = $deviceId;
        $this->ip = $ip;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }
    
    abstract public function connect(): bool;
    
    abstract public function disconnect(): void;
    
    abstract public function testConnection(): array;
    
    abstract public function getAttendance(?string $since = null, ?string $until = null): array;
    
    abstract public function getUsers(): array;
    
    public function getLastError(): array {
        return $this->lastError;
    }
    
    protected function setError(string $message, int $code = 0): void {
        $this->lastError = [
            'message' => $message,
            'code' => $code,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    public static function create(array $deviceConfig): ?BiometricDevice {
        $type = $deviceConfig['device_type'] ?? '';
        $id = $deviceConfig['id'] ?? 0;
        $ip = $deviceConfig['ip_address'] ?? '';
        $port = $deviceConfig['port'] ?? 4370;
        $username = $deviceConfig['username'] ?? null;
        $password = isset($deviceConfig['password_encrypted']) ? 
            self::decryptPassword($deviceConfig['password_encrypted']) : null;
        
        switch ($type) {
            case 'zkteco':
                return new ZKTecoDevice($id, $ip, $port, $username, $password);
            case 'hikvision':
                return new HikvisionDevice($id, $ip, $port, $username, $password);
            case 'biotime_cloud':
                $device = new BioTimeCloud($id, $ip, $port ?: 8090, $username, $password);
                if (!empty($deviceConfig['api_base_url'])) {
                    $device->setBaseUrl($deviceConfig['api_base_url']);
                }
                return $device;
            default:
                return null;
        }
    }
    
    public static function encryptPassword(string $password): string {
        if (empty($password)) return '';
        
        $secret = getenv('SESSION_SECRET') ?: 'default_encryption_key_change_me';
        $key = hash('sha256', $secret, true);
        $iv = openssl_random_pseudo_bytes(16);
        
        if ($iv === false) {
            error_log('Failed to generate IV for encryption');
            return base64_encode($password);
        }
        
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            error_log('OpenSSL encrypt failed: ' . openssl_error_string());
            return base64_encode($password);
        }
        
        return base64_encode($iv . $encrypted);
    }
    
    public static function decryptPassword(string $encrypted): string {
        if (empty($encrypted)) return '';
        
        // Try base64 decode - if it fails or data is too short, treat as plain text
        $data = base64_decode($encrypted, true);
        if ($data === false || strlen($data) < 17) {
            // Not valid encrypted format, return as plain text
            return $encrypted;
        }
        
        $secret = getenv('SESSION_SECRET') ?: 'default_encryption_key_change_me';
        $key = hash('sha256', $secret, true);
        
        $iv = substr($data, 0, 16);
        $encryptedData = substr($data, 16);
        
        $decrypted = openssl_decrypt($encryptedData, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted === false) {
            // Decryption failed - likely plain text or wrong key, return original
            error_log('Decryption failed, using password as-is');
            return $encrypted;
        }
        
        return $decrypted;
    }
}
