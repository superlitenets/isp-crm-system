<?php
namespace App;

class MikroTikAPI {
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private $socket;
    private bool $connected = false;
    private int $timeout = 10;
    
    public function __construct(string $host, int $port = 8728, string $username = 'admin', string $password = '') {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
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
        if ($this->socket) {
            fclose($this->socket);
            $this->connected = false;
        }
    }
    
    private function write(string $word, bool $end = true): void {
        $len = strlen($word);
        
        if ($len < 0x80) {
            fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            fwrite($this->socket, chr(($len >> 8) | 0x80) . chr($len & 0xFF));
        } elseif ($len < 0x200000) {
            fwrite($this->socket, chr(($len >> 16) | 0xC0) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } elseif ($len < 0x10000000) {
            fwrite($this->socket, chr(($len >> 24) | 0xE0) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } else {
            fwrite($this->socket, chr(0xF0) . chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        }
        
        fwrite($this->socket, $word);
        
        if ($end) {
            fwrite($this->socket, chr(0));
        }
    }
    
    private function read(): array {
        $response = [];
        $receivedDone = false;
        
        while (!$receivedDone) {
            $word = $this->readWord();
            
            if ($word === false || $word === '') {
                break;
            }
            
            $response[] = $word;
            
            if ($word === '!done' || $word === '!trap' || $word === '!fatal') {
                $receivedDone = true;
            }
        }
        
        return $response;
    }
    
    private function readWord(): string|false {
        $byte = fread($this->socket, 1);
        if ($byte === false || $byte === '') return false;
        
        $len = ord($byte);
        
        if ($len < 0x80) {
        } elseif ($len < 0xC0) {
            $len = (($len & 0x3F) << 8) + ord(fread($this->socket, 1));
        } elseif ($len < 0xE0) {
            $len = (($len & 0x1F) << 16) + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
        } elseif ($len < 0xF0) {
            $len = (($len & 0x0F) << 24) + (ord(fread($this->socket, 1)) << 16) + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
        } else {
            $len = (ord(fread($this->socket, 1)) << 24) + (ord(fread($this->socket, 1)) << 16) + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
        }
        
        if ($len === 0) return '';
        
        return fread($this->socket, $len);
    }
    
    public function command(string $cmd, array $params = []): array {
        if (!$this->connected) {
            $this->connect();
        }
        
        $this->write($cmd, empty($params));
        
        foreach ($params as $key => $value) {
            $isLast = ($key === array_key_last($params));
            $this->write('=' . $key . '=' . $value, $isLast);
        }
        
        return $this->parseResponse($this->read());
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
    
    public function addToBlockedList(string $address, string $comment = '', string $listName = 'crm-blocked'): bool {
        if ($this->addressListEntryExists($listName, $address)) {
            return $this->setAddressListEntryDisabled($listName, $address, false);
        }
        
        $result = $this->addAddressListEntry($listName, $address, $comment);
        return !isset($result['error']);
    }
    
    public function removeFromBlockedList(string $address, string $listName = 'crm-blocked'): bool {
        return $this->removeAddressListEntry($listName, $address);
    }
    
    public function syncBlockedList(array $blockedAddresses, string $listName = 'crm-blocked'): array {
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
    
    public function __destruct() {
        $this->disconnect();
    }
}
