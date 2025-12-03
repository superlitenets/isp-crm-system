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
            default:
                return null;
        }
    }
    
    public static function encryptPassword(string $password): string {
        $key = getenv('SESSION_SECRET') ?: 'default_encryption_key_change_me';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }
    
    public static function decryptPassword(string $encrypted): string {
        if (empty($encrypted)) return '';
        $key = getenv('SESSION_SECRET') ?: 'default_encryption_key_change_me';
        $parts = explode('::', base64_decode($encrypted), 2);
        if (count($parts) !== 2) return '';
        $iv = $parts[0];
        $encryptedData = $parts[1];
        return openssl_decrypt($encryptedData, 'AES-256-CBC', $key, 0, $iv) ?: '';
    }
}
