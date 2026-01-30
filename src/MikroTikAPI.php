<?php
namespace App;

class MikroTikAPI {
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private $socket;
    private bool $connected = false;
    private int $timeout = 30;
    
    public function __construct(string $host, int $port = 8728, string $username = 'admin', string $password = '', int $timeout = 30) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
    }
    
    public function setTimeout(int $seconds): void {
        $this->timeout = $seconds;
        if ($this->socket && is_resource($this->socket)) {
            stream_set_timeout($this->socket, $seconds);
        }
    }
    
    public function connect(): bool {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        
        if (!$this->socket) {
            throw new \Exception("Connection failed: {$errstr} ({$errno})");
        }
        
        stream_set_timeout($this->socket, $this->timeout);
        
        $this->write('/login', false);
        $this->write('=name=' . $this->username, false);
        $this->write('=password=' . $this->password);
        
        $response = $this->read();
        
        if (isset($response[0]) && $response[0] === '!done') {
            $this->connected = true;
            return true;
        }
        
        if (isset($response[0]) && $response[0] === '!trap') {
            throw new \Exception('Login failed: Invalid credentials');
        }
        
        throw new \Exception('Login failed: Unknown error');
    }
    
    public function disconnect(): void {
        if ($this->socket && is_resource($this->socket)) {
            @fclose($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
    }
    
    private function write(string $word, bool $end = true): void {
        if (!$this->socket || !is_resource($this->socket)) {
            throw new \Exception('Not connected to MikroTik');
        }
        
        $len = strlen($word);
        
        if ($len < 0x80) {
            @fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            @fwrite($this->socket, chr(($len >> 8) | 0x80) . chr($len & 0xFF));
        } elseif ($len < 0x200000) {
            @fwrite($this->socket, chr(($len >> 16) | 0xC0) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } elseif ($len < 0x10000000) {
            @fwrite($this->socket, chr(($len >> 24) | 0xE0) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } else {
            @fwrite($this->socket, chr(0xF0) . chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        }
        
        @fwrite($this->socket, $word);
        
        if ($end) {
            @fwrite($this->socket, chr(0));
        }
    }
    
    private function read(): array {
        $response = [];
        $receivedDone = false;
        
        // Wait for data to be available (up to timeout seconds)
        $read = [$this->socket];
        $write = null;
        $except = null;
        if (stream_select($read, $write, $except, $this->timeout) === 0) {
            return $response; // Timeout, no data
        }
        
        while (!$receivedDone) {
            $word = $this->readWord();
            
            if ($word === false) {
                break;
            }
            
            if ($word !== '') {
                $response[] = $word;
            }
            
            if ($word === '!done' || $word === '!trap' || $word === '!fatal') {
                $receivedDone = true;
            }
        }
        
        return $response;
    }
    
    private function readWord(): string|false {
        if (!$this->socket || !is_resource($this->socket)) {
            return false;
        }
        
        $byte = @fread($this->socket, 1);
        if ($byte === false || $byte === '') return false;
        
        $len = ord($byte);
        
        if ($len < 0x80) {
        } elseif ($len < 0xC0) {
            $len = (($len & 0x3F) << 8) + ord(@fread($this->socket, 1));
        } elseif ($len < 0xE0) {
            $len = (($len & 0x1F) << 16) + (ord(@fread($this->socket, 1)) << 8) + ord(@fread($this->socket, 1));
        } elseif ($len < 0xF0) {
            $len = (($len & 0x0F) << 24) + (ord(@fread($this->socket, 1)) << 16) + (ord(@fread($this->socket, 1)) << 8) + ord(@fread($this->socket, 1));
        } else {
            $len = (ord(@fread($this->socket, 1)) << 24) + (ord(@fread($this->socket, 1)) << 16) + (ord(@fread($this->socket, 1)) << 8) + ord(@fread($this->socket, 1));
        }
        
        if ($len === 0) return '';
        
        // Read exactly $len bytes, handling chunked data
        $data = '';
        $remaining = $len;
        while ($remaining > 0) {
            $chunk = @fread($this->socket, $remaining);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }
        return $data;
    }
    
    public function command(string $cmd, array $params = []): array {
        if (!$this->connected) {
            $this->connect();
        }
        
        $this->write($cmd, empty($params));
        
        foreach ($params as $key => $value) {
            $isLast = ($key === array_key_last($params));
            // Query params start with ? and should be formatted as ?key=value
            // Regular params should be formatted as =key=value
            if (str_starts_with($key, '?')) {
                $this->write($key . '=' . $value, $isLast);
            } else {
                $this->write('=' . $key . '=' . $value, $isLast);
            }
        }
        
        return $this->parseResponse($this->read());
    }
    
    public function commandRaw(string $cmd, array $params = []): array {
        if (!$this->connected) {
            $this->connect();
        }
        
        $this->write($cmd, empty($params));
        
        foreach ($params as $key => $value) {
            $isLast = ($key === array_key_last($params));
            // Query params start with ? and should be formatted as ?key=value
            // Regular params should be formatted as =key=value
            if (str_starts_with($key, '?')) {
                $this->write($key . '=' . $value, $isLast);
            } else {
                $this->write('=' . $key . '=' . $value, $isLast);
            }
        }
        
        return $this->read();
    }
    
    private function parseResponse(array $raw): array {
        $result = [];
        $current = [];
        
        foreach ($raw as $line) {
            if ($line === '!re') {
                if (!empty($current)) {
                    $result[] = $current;
                }
                $current = [];
            } elseif ($line === '!done' || $line === '!trap' || $line === '!fatal') {
                if (!empty($current)) {
                    $result[] = $current;
                }
            } elseif (str_starts_with($line, '=')) {
                $parts = explode('=', substr($line, 1), 2);
                if (count($parts) === 2) {
                    $current[$parts[0]] = $parts[1];
                }
            }
        }
        
        return $result;
    }
    
    public function getPPPoESecrets(): array {
        return $this->command('/ppp/secret/print');
    }
    
    public function addPPPoESecret(string $name, string $password, string $profile = 'default', ?string $remoteAddress = null): array {
        $params = [
            'name' => $name,
            'password' => $password,
            'profile' => $profile,
            'service' => 'pppoe'
        ];
        
        if ($remoteAddress) {
            $params['remote-address'] = $remoteAddress;
        }
        
        return $this->command('/ppp/secret/add', $params);
    }
    
    public function removePPPoESecret(string $name): array {
        $secrets = $this->command('/ppp/secret/print', ['?name' => $name]);
        
        if (!empty($secrets) && isset($secrets[0]['.id'])) {
            return $this->command('/ppp/secret/remove', ['.id' => $secrets[0]['.id']]);
        }
        
        return ['error' => 'Secret not found'];
    }
    
    public function updatePPPoESecret(string $name, array $updates): array {
        $secrets = $this->command('/ppp/secret/print', ['?name' => $name]);
        
        if (!empty($secrets) && isset($secrets[0]['.id'])) {
            $params = array_merge(['.id' => $secrets[0]['.id']], $updates);
            return $this->command('/ppp/secret/set', $params);
        }
        
        return ['error' => 'Secret not found'];
    }
    
    public function getActivePPPoE(): array {
        return $this->command('/ppp/active/print');
    }
    
    public function disconnectPPPoE(string $name): array {
        $active = $this->command('/ppp/active/print', ['?name' => $name]);
        
        if (!empty($active) && isset($active[0]['.id'])) {
            return $this->command('/ppp/active/remove', ['.id' => $active[0]['.id']]);
        }
        
        return ['error' => 'Active session not found'];
    }
    
    public function getProfiles(): array {
        return $this->command('/ppp/profile/print');
    }
    
    public function addProfile(string $name, string $rateLimit, ?string $addressPool = null): array {
        $params = [
            'name' => $name,
            'rate-limit' => $rateLimit
        ];
        
        if ($addressPool) {
            $params['local-address'] = $addressPool;
            $params['remote-address'] = $addressPool;
        }
        
        return $this->command('/ppp/profile/add', $params);
    }
    
    public function getIPPools(): array {
        return $this->command('/ip/pool/print');
    }
    
    public function getSystemResources(): array {
        return $this->command('/system/resource/print');
    }
    
    public function getInterfaces(): array {
        return $this->command('/interface/print');
    }
    
    public function getInterfaceStats(string $interface): array {
        $result = $this->command('/interface/print', [
            '?name' => $interface,
            '.proplist' => '.id,name,type,rx-byte,tx-byte,rx-packet,tx-packet,running'
        ]);
        return $result[0] ?? [];
    }
    
    public function getPPPoESessionTraffic(string $username): array {
        $interfaceName = '<pppoe-' . $username . '>';
        $sessions = $this->command('/ppp/active/print', ['?name' => $username]);
        
        if (empty($sessions)) {
            return ['error' => 'Session not found', 'online' => false];
        }
        
        $session = $sessions[0];
        $interfaceStats = $this->command('/interface/print', [
            '?name' => $interfaceName
        ]);
        
        if (!empty($interfaceStats)) {
            $stats = $interfaceStats[0];
            return [
                'online' => true,
                'username' => $username,
                'uptime' => $session['uptime'] ?? '0s',
                'caller_id' => $session['caller-id'] ?? '',
                'address' => $session['address'] ?? '',
                'rx_bytes' => (int)($stats['rx-byte'] ?? 0),
                'tx_bytes' => (int)($stats['tx-byte'] ?? 0),
                'rx_packets' => (int)($stats['rx-packet'] ?? 0),
                'tx_packets' => (int)($stats['tx-packet'] ?? 0),
                'timestamp' => time() * 1000
            ];
        }
        
        return [
            'online' => true,
            'username' => $username,
            'uptime' => $session['uptime'] ?? '0s',
            'caller_id' => $session['caller-id'] ?? '',
            'address' => $session['address'] ?? '',
            'rx_bytes' => 0,
            'tx_bytes' => 0,
            'timestamp' => time() * 1000
        ];
    }
    
    public function getDHCPLeaseTraffic(string $macAddress): array {
        $leases = $this->command('/ip/dhcp-server/lease/print', ['?mac-address' => strtoupper($macAddress)]);
        
        if (empty($leases)) {
            return ['error' => 'DHCP lease not found', 'online' => false];
        }
        
        $lease = $leases[0];
        $ip = $lease['address'] ?? '';
        
        if (!$ip) {
            return ['error' => 'No IP assigned', 'online' => false];
        }
        
        $queues = $this->command('/queue/simple/print', ['?target' => $ip . '/32']);
        
        if (!empty($queues)) {
            $queue = $queues[0];
            $bytesStr = $queue['bytes'] ?? '0/0';
            $parts = explode('/', $bytesStr);
            return [
                'online' => true,
                'ip' => $ip,
                'mac' => $macAddress,
                'rx_bytes' => (int)($parts[0] ?? 0),
                'tx_bytes' => (int)($parts[1] ?? 0),
                'timestamp' => time() * 1000
            ];
        }
        
        return [
            'online' => true,
            'ip' => $ip,
            'mac' => $macAddress,
            'rx_bytes' => 0,
            'tx_bytes' => 0,
            'timestamp' => time() * 1000
        ];
    }
    
    public function getHotspotUsers(): array {
        return $this->command('/ip/hotspot/user/print');
    }
    
    public function addHotspotUser(string $name, string $password, string $profile = 'default', ?string $limitUptime = null): array {
        $params = [
            'name' => $name,
            'password' => $password,
            'profile' => $profile
        ];
        
        if ($limitUptime) {
            $params['limit-uptime'] = $limitUptime;
        }
        
        return $this->command('/ip/hotspot/user/add', $params);
    }
    
    public function getActiveHotspot(): array {
        return $this->command('/ip/hotspot/active/print');
    }
    
    public function getAddressListEntries(string $list): array {
        return $this->command('/ip/firewall/address-list/print', ['?list' => $list]);
    }
    
    public function addAddressListEntry(string $list, string $address, ?string $comment = null, ?string $timeout = null): array {
        $params = [
            'list' => $list,
            'address' => $address
        ];
        
        if ($comment) {
            $params['comment'] = $comment;
        }
        
        if ($timeout) {
            $params['timeout'] = $timeout;
        }
        
        return $this->command('/ip/firewall/address-list/add', $params);
    }
    
    public function removeAddressListEntry(string $list, string $address): bool {
        $entries = $this->command('/ip/firewall/address-list/print', [
            '?list' => $list,
            '?address' => $address
        ]);
        
        if (empty($entries)) {
            return true;
        }
        
        foreach ($entries as $entry) {
            if (isset($entry['.id'])) {
                $this->command('/ip/firewall/address-list/remove', ['.id' => $entry['.id']]);
            }
        }
        
        return true;
    }
    
    public function addressListEntryExists(string $list, string $address): bool {
        $entries = $this->command('/ip/firewall/address-list/print', [
            '?list' => $list,
            '?address' => $address
        ]);
        
        return !empty($entries);
    }
    
    public function setAddressListEntryDisabled(string $list, string $address, bool $disabled): bool {
        $entries = $this->command('/ip/firewall/address-list/print', [
            '?list' => $list,
            '?address' => $address
        ]);
        
        if (empty($entries)) {
            return false;
        }
        
        foreach ($entries as $entry) {
            if (isset($entry['.id'])) {
                $this->command('/ip/firewall/address-list/set', [
                    '.id' => $entry['.id'],
                    'disabled' => $disabled ? 'yes' : 'no'
                ]);
            }
        }
        
        return true;
    }
    
    public function addToBlockedList(string $address, string $comment = '', string $listName = 'DISABLED_USERS'): bool {
        if ($this->addressListEntryExists($listName, $address)) {
            return $this->setAddressListEntryDisabled($listName, $address, false);
        }
        
        $result = $this->addAddressListEntry($listName, $address, $comment);
        return !isset($result['error']);
    }
    
    public function removeFromBlockedList(string $address, string $listName = 'DISABLED_USERS'): bool {
        return $this->removeAddressListEntry($listName, $address);
    }
    
    public function syncBlockedList(array $blockedAddresses, string $listName = 'DISABLED_USERS'): array {
        $results = ['added' => 0, 'removed' => 0, 'errors' => []];
        
        $currentEntries = $this->getAddressListEntries($listName);
        $currentAddresses = [];
        
        foreach ($currentEntries as $entry) {
            if (isset($entry['address'])) {
                $currentAddresses[$entry['address']] = $entry['.id'] ?? null;
            }
        }
        
        foreach ($blockedAddresses as $item) {
            $address = is_array($item) ? $item['address'] : $item;
            $comment = is_array($item) ? ($item['comment'] ?? '') : '';
            
            if (!isset($currentAddresses[$address])) {
                $result = $this->addAddressListEntry($listName, $address, $comment);
                if (!isset($result['error'])) {
                    $results['added']++;
                } else {
                    $results['errors'][] = "Failed to add: $address";
                }
            }
            unset($currentAddresses[$address]);
        }
        
        foreach ($currentAddresses as $address => $id) {
            if ($this->removeAddressListEntry($listName, $address)) {
                $results['removed']++;
            } else {
                $results['errors'][] = "Failed to remove: $address";
            }
        }
        
        return $results;
    }
    
    // ==================== VLAN Management ====================
    
    public function getVlans(): array {
        return $this->command('/interface/vlan/print');
    }
    
    public function getVlan(int $vlanId): ?array {
        $vlans = $this->command('/interface/vlan/print', ['?vlan-id' => (string)$vlanId]);
        return $vlans[0] ?? null;
    }
    
    public function createVlan(string $name, int $vlanId, string $interface, ?string $comment = null): array {
        $params = [
            'name' => $name,
            'vlan-id' => (string)$vlanId,
            'interface' => $interface
        ];
        if ($comment) {
            $params['comment'] = $comment;
        }
        return $this->command('/interface/vlan/add', $params);
    }
    
    public function removeVlan(string $name): bool {
        $vlans = $this->command('/interface/vlan/print', ['?name' => $name]);
        if (empty($vlans) || !isset($vlans[0]['.id'])) {
            return false;
        }
        $this->command('/interface/vlan/remove', ['.id' => $vlans[0]['.id']]);
        return true;
    }
    
    public function setVlanDisabled(string $name, bool $disabled): bool {
        $vlans = $this->command('/interface/vlan/print', ['?name' => $name]);
        if (empty($vlans) || !isset($vlans[0]['.id'])) {
            return false;
        }
        $this->command('/interface/vlan/set', [
            '.id' => $vlans[0]['.id'],
            'disabled' => ($disabled ? 'yes' : 'no')
        ]);
        return true;
    }
    
    // ==================== IP Address Management ====================
    
    public function getIpAddresses(?string $interface = null): array {
        $params = $interface ? ['?interface' => $interface] : [];
        return $this->command('/ip/address/print', $params);
    }
    
    public function addIpAddress(string $address, string $interface, ?string $comment = null, bool $disabled = false): array {
        $params = [
            'address' => $address,
            'interface' => $interface,
            'disabled' => ($disabled ? 'yes' : 'no')
        ];
        if ($comment) {
            $params['comment'] = $comment;
        }
        return $this->command('/ip/address/add', $params);
    }
    
    public function removeIpAddress(string $address): bool {
        $addresses = $this->command('/ip/address/print', ['?address' => $address]);
        if (empty($addresses) || !isset($addresses[0]['.id'])) {
            return false;
        }
        $this->command('/ip/address/remove', ['.id' => $addresses[0]['.id']]);
        return true;
    }
    
    // ==================== DHCP Server Management ====================
    
    public function getDhcpServers(): array {
        return $this->command('/ip/dhcp-server/print');
    }
    
    public function createDhcpServer(string $name, string $interface, string $addressPool, ?string $leaseTime = '1d'): array {
        return $this->command('/ip/dhcp-server/add', [
            'name' => $name,
            'interface' => $interface,
            'address-pool' => $addressPool,
            'lease-time' => $leaseTime,
            'disabled' => 'no'
        ]);
    }
    
    public function removeDhcpServer(string $name): bool {
        $servers = $this->command('/ip/dhcp-server/print', ['?name' => $name]);
        if (empty($servers) || !isset($servers[0]['.id'])) {
            return false;
        }
        $this->command('/ip/dhcp-server/remove', ['.id' => $servers[0]['.id']]);
        return true;
    }
    
    // ==================== DHCP Network ====================
    
    public function getDhcpNetworks(): array {
        return $this->command('/ip/dhcp-server/network/print');
    }
    
    public function addDhcpNetwork(string $address, string $gateway, ?string $dnsServer = null, ?string $comment = null): array {
        $params = [
            'address' => $address,
            'gateway' => $gateway
        ];
        if ($dnsServer) {
            $params['dns-server'] = $dnsServer;
        }
        if ($comment) {
            $params['comment'] = $comment;
        }
        return $this->command('/ip/dhcp-server/network/add', $params);
    }
    
    public function removeDhcpNetwork(string $address): bool {
        $networks = $this->command('/ip/dhcp-server/network/print', ['?address' => $address]);
        if (empty($networks) || !isset($networks[0]['.id'])) {
            return false;
        }
        $this->command('/ip/dhcp-server/network/remove', ['.id' => $networks[0]['.id']]);
        return true;
    }
    
    // ==================== DHCP Lease Management ====================
    
    public function getDhcpLeases(?string $server = null): array {
        $params = $server ? ['?server' => $server] : [];
        return $this->command('/ip/dhcp-server/lease/print', $params);
    }
    
    public function getDhcpLeaseByMac(string $macAddress): ?array {
        $leases = $this->command('/ip/dhcp-server/lease/print', ['?mac-address' => strtoupper($macAddress)]);
        return $leases[0] ?? null;
    }
    
    public function getDhcpLeaseByIp(string $ipAddress): ?array {
        $leases = $this->command('/ip/dhcp-server/lease/print', ['?address' => $ipAddress]);
        return $leases[0] ?? null;
    }
    
    public function createDhcpLease(string $ipAddress, string $macAddress, string $server, ?string $comment = null): array {
        $params = [
            'address' => $ipAddress,
            'mac-address' => strtoupper($macAddress),
            'server' => $server
        ];
        if ($comment) {
            $params['comment'] = $comment;
        }
        return $this->command('/ip/dhcp-server/lease/add', $params);
    }
    
    public function updateDhcpLease(string $id, array $updates): array {
        $params = ['.id' => $id];
        foreach ($updates as $key => $value) {
            $params[$key] = $value;
        }
        return $this->command('/ip/dhcp-server/lease/set', $params);
    }
    
    public function removeDhcpLease(string $ipAddress): bool {
        $leases = $this->command('/ip/dhcp-server/lease/print', ['?address' => $ipAddress]);
        if (empty($leases) || !isset($leases[0]['.id'])) {
            return false;
        }
        $this->command('/ip/dhcp-server/lease/remove', ['.id' => $leases[0]['.id']]);
        return true;
    }
    
    public function removeDhcpLeaseByMac(string $macAddress): bool {
        $leases = $this->command('/ip/dhcp-server/lease/print', ['?mac-address' => strtoupper($macAddress)]);
        if (empty($leases) || !isset($leases[0]['.id'])) {
            return false;
        }
        $this->command('/ip/dhcp-server/lease/remove', ['.id' => $leases[0]['.id']]);
        return true;
    }
    
    // ==================== Static ARP Management ====================
    
    public function getArpEntries(?string $interface = null): array {
        $params = $interface ? ['?interface' => $interface] : [];
        return $this->command('/ip/arp/print', $params);
    }
    
    public function addStaticArp(string $ipAddress, string $macAddress, string $interface, ?string $comment = null): array {
        $params = [
            'address' => $ipAddress,
            'mac-address' => strtoupper($macAddress),
            'interface' => $interface
        ];
        if ($comment) {
            $params['comment'] = $comment;
        }
        return $this->command('/ip/arp/add', $params);
    }
    
    public function removeArpEntry(string $ipAddress): bool {
        $entries = $this->command('/ip/arp/print', ['?address' => $ipAddress]);
        if (empty($entries) || !isset($entries[0]['.id'])) {
            return false;
        }
        $this->command('/ip/arp/remove', ['.id' => $entries[0]['.id']]);
        return true;
    }
    
    public function getEthernetInterfaces(): array {
        return $this->command('/interface/ethernet/print');
    }
    
    public function getBridges(): array {
        return $this->command('/interface/bridge/print');
    }
    
    public function __destruct() {
        $this->disconnect();
    }
}
