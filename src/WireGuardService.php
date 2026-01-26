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
            'vpn_gateway_ip' => '10.200.0.1',
            'vpn_network' => '10.200.0.0/24',
            'server_public_ip' => '',
            'container_name' => 'isp_crm_wireguard'
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
            
            // Default PostUp/PostDown for NAT and forwarding (safe - doesn't touch default gateway)
            $defaultPostUp = 'iptables -A FORWARD -i %i -j ACCEPT; iptables -A FORWARD -o %i -j ACCEPT; iptables -t nat -A POSTROUTING -o eth+ -j MASQUERADE';
            $defaultPostDown = 'iptables -D FORWARD -i %i -j ACCEPT; iptables -D FORWARD -o %i -j ACCEPT; iptables -t nat -D POSTROUTING -o eth+ -j MASQUERADE';
            
            $enabled = !empty($data['enabled']) ? 'true' : 'false';
            if (!isset($data['enabled'])) $enabled = 'true';
            
            $stmt = $this->db->prepare("
                INSERT INTO wireguard_servers 
                (name, interface_name, interface_addr, listen_port, public_key, private_key_encrypted, mtu, dns_servers, post_up_cmd, post_down_cmd, enabled)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::boolean)
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
                $data['post_up_cmd'] ?? $defaultPostUp,
                $data['post_down_cmd'] ?? $defaultPostDown,
                $enabled
            ]);
            
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("WireGuard createServer error: " . $e->getMessage());
            return null;
        }
    }
    
    public function updateServer(int $id, array $data): bool {
        try {
            $enabled = !empty($data['enabled']) ? 'true' : 'false';
            if (!isset($data['enabled'])) $enabled = 'true';
            
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
                    enabled = ?::boolean,
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
                $enabled,
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
    
    /**
     * Get the next available IP address from the VPN subnet
     */
    public function getNextAvailableIP(int $serverId): ?string {
        try {
            $server = $this->getServer($serverId);
            if (!$server) {
                return null;
            }
            
            $serverAddr = $server['interface_addr'];
            list($serverIp, $cidr) = explode('/', $serverAddr);
            $serverOctets = explode('.', $serverIp);
            $baseNetwork = $serverOctets[0] . '.' . $serverOctets[1] . '.' . $serverOctets[2] . '.';
            
            $usedIps = [];
            $usedIps[] = (int)$serverOctets[3];
            
            $stmt = $this->db->prepare("
                SELECT allowed_ips FROM wireguard_peers WHERE server_id = ?
            ");
            $stmt->execute([$serverId]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $allowedIps = $row['allowed_ips'];
                if (preg_match('/(\d+)\.(\d+)\.(\d+)\.(\d+)/', $allowedIps, $matches)) {
                    if ($matches[1] . '.' . $matches[2] . '.' . $matches[3] . '.' === $baseNetwork) {
                        $usedIps[] = (int)$matches[4];
                    }
                }
            }
            
            for ($i = 2; $i <= 254; $i++) {
                if (!in_array($i, $usedIps)) {
                    return $baseNetwork . $i . '/32';
                }
            }
            
            return null;
        } catch (PDOException $e) {
            error_log("WireGuard getNextAvailableIP error: " . $e->getMessage());
            return null;
        }
    }
    
    public function createPeer(array $data): ?int {
        try {
            $keys = $this->generateKeyPair();
            $psk = $this->generatePresharedKey();
            
            $isActive = !empty($data['is_active']) ? true : false;
            $isOltSite = !empty($data['is_olt_site']) ? true : false;
            $oltId = !empty($data['olt_id']) ? (int)$data['olt_id'] : null;
            
            $allowedIps = $data['allowed_ips'] ?? null;
            if (empty($allowedIps) && !empty($data['server_id'])) {
                $allowedIps = $this->getNextAvailableIP($data['server_id']);
                if (!$allowedIps) {
                    error_log("WireGuard createPeer: No available IPs in subnet");
                    return null;
                }
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO wireguard_peers 
                (server_id, name, description, public_key, private_key_encrypted, preshared_key_encrypted, allowed_ips, endpoint, persistent_keepalive, is_active, is_olt_site, olt_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::boolean, ?::boolean, ?)
                RETURNING id
            ");
            
            $stmt->execute([
                $data['server_id'],
                $data['name'],
                $data['description'] ?? null,
                $keys['public_key'],
                $this->encrypt($keys['private_key']),
                $this->encrypt($psk),
                $allowedIps,
                $data['endpoint'] ?? null,
                $data['persistent_keepalive'] ?? 25,
                $isActive ? 'true' : 'false',
                $isOltSite ? 'true' : 'false',
                $oltId
            ]);
            
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("WireGuard createPeer error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create a VPN peer for a NAS device and link them
     */
    public function createPeerForNAS(int $nasId, string $nasName, string $nasIp, ?array $additionalNetworks = null): ?int {
        try {
            $servers = $this->getServers();
            if (empty($servers)) {
                error_log("WireGuard createPeerForNAS: No VPN servers configured");
                return null;
            }
            $server = $servers[0];
            
            $allowedIps = $this->getNextAvailableIP($server['id']);
            if (!$allowedIps) {
                error_log("WireGuard createPeerForNAS: No available IPs");
                return null;
            }
            
            if ($additionalNetworks && is_array($additionalNetworks)) {
                $allowedIps .= ', ' . implode(', ', $additionalNetworks);
            }
            
            $peerId = $this->createPeer([
                'server_id' => $server['id'],
                'name' => $nasName,
                'description' => "NAS Device: $nasIp",
                'allowed_ips' => $allowedIps,
                'is_active' => true,
                'is_olt_site' => false
            ]);
            
            if ($peerId) {
                $stmt = $this->db->prepare("UPDATE radius_nas SET wireguard_peer_id = ? WHERE id = ?");
                $stmt->execute([$peerId, $nasId]);
            }
            
            return $peerId;
        } catch (PDOException $e) {
            error_log("WireGuard createPeerForNAS error: " . $e->getMessage());
            return null;
        }
    }
    
    public function updatePeer(int $id, array $data): bool {
        try {
            $isActive = !empty($data['is_active']) ? true : false;
            $isOltSite = !empty($data['is_olt_site']) ? true : false;
            $oltId = !empty($data['olt_id']) ? (int)$data['olt_id'] : null;
            
            $stmt = $this->db->prepare("
                UPDATE wireguard_peers SET
                    name = ?,
                    description = ?,
                    allowed_ips = ?,
                    endpoint = ?,
                    persistent_keepalive = ?,
                    is_active = ?::boolean,
                    is_olt_site = ?::boolean,
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
                $isActive ? 'true' : 'false',
                $isOltSite ? 'true' : 'false',
                $oltId,
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
        // Try using wg command for proper WireGuard key generation
        if ($this->isExecAvailable()) {
            $privateKey = \trim(\shell_exec('wg genkey 2>/dev/null') ?? '');
            if (!empty($privateKey) && \strlen($privateKey) === 44) {
                $publicKey = \trim(\shell_exec("echo '{$privateKey}' | wg pubkey 2>/dev/null") ?? '');
                if (!empty($publicKey) && \strlen($publicKey) === 44) {
                    return [
                        'private_key' => $privateKey,
                        'public_key' => $publicKey
                    ];
                }
            }
            
            // Try via docker exec if wg not available locally
            $containerName = $this->getSettings()['container_name'] ?? 'isp_crm_wireguard';
            $privateKey = \trim(\shell_exec("docker exec {$containerName} wg genkey 2>/dev/null") ?? '');
            if (!empty($privateKey) && \strlen($privateKey) === 44) {
                $publicKey = \trim(\shell_exec("docker exec {$containerName} sh -c \"echo '{$privateKey}' | wg pubkey\" 2>/dev/null") ?? '');
                if (!empty($publicKey) && \strlen($publicKey) === 44) {
                    return [
                        'private_key' => $privateKey,
                        'public_key' => $publicKey
                    ];
                }
            }
        }
        
        // Fallback to sodium (Curve25519) - compatible with WireGuard
        $keyPair = \sodium_crypto_box_keypair();
        $secretKey = \sodium_crypto_box_secretkey($keyPair);
        $publicKey = \sodium_crypto_box_publickey($keyPair);
        
        return [
            'private_key' => \base64_encode($secretKey),
            'public_key' => \base64_encode($publicKey)
        ];
    }
    
    public function generatePresharedKey(): string {
        // Try using wg command for proper preshared key generation
        if ($this->isExecAvailable()) {
            $psk = \trim(\shell_exec('wg genpsk 2>/dev/null') ?? '');
            if (!empty($psk) && \strlen($psk) === 44) {
                return $psk;
            }
            
            // Try via docker exec
            $containerName = $this->getSettings()['container_name'] ?? 'isp_crm_wireguard';
            $psk = \trim(\shell_exec("docker exec {$containerName} wg genpsk 2>/dev/null") ?? '');
            if (!empty($psk) && \strlen($psk) === 44) {
                return $psk;
            }
        }
        
        // Fallback to random bytes
        return \base64_encode(\random_bytes(32));
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
            
            // Include routed networks from wireguard_subnets table
            $allowedIps = [$peer['allowed_ips']];
            try {
                $stmt = $this->db->prepare("SELECT network_cidr FROM wireguard_subnets WHERE vpn_peer_id = ? AND is_active = TRUE");
                $stmt->execute([$peer['id']]);
                $subnets = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($subnets as $subnet) {
                    if (!empty($subnet)) {
                        $allowedIps[] = trim($subnet);
                    }
                }
            } catch (\Exception $e) {
                \error_log("Error fetching subnets for peer {$peer['id']}: " . $e->getMessage());
            }
            
            $config .= "AllowedIPs = " . implode(', ', array_unique($allowedIps)) . "\n";
            
            // Include endpoint if the peer has one (needed for server to initiate connections)
            if (!empty($peer['endpoint'])) {
                $config .= "Endpoint = {$peer['endpoint']}\n";
            }
            
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
        
        // Use server_public_ip setting for MikroTik endpoint (public IP, not tunnel IP)
        $serverPublicIp = $settings['server_public_ip'] ?? '';
        if (empty($serverPublicIp)) {
            // Fallback: try to detect public IP
            $serverPublicIp = $this->detectPublicIp();
        }
        $endpoint = $peer['endpoint'] ?: ($serverPublicIp . ':' . $server['listen_port']);
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
    
    /**
     * Detect the public IP of this server for WireGuard endpoint
     */
    private function detectPublicIp(): string {
        // Try multiple services to get public IP
        $services = [
            'https://api.ipify.org',
            'https://ifconfig.me/ip',
            'https://icanhazip.com',
            'https://ipecho.net/plain'
        ];
        
        foreach ($services as $url) {
            $ch = \curl_init($url);
            \curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            $ip = \trim(\curl_exec($ch) ?: '');
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            \curl_close($ch);
            
            if ($httpCode === 200 && \filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        }
        
        // Fallback to APP_URL if set
        $appUrl = \getenv('APP_URL');
        if ($appUrl) {
            $host = \parse_url($appUrl, PHP_URL_HOST);
            if ($host && \filter_var($host, FILTER_VALIDATE_IP)) {
                return $host;
            }
            // Try to resolve hostname to IP
            $resolvedIp = \gethostbyname($host);
            if ($resolvedIp !== $host) {
                return $resolvedIp;
            }
        }
        
        return '0.0.0.0'; // Fallback - user must configure manually
    }
    
    public function getTR069AcsUrl(): string {
        $settings = $this->getSettings();
        // Always use VPN gateway IP for TR-069 ACS URL
        return 'http://' . $settings['vpn_gateway_ip'] . ':7547';
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
     * Check if exec() function is available
     */
    private function isExecAvailable(): bool {
        $disabled = \explode(',', \ini_get('disable_functions'));
        $disabled = \array_map('trim', $disabled);
        return \function_exists('exec') && !\in_array('exec', $disabled);
    }
    
    /**
     * Test connectivity via OLT Session Manager (real ping when exec is disabled)
     */
    private function testConnectivityViaOltService(string $ip, int $count = 3, int $timeout = 2): array {
        $oltServiceUrl = \getenv('OLT_SERVICE_URL') ?: 'http://localhost:3002';
        
        $ch = \curl_init("{$oltServiceUrl}/ping");
        \curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => \json_encode(['ip' => $ip, 'count' => $count, 'timeout' => $timeout]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout + 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
        
        $response = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = \json_decode($response, true);
            if ($result) {
                return $result;
            }
        }
        
        return [
            'success' => false,
            'ip' => $ip,
            'error' => 'OLT Service unreachable',
            'method' => 'olt_service'
        ];
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
        
        if (!$this->isExecAvailable()) {
            return $this->testConnectivityViaOltService($ip, $count, $timeout);
        }
        
        $output = [];
        $returnVar = 0;
        
        \exec("ping -c {$count} -W {$timeout} " . \escapeshellarg($ip) . " 2>&1", $output, $returnVar);
        
        $result = [
            'success' => $returnVar === 0,
            'ip' => $ip,
            'method' => 'ping',
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
    
    /**
     * Write WireGuard config to file and apply via wg syncconf
     * @param int $serverId Server ID (or 0 for first/only server)
     * @return array Result with success status and message
     */
    public function syncConfig(int $serverId = 0): array {
        try {
            // Get server (use first one if not specified)
            if ($serverId === 0) {
                $servers = $this->getServers();
                if (empty($servers)) {
                    return ['success' => false, 'error' => 'No WireGuard server configured'];
                }
                $serverId = $servers[0]['id'];
            }
            
            $config = $this->getServerConfig($serverId);
            if (empty($config)) {
                return ['success' => false, 'error' => 'Failed to generate config'];
            }
            
            // Try multiple paths to write config
            $configWritten = false;
            $writtenPath = '';
            
            $storagePaths = [
                '/var/www/html/storage/wireguard/wg0.conf',
                '/tmp/wg0.conf',
                '/etc/wireguard/wg0.conf'
            ];
            
            foreach ($storagePaths as $path) {
                $dir = \dirname($path);
                if (!\is_dir($dir)) {
                    @\mkdir($dir, 0777, true);
                }
                
                if (@\file_put_contents($path, $config) !== false) {
                    $configWritten = true;
                    $writtenPath = $path;
                    break;
                }
            }
            
            if (!$configWritten) {
                return ['success' => false, 'error' => 'Could not write config file - check directory permissions'];
            }
            
            // Try to apply config using wg syncconf (hot reload, no restart needed)
            $syncResult = $this->applyConfig($writtenPath);
            
            // Manage host routes for all active subnets
            $routeResult = $this->syncHostRoutes();
            
            // Log the sync
            $this->logSync($serverId, $syncResult['success'], $syncResult['message'] ?? '');
            
            return [
                'success' => $syncResult['success'],
                'message' => $syncResult['success'] 
                    ? 'Config synced and applied successfully' . ($routeResult['message'] ?? '')
                    : 'Config written but could not apply: ' . ($syncResult['error'] ?? 'Unknown error'),
                'config_path' => $writtenPath,
                'applied' => $syncResult['success'],
                'routes' => $routeResult
            ];
            
        } catch (\Exception $e) {
            \error_log("WireGuard syncConfig error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Apply WireGuard config using wg syncconf or wg-quick
     */
    private function applyConfig(string $configPath): array {
        $containerName = 'isp_crm_wireguard';
        $containerConfigPath = '/config/wg_confs/wg0.conf';
        
        // Check if exec is available
        if (!$this->isExecAvailable()) {
            // exec is disabled - config was written, but can't auto-apply
            // Try using OLT service API to apply
            $applied = $this->applyConfigViaOltService($configPath);
            if ($applied['success']) {
                return $applied;
            }
            
            return [
                'success' => true,
                'message' => 'Config saved. Run manually on VPS: docker restart isp_crm_wireguard',
                'manual_required' => true
            ];
        }
        
        $output = [];
        $returnVar = 0;
        
        // Step 1: Copy the new config into the WireGuard container
        \exec("docker cp {$configPath} {$containerName}:{$containerConfigPath} 2>&1", $output, $returnVar);
        if ($returnVar !== 0) {
            return ['success' => false, 'error' => 'Failed to copy config to container: ' . implode(' ', $output)];
        }
        
        // Step 2: Strip the config and apply using wg syncconf (hot reload)
        $output = [];
        \exec("docker exec {$containerName} sh -c \"wg-quick strip wg0 | wg syncconf wg0 /dev/stdin\" 2>&1", $output, $returnVar);
        if ($returnVar === 0) {
            return ['success' => true, 'message' => 'Config copied and applied via wg syncconf'];
        }
        
        // Fallback: Try direct wg setconf
        $output = [];
        \exec("docker exec {$containerName} wg setconf wg0 {$containerConfigPath} 2>&1", $output, $returnVar);
        if ($returnVar === 0) {
            return ['success' => true, 'message' => 'Config copied and applied via wg setconf'];
        }
        
        // Last resort: Restart container to apply new config
        $output = [];
        \exec("docker restart {$containerName} 2>&1", $output, $returnVar);
        if ($returnVar === 0) {
            \sleep(3); // Wait for container to fully start
            return ['success' => true, 'message' => 'Config copied, container restarted'];
        }
        
        return ['success' => false, 'error' => 'Could not apply config: ' . implode(' ', $output)];
    }
    
    /**
     * Apply WireGuard config via OLT service API (when exec is disabled)
     */
    private function applyConfigViaOltService(string $configPath): array {
        $oltServiceUrl = \getenv('OLT_SERVICE_URL') ?: 'http://localhost:3002';
        
        try {
            $config = \file_get_contents($configPath);
            
            // Get all active subnets for route sync
            $subnets = $this->getActiveSubnetCidrs();
            
            $ch = \curl_init("{$oltServiceUrl}/wireguard/apply");
            \curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => \json_encode([
                    'config' => $config,
                    'subnets' => $subnets
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 10
            ]);
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = \curl_error($ch);
            \curl_close($ch);
            
            if ($httpCode === 200) {
                $data = \json_decode($response, true);
                if ($data && isset($data['success']) && $data['success']) {
                    $msg = 'Applied via OLT service';
                    if (!empty($data['routesAdded'])) {
                        $msg .= ' | Routes added: ' . \implode(', ', $data['routesAdded']);
                    }
                    if (!empty($data['routesRemoved'])) {
                        $msg .= ' | Routes removed: ' . \implode(', ', $data['routesRemoved']);
                    }
                    return ['success' => true, 'message' => $msg];
                }
                $error = $data['error'] ?? ($data['errors'] ? \implode(', ', $data['errors']) : 'Unknown error');
                return ['success' => false, 'error' => $error];
            }
            
            return ['success' => false, 'error' => $curlError ?: "HTTP {$httpCode}"];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get all active subnet CIDRs from peers for routing
     */
    private function getActiveSubnetCidrs(): array {
        $subnets = [];
        try {
            $stmt = $this->db->query("
                SELECT DISTINCT unnest(string_to_array(allowed_ips, ',')) as subnet
                FROM wireguard_peers 
                WHERE is_active = true
            ");
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $subnet = \trim($row['subnet']);
                // Only include subnet CIDR ranges, not /32 single IPs unless it's the peer tunnel IP
                if (!empty($subnet) && \preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $subnet)) {
                    // Skip tunnel IPs (/32) that aren't part of a larger network
                    if (!\preg_match('/\/32$/', $subnet) || \strpos($subnet, '10.200.0.') === 0) {
                        $subnets[] = $subnet;
                    }
                }
            }
        } catch (\Exception $e) {
            \error_log("getActiveSubnetCidrs error: " . $e->getMessage());
        }
        return \array_unique($subnets);
    }
    
    /**
     * Log sync operation
     */
    private function logSync(int $serverId, bool $success, string $message): void {
        try {
            $this->db->prepare("
                INSERT INTO wireguard_sync_logs (server_id, success, message, synced_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ")->execute([$serverId, $success, $message]);
        } catch (\Exception $e) {
            \error_log("Failed to log WireGuard sync: " . $e->getMessage());
        }
    }
    
    /**
     * Get recent sync logs
     */
    public function getSyncLogs(int $limit = 10): array {
        try {
            $stmt = $this->db->prepare("
                SELECT l.*, s.name as server_name 
                FROM wireguard_sync_logs l
                LEFT JOIN wireguard_servers s ON l.server_id = s.id
                ORDER BY l.synced_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Sync WireGuard container routes for all active subnets
     * Adds routes for new subnets and removes routes for deleted ones
     * Routes are added inside the WireGuard container using: docker exec isp_crm_wireguard ip route add X.X.X.X/XX dev wg0
     * @return array Result with success status and details
     */
    public function syncHostRoutes(): array {
        $result = [
            'success' => true,
            'added' => [],
            'removed' => [],
            'errors' => [],
            'message' => ''
        ];
        
        try {
            $settings = $this->getSettings();
            $containerName = $settings['container_name'] ?? 'isp_crm_wireguard';
            $wgInterface = 'wg0';
            
            // Get all active subnets from wireguard_subnets table
            $activeSubnets = [];
            try {
                $stmt = $this->db->query("SELECT DISTINCT network_cidr FROM wireguard_subnets WHERE is_active = TRUE");
                $activeSubnets = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
                // Table might not exist
            }
            
            // Also get subnets from peer allowed_ips (excluding /32 individual IPs)
            $stmt = $this->db->query("SELECT allowed_ips FROM wireguard_peers WHERE is_active = TRUE");
            $peers = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($peers as $allowedIps) {
                $ips = explode(',', $allowedIps);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    // Add routes for subnets (not /32 individual IPs)
                    if (!empty($ip) && !preg_match('/\/32$/', $ip)) {
                        $activeSubnets[] = $ip;
                    }
                }
            }
            
            $activeSubnets = array_unique(array_filter($activeSubnets));
            
            // Get current routes inside the WireGuard container
            $currentRoutes = $this->getContainerRoutes($containerName, $wgInterface);
            
            // Add routes for new subnets
            foreach ($activeSubnets as $subnet) {
                $subnet = trim($subnet);
                if (empty($subnet)) continue;
                
                if (!in_array($subnet, $currentRoutes)) {
                    $addResult = $this->addContainerRoute($containerName, $subnet, $wgInterface);
                    if ($addResult['success']) {
                        $result['added'][] = $subnet;
                    } else {
                        $result['errors'][] = "Failed to add route for {$subnet}: " . ($addResult['error'] ?? 'Unknown error');
                    }
                }
            }
            
            // Remove routes for deleted subnets (only those going through wg0)
            foreach ($currentRoutes as $existingRoute) {
                if (!in_array($existingRoute, $activeSubnets)) {
                    $removeResult = $this->removeContainerRoute($containerName, $existingRoute);
                    if ($removeResult['success']) {
                        $result['removed'][] = $existingRoute;
                    } else {
                        $result['errors'][] = "Failed to remove route for {$existingRoute}: " . ($removeResult['error'] ?? 'Unknown error');
                    }
                }
            }
            
            // Update persistent routes file for container restart
            $this->updatePersistentRoutes($activeSubnets, $containerName, $wgInterface);
            
            // Build message
            $messages = [];
            if (!empty($result['added'])) {
                $messages[] = 'Added routes: ' . implode(', ', $result['added']);
            }
            if (!empty($result['removed'])) {
                $messages[] = 'Removed routes: ' . implode(', ', $result['removed']);
            }
            $result['message'] = !empty($messages) ? ' | Routes: ' . implode('; ', $messages) : '';
            
            if (!empty($result['errors'])) {
                $result['success'] = false;
            }
            
        } catch (\Exception $e) {
            \error_log("syncHostRoutes error: " . $e->getMessage());
            $result['success'] = false;
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Get current routes on host that use wg0 interface
     * Routes are on the host, not inside a container (wg0 is a host interface)
     */
    private function getContainerRoutes(string $containerName, string $interface): array {
        $routes = [];
        
        if (!$this->isExecAvailable()) {
            return $routes;
        }
        
        $output = [];
        $returnVar = 0;
        
        // Routes are on HOST, not in container - wg0 is a host interface
        \exec("ip route show dev {$interface} 2>/dev/null", $output, $returnVar);
        
        foreach ($output as $line) {
            // Match subnet patterns like 10.78.0.0/24, 10.60.0.0/16
            if (\preg_match('/^([\d.]+\/\d+)/', \trim($line), $matches)) {
                $routes[] = $matches[1];
            }
        }
        
        return $routes;
    }
    
    /**
     * Add a route on the host for the WireGuard interface
     * wg0 is a host interface, routes must be added on host, not in container
     */
    private function addContainerRoute(string $containerName, string $subnet, string $interface): array {
        if (!$this->isExecAvailable()) {
            return ['success' => false, 'error' => 'exec() not available'];
        }
        
        $output = [];
        $returnVar = 0;
        
        // Validate subnet format
        if (!\preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $subnet)) {
            return ['success' => false, 'error' => 'Invalid subnet format'];
        }
        
        // Add route on HOST (wg0 is a host interface, not in a container)
        $cmd = "ip route add {$subnet} dev {$interface} 2>&1";
        \exec($cmd, $output, $returnVar);
        
        if ($returnVar === 0) {
            \error_log("Added route {$subnet} via {$interface} in container {$containerName}");
            return ['success' => true, 'message' => 'Route added'];
        }
        
        // Check if route already exists (not an error)
        $outputStr = \implode("\n", $output);
        if (\strpos($outputStr, 'File exists') !== false) {
            return ['success' => true, 'message' => 'Route already exists'];
        }
        
        return ['success' => false, 'error' => $outputStr];
    }
    
    /**
     * Remove a route from the WireGuard container
     */
    private function removeContainerRoute(string $containerName, string $subnet): array {
        if (!$this->isExecAvailable()) {
            return ['success' => false, 'error' => 'exec() not available'];
        }
        
        $output = [];
        $returnVar = 0;
        
        // Remove route from HOST (wg0 is a host interface, not in a container)
        $cmd = "ip route del {$subnet} 2>&1";
        \exec($cmd, $output, $returnVar);
        
        if ($returnVar === 0) {
            \error_log("Removed route {$subnet} from container {$containerName}");
            return ['success' => true, 'message' => 'Route removed'];
        }
        
        // Check if route doesn't exist (not an error)
        $outputStr = \implode("\n", $output);
        if (\strpos($outputStr, 'No such process') !== false) {
            return ['success' => true, 'message' => 'Route did not exist'];
        }
        
        return ['success' => false, 'error' => $outputStr];
    }
    
    /**
     * Update persistent routes file for container restart persistence
     * This creates a script that can be run after container restart
     */
    private function updatePersistentRoutes(array $subnets, string $containerName, string $interface): void {
        $routesScript = "#!/bin/bash\n";
        $routesScript .= "# Auto-generated by ISP CRM WireGuard Service\n";
        $routesScript .= "# Generated: " . date('Y-m-d H:i:s') . "\n";
        $routesScript .= "# Run this script after WireGuard container restart to restore routes\n\n";
        $routesScript .= "CONTAINER=\"{$containerName}\"\n";
        $routesScript .= "INTERFACE=\"{$interface}\"\n\n";
        $routesScript .= "# Wait for container to be ready\n";
        $routesScript .= "sleep 2\n\n";
        
        foreach ($subnets as $subnet) {
            $subnet = trim($subnet);
            if (!empty($subnet) && preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $subnet)) {
                $routesScript .= "docker exec \$CONTAINER ip route add {$subnet} dev \$INTERFACE 2>/dev/null || true\n";
            }
        }
        
        $routesScript .= "\necho \"Routes applied: " . count($subnets) . " subnets\"\n";
        
        // Write to a persistent location (shared volume)
        $routesPaths = [
            '/var/www/html/storage/wireguard/apply_routes.sh',
            '/config/wireguard/apply_routes.sh'
        ];
        
        foreach ($routesPaths as $path) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (is_dir($dir)) {
                if (@file_put_contents($path, $routesScript) !== false) {
                    @chmod($path, 0755);
                    \error_log("Persistent routes script written to: {$path}");
                    break;
                }
            }
        }
    }
    
    /**
     * Get all active subnets
     */
    public function getActiveSubnets(): array {
        try {
            $stmt = $this->db->query("
                SELECT s.*, p.name as peer_name 
                FROM wireguard_subnets s
                LEFT JOIN wireguard_peers p ON s.vpn_peer_id = p.id
                WHERE s.is_active = TRUE
                ORDER BY s.network_cidr
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
