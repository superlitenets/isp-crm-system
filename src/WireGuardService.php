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
    
    /**
     * Restart the WireGuard Docker container to apply configuration changes
     * @return array Result with success status and message
     */
    public function restartContainer(): array {
        $settings = $this->getSettings();
        $containerName = $settings['container_name'] ?? 'wireguard';
        
        // Try to restart using Docker
        $output = [];
        $returnVar = 0;
        
        // First try docker restart
        \exec("docker restart {$containerName} 2>&1", $output, $returnVar);
        
        if ($returnVar === 0) {
            return ['success' => true, 'message' => 'WireGuard container restarted successfully'];
        }
        
        // If docker restart fails, try docker-compose
        $composeFile = $settings['compose_file'] ?? '/opt/wireguard/docker-compose.yml';
        if (\file_exists($composeFile)) {
            $dir = \dirname($composeFile);
            \exec("cd {$dir} && docker-compose restart wireguard 2>&1", $output, $returnVar);
            if ($returnVar === 0) {
                return ['success' => true, 'message' => 'WireGuard restarted via docker-compose'];
            }
        }
        
        // Log the failure but don't crash
        error_log("WireGuard restart failed: " . implode("\n", $output));
        return ['success' => false, 'message' => 'Could not restart WireGuard container. Manual restart may be required.'];
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
    
    public function getMikroTikScript(int $peerId): string {
        $peer = $this->getPeer($peerId);
        if (!$peer) return '';
        
        $server = $this->getServer($peer['server_id']);
        if (!$server) return '';
        
        $settings = $this->getSettings();
        
        $privateKey = $this->decrypt($peer['private_key_encrypted']);
        $peerIp = $this->extractPeerIP($peer['allowed_ips']);
        $psk = $peer['preshared_key_encrypted'] ? $this->decrypt($peer['preshared_key_encrypted']) : '';
        
        $interfaceName = 'wg-' . preg_replace('/[^a-zA-Z0-9]/', '', strtolower($peer['name']));
        if (strlen($interfaceName) > 15) {
            $interfaceName = substr($interfaceName, 0, 15);
        }
        
        $endpoint = $peer['endpoint'] ?: ($settings['vpn_gateway_ip'] . ':' . $server['listen_port']);
        $keepalive = $peer['persistent_keepalive'] ?: 25;
        $vpnNetwork = $settings['vpn_network'] ?: '10.200.0.0/24';
        
        $script = "# ============================================\n";
        $script .= "# MikroTik WireGuard Configuration Script\n";
        $script .= "# Peer: {$peer['name']}\n";
        $script .= "# Generated: " . date('Y-m-d H:i:s') . "\n";
        $script .= "# ============================================\n";
        $script .= "# IMPORTANT: RouterOS 7.x required for WireGuard\n";
        $script .= "# Copy and paste this entire script into MikroTik terminal\n";
        $script .= "# ============================================\n\n";
        
        $script .= "# Remove existing interface if exists (optional - uncomment if needed)\n";
        $script .= "# /interface wireguard remove [find name=\"{$interfaceName}\"]\n\n";
        
        $script .= "# 1. Create WireGuard interface with private key\n";
        $script .= "/interface wireguard add name=\"{$interfaceName}\" private-key=\"{$privateKey}\" listen-port=51821 mtu=" . ($server['mtu'] ?: 1420) . "\n\n";
        
        $script .= "# 2. Assign IP address to WireGuard interface\n";
        $script .= "/ip address add address={$peerIp} interface=\"{$interfaceName}\" network=" . $this->extractNetwork($vpnNetwork) . "\n\n";
        
        $script .= "# 3. Add VPS server as peer\n";
        $script .= "/interface wireguard peers add \\\n";
        $script .= "    interface=\"{$interfaceName}\" \\\n";
        $script .= "    public-key=\"{$server['public_key']}\" \\\n";
        if ($psk) {
            $script .= "    preshared-key=\"{$psk}\" \\\n";
        }
        $script .= "    endpoint-address=\"" . $this->extractHost($endpoint) . "\" \\\n";
        $script .= "    endpoint-port=" . $this->extractPort($endpoint, $server['listen_port']) . " \\\n";
        $script .= "    allowed-address={$vpnNetwork} \\\n";
        $script .= "    persistent-keepalive={$keepalive}s\n\n";
        
        $script .= "# 4. Add firewall rules (if not already present)\n";
        $script .= "/ip firewall filter add chain=input action=accept protocol=udp dst-port=51821 comment=\"Allow WireGuard\" place-before=0\n";
        $script .= "/ip firewall filter add chain=input action=accept in-interface=\"{$interfaceName}\" comment=\"Allow WireGuard traffic\" place-before=1\n\n";
        
        $script .= "# 5. Add route to VPN network (optional - for accessing other VPN peers)\n";
        $script .= "/ip route add dst-address={$vpnNetwork} gateway=\"{$interfaceName}\" comment=\"WireGuard VPN route\"\n\n";
        
        $script .= "# ============================================\n";
        $script .= "# Verification Commands (run after setup):\n";
        $script .= "# ============================================\n";
        $script .= "# /interface wireguard print\n";
        $script .= "# /interface wireguard peers print\n";
        $script .= "# /ping " . $this->extractNetwork($vpnNetwork, true) . " interface=\"{$interfaceName}\"\n";
        $script .= "# ============================================\n";
        
        return $script;
    }
    
    private function extractNetwork(string $cidr, bool $firstHost = false): string {
        $parts = explode('/', $cidr);
        $ip = trim($parts[0]);
        if ($firstHost) {
            $octets = explode('.', $ip);
            $octets[3] = '1';
            return implode('.', $octets);
        }
        return $ip;
    }
    
    private function extractHost(string $endpoint): string {
        $parts = explode(':', $endpoint);
        return trim($parts[0]);
    }
    
    private function extractPort(string $endpoint, int $default = 51820): int {
        $parts = explode(':', $endpoint);
        return isset($parts[1]) ? (int)$parts[1] : $default;
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
    
    /**
     * Test connectivity to a remote IP address via ping
     * @param string $ip IP address to ping
     * @param int $count Number of ping attempts
     * @param int $timeout Timeout per ping in seconds
     * @return array Result with success, latency, and details
     */
    public function testConnectivity(string $ip, int $count = 3, int $timeout = 2): array {
        if (!\filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'error' => 'Invalid IP address', 'ip' => $ip];
        }
        
        $output = [];
        $returnVar = 0;
        
        \exec("ping -c {$count} -W {$timeout} " . \escapeshellarg($ip) . " 2>&1", $output, $returnVar);
        
        $result = [
            'success' => $returnVar === 0,
            'ip' => $ip,
            'output' => \implode("\n", $output),
            'packets_sent' => $count,
            'packets_received' => 0,
            'latency_avg' => null,
            'latency_min' => null,
            'latency_max' => null
        ];
        
        foreach ($output as $line) {
            if (\preg_match('/(\d+) packets transmitted, (\d+) (?:packets )?received/', $line, $matches)) {
                $result['packets_received'] = (int)$matches[2];
            }
            if (\preg_match('/min\/avg\/max.*= ([\d.]+)\/([\d.]+)\/([\d.]+)/', $line, $matches)) {
                $result['latency_min'] = (float)$matches[1];
                $result['latency_avg'] = (float)$matches[2];
                $result['latency_max'] = (float)$matches[3];
            }
        }
        
        return $result;
    }
    
    /**
     * Test connectivity to all routed networks of a peer
     * @param int $peerId Peer ID
     * @return array Results for each network
     */
    public function testPeerConnectivity(int $peerId): array {
        $peer = $this->getPeer($peerId);
        if (!$peer) {
            return ['success' => false, 'error' => 'Peer not found'];
        }
        
        // Fetch routed networks from wireguard_subnets table
        $routedNetworks = [];
        try {
            $stmt = $this->db->prepare("SELECT network_cidr FROM wireguard_subnets WHERE vpn_peer_id = ? AND is_active = TRUE");
            $stmt->execute([$peerId]);
            $routedNetworks = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            \error_log("Error fetching subnets: " . $e->getMessage());
        }
        
        $results = [
            'peer_name' => $peer['name'],
            'peer_ip' => $peer['allowed_ips'],
            'vpn_reachable' => false,
            'networks' => []
        ];
        
        // First test the VPN tunnel IP
        $vpnIp = \explode('/', $peer['allowed_ips'])[0];
        $vpnTest = $this->testConnectivity($vpnIp, 2, 2);
        $results['vpn_reachable'] = $vpnTest['success'];
        $results['vpn_latency'] = $vpnTest['latency_avg'];
        
        // Then test each routed network (ping the .1 gateway)
        foreach ($routedNetworks as $network) {
            $network = \trim($network);
            if (empty($network)) continue;
            
            $networkIp = \explode('/', $network)[0];
            $parts = \explode('.', $networkIp);
            if (\count($parts) === 4) {
                // Ping the .1 address (usually the gateway)
                $parts[3] = '1';
                $testIp = \implode('.', $parts);
                
                $test = $this->testConnectivity($testIp, 2, 2);
                $results['networks'][] = [
                    'network' => $network,
                    'test_ip' => $testIp,
                    'reachable' => $test['success'],
                    'latency' => $test['latency_avg'],
                    'packets_received' => $test['packets_received']
                ];
            }
        }
        
        return $results;
    }
}
