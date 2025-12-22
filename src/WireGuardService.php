<?php

namespace App;

use PDO;
use PDOException;

class WireGuardService {
    private PDO $db;
    private string $encryptionKey;
    
    public function __construct(PDO $db) {
        $this->db = $db;
        $this->encryptionKey = getenv('SESSION_SECRET') ?: 'default-wireguard-key-2025';
    }
    
    private function encrypt(string $data): string {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    private function decrypt(string $data): string {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
    }
    
    public function getSettings(): array {
        $settings = [];
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value FROM wireguard_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            error_log("WireGuard getSettings error: " . $e->getMessage());
        }
        
        return array_merge([
            'vpn_enabled' => 'false',
            'tr069_use_vpn_gateway' => 'false',
            'tr069_acs_url' => 'http://localhost:7547',
            'vpn_gateway_ip' => '10.200.0.1',
            'vpn_network' => '10.200.0.0/24'
        ], $settings);
    }
    
    public function updateSetting(string $key, string $value): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO wireguard_settings (setting_key, setting_value, updated_at) 
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (setting_key) DO UPDATE SET 
                    setting_value = EXCLUDED.setting_value,
                    updated_at = CURRENT_TIMESTAMP
            ");
            return $stmt->execute([$key, $value]);
        } catch (PDOException $e) {
            error_log("WireGuard updateSetting error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateSettings(array $settings): bool {
        foreach ($settings as $key => $value) {
            if (!$this->updateSetting($key, $value)) {
                return false;
            }
        }
        return true;
    }
    
    public function getServers(): array {
        try {
            $stmt = $this->db->query("SELECT * FROM wireguard_servers ORDER BY name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("WireGuard getServers error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getServer(int $id): ?array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM wireguard_servers WHERE id = ?");
            $stmt->execute([$id]);
            $server = $stmt->fetch(PDO::FETCH_ASSOC);
            return $server ?: null;
        } catch (PDOException $e) {
            error_log("WireGuard getServer error: " . $e->getMessage());
            return null;
        }
    }
    
    public function createServer(array $data): ?int {
        try {
            $keys = $this->generateKeyPair();
            
            $stmt = $this->db->prepare("
                INSERT INTO wireguard_servers 
                (name, interface_name, interface_addr, listen_port, public_key, private_key_encrypted, mtu, dns_servers, post_up_cmd, post_down_cmd, enabled)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            
            $stmt->execute([
                $data['name'],
                $data['interface_name'] ?? 'wg0',
                $data['interface_addr'],
                $data['listen_port'] ?? 51820,
                $keys['public_key'],
                $this->encrypt($keys['private_key']),
                $data['mtu'] ?? 1420,
                $data['dns_servers'] ?? null,
                $data['post_up_cmd'] ?? null,
                $data['post_down_cmd'] ?? null,
                $data['enabled'] ?? true
            ]);
            
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("WireGuard createServer error: " . $e->getMessage());
            return null;
        }
    }
    
    public function updateServer(int $id, array $data): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE wireguard_servers SET
                    name = ?,
                    interface_name = ?,
                    interface_addr = ?,
                    listen_port = ?,
                    mtu = ?,
                    dns_servers = ?,
                    post_up_cmd = ?,
                    post_down_cmd = ?,
                    enabled = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['name'],
                $data['interface_name'] ?? 'wg0',
                $data['interface_addr'],
                $data['listen_port'] ?? 51820,
                $data['mtu'] ?? 1420,
                $data['dns_servers'] ?? null,
                $data['post_up_cmd'] ?? null,
                $data['post_down_cmd'] ?? null,
                $data['enabled'] ?? true,
                $id
            ]);
        } catch (PDOException $e) {
            error_log("WireGuard updateServer error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteServer(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM wireguard_servers WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("WireGuard deleteServer error: " . $e->getMessage());
            return false;
        }
    }
    
    public function regenerateServerKeys(int $id): bool {
        try {
            $keys = $this->generateKeyPair();
            $stmt = $this->db->prepare("
                UPDATE wireguard_servers SET
                    public_key = ?,
                    private_key_encrypted = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            return $stmt->execute([$keys['public_key'], $this->encrypt($keys['private_key']), $id]);
        } catch (PDOException $e) {
            error_log("WireGuard regenerateServerKeys error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getPeers(int $serverId): array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM wireguard_peers WHERE server_id = ? ORDER BY name");
            $stmt->execute([$serverId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("WireGuard getPeers error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAllPeers(): array {
        try {
            $stmt = $this->db->query("
                SELECT p.*, s.name as server_name 
                FROM wireguard_peers p 
                LEFT JOIN wireguard_servers s ON p.server_id = s.id 
                ORDER BY p.name
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("WireGuard getAllPeers error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getPeer(int $id): ?array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM wireguard_peers WHERE id = ?");
            $stmt->execute([$id]);
            $peer = $stmt->fetch(PDO::FETCH_ASSOC);
            return $peer ?: null;
        } catch (PDOException $e) {
            error_log("WireGuard getPeer error: " . $e->getMessage());
            return null;
        }
    }
    
    public function createPeer(array $data): ?int {
        try {
            $keys = $this->generateKeyPair();
            $psk = $this->generatePresharedKey();
            
            $stmt = $this->db->prepare("
                INSERT INTO wireguard_peers 
                (server_id, name, description, public_key, private_key_encrypted, preshared_key_encrypted, allowed_ips, endpoint, persistent_keepalive, is_active, is_olt_site, olt_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            
            $stmt->execute([
                $data['server_id'],
                $data['name'],
                $data['description'] ?? null,
                $keys['public_key'],
                $this->encrypt($keys['private_key']),
                $this->encrypt($psk),
                $data['allowed_ips'],
                $data['endpoint'] ?? null,
                $data['persistent_keepalive'] ?? 25,
                $data['is_active'] ?? true,
                $data['is_olt_site'] ?? false,
                $data['olt_id'] ?? null
            ]);
            
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("WireGuard createPeer error: " . $e->getMessage());
            return null;
        }
    }
    
    public function updatePeer(int $id, array $data): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE wireguard_peers SET
                    name = ?,
                    description = ?,
                    allowed_ips = ?,
                    endpoint = ?,
                    persistent_keepalive = ?,
                    is_active = ?,
                    is_olt_site = ?,
                    olt_id = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['allowed_ips'],
                $data['endpoint'] ?? null,
                $data['persistent_keepalive'] ?? 25,
                $data['is_active'] ?? true,
                $data['is_olt_site'] ?? false,
                $data['olt_id'] ?? null,
                $id
            ]);
        } catch (PDOException $e) {
            error_log("WireGuard updatePeer error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deletePeer(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM wireguard_peers WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("WireGuard deletePeer error: " . $e->getMessage());
            return false;
        }
    }
    
    public function regeneratePeerKeys(int $id): bool {
        try {
            $keys = $this->generateKeyPair();
            $psk = $this->generatePresharedKey();
            
            $stmt = $this->db->prepare("
                UPDATE wireguard_peers SET
                    public_key = ?,
                    private_key_encrypted = ?,
                    preshared_key_encrypted = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            return $stmt->execute([
                $keys['public_key'],
                $this->encrypt($keys['private_key']),
                $this->encrypt($psk),
                $id
            ]);
        } catch (PDOException $e) {
            error_log("WireGuard regeneratePeerKeys error: " . $e->getMessage());
            return false;
        }
    }
    
    public function generateKeyPair(): array {
        $privateKey = sodium_crypto_box_keypair();
        $publicKey = sodium_crypto_box_publickey($privateKey);
        $secretKey = sodium_crypto_box_secretkey($privateKey);
        
        return [
            'private_key' => base64_encode($secretKey),
            'public_key' => base64_encode($publicKey)
        ];
    }
    
    public function generatePresharedKey(): string {
        return base64_encode(random_bytes(32));
    }
    
    public function getServerConfig(int $serverId): string {
        $server = $this->getServer($serverId);
        if (!$server) return '';
        
        $privateKey = $this->decrypt($server['private_key_encrypted']);
        $peers = $this->getPeers($serverId);
        
        $config = "[Interface]\n";
        $config .= "PrivateKey = {$privateKey}\n";
        $config .= "Address = {$server['interface_addr']}\n";
        $config .= "ListenPort = {$server['listen_port']}\n";
        
        if ($server['dns_servers']) {
            $config .= "DNS = {$server['dns_servers']}\n";
        }
        if ($server['mtu']) {
            $config .= "MTU = {$server['mtu']}\n";
        }
        if ($server['post_up_cmd']) {
            $config .= "PostUp = {$server['post_up_cmd']}\n";
        }
        if ($server['post_down_cmd']) {
            $config .= "PostDown = {$server['post_down_cmd']}\n";
        }
        
        foreach ($peers as $peer) {
            if (!$peer['is_active']) continue;
            
            $config .= "\n[Peer]\n";
            $config .= "# {$peer['name']}\n";
            $config .= "PublicKey = {$peer['public_key']}\n";
            
            if ($peer['preshared_key_encrypted']) {
                $psk = $this->decrypt($peer['preshared_key_encrypted']);
                $config .= "PresharedKey = {$psk}\n";
            }
            
            $config .= "AllowedIPs = {$peer['allowed_ips']}\n";
            
            if ($peer['persistent_keepalive']) {
                $config .= "PersistentKeepalive = {$peer['persistent_keepalive']}\n";
            }
        }
        
        return $config;
    }
    
    public function getPeerConfig(int $peerId): string {
        $peer = $this->getPeer($peerId);
        if (!$peer) return '';
        
        $server = $this->getServer($peer['server_id']);
        if (!$server) return '';
        
        $settings = $this->getSettings();
        
        $privateKey = $this->decrypt($peer['private_key_encrypted']);
        $peerIp = $this->extractPeerIP($peer['allowed_ips']);
        
        $config = "[Interface]\n";
        $config .= "PrivateKey = {$privateKey}\n";
        $config .= "Address = {$peerIp}\n";
        
        if ($server['dns_servers']) {
            $config .= "DNS = {$server['dns_servers']}\n";
        }
        if ($server['mtu']) {
            $config .= "MTU = {$server['mtu']}\n";
        }
        
        $config .= "\n[Peer]\n";
        $config .= "PublicKey = {$server['public_key']}\n";
        
        if ($peer['preshared_key_encrypted']) {
            $psk = $this->decrypt($peer['preshared_key_encrypted']);
            $config .= "PresharedKey = {$psk}\n";
        }
        
        $endpoint = $peer['endpoint'] ?: ($settings['vpn_gateway_ip'] . ':' . $server['listen_port']);
        $config .= "Endpoint = {$endpoint}\n";
        $config .= "AllowedIPs = {$settings['vpn_network']}\n";
        
        if ($peer['persistent_keepalive']) {
            $config .= "PersistentKeepalive = {$peer['persistent_keepalive']}\n";
        }
        
        return $config;
    }
    
    private function extractPeerIP(string $allowedIps): string {
        $ips = explode(',', $allowedIps);
        return trim($ips[0]);
    }
    
    public function getTR069AcsUrl(): string {
        $settings = $this->getSettings();
        
        if ($settings['tr069_use_vpn_gateway'] === 'true' && $settings['vpn_enabled'] === 'true') {
            return 'http://' . $settings['vpn_gateway_ip'] . ':7547';
        }
        
        return $settings['tr069_acs_url'];
    }
    
    public function getOLTSitePeers(): array {
        try {
            $stmt = $this->db->query("
                SELECT p.*, s.name as server_name, o.name as olt_name
                FROM wireguard_peers p 
                LEFT JOIN wireguard_servers s ON p.server_id = s.id 
                LEFT JOIN huawei_olts o ON p.olt_id = o.id
                WHERE p.is_olt_site = TRUE
                ORDER BY p.name
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("WireGuard getOLTSitePeers error: " . $e->getMessage());
            return [];
        }
    }
    
    public function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
    }
}
