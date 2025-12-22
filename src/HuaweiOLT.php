<?php
namespace App;

use phpseclib3\Net\SSH2;

class HuaweiOLT {
    private \PDO $db;
    private string $encryptionKey;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
        $this->encryptionKey = $this->getEncryptionKey();
    }
    
    private function getEncryptionKey(): string {
        $key = getenv('SESSION_SECRET');
        if (empty($key)) {
            $key = $_ENV['SESSION_SECRET'] ?? '';
        }
        if (empty($key)) {
            throw new \RuntimeException('SESSION_SECRET environment variable is required for OLT credential encryption');
        }
        return $key;
    }
    
    private function encrypt(string $data): string {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    private function decrypt(string $data): string {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv) ?: '';
    }
    
    private function castBoolean($value): string {
        if ($value === '' || $value === null || $value === false || $value === 0 || $value === '0') {
            return 'false';
        }
        if ($value === true || $value === 1) {
            return 'true';
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? 'true' : 'false';
        }
        return $value ? 'true' : 'false';
    }
    
    // ==================== OLT Management ====================
    
    public function getOLTs(bool $activeOnly = true): array {
        $sql = "SELECT * FROM huawei_olts";
        if ($activeOnly) {
            $sql .= " WHERE is_active = TRUE";
        }
        $sql .= " ORDER BY name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getOLT(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM huawei_olts WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    public function addOLT(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO huawei_olts (name, ip_address, port, connection_type, username, password_encrypted, 
                                     snmp_read_community, snmp_write_community, snmp_version, snmp_port, vendor, model, location, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['ip_address'],
            $data['port'] ?? 23,
            $data['connection_type'] ?? 'telnet',
            $data['username'] ?? '',
            !empty($data['password']) ? $this->encrypt($data['password']) : '',
            $data['snmp_read_community'] ?? 'public',
            $data['snmp_write_community'] ?? 'private',
            $data['snmp_version'] ?? 'v2c',
            $data['snmp_port'] ?? 161,
            $data['vendor'] ?? 'Huawei',
            $data['model'] ?? '',
            $data['location'] ?? '',
            $this->castBoolean($data['is_active'] ?? true)
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateOLT(int $id, array $data): bool {
        $fields = ['name', 'ip_address', 'port', 'connection_type', 'username', 
                   'snmp_read_community', 'snmp_write_community', 'snmp_version', 'snmp_port', 'vendor', 'model', 'location'];
        $booleanFields = ['is_active'];
        $updates = [];
        $params = [];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $this->castBoolean($data[$field]);
            }
        }
        
        if (!empty($data['password'])) {
            $updates[] = "password_encrypted = ?";
            $params[] = $this->encrypt($data['password']);
        }
        
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $stmt = $this->db->prepare("UPDATE huawei_olts SET " . implode(', ', $updates) . " WHERE id = ?");
        return $stmt->execute($params);
    }
    
    public function deleteOLT(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM huawei_olts WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function testConnection(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'message' => 'OLT not found'];
        }
        
        $password = !empty($olt['password_encrypted']) ? $this->decrypt($olt['password_encrypted']) : '';
        
        if ($olt['connection_type'] === 'telnet') {
            return $this->testTelnetConnection($olt['ip_address'], $olt['port'], $olt['username'], $password);
        } elseif ($olt['connection_type'] === 'ssh') {
            return $this->testSSHConnection($olt['ip_address'], $olt['port'], $olt['username'], $password);
        } elseif ($olt['connection_type'] === 'snmp') {
            return $this->testSNMPConnection($olt['ip_address'], $olt['snmp_community'] ?? $olt['snmp_read_community'] ?? 'public', $olt['snmp_port']);
        }
        
        return ['success' => false, 'message' => 'Unknown connection type'];
    }
    
    private function testTelnetConnection(string $ip, int $port, string $username, string $password): array {
        $timeout = 5;
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        
        if (!$socket) {
            return ['success' => false, 'message' => "Connection failed: {$errstr}"];
        }
        
        stream_set_timeout($socket, $timeout);
        $banner = fread($socket, 1024);
        fclose($socket);
        
        return [
            'success' => true,
            'message' => 'Telnet connection successful',
            'banner' => substr($banner, 0, 200)
        ];
    }
    
    private function testSSHConnection(string $ip, int $port, string $username, string $password): array {
        if (!function_exists('ssh2_connect')) {
            $socket = @fsockopen($ip, $port, $errno, $errstr, 5);
            if (!$socket) {
                return ['success' => false, 'message' => "SSH port check failed: {$errstr}"];
            }
            fclose($socket);
            return ['success' => true, 'message' => 'SSH port is open (full SSH requires php-ssh2 extension)'];
        }
        
        $connection = @ssh2_connect($ip, $port);
        if (!$connection) {
            return ['success' => false, 'message' => 'SSH connection failed'];
        }
        
        if (!@ssh2_auth_password($connection, $username, $password)) {
            return ['success' => false, 'message' => 'SSH authentication failed'];
        }
        
        return ['success' => true, 'message' => 'SSH connection successful'];
    }
    
    private function testSNMPConnection(string $ip, string $community, int $port): array {
        if (!function_exists('snmpget')) {
            $socket = @fsockopen("udp://{$ip}", $port, $errno, $errstr, 2);
            if (!$socket) {
                return ['success' => false, 'message' => "SNMP port check failed: {$errstr}"];
            }
            fclose($socket);
            return ['success' => true, 'message' => 'SNMP port is open (full SNMP requires php-snmp extension)'];
        }
        
        $sysDescr = @snmpget($ip, $community, '1.3.6.1.2.1.1.1.0', 2000000, 1);
        if ($sysDescr === false) {
            return ['success' => false, 'message' => 'SNMP query failed - check community string'];
        }
        
        return ['success' => true, 'message' => 'SNMP connection successful', 'sysDescr' => $sysDescr];
    }
    
    // ==================== SNMP Read/Write Operations ====================
    
    public function snmpRead(int $oltId, string $oid, int $timeout = 2000000, int $retries = 2): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        $community = $olt['snmp_community'] ?? $olt['snmp_read_community'] ?? 'public';
        $host = $olt['ip_address'];
        $port = $olt['snmp_port'] ?? 161;
        
        // Try PHP extension first, fall back to CLI
        if (function_exists('snmpget')) {
            $result = @snmpget("{$host}:{$port}", $community, $oid, $timeout, $retries);
            if ($result !== false) {
                return ['success' => true, 'value' => $result, 'oid' => $oid];
            }
        }
        
        // Fallback to CLI snmpget
        $timeoutSec = max(1, intval($timeout / 1000000));
        $cmd = sprintf(
            'snmpget -v 2c -c %s -t %d -r %d %s:%d %s 2>&1',
            escapeshellarg($community),
            $timeoutSec,
            $retries,
            escapeshellarg($host),
            $port,
            escapeshellarg($oid)
        );
        
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $result = implode("\n", $output);
            // Parse SNMP output format: OID = TYPE: value
            if (preg_match('/=\s*(?:STRING:|INTEGER:|Timeticks:|Gauge32:|Counter32:|OID:)?\s*(.+)$/i', $result, $m)) {
                return ['success' => true, 'value' => trim($m[1], '"'), 'oid' => $oid, 'raw' => $result];
            }
            return ['success' => true, 'value' => $result, 'oid' => $oid];
        }
        
        return ['success' => false, 'error' => 'SNMP read failed for OID: ' . $oid];
    }
    
    public function snmpWrite(int $oltId, string $oid, string $type, $value, int $timeout = 2000000, int $retries = 2): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        if (!function_exists('snmpset')) {
            return ['success' => false, 'error' => 'PHP SNMP extension not installed'];
        }
        
        $community = $olt['snmp_write_community'] ?? 'private';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        
        $result = @snmpset($host, $community, $oid, $type, $value, $timeout, $retries);
        
        if ($result === false) {
            return ['success' => false, 'error' => 'SNMP write failed for OID: ' . $oid];
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'snmp_write',
            'status' => 'success',
            'message' => "SNMP write: {$oid} = {$value}",
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return ['success' => true, 'oid' => $oid, 'value' => $value];
    }
    
    public function snmpWalk(int $oltId, string $oid, int $timeout = 5000000, int $retries = 2): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        $community = $olt['snmp_community'] ?? $olt['snmp_read_community'] ?? 'public';
        $host = $olt['ip_address'];
        $port = $olt['snmp_port'] ?? 161;
        
        // Try PHP extension first
        if (function_exists('snmprealwalk')) {
            $result = @snmprealwalk("{$host}:{$port}", $community, $oid, $timeout, $retries);
            if ($result !== false) {
                return ['success' => true, 'data' => $result, 'count' => count($result)];
            }
        }
        
        // Fallback to CLI snmpwalk
        $timeoutSec = max(1, intval($timeout / 1000000));
        $cmd = sprintf(
            'snmpwalk -v 2c -c %s -t %d -r %d %s:%d %s 2>&1',
            escapeshellarg($community),
            $timeoutSec,
            $retries,
            escapeshellarg($host),
            $port,
            escapeshellarg($oid)
        );
        
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $data = [];
            foreach ($output as $line) {
                // Parse: OID = TYPE: value
                if (preg_match('/^([^\s=]+)\s*=\s*(?:STRING:|INTEGER:|Timeticks:|Gauge32:|Counter32:|OID:|Hex-STRING:)?\s*(.+)$/i', $line, $m)) {
                    $data[$m[1]] = trim($m[2], '"');
                }
            }
            return ['success' => true, 'data' => $data, 'count' => count($data)];
        }
        
        return ['success' => false, 'error' => 'SNMP walk failed for OID: ' . $oid];
    }
    
    public function getOLTSystemInfoViaSNMP(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        $systemOIDs = [
            'sysDescr' => '1.3.6.1.2.1.1.1.0',
            'sysObjectID' => '1.3.6.1.2.1.1.2.0',
            'sysUpTime' => '1.3.6.1.2.1.1.3.0',
            'sysContact' => '1.3.6.1.2.1.1.4.0',
            'sysName' => '1.3.6.1.2.1.1.5.0',
            'sysLocation' => '1.3.6.1.2.1.1.6.0',
        ];
        
        $info = [];
        $anySuccess = false;
        foreach ($systemOIDs as $name => $oid) {
            $result = $this->snmpRead($oltId, $oid, 5000000, 2);
            if ($result['success']) {
                $info[$name] = $this->cleanSnmpValue($result['value']);
                $anySuccess = true;
            } else {
                $info[$name] = null;
            }
        }
        
        if ($anySuccess) {
            $this->db->prepare("UPDATE huawei_olts SET last_sync_at = CURRENT_TIMESTAMP, last_status = 'online' WHERE id = ?")->execute([$oltId]);
            return ['success' => true, 'info' => $info];
        }
        
        return ['success' => false, 'error' => 'SNMP query failed - no response from OLT', 'info' => $info];
    }
    
    public function setOLTSystemInfoViaSNMP(int $oltId, string $field, string $value): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        $writableOIDs = [
            'sysContact' => '1.3.6.1.2.1.1.4.0',
            'sysName' => '1.3.6.1.2.1.1.5.0',
            'sysLocation' => '1.3.6.1.2.1.1.6.0',
        ];
        
        if (!isset($writableOIDs[$field])) {
            return ['success' => false, 'error' => 'Field not writable: ' . $field];
        }
        
        return $this->snmpWrite($oltId, $writableOIDs[$field], 's', $value);
    }
    
    public function getONUListViaSNMP(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        if (!function_exists('snmpwalk')) {
            return ['success' => false, 'error' => 'PHP SNMP extension not installed'];
        }
        
        $community = $olt['snmp_community'] ?? $olt['snmp_read_community'] ?? 'public';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        
        // Correct Huawei MA5680T OIDs:
        // .43.1.9 = hwGponDeviceOntSn (Serial Number)
        // .43.1.3 = hwGponOntOpticalDdmRxPower (RX Power - NOT serial!)
        // .46.1.15 = hwGponDeviceOntControlRunStatus (Status)
        // .43.1.2 = hwGponDeviceOntDespt (Description)
        $huaweiONTSerialBase = '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.9';
        $huaweiONTStatusBase = '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.15';
        $huaweiONTDescBase = '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.2';
        
        $serials = @snmprealwalk($host, $community, $huaweiONTSerialBase, 10000000, 2);
        
        // Debug: log what we got with first few entries for analysis
        error_log("SNMP Serial Walk Result for OID {$huaweiONTSerialBase}: " . ($serials === false ? 'FAILED' : count($serials) . ' entries'));
        if ($serials !== false && count($serials) > 0) {
            $count = 0;
            foreach ($serials as $oid => $val) {
                error_log("SNMP Entry: {$oid} = {$val}");
                if (++$count >= 3) break;
            }
        }
        
        // If .43.1.9 returns nothing, try the description OID which also uses 4-part index
        if ($serials === false || empty($serials)) {
            // Try hwGponDeviceOntDespt (description) table - same index format as serial
            $altSerialOid = '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.2';
            $descriptions = @snmprealwalk($host, $community, $altSerialOid, 10000000, 2);
            error_log("Fallback SNMP Walk for Descriptions .43.1.2: " . ($descriptions === false ? 'FAILED' : count($descriptions) . ' entries'));
            
            if ($descriptions !== false && count($descriptions) > 0) {
                // We got descriptions - now we need to get serials for each using the same index
                $serials = [];
                foreach ($descriptions as $oid => $desc) {
                    $indexPart = substr($oid, strlen($altSerialOid) + 1);
                    // Try to get serial using same index
                    $serialOid = $huaweiONTSerialBase . '.' . $indexPart;
                    $sn = @snmpget($host, $community, $serialOid, 5000000);
                    if ($sn !== false) {
                        $serials[$serialOid] = $sn;
                    }
                }
                error_log("Retrieved " . count($serials) . " serials using description indexes");
            }
        }
        
        if ($serials === false || empty($serials)) {
            return ['success' => false, 'error' => 'Failed to get ONU list via SNMP. No data returned from OID .43.1.9 or .43.1.3'];
        }
        
        $statuses = @snmprealwalk($host, $community, $huaweiONTStatusBase, 10000000, 2);
        $descriptions = @snmprealwalk($host, $community, $huaweiONTDescBase, 10000000, 2);
        
        $onus = [];
        foreach ($serials as $oid => $serial) {
            $indexPart = substr($oid, strlen($huaweiONTSerialBase) + 1);
            $parts = explode('.', $indexPart);
            
            $frame = 0;
            $slot = 0;
            $port = 0;
            $onuId = 0;
            
            // MA5680T can use different index formats depending on the OID table
            if (count($parts) >= 4) {
                // 4-part index: frame.slot.port.onu_id (e.g., 0.1.3.12)
                $frame = (int)$parts[0];
                $slot = (int)$parts[1];
                $port = (int)$parts[2];
                $onuId = (int)$parts[3];
            } elseif (count($parts) >= 2) {
                // 2-part index: ifIndex.onuId (Huawei uses ifIndex encoding)
                $ifIndex = (int)$parts[0];
                $onuId = (int)$parts[1];
                
                // Huawei ifIndex bit-mask decoding for GPON interfaces
                // Large ifIndex (like 4194320384 = 0xFA004000) format:
                // - Byte 3 (bits 24-31): 0xFA = interface type (GPON)
                // - Lower 24 bits: ponIndex encoding
                
                if ($ifIndex > 0xFFFFFF) {
                    // Strip interface type prefix (0xFA...) to get ponIndex
                    $ponIndex = $ifIndex & 0xFFFFFF;
                } else {
                    $ponIndex = $ifIndex;
                }
                
                // MA5683T/MA5680T ponIndex encoding (verified):
                // ponIndex bit layout:
                // - Bits 13-15: port (0-7)
                // - Bits 8-12: slot (0-21)
                // - Bits 0-7: reserved/frame
                if ($ponIndex > 0) {
                    $port = ($ponIndex >> 13) & 0x7;   // Bits 13-15
                    $slot = ($ponIndex >> 8) & 0x1F;   // Bits 8-12
                    $frame = $ponIndex & 0xFF;         // Bits 0-7 (usually 0)
                    
                    // If frame seems like it contains encoded data, try alternate layout
                    if ($frame > 7) {
                        // Some firmware uses: slot*256 + port
                        $slot = (int)floor($ponIndex / 256);
                        $port = $ponIndex % 256;
                        $frame = 0;
                        
                        // If still invalid, try: slot*8 + port
                        if ($slot > 21 || $port > 15) {
                            $slot = (int)floor($ponIndex / 8);
                            $port = $ponIndex % 8;
                        }
                    }
                }
                
                // Sanity check - valid ranges for MA5683T
                if ($frame > 7) $frame = 0;
                if ($slot > 21) $slot = 0;
                if ($port > 15) $port = 0;
            } else {
                continue; // Skip invalid entries
            }
            
            $statusOid = $huaweiONTStatusBase . '.' . $indexPart;
            $status = isset($statuses[$statusOid]) ? $this->parseONUStatus((int)$this->cleanSnmpValue($statuses[$statusOid])) : 'unknown';
            
            $descOid = $huaweiONTDescBase . '.' . $indexPart;
            $desc = isset($descriptions[$descOid]) ? $this->cleanSnmpValue($descriptions[$descOid]) : '';
            
            $onus[] = [
                'sn' => $this->cleanSnmpValue($serial),
                'frame' => $frame,
                'slot' => $slot,
                'port' => $port,
                'onu_id' => $onuId,
                'status' => $status,
                'description' => $desc,
                'index' => $indexPart
            ];
        }
        
        error_log("Parsed " . count($onus) . " ONUs from SNMP");
        
        return ['success' => true, 'onus' => $onus, 'count' => count($onus)];
    }
    
    public function getONUOpticalInfoViaSNMP(int $oltId, int $frame, int $slot, int $port, int $onuId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        // Try CLI first (faster and more reliable when SNMP port is blocked)
        $cliResult = $this->getONUOpticalInfoViaCLI($oltId, $frame, $slot, $port, $onuId);
        if ($cliResult['success'] && !empty($cliResult['optical']) && 
            ($cliResult['optical']['rx_power'] !== null || $cliResult['optical']['tx_power'] !== null)) {
            return $cliResult;
        }
        
        // Fall back to SNMP if CLI didn't work
        if (!function_exists('snmpget')) {
            return $cliResult; // Return CLI result even if incomplete
        }
        
        $community = $olt['snmp_community'] ?? 'public';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        
        if (function_exists('snmp_set_quick_print')) {
            snmp_set_quick_print(true);
        }
        if (function_exists('snmp_set_valueretrieval')) {
            snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
        }
        
        // Huawei optical power OIDs - index format: ponIndex.onuId
        // ponIndex = frame*8192 + slot*256 + port
        $ponIndex = $frame * 8192 + $slot * 256 + $port;
        $indexSuffix = "{$ponIndex}.{$onuId}";
        
        // Use short timeout (500ms) and no retries since CLI is primary
        $timeout = 500000; // 500ms
        $retries = 0;
        
        // Try primary OID set only
        $rxOid = "1.3.6.1.4.1.2011.6.128.1.1.2.43.1.7.{$indexSuffix}";
        $txOid = "1.3.6.1.4.1.2011.6.128.1.1.2.43.1.8.{$indexSuffix}";
        
        $rxPower = @snmpget($host, $community, $rxOid, $timeout, $retries);
        $txPower = @snmpget($host, $community, $txOid, $timeout, $retries);
        
        $rxValue = $this->parseOpticalPower($rxPower, 1);
        $txValue = $this->parseOpticalPower($txPower, 1);
        
        // Validate range
        if ($rxValue !== null && ($rxValue < -50 || $rxValue > 10)) $rxValue = null;
        if ($txValue !== null && ($txValue < -50 || $txValue > 10)) $txValue = null;
        
        // Fetch distance via SNMP
        $distanceOID = "1.3.6.1.4.1.2011.6.128.1.1.2.43.1.5.{$indexSuffix}";
        $distanceRaw = @snmpget($host, $community, $distanceOID, $timeout, $retries);
        $distance = null;
        if ($distanceRaw !== false) {
            $cleaned = $this->cleanSnmpValue((string)$distanceRaw);
            if (is_numeric($cleaned) && (int)$cleaned > 0) {
                $distance = (int)$cleaned;
            }
        }
        
        // Fetch status via SNMP
        $statusOID = "1.3.6.1.4.1.2011.6.128.1.1.2.43.1.9.{$indexSuffix}";
        $statusRaw = @snmpget($host, $community, $statusOID, $timeout, $retries);
        $status = null;
        if ($statusRaw !== false) {
            $statusCode = (int)$this->cleanSnmpValue((string)$statusRaw);
            $statusMap = [1 => 'online', 2 => 'offline', 3 => 'los', 4 => 'power_fail', 5 => 'auth_fail'];
            $status = $statusMap[$statusCode] ?? null;
        }
        
        // Use CLI data if SNMP didn't get distance
        if ($distance === null && isset($cliResult['optical']['distance'])) {
            $distance = $cliResult['optical']['distance'];
        }
        if ($status === null && isset($cliResult['optical']['status'])) {
            $status = $cliResult['optical']['status'];
        }
        
        // Return SNMP data if we got it, otherwise return CLI result
        if ($rxValue === null && $txValue === null) {
            return $cliResult;
        }
        
        return [
            'success' => true,
            'optical' => [
                'rx_power' => $rxValue,
                'tx_power' => $txValue,
                'distance' => $distance,
                'status' => $status,
            ],
            'debug' => [
                'ponIndex' => $ponIndex,
                'index' => $indexSuffix,
                'rx_oid' => $rxOid,
                'tx_oid' => $txOid,
                'rx_raw' => $rxPower ?? false,
                'tx_raw' => $txPower ?? false,
                'distance_raw' => $distanceRaw ?? false,
                'status_raw' => $statusRaw ?? false,
                'method' => 'snmp',
            ]
        ];
    }
    
    public function getONUDistanceViaCLI(int $oltId, int $frame, int $slot, int $port, int $onuId): array {
        $command = "display ont info {$frame}/{$slot}/{$port} {$onuId}";
        $result = $this->executeCommand($oltId, $command);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'CLI command failed'];
        }
        
        $output = $result['output'] ?? '';
        $distance = null;
        $status = null;
        
        // Parse distance - formats:
        // "Distance(m)            : 1234"
        // "ONU Distance           : 1234"
        // "Ont distance(m)        : 1234"
        if (preg_match('/(?:Distance|Ont distance)\s*\(?m?\)?\s*:\s*(\d+)/i', $output, $m)) {
            $distance = (int)$m[1];
        }
        
        // Parse run state for status
        // "Run state              : online"
        if (preg_match('/Run state\s*:\s*(\w+)/i', $output, $m)) {
            $status = strtolower($m[1]);
        }
        
        return [
            'success' => true,
            'distance' => $distance,
            'status' => $status,
        ];
    }
    
    public function getONUOpticalInfoViaCLI(int $oltId, int $frame, int $slot, int $port, int $onuId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        // Huawei requires entering GPON interface context first
        // Run optical-info command for power levels (distance retrieved separately if needed)
        $command = "interface gpon {$frame}/{$slot}\r\ndisplay ont optical-info {$port} {$onuId}";
        $result = $this->executeCommand($oltId, $command);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'CLI command failed'];
        }
        
        $output = $result['output'] ?? '';
        
        $rxPower = null;
        $txPower = null;
        $temperature = null;
        $voltage = null;
        $current = null;
        $distance = null;
        $status = null;
        
        // Parse optical info from CLI output
        if (preg_match('/Rx optical power\(dBm\)\s*:\s*([-\d.]+)/i', $output, $m)) {
            $rxPower = (float)$m[1];
        }
        if (preg_match('/OLT Rx ONT optical power\(dBm\)\s*:\s*([-\d.]+)/i', $output, $m)) {
            $rxPower = (float)$m[1]; // This is what the OLT sees from ONU
        }
        if (preg_match('/Tx optical power\(dBm\)\s*:\s*([-\d.]+)/i', $output, $m)) {
            $txPower = (float)$m[1];
        }
        if (preg_match('/Temperature\(C\)\s*:\s*([-\d.]+)/i', $output, $m)) {
            $temperature = (float)$m[1];
        }
        if (preg_match('/Voltage\(V\)\s*:\s*([-\d.]+)/i', $output, $m)) {
            $voltage = (float)$m[1];
        }
        if (preg_match('/Current\(mA\)\s*:\s*([-\d.]+)/i', $output, $m)) {
            $current = (float)$m[1];
        }
        
        // Parse distance and status from ont info output (in same response)
        if (preg_match('/(?:Distance|Ont distance)\s*\(?m?\)?\s*:\s*(\d+)/i', $output, $m)) {
            $distance = (int)$m[1];
        }
        if (preg_match('/Run state\s*:\s*(\w+)/i', $output, $m)) {
            $status = strtolower($m[1]);
        }
        
        return [
            'success' => true,
            'optical' => [
                'rx_power' => $rxPower,
                'tx_power' => $txPower,
                'temperature' => $temperature,
                'voltage' => $voltage,
                'current' => $current,
                'distance' => $distance,
                'status' => $status,
            ],
            'debug' => [
                'method' => 'cli',
                'command' => $command,
                'output_length' => strlen($output),
            ]
        ];
    }
    
    public function syncONUsFromSNMP(int $oltId): array {
        $result = $this->getONUListViaSNMP($oltId);
        if (!$result['success']) {
            return $result;
        }
        
        $synced = 0;
        $added = 0;
        $updated = 0;
        
        foreach ($result['onus'] as $onu) {
            $existing = $this->getONUBySN($onu['sn']);
            
            // Generate a meaningful name from description - extract SNS code only
            $onuName = '';
            $desc = $onu['description'] ?? '';
            if (!empty($desc)) {
                // Extract SNS/SFL code (e.g., SNS000540, SFL0034) from description
                if (preg_match('/^(SNS\d+|SFL\d+)/i', $desc, $m)) {
                    $onuName = strtoupper($m[1]);
                } else {
                    // If no SNS code, use first part before underscore
                    $parts = explode('_', $desc);
                    $onuName = $parts[0];
                }
            }
            if (empty($onuName)) {
                // Fallback to location-based name
                $onuName = "Port {$onu['slot']}/{$onu['port']} ONU #{$onu['onu_id']}";
            }
            
            // ONUs visible via SNMP are already authorized on the OLT
            $data = [
                'olt_id' => $oltId,
                'sn' => $onu['sn'],
                'name' => $onuName,
                'frame' => $onu['frame'],
                'slot' => $onu['slot'],
                'port' => $onu['port'],
                'onu_id' => $onu['onu_id'],
                'status' => $onu['status'],
                'description' => $desc,
                'is_authorized' => true,
            ];
            
            try {
                if ($existing) {
                    $this->updateONU($existing['id'], $data);
                    $updated++;
                } else {
                    $this->addONU($data);
                    $added++;
                }
                $synced++;
            } catch (\Exception $e) {
                error_log("SYNC ERROR - ONU Data: " . json_encode($data));
                error_log("SYNC ERROR - Raw ONU: " . json_encode($onu));
                error_log("SYNC ERROR - Exception: " . $e->getMessage());
                throw $e;
            }
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'snmp_sync',
            'status' => 'success',
            'message' => "Synced {$synced} ONUs ({$added} new, {$updated} updated)",
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'synced' => $synced,
            'added' => $added,
            'updated' => $updated
        ];
    }
    
    public function syncONULocationsFromSNMP(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        $community = $olt['snmp_community'] ?? 'public';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        
        // Huawei stores ONU serials in different tables depending on firmware:
        // Table 43.1.3 - hwGponDeviceOntSn (MA5680T confirmed)
        // Table 46.1.3 - common on MA5800/newer MA5600
        // Table 45.1.4 - alternative location
        // Table 43.1.9 - older firmware
        $serialOids = [
            '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.3',  // hwGponDeviceOntSn - MA5680T confirmed
            '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.3',  // Device info table
            '1.3.6.1.4.1.2011.6.128.1.1.2.45.1.4',  // Auth table
            '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.9',  // Older firmware
        ];
        
        $serials = false;
        $serialOid = '';
        $usedOid = '';
        
        foreach ($serialOids as $tryOid) {
            $result = @snmprealwalk($host, $community, $tryOid, 10000000, 2);
            if ($result !== false && !empty($result)) {
                // Just check we got data - validation happens during parsing
                $serials = $result;
                $usedOid = $tryOid;
                break;
            }
        }
        
        if ($serials === false || empty($serials)) {
            return ['success' => false, 'error' => 'Failed to get ONU serial numbers. Tried OIDs: .43.1.3, .46.1.3, .45.1.4, .43.1.9. None returned valid serials. Your OLT may not expose serials via SNMP.'];
        }
        
        // Build map: serial -> location from SNMP
        // Huawei OID format: ...43.1.3.{ponIndex}.{onuId}
        // ponIndex = frame*8192 + slot*256 + port
        $snmpOnuMap = [];
        $sampleSnmp = [];
        $count = 0;
        
        foreach ($serials as $oid => $rawSerial) {
            // Extract index parts after base OID
            $indexPart = substr($oid, strlen($usedOid) + 1);
            $parts = explode('.', $indexPart);
            
            // Huawei uses 2-part index: ponIndex.onuId
            if (count($parts) >= 2) {
                $ponIndex = (int)$parts[0];
                $onuId = (int)$parts[1];
                
                // Decode Huawei ponIndex: frame*8192 + slot*256 + port
                $frame = intdiv($ponIndex, 8192);
                $remainder = $ponIndex % 8192;
                $slot = intdiv($remainder, 256);
                $port = $remainder % 256;
                
                // Clean and normalize serial number
                $sn = $this->cleanSnmpValue($rawSerial);
                
                // Handle hex byte format (e.g., "Hex-STRING: 48 57 54 43...")
                if (stripos($sn, 'hex') !== false) {
                    $sn = preg_replace('/[^0-9a-fA-F]/', '', $sn);
                } elseif (preg_match('/^[0-9a-fA-F\s]+$/', $sn) && strpos($sn, ' ') !== false) {
                    $sn = str_replace(' ', '', $sn);
                }
                
                $sn = strtoupper(trim($sn));
                
                // Skip empty or numeric-only values
                if (empty($sn) || is_numeric($sn)) {
                    continue;
                }
                
                $snmpOnuMap[$sn] = [
                    'frame' => $frame,
                    'slot' => $slot,
                    'port' => $port,
                    'onu_id' => $onuId,
                    'index' => $indexPart
                ];
                
                // Collect samples for debug
                if ($count < 3) {
                    $sampleSnmp[] = $sn;
                }
                $count++;
            }
        }
        
        if (empty($snmpOnuMap)) {
            return ['success' => false, 'error' => "SNMP returned data but no valid serial numbers found. Raw count: " . count($serials)];
        }
        
        // Get all existing ONUs in huawei_onus table for this OLT
        $existingOnus = $this->getONUs(['olt_id' => $oltId]);
        
        // Collect sample DB serials for debug
        $sampleDb = [];
        foreach (array_slice($existingOnus, 0, 3) as $e) {
            $sampleDb[] = strtoupper(trim($e['sn'] ?? ''));
        }
        
        $updated = 0;
        $notFound = 0;
        $errors = [];
        
        foreach ($existingOnus as $existing) {
            $sn = strtoupper(trim($existing['sn'] ?? ''));
            if (empty($sn)) continue;
            
            if (isset($snmpOnuMap[$sn])) {
                $snmpData = $snmpOnuMap[$sn];
                
                // Update location data from SNMP - also mark as authorized since it's on OLT
                try {
                    $stmt = $this->db->prepare("
                        UPDATE huawei_onus 
                        SET frame = ?, slot = ?, port = ?, onu_id = ?, status = ?, is_authorized = TRUE, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $snmpData['frame'],
                        $snmpData['slot'],
                        $snmpData['port'],
                        $snmpData['onu_id'],
                        $snmpData['status'] ?? $existing['status'],
                        $existing['id']
                    ]);
                    $updated++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to update {$sn}: " . $e->getMessage();
                }
            } else {
                $notFound++;
            }
        }
        
        // Also add any new ONUs from SNMP that don't exist in database
        $added = 0;
        foreach ($snmpOnuMap as $sn => $snmpData) {
            $existing = $this->getONUBySN($sn);
            if (!$existing) {
                try {
                    $this->addONU([
                        'olt_id' => $oltId,
                        'sn' => $sn,
                        'frame' => $snmpData['frame'],
                        'slot' => $snmpData['slot'],
                        'port' => $snmpData['port'],
                        'onu_id' => $snmpData['onu_id'],
                        'status' => $snmpData['status'] ?? 'unknown',
                        'description' => $snmpData['description'] ?? '',
                        'is_authorized' => true,
                    ]);
                    $added++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to add {$sn}: " . $e->getMessage();
                }
            }
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'sync_locations',
            'status' => 'success',
            'message' => "Updated {$updated} ONUs, added {$added} new, {$notFound} not found on OLT",
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'updated' => $updated,
            'added' => $added,
            'not_found' => $notFound,
            'snmp_total' => count($snmpOnuMap),
            'db_total' => count($existingOnus),
            'sample_snmp' => $sampleSnmp,
            'sample_db' => $sampleDb,
            'used_oid' => $usedOid,
            'errors' => $errors
        ];
    }
    
    /**
     * Import ONUs from SmartOLT API
     * SmartOLT uses port_index encoding: (frame<<25)|(slot<<19)|(port<<8)|onu_id
     */
    public function importFromSmartOLT(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        // Initialize SmartOLT
        require_once __DIR__ . '/SmartOLT.php';
        $smartolt = new \SmartOLT($this->db);
        
        // Get all ONUs from SmartOLT
        $detailsResult = $smartolt->getAllONUsDetails();
        if (!isset($detailsResult['status']) || !$detailsResult['status']) {
            return ['success' => false, 'error' => 'Failed to fetch ONUs from SmartOLT: ' . ($detailsResult['error'] ?? 'Unknown error')];
        }
        
        $onus = $detailsResult['response'] ?? [];
        if (empty($onus)) {
            return ['success' => false, 'error' => 'No ONUs found in SmartOLT'];
        }
        
        // Get statuses and signals for additional data
        $statusesResult = $smartolt->getAllONUsStatuses();
        $statusMap = [];
        if (isset($statusesResult['status']) && $statusesResult['status'] && isset($statusesResult['response'])) {
            foreach ($statusesResult['response'] as $s) {
                $id = $s['onu_external_id'] ?? $s['id'] ?? null;
                if ($id) {
                    $statusMap[$id] = $s['status'] ?? 'unknown';
                }
            }
        }
        
        $signalsResult = $smartolt->getAllONUsSignals();
        $signalMap = [];
        if (isset($signalsResult['status']) && $signalsResult['status'] && isset($signalsResult['response'])) {
            foreach ($signalsResult['response'] as $sig) {
                $id = $sig['onu_external_id'] ?? $sig['id'] ?? null;
                if ($id) {
                    $signalMap[$id] = [
                        'rx_power' => $sig['onu_rx_power'] ?? null,
                        'tx_power' => $sig['olt_rx_power'] ?? null, // OLT RX = ONU TX perspective
                    ];
                }
            }
        }
        
        $added = 0;
        $updated = 0;
        $errors = [];
        
        foreach ($onus as $onu) {
            $sn = strtoupper(trim($onu['sn'] ?? $onu['serial_number'] ?? ''));
            if (empty($sn)) continue;
            
            // Decode SmartOLT port_index: (frame<<25)|(slot<<19)|(port<<8)|onu_id
            $portIndex = (int)($onu['port_index'] ?? $onu['onu_id'] ?? 0);
            
            // Decode using SmartOLT formula
            $frame = ($portIndex >> 25) & 0x7F;  // 7 bits for frame
            $slot = ($portIndex >> 19) & 0x3F;   // 6 bits for slot
            $port = ($portIndex >> 8) & 0x7FF;   // 11 bits for port
            $onuId = $portIndex & 0xFF;          // 8 bits for onu_id
            
            // Get external ID for status/signal lookup
            $extId = $onu['onu_external_id'] ?? $onu['id'] ?? null;
            $status = isset($statusMap[$extId]) ? strtolower($statusMap[$extId]) : 'unknown';
            
            // Normalize status
            if (strpos($status, 'online') !== false) {
                $status = 'online';
            } elseif (strpos($status, 'los') !== false) {
                $status = 'los';
            } elseif (strpos($status, 'power') !== false || strpos($status, 'dying') !== false) {
                $status = 'power_fail';
            } else {
                $status = 'offline';
            }
            
            $rxPower = null;
            $txPower = null;
            if (isset($signalMap[$extId])) {
                $rxPower = $this->parseSignalPower($signalMap[$extId]['rx_power']);
                $txPower = $this->parseSignalPower($signalMap[$extId]['tx_power']);
            }
            
            // Check if ONU exists
            $existing = $this->getONUBySN($sn);
            
            try {
                if ($existing) {
                    // Update existing ONU - SmartOLT ONUs are already authorized
                    $stmt = $this->db->prepare("
                        UPDATE huawei_onus 
                        SET frame = ?, slot = ?, port = ?, onu_id = ?, status = ?, 
                            rx_power = COALESCE(?, rx_power), tx_power = COALESCE(?, tx_power),
                            name = COALESCE(?, name), is_authorized = TRUE, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $frame, $slot, $port, $onuId, $status,
                        $rxPower, $txPower,
                        $onu['name'] ?? $onu['onu_name'] ?? null,
                        $existing['id']
                    ]);
                    $updated++;
                } else {
                    // Add new ONU - SmartOLT ONUs are already authorized on OLT
                    $this->addONU([
                        'olt_id' => $oltId,
                        'sn' => $sn,
                        'frame' => $frame,
                        'slot' => $slot,
                        'port' => $port,
                        'onu_id' => $onuId,
                        'status' => $status,
                        'rx_power' => $rxPower,
                        'tx_power' => $txPower,
                        'name' => $onu['name'] ?? $onu['onu_name'] ?? '',
                        'description' => $onu['description'] ?? '',
                        'is_authorized' => true,
                    ]);
                    $added++;
                }
            } catch (\Exception $e) {
                $errors[] = "Failed for {$sn}: " . $e->getMessage();
            }
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'import_smartolt',
            'status' => 'success',
            'message' => "Imported from SmartOLT: {$added} added, {$updated} updated",
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'total' => count($onus),
            'added' => $added,
            'updated' => $updated,
            'errors' => $errors
        ];
    }
    
    private function parseSignalPower(?string $power): ?float {
        if (empty($power) || $power === 'N/A' || $power === '-') {
            return null;
        }
        preg_match('/([-\d.]+)/', $power, $matches);
        return isset($matches[1]) ? (float)$matches[1] : null;
    }
    
    public function discoverONUsViaCLI(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        // Use display ont info summary - same as SmartOLT
        $result = $this->executeCommand($oltId, 'display ont info summary');
        if (!$result['success']) {
            return ['success' => false, 'error' => 'Failed to get ONU summary: ' . ($result['message'] ?? 'Unknown error')];
        }
        
        $output = $result['output'] ?? '';
        $lines = explode("\n", $output);
        $onus = [];
        
        foreach ($lines as $line) {
            // Parse: F/S/P  ONU-ID  SN  Status  ...
            // Format: 0/1/3    12    48575443E3E5FA9E  online
            if (preg_match('/(\d+)\/(\d+)\/(\d+)\s+(\d+)\s+([A-Z0-9]{16})\s+(\w+)/i', $line, $m)) {
                list(, $frame, $slot, $port, $onuId, $serial, $status) = $m;
                $serial = strtoupper($serial);
                
                $onus[$serial] = [
                    'sn' => $serial,
                    'frame' => (int)$frame,
                    'slot' => (int)$slot,
                    'port' => (int)$port,
                    'onu_id' => (int)$onuId,
                    'status' => strtolower($status),
                ];
            }
        }
        
        return [
            'success' => true,
            'onus' => $onus,
            'count' => count($onus)
        ];
    }
    
    public function getONUDetailedInfo(int $oltId, ?int $slotFilter = null): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        $password = !empty($olt['password_encrypted']) ? $this->decrypt($olt['password_encrypted']) : '';
        
        $socket = @fsockopen($olt['ip_address'], $olt['port'], $errno, $errstr, 10);
        if (!$socket) {
            return ['success' => false, 'error' => "Connection failed: {$errstr}"];
        }
        
        stream_set_blocking($socket, false);
        
        $readResponse = function($sock, $maxWait = 10, $handleMore = true) {
            $output = '';
            $start = time();
            $lastData = time();
            while ((time() - $start) < $maxWait) {
                $chunk = @fread($sock, 32768);
                if ($chunk) {
                    $output .= $chunk;
                    $lastData = time();
                    
                    // Handle More pagination - check the FULL output not just chunk
                    if ($handleMore && preg_match('/----\s*More.*?----\s*$/is', $output)) {
                        fwrite($sock, ' ');
                        usleep(150000);
                        continue;
                    }
                    // Handle parameter prompt
                    if (preg_match('/\{[^}]*\}\s*:\s*$/', $output)) {
                        fwrite($sock, "\r\n");
                        usleep(100000);
                        continue;
                    }
                    // Check for command prompt (end of output)
                    if (preg_match('/[>#]\s*$/', $output)) {
                        break;
                    }
                } else {
                    // No data received, check for timeout only if we have some output
                    if ((time() - $lastData) > 4 && strlen($output) > 50) {
                        break;
                    }
                }
                usleep(50000);
            }
            return $output;
        };
        
        // Login
        $readResponse($socket, 8, false);
        if (stripos($readResponse($socket, 5, false), 'name') === false) {
            // Retry read for username prompt
        }
        fwrite($socket, $olt['username'] . "\r\n");
        $readResponse($socket, 5, false);
        fwrite($socket, $password . "\r\n");
        sleep(2);
        @fread($socket, 8192);
        
        fwrite($socket, "enable\r\n"); $readResponse($socket, 3, false);
        fwrite($socket, "config\r\n"); $readResponse($socket, 3, false);
        
        // Get GPON slots from board info
        fwrite($socket, "display board 0\r\n");
        $boardOutput = $readResponse($socket, 5);
        
        $gponSlots = [];
        foreach (explode("\n", $boardOutput) as $line) {
            if (preg_match('/^\s*(\d{1,2})\s+(H\d{3}[A-Z0-9]*GP[A-Z0-9]*)\s+\w+/i', $line, $m)) {
                $gponSlots[] = (int)$m[1];
            }
        }
        if (empty($gponSlots)) $gponSlots = [0, 1];
        if ($slotFilter !== null) $gponSlots = [$slotFilter];
        
        $allOnus = [];
        
        foreach ($gponSlots as $slot) {
            // FIRST: Enter GPON interface and get optical data (small clean output)
            fwrite($socket, "interface gpon 0/{$slot}\r\n");
            $readResponse($socket, 3, false);
            
            // Collect all optical data for all ports (0-15)
            $opticalData = [];
            for ($portNum = 0; $portNum <= 15; $portNum++) {
                fwrite($socket, "display ont optical-info {$portNum} all\r\n");
                sleep(1);
                $optOutput = $readResponse($socket, 8);
                
                foreach (explode("\n", $optOutput) as $line) {
                    if (preg_match('/^\s*(\d+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)/i', trim($line), $m)) {
                        $key = "{$portNum}-{$m[1]}";
                        $opticalData[$key] = ['rx' => (float)$m[2], 'tx' => (float)$m[3]];
                    }
                }
            }
            
            // Exit interface mode
            fwrite($socket, "quit\r\n");
            $readResponse($socket, 2, false);
            
            // SECOND: Get ONU info (large paginated output)
            fwrite($socket, "display ont info 0 {$slot} all\r\n");
            $output = $readResponse($socket, 60);
            
            // Parse ONU info
            $currentOnus = [];
            foreach (explode("\n", $output) as $line) {
                $line = trim($line);
                
                // Parse: 0/ 0/0    1  48575443F2D52CC3  active  online  normal  match
                if (preg_match('/(\d+)\/\s*(\d+)\/(\d+)\s+(\d+)\s+([A-F0-9]{16})\s+(\w+)\s+(\w+)\s+(\w+)\s+(\w+)/i', $line, $m)) {
                    $runState = strtolower($m[7]);
                    $status = ($runState === 'offline') ? 'offline' : (($runState === 'los') ? 'los' : 'online');
                    
                    $port = (int)$m[3];
                    $onuId = (int)$m[4];
                    $optKey = "{$port}-{$onuId}";
                    
                    $key = "{$m[1]}/{$m[2]}/{$m[3]}-{$m[4]}";
                    $currentOnus[$key] = [
                        'frame' => (int)$m[1],
                        'slot' => (int)$m[2],
                        'port' => $port,
                        'onu_id' => $onuId,
                        'sn' => strtoupper($m[5]),
                        'status' => $status,
                        'run_state' => $runState,
                        'config_state' => strtolower($m[8]),
                        'name' => '',
                        'rx_power' => isset($opticalData[$optKey]) ? $opticalData[$optKey]['rx'] : null,
                        'tx_power' => isset($opticalData[$optKey]) ? $opticalData[$optKey]['tx'] : null,
                    ];
                }
                
                // Parse description
                if (preg_match('/(\d+)\/\s*(\d+)\/(\d+)\s+(\d+)\s+(\S.*)$/i', $line, $m)) {
                    $descText = trim($m[5]);
                    if (!preg_match('/^[A-F0-9]{16}/i', $descText)) {
                        $key = "{$m[1]}/{$m[2]}/{$m[3]}-{$m[4]}";
                        if (isset($currentOnus[$key])) {
                            $name = $descText;
                            if (preg_match('/(SNS\d+|SFL\d+)/i', $descText, $nameMatch)) {
                                $name = strtoupper($nameMatch[1]);
                            }
                            $currentOnus[$key]['name'] = $name;
                        }
                    }
                }
            }
            
            $allOnus = array_merge($allOnus, array_values($currentOnus));
        }
        
        fwrite($socket, "quit\r\n"); usleep(200000);
        fwrite($socket, "quit\r\n"); usleep(200000);
        fclose($socket);
        
        usort($allOnus, function($a, $b) {
            if ($a['slot'] !== $b['slot']) return $a['slot'] - $b['slot'];
            if ($a['port'] !== $b['port']) return $a['port'] - $b['port'];
            return $a['onu_id'] - $b['onu_id'];
        });
        
        return [
            'success' => true,
            'onus' => $allOnus,
            'count' => count($allOnus),
            'slots_scanned' => $gponSlots
        ];
    }
    
    /**
     * Get live data for a single ONU from OLT
     */
    public function getSingleONULiveData(int $oltId, int $frame, ?int $slot, ?int $port, ?int $onuId, string $sn = ''): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        if ($slot === null || $port === null || $onuId === null) {
            return ['success' => false, 'error' => 'Slot, port and ONU ID required'];
        }
        
        set_time_limit(120);
        
        $socket = @fsockopen($olt['ip_address'], $olt['telnet_port'] ?: 23, $errno, $errstr, 15);
        if (!$socket) {
            return ['success' => false, 'error' => "Connection failed: $errstr"];
        }
        
        stream_set_timeout($socket, 30);
        
        $password = $this->decrypt($olt['password_encrypted']);
        
        // Login
        $this->readUntilOLT($socket, ['Username:', 'User name:'], 10);
        fwrite($socket, $olt['username'] . "\n");
        usleep(300000);
        
        $this->readUntilOLT($socket, ['Password:'], 10);
        fwrite($socket, $password . "\n");
        usleep(500000);
        
        $loginResp = $this->readUntilOLT($socket, ['>', '#', 'fail', 'error'], 10);
        if (stripos($loginResp, 'fail') !== false || stripos($loginResp, 'error') !== false) {
            fclose($socket);
            return ['success' => false, 'error' => 'Login failed'];
        }
        
        // Disable pagination
        fwrite($socket, "enable\n");
        usleep(500000);
        $this->drainSocketOLT($socket);
        
        fwrite($socket, "undo terminal monitor\n");
        usleep(300000);
        $this->drainSocketOLT($socket);
        
        fwrite($socket, "scroll 512\n");
        usleep(300000);
        $this->drainSocketOLT($socket);
        
        // Get ONU optical power
        $cmd = sprintf("display ont optical-info %d %d\n", $port, $onuId);
        fwrite($socket, "interface gpon $frame/$slot\n");
        usleep(500000);
        $this->drainSocketOLT($socket);
        
        fwrite($socket, $cmd);
        usleep(2000000);
        $opticalOutput = $this->drainSocketOLT($socket);
        
        // Get ONU info summary 
        $cmd2 = sprintf("display ont info %d %d\n", $port, $onuId);
        fwrite($socket, $cmd2);
        usleep(2000000);
        $infoOutput = $this->drainSocketOLT($socket);
        
        fwrite($socket, "quit\n");
        usleep(300000);
        fwrite($socket, "quit\n");
        usleep(300000);
        fclose($socket);
        
        // Parse optical power
        $rxPower = null;
        $txPower = null;
        
        // Parse: RX optical power(dBm)    : -22.85
        if (preg_match('/RX\s+optical\s+power\s*\([^)]*\)\s*:\s*([-\d.]+)/i', $opticalOutput, $m)) {
            $rxPower = (float)$m[1];
        }
        // Parse: OLT RX ONT optical power(dBm): -22.23
        if (preg_match('/OLT\s+RX\s+ONT\s+optical\s+power\s*\([^)]*\)\s*:\s*([-\d.]+)/i', $opticalOutput, $m)) {
            $txPower = (float)$m[1];
        }
        
        // Parse status from info output
        $status = 'offline';
        if (preg_match('/Run\s+state\s*:\s*(\w+)/i', $infoOutput, $m)) {
            $state = strtolower($m[1]);
            if ($state === 'online') {
                $status = 'online';
            } elseif (stripos($state, 'los') !== false) {
                $status = 'los';
            }
        }
        
        // Get name/description
        $name = '';
        if (preg_match('/Name\s*:\s*(.+)/i', $infoOutput, $m)) {
            $name = trim($m[1]);
        }
        
        return [
            'success' => true,
            'onu' => [
                'sn' => $sn,
                'frame' => $frame,
                'slot' => $slot,
                'port' => $port,
                'onu_id' => $onuId,
                'name' => $name,
                'status' => $status,
                'rx_power' => $rxPower,
                'tx_power' => $txPower,
            ]
        ];
    }
    
    public function syncONUsFromCLI(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        set_time_limit(300);
        
        // First try display ont info summary (fast, gets serial + status + location)
        $summaryResult = $this->discoverONUsViaCLI($oltId);
        $summaryOnus = [];
        if ($summaryResult['success'] && !empty($summaryResult['onus'])) {
            foreach ($summaryResult['onus'] as $onu) {
                $summaryOnus[$onu['sn']] = $onu;
            }
        }
        
        // Get FULL configuration - section gpon may not include ont add on some firmware
        // Must parse full config to properly track interface gpon X/Y context
        $configResult = $this->executeCommand($oltId, 'display current-configuration');
        
        if (!$configResult['success']) {
            return ['success' => false, 'error' => 'Failed to get ONU configuration: ' . ($configResult['message'] ?? 'Unknown error')];
        }
        
        $output = $configResult['output'] ?? '';
        $lines = explode("\n", $output);
        
        $added = 0;
        $updated = 0;
        $errors = [];
        $parsed = [];
        
        // Track current interface context
        $currentFrame = 0;
        $currentSlot = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Track interface context: "interface gpon 0/2" means frame 0, slot 2
            if (preg_match('/interface\s+gpon\s+(\d+)\/(\d+)/i', $line, $m)) {
                $currentFrame = (int)$m[1];
                $currentSlot = (int)$m[2];
                continue;
            }
            
            // Parse: ont add <port> <onu_id> sn-auth "<serial>" ...
            if (preg_match('/ont\s+add\s+(\d+)\s+(\d+)\s+(\w+)-auth\s+"?([A-Fa-f0-9]+)"?/i', $line, $matches)) {
                $port = (int)$matches[1];
                $onuId = (int)$matches[2];
                $authType = strtolower($matches[3]);
                $serial = strtoupper(trim($matches[4], '"'));
                
                $lineProfileId = null;
                if (preg_match('/ont-lineprofile-id\s+(\d+)/i', $line, $m)) {
                    $lineProfileId = (int)$m[1];
                }
                
                $srvProfileId = null;
                if (preg_match('/ont-srvprofile-id\s+(\d+)/i', $line, $m)) {
                    $srvProfileId = (int)$m[1];
                }
                
                $description = '';
                if (preg_match('/desc\s+"([^"]+)"/i', $line, $m)) {
                    $description = $m[1];
                }
                
                // Get status from summary if available
                $status = 'online';
                if (isset($summaryOnus[$serial])) {
                    $status = $summaryOnus[$serial]['status'] ?? 'online';
                }
                
                $parsed[] = [
                    'sn' => $serial,
                    'frame' => $currentFrame,
                    'slot' => $currentSlot,
                    'port' => $port,
                    'onu_id' => $onuId,
                    'auth_type' => $authType,
                    'line_profile_id' => $lineProfileId,
                    'srv_profile_id' => $srvProfileId,
                    'description' => $description,
                    'status' => $status,
                ];
            }
        }
        
        // Now process parsed ONUs - use addONU with ON CONFLICT for reliability
        foreach ($parsed as $onu) {
            try {
                // Check if exists first for counting
                $existing = $this->getONUBySN($onu['sn']);
                
                // Use addONU which has ON CONFLICT (olt_id, sn) DO UPDATE
                // Generate a meaningful name - extract SNS code only
                $onuName = '';
                if (!empty($onu['description'])) {
                    // Extract SNS/SFL code (e.g., SNS000540, SFL0034) from description
                    if (preg_match('/^(SNS\d+|SFL\d+)/i', $onu['description'], $m)) {
                        $onuName = strtoupper($m[1]);
                    } else {
                        // If no SNS code, use first part before underscore
                        $parts = explode('_', $onu['description']);
                        $onuName = $parts[0];
                    }
                }
                if (empty($onuName)) {
                    // Fallback to location-based name
                    $onuName = "ONU {$onu['frame']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_id']}";
                }
                
                $this->addONU([
                    'olt_id' => $oltId,
                    'sn' => $onu['sn'],
                    'name' => $onuName,
                    'frame' => $onu['frame'],
                    'slot' => $onu['slot'],
                    'port' => $onu['port'],
                    'onu_id' => $onu['onu_id'],
                    'description' => $onu['description'],
                    'line_profile_id' => $onu['line_profile_id'],
                    'srv_profile_id' => $onu['srv_profile_id'],
                    'auth_type' => $onu['auth_type'],
                    'is_authorized' => true,
                    'status' => $onu['status'],
                ]);
                
                if ($existing) {
                    $updated++;
                } else {
                    $added++;
                }
            } catch (\Exception $e) {
                $errors[] = "Failed for {$onu['sn']}: " . $e->getMessage();
            }
        }
        
        // Warn if no ONUs were parsed from config
        if (empty($parsed)) {
            $this->addLog([
                'olt_id' => $oltId,
                'action' => 'sync_cli',
                'status' => 'warning',
                'message' => 'No ONUs found in configuration output',
                'details' => 'Config lines: ' . count($lines) . ', Output size: ' . strlen($output),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'No ONUs found in OLT configuration. The OLT may have returned incomplete data.',
                'total' => 0,
                'config_lines' => count($lines),
                'output_size' => strlen($output)
            ];
        }
        
        // Now get optical power levels for all ONUs via SNMP (faster than CLI)
        $opticalResult = $this->refreshAllONUOptical($oltId);
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'sync_cli',
            'status' => 'success',
            'message' => "CLI Sync: {$added} added, {$updated} updated, " . count($parsed) . " total parsed",
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'total' => count($parsed),
            'added' => $added,
            'updated' => $updated,
            'optical_sync' => $opticalResult,
            'errors' => $errors
        ];
    }
    
    public function syncOpticalPowerFromCLI(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        // Get all ONUs for this OLT
        $onus = $this->getONUs(['olt_id' => $oltId]);
        if (empty($onus)) {
            return ['success' => true, 'updated' => 0, 'message' => 'No ONUs to update'];
        }
        
        // Group ONUs by slot/port for efficient batch queries
        $bySlotPort = [];
        foreach ($onus as $onu) {
            if ($onu['slot'] !== null && $onu['port'] !== null) {
                $key = $onu['frame'] . '/' . $onu['slot'] . '/' . $onu['port'];
                $bySlotPort[$key][] = $onu;
            }
        }
        
        $updated = 0;
        
        foreach ($bySlotPort as $location => $onuList) {
            list($frame, $slot, $port) = explode('/', $location);
            
            // Get optical info for all ONUs on this port
            $cmd = "display ont optical-info {$frame}/{$slot} {$port} all";
            $result = $this->executeCommand($oltId, $cmd);
            
            if (!$result['success'] || empty($result['output'])) {
                continue;
            }
            
            // Parse output - format varies but typically:
            // ONT-ID  Rx power(dBm)  Tx power(dBm)  OLT Rx ONT power(dBm)  ...
            // 1       -18.50         2.35           -19.20
            $lines = explode("\n", $result['output']);
            $opticalData = [];
            
            foreach ($lines as $line) {
                // Match lines with ONU ID and power values
                // Format: <onu_id>  <rx_power>  <tx_power>  <olt_rx>
                if (preg_match('/^\s*(\d+)\s+([-\d.]+)\s+([-\d.]+)/m', $line, $m)) {
                    $opticalData[(int)$m[1]] = [
                        'rx_power' => (float)$m[2],
                        'tx_power' => (float)$m[3],
                    ];
                }
            }
            
            // Update each ONU
            foreach ($onuList as $onu) {
                if (isset($opticalData[$onu['onu_id']])) {
                    $data = $opticalData[$onu['onu_id']];
                    $this->updateONU($onu['id'], [
                        'rx_power' => $data['rx_power'],
                        'tx_power' => $data['tx_power'],
                    ]);
                    $updated++;
                }
            }
        }
        
        return ['success' => true, 'updated' => $updated];
    }
    
    public function refreshAllONUOptical(int $oltId): array {
        $onus = $this->getONUs(['olt_id' => $oltId]);
        
        // Try bulk SNMP walk first (more efficient)
        if (function_exists('snmprealwalk')) {
            $bulkResult = $this->bulkPollOpticalPowerViaSNMP($oltId);
            if ($bulkResult['success'] && !empty($bulkResult['data'])) {
                $refreshed = 0;
                foreach ($onus as $onu) {
                    if ($onu['slot'] !== null && $onu['port'] !== null && $onu['onu_id'] !== null) {
                        $key = $this->buildOpticalKey($onu['slot'], $onu['port'], $onu['onu_id']);
                        if (isset($bulkResult['data'][$key])) {
                            $data = $bulkResult['data'][$key];
                            $this->updateONUOpticalInDB(
                                $onu['id'], 
                                $data['rx_power'] ?? null, 
                                $data['tx_power'] ?? null,
                                $data['distance'] ?? null
                            );
                            $refreshed++;
                        }
                    }
                }
                return [
                    'success' => true,
                    'refreshed' => $refreshed,
                    'failed' => count($onus) - $refreshed,
                    'total' => count($onus),
                    'method' => 'snmp_bulk'
                ];
            }
        }
        
        // Fallback to individual polling
        $refreshed = 0;
        $failed = 0;
        
        foreach ($onus as $onu) {
            if ($onu['slot'] !== null && $onu['port'] !== null && $onu['onu_id'] !== null) {
                $result = $this->refreshONUOptical($onu['id']);
                if ($result['success']) {
                    $refreshed++;
                } else {
                    $failed++;
                }
            }
        }
        
        return [
            'success' => true,
            'refreshed' => $refreshed,
            'failed' => $failed,
            'total' => count($onus),
            'method' => 'individual'
        ];
    }
    
    public function bulkPollOpticalPowerViaSNMP(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        if (!function_exists('snmprealwalk')) {
            return ['success' => false, 'error' => 'PHP SNMP extension not installed'];
        }
        
        $community = $olt['snmp_community'] ?? 'public';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        
        // Huawei optical power OIDs - try multiple tables as firmware varies
        // Index format: ponIndex.onuId where ponIndex = frame*8192 + slot*256 + port
        $oidSets = [
            // Primary: hwGponOnuInfo table .43.1.x (raw dBm values)
            ['rx' => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.7', 'tx' => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.8', 'divisor' => 1],
            // Fallback: hwGponOltOptics table .51.1.x (0.01 dBm units)
            ['rx' => '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.4', 'tx' => '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.6', 'divisor' => 100],
        ];
        
        // Try each OID set until we get data
        $oids = null;
        $divisor = 1;
        foreach ($oidSets as $set) {
            $testResult = @snmprealwalk($host, $community, $set['rx'], 5000000, 1);
            if ($testResult !== false && !empty($testResult)) {
                $oids = $set;
                $divisor = $set['divisor'];
                break;
            }
        }
        
        if (!$oids) {
            return ['success' => false, 'error' => 'No optical power data available via SNMP'];
        }
        
        $results = [];
        
        // Configure SNMP for plain values
        if (function_exists('snmp_set_quick_print')) {
            snmp_set_quick_print(true);
        }
        if (function_exists('snmp_set_valueretrieval')) {
            snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
        }
        
        // Walk RX power table
        $rxResults = @snmprealwalk($host, $community, $oids['rx'], 10000000, 2);
        if ($rxResults !== false) {
            foreach ($rxResults as $oid => $value) {
                $key = $this->parseHuaweiOpticalIndex($oid, $oids['rx']);
                if ($key) {
                    $power = $this->parseOpticalPower($value, $divisor);
                    if ($power !== null && $power > -50 && $power < 10) {
                        if (!isset($results[$key])) {
                            $results[$key] = [];
                        }
                        $results[$key]['rx_power'] = $power;
                    }
                }
            }
        }
        
        // Walk TX power table
        $txResults = @snmprealwalk($host, $community, $oids['tx'], 10000000, 2);
        if ($txResults !== false) {
            foreach ($txResults as $oid => $value) {
                $key = $this->parseHuaweiOpticalIndex($oid, $oids['tx']);
                if ($key) {
                    $power = $this->parseOpticalPower($value, $divisor);
                    if ($power !== null && $power > -50 && $power < 10) {
                        if (!isset($results[$key])) {
                            $results[$key] = [];
                        }
                        $results[$key]['tx_power'] = $power;
                    }
                }
            }
        }
        
        // Walk distance table (hwGponOntDistance .43.1.5 - in meters)
        $distanceOid = '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.5';
        $distanceResults = @snmprealwalk($host, $community, $distanceOid, 10000000, 2);
        if ($distanceResults !== false) {
            foreach ($distanceResults as $oid => $value) {
                $key = $this->parseHuaweiOpticalIndex($oid, $distanceOid);
                if ($key) {
                    $distance = (int)$this->cleanSnmpValue((string)$value);
                    if ($distance > 0 && $distance < 100000) { // Reasonable range (0-100km)
                        if (!isset($results[$key])) {
                            $results[$key] = [];
                        }
                        $results[$key]['distance'] = $distance;
                    }
                }
            }
        }
        
        return [
            'success' => true,
            'data' => $results,
            'count' => count($results)
        ];
    }
    
    private function parseHuaweiOpticalIndex(string $oid, string $baseOid): ?string {
        // Extract ponIndex.onuId from OID
        // OID format: baseOid.ponIndex.onuId
        $indexPart = substr($oid, strlen($baseOid) + 1);
        $parts = explode('.', $indexPart);
        
        if (count($parts) >= 2) {
            $ponIndex = (int)$parts[0];
            $onuId = (int)$parts[1];
            
            // Decode ponIndex: frame*8192 + slot*256 + port
            $frame = intdiv($ponIndex, 8192);
            $remainder = $ponIndex % 8192;
            $slot = intdiv($remainder, 256);
            $port = $remainder % 256;
            
            // Return key format: slot.port.onuId (matching buildOpticalKey format)
            return "{$slot}.{$port}.{$onuId}";
        }
        return null;
    }
    
    private function parseOpticalPower($value, int $divisor = 1): ?float {
        // Huawei power values: .43.1.x = raw dBm, .51.1.x = 0.01 dBm units
        $value = $this->cleanSnmpValue((string)$value);
        if (is_numeric($value)) {
            return (float)$value / $divisor;
        }
        return null;
    }
    
    private function parseHuaweiPortOnuIndex($indexOrOid, string $baseOid): ?string {
        // Index format: port_index.onu_id
        // port_index = (frame<<25)|(slot<<16)|port
        if (is_string($indexOrOid) && strpos($indexOrOid, $baseOid) !== false) {
            $indexPart = substr($indexOrOid, strlen($baseOid) + 1);
        } else {
            $indexPart = (string)$indexOrOid;
        }
        
        $parts = explode('.', $indexPart);
        if (count($parts) >= 2) {
            $portIndex = (int)$parts[0];
            $onuId = (int)$parts[1];
            
            // Decode port_index: frame = bits 25-31, slot = bits 16-24, port = bits 0-15
            $frame = ($portIndex >> 25) & 0x7F;
            $slot = ($portIndex >> 16) & 0x1FF;
            $port = $portIndex & 0xFFFF;
            
            return "{$slot}.{$port}.{$onuId}";
        }
        return null;
    }
    
    private function parseHuaweiOnuIndex($indexOrOid, string $baseOid): ?string {
        // Index format: frame.slot.pon.onu (e.g., 0.1.3.12)
        // Extract index from OID or use directly if already parsed
        if (is_string($indexOrOid) && strpos($indexOrOid, $baseOid) !== false) {
            $indexPart = substr($indexOrOid, strlen($baseOid) + 1);
        } else {
            $indexPart = (string)$indexOrOid;
        }
        
        $parts = explode('.', $indexPart);
        if (count($parts) >= 4) {
            // frame.slot.pon.onu
            $frame = (int)$parts[0];
            $slot = (int)$parts[1];
            $port = (int)$parts[2];
            $onuId = (int)$parts[3];
            return "{$slot}.{$port}.{$onuId}";
        }
        return null;
    }
    
    private function parseHuaweiOnuStatus(int $status): string {
        return match($status) {
            1 => 'online',
            2 => 'offline',
            3 => 'los',
            4 => 'power_fail',
            default => 'unknown'
        };
    }
    
    private function buildOpticalKey(int $slot, int $port, int $onuId): string {
        return "{$slot}.{$port}.{$onuId}";
    }
    
    private function decodeSmartOLTPortIndex(int $portIndex): ?array {
        // SmartOLT/Huawei port_index format:
        // port_index = (frame << 25) | (slot << 19) | (port << 8) | onu_id
        // 
        // Decoding:
        // - frame  = (port_index >> 25) & 0x7F  (bits 25-31)
        // - slot   = (port_index >> 19) & 0x3F  (bits 19-24)
        // - port   = (port_index >> 8) & 0x7FF  (bits 8-18)
        // - onu_id = port_index & 0xFF          (bits 0-7)
        
        $frame = ($portIndex >> 25) & 0x7F;
        $slot = ($portIndex >> 19) & 0x3F;
        $port = ($portIndex >> 8) & 0x7FF;
        $onuId = $portIndex & 0xFF;
        
        // Validate decoded values (reasonable ranges for Huawei OLT)
        // Frame: 0-7, Slot: 0-21, Port: 0-16, ONU: 0-127
        if ($frame <= 7 && $slot <= 21 && $port <= 16 && $onuId <= 127) {
            return [
                'frame' => $frame,
                'slot' => $slot,
                'port' => $port,
                'onu_id' => $onuId,
                'decoded_from' => 'huawei_port_index'
            ];
        }
        
        // Try alternate format: simple flat index
        // Format may be just slot.port.onu_id combined differently
        // port_index = (slot << 16) | (port << 8) | onu_id
        $altSlot = ($portIndex >> 16) & 0xFF;
        $altPort = ($portIndex >> 8) & 0xFF;
        $altOnuId = $portIndex & 0xFF;
        
        if ($altSlot <= 21 && $altPort <= 16 && $altOnuId <= 127) {
            return [
                'frame' => 0,
                'slot' => $altSlot,
                'port' => $altPort,
                'onu_id' => $altOnuId,
                'decoded_from' => 'alt_format'
            ];
        }
        
        // Return raw decoded values for debugging even if validation fails
        return [
            'frame' => $frame,
            'slot' => $slot,
            'port' => $port,
            'onu_id' => $onuId,
            'decoded_from' => 'raw_decode',
            'warning' => 'Values may be out of expected range'
        ];
    }
    
    private function parseOpticalPowerDiv10($value): ?float {
        $cleaned = $this->cleanSnmpValue((string)$value);
        if (is_numeric($cleaned)) {
            $power = round((float)$cleaned / 10, 1);
            // Valid range check: -50 to +10 dBm
            if ($power >= -50 && $power <= 10) {
                return $power;
            }
        }
        return null;
    }
    
    private function parseOpticalPowerWithValidation($value): ?float {
        // Legacy method for backward compatibility (divide by 100)
        $cleaned = $this->cleanSnmpValue((string)$value);
        if (is_numeric($cleaned)) {
            $power = round((float)$cleaned / 100, 2);
            if ($power >= -50 && $power <= 10) {
                return $power;
            }
        }
        return null;
    }
    
    public function refreshONUOptical(int $onuId, bool $force = false): array {
        $onu = $this->getONU($onuId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        // Throttle: skip if optical data was updated within 60 seconds (unless forced)
        if (!$force && !empty($onu['optical_updated_at'])) {
            $lastUpdate = strtotime($onu['optical_updated_at']);
            if ($lastUpdate && (time() - $lastUpdate) < 60) {
                return [
                    'success' => true,
                    'throttled' => true,
                    'rx_power' => $onu['rx_power'],
                    'tx_power' => $onu['tx_power'],
                    'distance' => $onu['distance'] ?? null,
                    'status' => $onu['status']
                ];
            }
        }
        
        if ($onu['onu_id'] === null) {
            return ['success' => false, 'error' => 'ONU ID not set'];
        }
        
        // Detect SmartOLT format: if onu_id is large (port_index) and slot/port are 0/null
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuIdNum = $onu['onu_id'];
        
        // SmartOLT stores port_index in onu_id field when slot/port are not set
        // Only trigger SmartOLT decode if slot AND port are BOTH null (not 0, which is valid)
        if ($slot === null && $port === null && $onuIdNum > 1000) {
            // Decode SmartOLT port_index format
            // Format: port_index.onu_id where port_index encodes frame/slot/port
            // port_index = (frame << 25) | (slot << 16) | port (standard Huawei encoding)
            // But SmartOLT may use different encoding, let's try to decode
            $decoded = $this->decodeSmartOLTPortIndex($onuIdNum);
            if ($decoded) {
                $frame = $decoded['frame'];
                $slot = $decoded['slot'];
                $port = $decoded['port'];
                $onuIdNum = $decoded['onu_id'];
            } else {
                return ['success' => false, 'error' => "Cannot decode SmartOLT port_index: {$onuIdNum}. Please sync ONUs from OLT via SNMP."];
            }
        }
        
        if ($slot === null || $port === null) {
            return ['success' => false, 'error' => 'ONU location (slot/port) not set'];
        }
        
        // Try CLI first (faster and works when SNMP is blocked), then SNMP as fallback
        $optical = $this->getONUOpticalInfoViaCLI(
            $onu['olt_id'],
            $frame,
            $slot,
            $port,
            $onuIdNum
        );
        
        // If CLI failed or didn't get power data, try SNMP as fallback
        if (!$optical['success'] || 
            ($optical['optical']['rx_power'] === null && $optical['optical']['tx_power'] === null)) {
            if (function_exists('snmpget')) {
                $snmpOptical = $this->getONUOpticalInfoViaSNMP(
                    $onu['olt_id'],
                    $frame,
                    $slot,
                    $port,
                    $onuIdNum
                );
                if ($snmpOptical['success']) {
                    $optical = $snmpOptical;
                }
            }
        }
        
        if (!$optical['success']) {
            return ['success' => false, 'error' => $optical['error'] ?? 'Failed to get optical info'];
        }
        
        if ($optical['optical']['rx_power'] === null && $optical['optical']['tx_power'] === null) {
            $debug = $optical['debug'] ?? [];
            $debugStr = json_encode($debug);
            return ['success' => false, 'error' => "No power data. Debug: {$debugStr}"];
        }
        
        $distance = $optical['optical']['distance'] ?? null;
        $this->updateONUOpticalInDB($onuId, $optical['optical']['rx_power'], $optical['optical']['tx_power'], $distance);
        
        // Also update status if available
        $status = $optical['optical']['status'] ?? null;
        if ($status) {
            $this->updateONUStatus($onuId, $status);
        }
        
        return [
            'success' => true,
            'rx_power' => $optical['optical']['rx_power'],
            'tx_power' => $optical['optical']['tx_power'],
            'distance' => $distance,
            'status' => $status
        ];
    }
    
    private function updateONUStatus(int $onuId, string $status): void {
        $stmt = $this->db->prepare("UPDATE huawei_onus SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$status, $onuId]);
    }
    
    private function updateONUOpticalInDB(int $onuId, ?float $rxPower, ?float $txPower, ?int $distance = null): void {
        if ($distance !== null) {
            $stmt = $this->db->prepare("
                UPDATE huawei_onus 
                SET rx_power = ?, tx_power = ?, distance = ?, optical_updated_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$rxPower, $txPower, $distance, $onuId]);
        } else {
            $stmt = $this->db->prepare("
                UPDATE huawei_onus 
                SET rx_power = ?, tx_power = ?, optical_updated_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$rxPower, $txPower, $onuId]);
        }
    }
    
    public function discoverUnconfiguredONUs(int $oltId): array {
        // Try CLI first (more reliable), then fall back to SNMP
        $cliResult = $this->discoverUnconfiguredONUsViaCLI($oltId);
        if ($cliResult['success'] && !empty($cliResult['onus'])) {
            return $cliResult;
        }
        
        // Fall back to SNMP
        return $this->discoverUnconfiguredONUsViaSNMP($oltId);
    }
    
    public function discoverUnconfiguredONUsViaCLI(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        // Run CLI command to find unconfigured ONUs
        $result = $this->executeCommand($oltId, 'display ont autofind all');
        
        if (!$result['success']) {
            return ['success' => false, 'error' => 'Failed to execute autofind command: ' . ($result['message'] ?? 'Unknown error')];
        }
        
        $output = $result['output'] ?? '';
        $lines = explode("\n", $output);
        
        $unconfigured = [];
        $added = 0;
        $currentFrame = 0;
        $currentSlot = 0;
        $currentPort = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Parse port header: "   ----------------------------------------------------------------------------"
            // followed by "   Port : 0/1/0" or "   F/S/P : 0/1/0"
            if (preg_match('/(?:Port|F\/S\/P)\s*:\s*(\d+)\/(\d+)\/(\d+)/i', $line, $m)) {
                $currentFrame = (int)$m[1];
                $currentSlot = (int)$m[2];
                $currentPort = (int)$m[3];
                continue;
            }
            
            // Parse ONU entry - formats vary by firmware:
            // Format 1: "   1    HWTC12345678    auto    0    SN"
            // Format 2: "   Ont SN           : HWTC12345678"
            // Format 3: Table row with Number, SN, Password, LOID, etc.
            
            // Table format: Number | SN/MAC | Password/LOID | Type | Auth mode
            if (preg_match('/^\s*(\d+)\s+([A-Fa-f0-9]{8,16})\s+/i', $line, $m)) {
                $autofindId = (int)$m[1];
                $sn = strtoupper($m[2]);
                
                // Skip if already authorized
                $existing = $this->getONUBySN($sn);
                if (!$existing) {
                    $this->addONU([
                        'olt_id' => $oltId,
                        'sn' => $sn,
                        'frame' => $currentFrame,
                        'slot' => $currentSlot,
                        'port' => $currentPort,
                        'status' => 'unconfigured',
                        'is_authorized' => false,
                    ]);
                    $added++;
                }
                
                $unconfigured[] = [
                    'sn' => $sn,
                    'frame' => $currentFrame,
                    'slot' => $currentSlot,
                    'port' => $currentPort,
                    'autofind_id' => $autofindId,
                    'method' => 'cli'
                ];
                continue;
            }
            
            // Alternative format: "Ont SN : HWTC12345678"
            if (preg_match('/Ont\s+SN\s*:\s*([A-Fa-f0-9]{8,16})/i', $line, $m)) {
                $sn = strtoupper($m[1]);
                
                $existing = $this->getONUBySN($sn);
                if (!$existing) {
                    $this->addONU([
                        'olt_id' => $oltId,
                        'sn' => $sn,
                        'frame' => $currentFrame,
                        'slot' => $currentSlot,
                        'port' => $currentPort,
                        'status' => 'unconfigured',
                        'is_authorized' => false,
                    ]);
                    $added++;
                }
                
                $unconfigured[] = [
                    'sn' => $sn,
                    'frame' => $currentFrame,
                    'slot' => $currentSlot,
                    'port' => $currentPort,
                    'autofind_id' => count($unconfigured) + 1,
                    'method' => 'cli'
                ];
            }
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'discover_unconfigured_cli',
            'status' => 'success',
            'message' => "Found " . count($unconfigured) . " unconfigured ONUs via CLI, added {$added} new",
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'onus' => $unconfigured,
            'count' => count($unconfigured),
            'added' => $added,
            'method' => 'cli',
            'raw_output' => $output
        ];
    }
    
    public function discoverUnconfiguredONUsViaSNMP(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        if (!function_exists('snmpwalk')) {
            return ['success' => false, 'error' => 'PHP SNMP extension not installed'];
        }
        
        $community = $olt['snmp_community'] ?? $olt['snmp_read_community'] ?? 'public';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        
        // Multiple autofind OIDs to try - different Huawei firmware versions use different tables
        $autofindOids = [
            '1.3.6.1.4.1.2011.6.128.1.1.2.42.1.3',  // hwGponOntAutoFindSn - common on MA5680T
            '1.3.6.1.4.1.2011.6.128.1.1.2.45.1.3',  // hwGponOltAutoFindOntSn - alternative
            '1.3.6.1.4.1.2011.6.128.1.1.2.45.1.4',  // hwGponOltAutoFindOntPassword
        ];
        
        $serials = false;
        $usedOid = '';
        $debugInfo = [];
        
        foreach ($autofindOids as $tryOid) {
            $result = @snmprealwalk($host, $community, $tryOid, 10000000, 2);
            $debugInfo[] = "OID {$tryOid}: " . ($result === false ? 'FAILED' : count($result) . ' entries');
            
            if ($result !== false && !empty($result)) {
                $serials = $result;
                $usedOid = $tryOid;
                break;
            }
        }
        
        error_log("Autofind discovery debug: " . implode(', ', $debugInfo));
        
        if ($serials === false || empty($serials)) {
            return [
                'success' => false, 
                'error' => 'No unconfigured ONUs found. Make sure the ONU is powered on and connected to the OLT fiber.',
                'debug' => $debugInfo
            ];
        }
        
        $huaweiAutofindTypeBase = str_replace('.3', '.5', $usedOid);
        $types = @snmprealwalk($host, $community, $huaweiAutofindTypeBase, 10000000, 2);
        
        $unconfigured = [];
        $added = 0;
        
        foreach ($serials as $oid => $serial) {
            $indexPart = substr($oid, strlen($usedOid) + 1);
            $parts = explode('.', $indexPart);
            
            if (count($parts) >= 2) {
                $ponIndex = (int)$parts[0];
                $autofindId = (int)$parts[1];
                
                $frame = intdiv($ponIndex, 8192);
                $remainder = $ponIndex % 8192;
                $slot = intdiv($remainder, 256);
                $port = $remainder % 256;
                
                $typeOid = $huaweiAutofindTypeBase . '.' . $indexPart;
                $onuType = isset($types[$typeOid]) ? $this->cleanSnmpValue($types[$typeOid]) : '';
                
                $sn = $this->cleanSnmpValue($serial);
                
                if (stripos($sn, 'hex') !== false || preg_match('/^[0-9a-fA-F\s]+$/', $sn)) {
                    $sn = preg_replace('/[^0-9a-fA-F]/', '', $sn);
                }
                $sn = strtoupper(trim($sn));
                
                if (empty($sn) || is_numeric($sn)) {
                    continue;
                }
                
                $existing = $this->getONUBySN($sn);
                if (!$existing) {
                    $this->addONU([
                        'olt_id' => $oltId,
                        'sn' => $sn,
                        'frame' => $frame,
                        'slot' => $slot,
                        'port' => $port,
                        'onu_type' => $onuType,
                        'status' => 'unconfigured',
                        'is_authorized' => false,
                    ]);
                    $added++;
                }
                
                $unconfigured[] = [
                    'sn' => $sn,
                    'frame' => $frame,
                    'slot' => $slot,
                    'port' => $port,
                    'onu_type' => $onuType,
                    'autofind_id' => $autofindId,
                    'method' => 'snmp'
                ];
            }
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'discover_unconfigured_snmp',
            'status' => 'success',
            'message' => "Found " . count($unconfigured) . " unconfigured ONUs via SNMP, added {$added} new",
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'onus' => $unconfigured,
            'count' => count($unconfigured),
            'added' => $added,
            'method' => 'snmp',
            'used_oid' => $usedOid
        ];
    }
    
    private function cleanSnmpValue(string $value): string {
        $value = preg_replace('/^(STRING|INTEGER|Hex-STRING|OID|Timeticks|Counter32|Gauge32|IpAddress):\s*/i', '', $value);
        $value = trim($value, '" ');
        return $value;
    }
    
    private function parseONUStatus(int $status): string {
        $statusMap = [
            1 => 'online',
            2 => 'offline',
            3 => 'los',
            4 => 'dyinggasp',
            5 => 'authfailed',
            6 => 'deregistered',
        ];
        return $statusMap[$status] ?? 'unknown';
    }
    
    // ==================== ONU Management ====================
    
    public function getONUs(array $filters = []): array {
        $sql = "SELECT o.*, olt.name as olt_name, c.name as customer_name, sp.name as profile_name
                FROM huawei_onus o
                LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN huawei_service_profiles sp ON o.service_profile_id = sp.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['olt_id'])) {
            $sql .= " AND o.olt_id = ?";
            $params[] = $filters['olt_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND o.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (o.sn ILIKE ? OR o.name ILIKE ? OR o.description ILIKE ? OR c.name ILIKE ?)";
            $term = "%{$filters['search']}%";
            $params = array_merge($params, [$term, $term, $term, $term]);
        }
        
        if (isset($filters['is_authorized'])) {
            $sql .= " AND o.is_authorized = ?";
            $params[] = $this->castBoolean($filters['is_authorized']);
        }
        
        $sql .= " ORDER BY olt.name, o.frame, o.slot, o.port, o.onu_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getONU(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT o.*, olt.name as olt_name, c.name as customer_name, sp.name as profile_name
            FROM huawei_onus o
            LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN huawei_service_profiles sp ON o.service_profile_id = sp.id
            WHERE o.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    public function getONUBySN(string $sn): ?array {
        $stmt = $this->db->prepare("SELECT * FROM huawei_onus WHERE sn = ?");
        $stmt->execute([$sn]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    public function addONU(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO huawei_onus (olt_id, customer_id, sn, name, description, frame, slot, port, onu_id,
                                     onu_type, mac_address, status, service_profile_id, line_profile, srv_profile,
                                     is_authorized, auth_type, password, vlan_id, vlan_priority, ip_mode,
                                     line_profile_id, srv_profile_id, tr069_profile_id, zone, area, customer_name, auth_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (olt_id, sn) DO UPDATE SET
                name = EXCLUDED.name, description = EXCLUDED.description, frame = EXCLUDED.frame,
                slot = EXCLUDED.slot, port = EXCLUDED.port, onu_id = EXCLUDED.onu_id,
                onu_type = EXCLUDED.onu_type, status = EXCLUDED.status, vlan_id = EXCLUDED.vlan_id,
                vlan_priority = EXCLUDED.vlan_priority, ip_mode = EXCLUDED.ip_mode,
                line_profile_id = EXCLUDED.line_profile_id, srv_profile_id = EXCLUDED.srv_profile_id,
                tr069_profile_id = EXCLUDED.tr069_profile_id, zone = EXCLUDED.zone, area = EXCLUDED.area,
                customer_name = EXCLUDED.customer_name, auth_date = EXCLUDED.auth_date,
                updated_at = CURRENT_TIMESTAMP
            RETURNING id
        ");
        $stmt->execute([
            $data['olt_id'],
            $data['customer_id'] ?? null,
            $data['sn'],
            $data['name'] ?? '',
            $data['description'] ?? '',
            $data['frame'] ?? 0,
            $data['slot'] ?? null,
            $data['port'] ?? null,
            $data['onu_id'] ?? null,
            $data['onu_type'] ?? '',
            $data['mac_address'] ?? '',
            $data['status'] ?? 'offline',
            $data['service_profile_id'] ?? null,
            $data['line_profile'] ?? '',
            $data['srv_profile'] ?? '',
            $this->castBoolean($data['is_authorized'] ?? false),
            $data['auth_type'] ?? 'sn',
            $data['password'] ?? '',
            $data['vlan_id'] ?? null,
            $data['vlan_priority'] ?? 0,
            $data['ip_mode'] ?? 'dhcp',
            $data['line_profile_id'] ?? null,
            $data['srv_profile_id'] ?? null,
            $data['tr069_profile_id'] ?? null,
            $data['zone'] ?? null,
            $data['area'] ?? null,
            $data['customer_name'] ?? null,
            $data['auth_date'] ?? null
        ]);
        return (int)$stmt->fetchColumn();
    }
    
    public function parseONUDescription(string $description): array {
        $result = [
            'customer_name' => null,
            'zone' => null,
            'area' => null,
            'auth_date' => null
        ];
        
        if (preg_match('/^([^_]+)_zone_([^_]+)_([^_]+)(?:_descr_([^_]+))?_authd_(\d{8})$/i', $description, $matches)) {
            $result['customer_name'] = $matches[1];
            $result['zone'] = $matches[2];
            $result['area'] = $matches[3];
            if (!empty($matches[4])) {
                $result['customer_name'] = $matches[4];
            }
            $dateStr = $matches[5];
            $result['auth_date'] = substr($dateStr, 0, 4) . '-' . substr($dateStr, 4, 2) . '-' . substr($dateStr, 6, 2);
        }
        
        return $result;
    }
    
    public function parseONUConfigFromCLI(string $cliOutput): array {
        $onus = [];
        $lines = explode("\n", $cliOutput);
        $currentOnu = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (preg_match('/ont add (\d+) (\d+) sn-auth "([^"]+)" omci ont-lineprofile-id (\d+)/', $line, $m)) {
                if ($currentOnu) {
                    $onus[] = $currentOnu;
                }
                $currentOnu = [
                    'port' => (int)$m[1],
                    'onu_id' => (int)$m[2],
                    'sn' => $m[3],
                    'line_profile_id' => (int)$m[4],
                    'auth_type' => 'sn'
                ];
            }
            
            if (preg_match('/ont-srvprofile-id (\d+) desc "([^"]+)"/', $line, $m)) {
                if ($currentOnu) {
                    $currentOnu['srv_profile_id'] = (int)$m[1];
                    $currentOnu['description'] = $m[2];
                    $parsed = $this->parseONUDescription($m[2]);
                    $currentOnu = array_merge($currentOnu, $parsed);
                }
            }
            
            if (preg_match('/ont ipconfig (\d+) (\d+) (dhcp|static) vlan (\d+) priority (\d+)/', $line, $m)) {
                if ($currentOnu && (int)$m[2] === $currentOnu['onu_id']) {
                    $currentOnu['ip_mode'] = $m[3];
                    $currentOnu['vlan_id'] = (int)$m[4];
                    $currentOnu['vlan_priority'] = (int)$m[5];
                }
            }
            
            if (preg_match('/ont tr069-server-config (\d+) (\d+) profile-id (\d+)/', $line, $m)) {
                if ($currentOnu && (int)$m[2] === $currentOnu['onu_id']) {
                    $currentOnu['tr069_profile_id'] = (int)$m[3];
                }
            }
        }
        
        if ($currentOnu) {
            $onus[] = $currentOnu;
        }
        
        return $onus;
    }
    
    public function updateONU(int $id, array $data): bool {
        $fields = ['customer_id', 'name', 'description', 'frame', 'slot', 'port', 'onu_id', 'onu_type',
                   'mac_address', 'status', 'rx_power', 'tx_power', 'distance', 'service_profile_id',
                   'line_profile', 'srv_profile', 'firmware_version', 'ip_address',
                   'config_state', 'run_state', 'auth_type', 'password', 'last_down_cause',
                   'vlan_id', 'vlan_priority', 'ip_mode', 'line_profile_id', 'srv_profile_id',
                   'tr069_profile_id', 'zone', 'area', 'customer_name', 'auth_date'];
        $booleanFields = ['is_authorized'];
        $updates = [];
        $params = [];
        
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $this->castBoolean($data[$field]);
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $stmt = $this->db->prepare("UPDATE huawei_onus SET " . implode(', ', $updates) . " WHERE id = ?");
        return $stmt->execute($params);
    }
    
    public function deleteONU(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM huawei_onus WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function deleteAllONUs(?int $oltId = null): array {
        try {
            if ($oltId) {
                $stmt = $this->db->prepare("DELETE FROM huawei_onus WHERE olt_id = ?");
                $stmt->execute([$oltId]);
            } else {
                $this->db->exec("DELETE FROM huawei_onus");
            }
            $count = $stmt->rowCount() ?? 0;
            
            $this->addLog([
                'olt_id' => $oltId,
                'action' => 'delete_all_onus',
                'status' => 'success',
                'message' => "Deleted all ONUs" . ($oltId ? " for OLT #{$oltId}" : ""),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return ['success' => true, 'count' => $count];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ==================== Service Profiles ====================
    
    public function getServiceProfiles(bool $activeOnly = true): array {
        $sql = "SELECT * FROM huawei_service_profiles";
        if ($activeOnly) {
            $sql .= " WHERE is_active = TRUE";
        }
        $sql .= " ORDER BY name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getServiceProfile(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM huawei_service_profiles WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    public function getDefaultServiceProfile(): ?array {
        $stmt = $this->db->query("SELECT * FROM huawei_service_profiles WHERE is_default = TRUE AND is_active = TRUE LIMIT 1");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$result) {
            // Fallback to first active profile
            $stmt = $this->db->query("SELECT * FROM huawei_service_profiles WHERE is_active = TRUE ORDER BY id LIMIT 1");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        return $result ?: null;
    }
    
    public function addServiceProfile(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO huawei_service_profiles (name, description, profile_type, vlan_id, vlan_mode,
                                                  speed_profile_up, speed_profile_down, qos_profile, gem_port,
                                                  tcont_profile, line_profile, srv_profile, native_vlan,
                                                  additional_config, is_default, is_active,
                                                  tr069_vlan, tr069_profile_id, tr069_gem_port)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['profile_type'] ?? 'internet',
            $data['vlan_id'] ?? null,
            $data['vlan_mode'] ?? 'tag',
            $data['speed_profile_up'] ?? '',
            $data['speed_profile_down'] ?? '',
            $data['qos_profile'] ?? '',
            $data['gem_port'] ?? null,
            $data['tcont_profile'] ?? '',
            $data['line_profile'] ?? '',
            $data['srv_profile'] ?? '',
            $data['native_vlan'] ?? null,
            $data['additional_config'] ?? '',
            $this->castBoolean($data['is_default'] ?? false),
            $this->castBoolean($data['is_active'] ?? true),
            !empty($data['tr069_vlan']) ? (int)$data['tr069_vlan'] : null,
            !empty($data['tr069_profile_id']) ? (int)$data['tr069_profile_id'] : null,
            !empty($data['tr069_gem_port']) ? (int)$data['tr069_gem_port'] : 2
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateServiceProfile(int $id, array $data): bool {
        $fields = ['name', 'description', 'profile_type', 'vlan_id', 'vlan_mode', 'speed_profile_up',
                   'speed_profile_down', 'qos_profile', 'gem_port', 'tcont_profile', 'line_profile',
                   'srv_profile', 'native_vlan', 'additional_config', 'tr069_vlan', 'tr069_profile_id', 'tr069_gem_port'];
        $booleanFields = ['is_default', 'is_active'];
        $updates = [];
        $params = [];
        
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $this->castBoolean($data[$field]);
            }
        }
        
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $stmt = $this->db->prepare("UPDATE huawei_service_profiles SET " . implode(', ', $updates) . " WHERE id = ?");
        return $stmt->execute($params);
    }
    
    public function deleteServiceProfile(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM huawei_service_profiles WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // ==================== Location Management (Zones, Subzones, Apartments, ODBs) ====================
    
    public function getZones(bool $activeOnly = false): array {
        try {
            $hasOnuZoneCol = $this->db->query("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'huawei_onus' AND column_name = 'zone_id')")->fetchColumn();
            $onuCountSql = $hasOnuZoneCol ? "(SELECT COUNT(*) FROM huawei_onus WHERE zone_id = z.id)" : "0";
            $sql = "SELECT z.*, 
                    (SELECT COUNT(*) FROM huawei_subzones WHERE zone_id = z.id) as subzone_count,
                    (SELECT COUNT(*) FROM huawei_apartments WHERE zone_id = z.id) as apartment_count,
                    (SELECT COUNT(*) FROM huawei_odb_units WHERE zone_id = z.id) as odb_count,
                    {$onuCountSql} as onu_count
                    FROM huawei_zones z";
            if ($activeOnly) $sql .= " WHERE z.is_active = TRUE";
            $sql .= " ORDER BY z.name";
            return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("getZones error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getZone(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM huawei_zones WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function addZone(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO huawei_zones (name, description, is_active) VALUES (?, ?, ?)");
        $stmt->execute([$data['name'], $data['description'] ?? '', $this->castBoolean($data['is_active'] ?? true)]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateZone(int $id, string $name, ?string $description = null, bool $isActive = true): array {
        try {
            $stmt = $this->db->prepare("UPDATE huawei_zones SET name = ?, description = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$name, $description ?? '', $this->castBoolean($isActive), $id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteZone(int $id): array {
        try {
            $stmt = $this->db->prepare("DELETE FROM huawei_zones WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getSubzones(?int $zoneId = null, bool $activeOnly = false): array {
        $sql = "SELECT s.*, z.name as zone_name,
                (SELECT COUNT(*) FROM huawei_apartments WHERE subzone_id = s.id) as apartment_count,
                (SELECT COUNT(*) FROM huawei_odb_units WHERE subzone_id = s.id) as odb_count
                FROM huawei_subzones s
                LEFT JOIN huawei_zones z ON s.zone_id = z.id";
        $params = [];
        $where = [];
        if ($zoneId) { $where[] = "s.zone_id = ?"; $params[] = $zoneId; }
        if ($activeOnly) { $where[] = "s.is_active = TRUE"; }
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY z.name, s.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function addSubzone(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO huawei_subzones (zone_id, name, description, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['zone_id'], $data['name'], $data['description'] ?? '', $this->castBoolean($data['is_active'] ?? true)]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateSubzone(int $id, int $zoneId, string $name, ?string $description = null, bool $isActive = true): array {
        try {
            $stmt = $this->db->prepare("UPDATE huawei_subzones SET zone_id = ?, name = ?, description = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$zoneId, $name, $description ?? '', $this->castBoolean($isActive), $id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteSubzone(int $id): array {
        try {
            $stmt = $this->db->prepare("DELETE FROM huawei_subzones WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getApartments(?int $zoneId = null, ?int $subzoneId = null, bool $activeOnly = false): array {
        try {
            $hasOnuAptCol = $this->db->query("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'huawei_onus' AND column_name = 'apartment_id')")->fetchColumn();
            $onuCountSql = $hasOnuAptCol ? "(SELECT COUNT(*) FROM huawei_onus WHERE apartment_id = a.id)" : "0";
            $sql = "SELECT a.*, z.name as zone_name, s.name as subzone_name,
                    (SELECT COUNT(*) FROM huawei_odb_units WHERE apartment_id = a.id) as odb_count,
                    {$onuCountSql} as onu_count
                    FROM huawei_apartments a
                    LEFT JOIN huawei_zones z ON a.zone_id = z.id
                    LEFT JOIN huawei_subzones s ON a.subzone_id = s.id";
            $params = [];
            $where = [];
            if ($zoneId) { $where[] = "a.zone_id = ?"; $params[] = $zoneId; }
            if ($subzoneId) { $where[] = "a.subzone_id = ?"; $params[] = $subzoneId; }
            if ($activeOnly) { $where[] = "a.is_active = TRUE"; }
            if ($where) $sql .= " WHERE " . implode(' AND ', $where);
            $sql .= " ORDER BY z.name, a.name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("getApartments error: " . $e->getMessage());
            return [];
        }
    }
    
    public function addApartment(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO huawei_apartments (zone_id, subzone_id, name, address, floors, units_count, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['zone_id'],
            !empty($data['subzone_id']) ? $data['subzone_id'] : null,
            $data['name'],
            $data['address'] ?? '',
            !empty($data['floors']) ? (int)$data['floors'] : null,
            !empty($data['units_count']) ? (int)$data['units_count'] : null,
            $this->castBoolean($data['is_active'] ?? true)
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateApartment(int $id, array $data): array {
        try {
            $stmt = $this->db->prepare("UPDATE huawei_apartments SET zone_id = ?, subzone_id = ?, name = ?, address = ?, floors = ?, units_count = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([
                $data['zone_id'],
                !empty($data['subzone_id']) ? $data['subzone_id'] : null,
                $data['name'],
                $data['address'] ?? '',
                !empty($data['floors']) ? (int)$data['floors'] : null,
                !empty($data['units_per_floor']) ? (int)$data['units_per_floor'] : null,
                $this->castBoolean($data['is_active'] ?? true),
                $id
            ]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteApartment(int $id): array {
        try {
            $stmt = $this->db->prepare("DELETE FROM huawei_apartments WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getODBs(?int $zoneId = null, ?int $apartmentId = null, bool $activeOnly = false): array {
        try {
            $hasOnuOdbCol = $this->db->query("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'huawei_onus' AND column_name = 'odb_id')")->fetchColumn();
            $onuCountSql = $hasOnuOdbCol ? "(SELECT COUNT(*) FROM huawei_onus WHERE odb_id = o.id)" : "0";
            $sql = "SELECT o.*, z.name as zone_name, s.name as subzone_name, a.name as apartment_name,
                    {$onuCountSql} as onu_count
                    FROM huawei_odb_units o
                    LEFT JOIN huawei_zones z ON o.zone_id = z.id
                    LEFT JOIN huawei_subzones s ON o.subzone_id = s.id
                    LEFT JOIN huawei_apartments a ON o.apartment_id = a.id";
            $params = [];
            $where = [];
            if ($zoneId) { $where[] = "o.zone_id = ?"; $params[] = $zoneId; }
            if ($apartmentId) { $where[] = "o.apartment_id = ?"; $params[] = $apartmentId; }
            if ($activeOnly) { $where[] = "o.is_active = TRUE"; }
            if ($where) $sql .= " WHERE " . implode(' AND ', $where);
            $sql .= " ORDER BY o.code";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("getODBs error: " . $e->getMessage());
            return [];
        }
    }
    
    public function addODB(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO huawei_odb_units (zone_id, subzone_id, apartment_id, code, capacity, location_description, latitude, longitude, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['zone_id'],
            !empty($data['subzone_id']) ? $data['subzone_id'] : null,
            !empty($data['apartment_id']) ? $data['apartment_id'] : null,
            $data['code'],
            !empty($data['capacity']) ? (int)$data['capacity'] : 8,
            $data['location_description'] ?? '',
            !empty($data['latitude']) ? $data['latitude'] : null,
            !empty($data['longitude']) ? $data['longitude'] : null,
            $this->castBoolean($data['is_active'] ?? true)
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateODB(int $id, array $data): array {
        try {
            $stmt = $this->db->prepare("UPDATE huawei_odb_units SET zone_id = ?, subzone_id = ?, apartment_id = ?, code = ?, capacity = ?, location_description = ?, latitude = ?, longitude = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([
                $data['zone_id'],
                !empty($data['subzone_id']) ? $data['subzone_id'] : null,
                !empty($data['apartment_id']) ? $data['apartment_id'] : null,
                $data['code'],
                !empty($data['capacity']) ? (int)$data['capacity'] : 8,
                $data['location_description'] ?? '',
                !empty($data['latitude']) ? $data['latitude'] : null,
                !empty($data['longitude']) ? $data['longitude'] : null,
                $this->castBoolean($data['is_active'] ?? true),
                $id
            ]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteODB(int $id): array {
        try {
            $stmt = $this->db->prepare("DELETE FROM huawei_odb_units WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function updateODBUsage(int $odbId): void {
        $stmt = $this->db->prepare("UPDATE huawei_odb_units SET ports_used = (SELECT COUNT(*) FROM huawei_onus WHERE odb_id = ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$odbId, $odbId]);
    }
    
    // Wrapper methods for template compatibility
    public function createZone(string $name, ?string $description = null, bool $isActive = true): array {
        try {
            $id = $this->addZone(['name' => $name, 'description' => $description, 'is_active' => $isActive]);
            return ['success' => true, 'id' => $id];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function createSubzone(int $zoneId, string $name, ?string $description = null, bool $isActive = true): array {
        try {
            $id = $this->addSubzone(['zone_id' => $zoneId, 'name' => $name, 'description' => $description, 'is_active' => $isActive]);
            return ['success' => true, 'id' => $id];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function createApartment(array $data): array {
        try {
            $id = $this->addApartment($data);
            return ['success' => true, 'id' => $id];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function createODB(array $data): array {
        try {
            $id = $this->addODB($data);
            return ['success' => true, 'id' => $id];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ==================== Dashboard Stats ====================
    
    public function getDashboardStats(): array {
        $stats = [
            'total_olts' => 0,
            'active_olts' => 0,
            'total_onus' => 0,
            'online_onus' => 0,
            'offline_onus' => 0,
            'los_onus' => 0,
            'unconfigured_onus' => 0,
            'total_profiles' => 0,
            'recent_alerts' => 0
        ];
        
        $stmt = $this->db->query("SELECT COUNT(*) as total, COUNT(*) FILTER (WHERE is_active = TRUE) as active FROM huawei_olts");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stats['total_olts'] = (int)$row['total'];
        $stats['active_olts'] = (int)$row['active'];
        
        $stmt = $this->db->query("
            SELECT COUNT(*) as total,
                   COUNT(*) FILTER (WHERE status = 'online') as online,
                   COUNT(*) FILTER (WHERE status = 'offline') as offline,
                   COUNT(*) FILTER (WHERE status = 'los') as los,
                   COUNT(*) FILTER (WHERE is_authorized = FALSE) as unconfigured
            FROM huawei_onus
        ");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stats['total_onus'] = (int)$row['total'];
        $stats['online_onus'] = (int)$row['online'];
        $stats['offline_onus'] = (int)$row['offline'];
        $stats['los_onus'] = (int)$row['los'];
        $stats['unconfigured_onus'] = (int)$row['unconfigured'];
        
        $stats['total_profiles'] = (int)$this->db->query("SELECT COUNT(*) FROM huawei_service_profiles WHERE is_active = TRUE")->fetchColumn();
        $stats['recent_alerts'] = (int)$this->db->query("SELECT COUNT(*) FROM huawei_alerts WHERE is_read = FALSE")->fetchColumn();
        
        return $stats;
    }
    
    public function getONUsByStatus(): array {
        $stmt = $this->db->query("
            SELECT status, COUNT(*) as count
            FROM huawei_onus
            GROUP BY status
            ORDER BY count DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getONUsByOLT(): array {
        $stmt = $this->db->query("
            SELECT olt.id, olt.name, COUNT(o.id) as onu_count,
                   COUNT(*) FILTER (WHERE o.status = 'online') as online,
                   COUNT(*) FILTER (WHERE o.status != 'online') as offline
            FROM huawei_olts olt
            LEFT JOIN huawei_onus o ON olt.id = o.olt_id
            WHERE olt.is_active = TRUE
            GROUP BY olt.id, olt.name
            ORDER BY olt.name
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== Provisioning Logs ====================
    
    public function addLog(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO huawei_provisioning_logs (olt_id, onu_id, action, status, message, details, command_sent, command_response, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['olt_id'] ?? null,
            $data['onu_id'] ?? null,
            $data['action'],
            $data['status'] ?? 'pending',
            $data['message'] ?? '',
            $data['details'] ?? '',
            $data['command_sent'] ?? '',
            $data['command_response'] ?? '',
            $data['user_id'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function getLogs(array $filters = [], int $limit = 100): array {
        $sql = "SELECT l.*, olt.name as olt_name, o.sn as onu_sn, u.name as user_name
                FROM huawei_provisioning_logs l
                LEFT JOIN huawei_olts olt ON l.olt_id = olt.id
                LEFT JOIN huawei_onus o ON l.onu_id = o.id
                LEFT JOIN users u ON l.user_id = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['olt_id'])) {
            $sql .= " AND l.olt_id = ?";
            $params[] = $filters['olt_id'];
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND l.action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND l.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY l.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== Alerts ====================
    
    public function addAlert(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO huawei_alerts (olt_id, onu_id, alert_type, severity, title, message)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['olt_id'] ?? null,
            $data['onu_id'] ?? null,
            $data['alert_type'],
            $data['severity'] ?? 'info',
            $data['title'],
            $data['message'] ?? ''
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function getAlerts(bool $unreadOnly = false, int $limit = 50): array {
        $sql = "SELECT a.*, olt.name as olt_name, o.sn as onu_sn
                FROM huawei_alerts a
                LEFT JOIN huawei_olts olt ON a.olt_id = olt.id
                LEFT JOIN huawei_onus o ON a.onu_id = o.id";
        if ($unreadOnly) {
            $sql .= " WHERE a.is_read = FALSE";
        }
        $sql .= " ORDER BY a.created_at DESC LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function markAlertRead(int $id): bool {
        $stmt = $this->db->prepare("UPDATE huawei_alerts SET is_read = TRUE WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function markAllAlertsRead(): bool {
        return $this->db->exec("UPDATE huawei_alerts SET is_read = TRUE WHERE is_read = FALSE") !== false;
    }
    
    public function checkONUSignalHealth(int $oltId = null): array {
        // Include ALL ONUs, not just those with rx_power - LOS/offline devices often lack telemetry
        $conditions = ['1=1'];
        $params = [];
        
        if ($oltId) {
            $conditions[] = 'olt_id = ?';
            $params[] = $oltId;
        }
        
        $sql = "SELECT id, olt_id, sn, description, status, rx_power, tx_power, 
                       slot, port, onu_id, updated_at
                FROM huawei_onus 
                WHERE " . implode(' AND ', $conditions);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $onus = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $issues = [
            'critical' => [], // rx_power <= -28
            'warning' => [],  // rx_power <= -25
            'offline' => [],  // status = offline
            'los' => [],      // status = los
            'no_signal' => [] // rx_power is null (no telemetry)
        ];
        
        $alertsCreated = 0;
        
        foreach ($onus as $onu) {
            $rx = $onu['rx_power'];
            $status = strtolower($onu['status'] ?? '');
            
            // Check status first (these are critical issues)
            if ($status === 'los') {
                $issues['los'][] = $onu;
                $this->createSignalAlert($onu, 'error', 'Loss of Signal', 
                    "ONU {$onu['sn']} has lost signal (LOS)");
                $alertsCreated++;
            } elseif ($status === 'offline') {
                $issues['offline'][] = $onu;
            }
            
            // Check signal level thresholds (only if we have rx_power data)
            if ($rx !== null) {
                if ($rx <= -28) {
                    $issues['critical'][] = $onu;
                    $this->createSignalAlert($onu, 'critical', 'Critical Signal Level', 
                        "ONU {$onu['sn']} has critical RX power: {$rx} dBm");
                    $alertsCreated++;
                } elseif ($rx <= -25) {
                    $issues['warning'][] = $onu;
                }
            } elseif ($status === 'online') {
                // Online but no signal data - flag as needing attention
                $issues['no_signal'][] = $onu;
            }
        }
        
        return [
            'success' => true,
            'issues' => $issues,
            'summary' => [
                'total_checked' => count($onus),
                'critical_signal' => count($issues['critical']),
                'warning_signal' => count($issues['warning']),
                'offline' => count($issues['offline']),
                'los' => count($issues['los']),
                'no_telemetry' => count($issues['no_signal'])
            ],
            'alerts_created' => $alertsCreated
        ];
    }
    
    private function createSignalAlert(array $onu, string $severity, string $title, string $message): void {
        // Check if similar alert exists recently (within 1 hour)
        $stmt = $this->db->prepare("
            SELECT id FROM huawei_alerts 
            WHERE onu_id = ? AND alert_type = 'signal' AND title = ? 
            AND created_at > NOW() - INTERVAL '1 hour'
        ");
        $stmt->execute([$onu['id'], $title]);
        
        if (!$stmt->fetch()) {
            $this->addAlert([
                'olt_id' => $onu['olt_id'],
                'onu_id' => $onu['id'],
                'alert_type' => 'signal',
                'severity' => $severity,
                'title' => $title,
                'message' => $message
            ]);
        }
    }
    
    public function getONUSignalStats(int $oltId = null): array {
        $conditions = ['1=1'];
        $params = [];
        
        if ($oltId) {
            $conditions[] = 'olt_id = ?';
            $params[] = $oltId;
        }
        
        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN LOWER(status) = 'online' THEN 1 END) as online,
                    COUNT(CASE WHEN LOWER(status) = 'offline' THEN 1 END) as offline,
                    COUNT(CASE WHEN LOWER(status) = 'los' THEN 1 END) as los,
                    COUNT(CASE WHEN rx_power <= -28 THEN 1 END) as critical_signal,
                    COUNT(CASE WHEN rx_power > -28 AND rx_power <= -25 THEN 1 END) as warning_signal,
                    COUNT(CASE WHEN rx_power > -25 AND rx_power IS NOT NULL THEN 1 END) as good_signal,
                    COUNT(CASE WHEN rx_power IS NULL AND LOWER(status) = 'online' THEN 1 END) as no_telemetry,
                    AVG(rx_power) FILTER (WHERE rx_power IS NOT NULL) as avg_rx_power
                FROM huawei_onus 
                WHERE " . implode(' AND ', $conditions);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function getONUsWithIssues(int $oltId = null, int $limit = 20): array {
        $conditions = ['(rx_power <= -25 OR status IN (\'offline\', \'los\'))'];
        $params = [];
        
        if ($oltId) {
            $conditions[] = 'o.olt_id = ?';
            $params[] = $oltId;
        }
        
        $params[] = $limit;
        
        $sql = "SELECT o.*, olt.name as olt_name, c.name as customer_name
                FROM huawei_onus o
                LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY 
                    CASE WHEN o.status = 'los' THEN 0
                         WHEN o.rx_power <= -28 THEN 1
                         WHEN o.status = 'offline' THEN 2
                         ELSE 3 END,
                    o.rx_power ASC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== ONU Operations ====================
    
    public function executeCommand(int $oltId, string $command): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'message' => 'OLT not found'];
        }
        
        $password = !empty($olt['password_encrypted']) ? $this->decrypt($olt['password_encrypted']) : '';
        
        $result = ['success' => false, 'message' => 'Unsupported connection type for commands'];
        
        if ($olt['connection_type'] === 'telnet') {
            $result = $this->executeTelnetCommand($olt['ip_address'], $olt['port'], $olt['username'], $password, $command);
        } elseif ($olt['connection_type'] === 'ssh') {
            $result = $this->executeSSHCommand($olt['ip_address'], $olt['port'], $olt['username'], $password, $command);
        }
        
        // Log command with response
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'command',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'] ?? '',
            'command_sent' => $command,
            'command_response' => substr($result['output'] ?? '', 0, 10000),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    private function executeTelnetCommand(string $ip, int $port, string $username, string $password, string $command): array {
        $timeout = 15;
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        
        if (!$socket) {
            return ['success' => false, 'message' => "Connection failed: {$errstr}"];
        }
        
        stream_set_blocking($socket, false);
        
        // Wait for login prompt
        $response = '';
        $startTime = time();
        while ((time() - $startTime) < 8) {
            $chunk = @fread($socket, 4096);
            if ($chunk) $response .= $chunk;
            if (stripos($response, 'name') !== false || stripos($response, 'login') !== false) break;
            usleep(100000);
        }
        
        if (stripos($response, 'name') === false && stripos($response, 'login') === false) {
            fclose($socket);
            return ['success' => false, 'message' => 'No login prompt received'];
        }
        
        // Send username
        fwrite($socket, $username . "\r\n");
        sleep(1);
        
        $response = '';
        $startTime = time();
        while ((time() - $startTime) < 5) {
            $chunk = @fread($socket, 4096);
            if ($chunk) $response .= $chunk;
            if (stripos($response, 'assword') !== false) break;
            usleep(100000);
        }
        
        if (stripos($response, 'assword') === false) {
            fclose($socket);
            return ['success' => false, 'message' => 'No password prompt received'];
        }
        
        // Send password
        fwrite($socket, $password . "\r\n");
        sleep(2);
        
        $response = '';
        $startTime = time();
        while ((time() - $startTime) < 5) {
            $chunk = @fread($socket, 4096);
            if ($chunk) $response .= $chunk;
            usleep(100000);
        }
        
        if (stripos($response, 'invalid') !== false) {
            fclose($socket);
            return ['success' => false, 'message' => 'Authentication failed - invalid credentials'];
        }
        
        // Enter enable mode
        fwrite($socket, "enable\r\n");
        sleep(1);
        @fread($socket, 4096);
        
        // Enter config mode
        fwrite($socket, "config\r\n");
        sleep(1);
        @fread($socket, 4096);
        
        // Handle multi-command sequences (separated by \r\n within the command string)
        // This allows entering interface mode before running commands
        $commands = preg_split('/\r?\n/', $command);
        foreach ($commands as $i => $cmd) {
            $cmd = trim($cmd);
            if (empty($cmd)) continue;
            fwrite($socket, $cmd . "\r\n");
            // Wait 2 seconds between commands to ensure previous command completes
            sleep(2);
            // Clear buffer for intermediate commands
            if ($i < count($commands) - 1) {
                @fread($socket, 8192);
            }
        }
        
        // Read output with timeout
        $output = '';
        $startTime = time();
        $lastDataTime = time();
        $maxWait = 30; // 30 seconds max for command output
        $idleTimeout = 5; // 5 seconds without data = done
        
        while ((time() - $startTime) < $maxWait) {
            $chunk = @fread($socket, 16384);
            if ($chunk) {
                $output .= $chunk;
                $lastDataTime = time();
                if (strlen($output) > 2097152) break; // 2MB limit
                
                // Handle "---- More ----" pagination
                if (preg_match('/----\s*More\s*----/i', $chunk)) {
                    fwrite($socket, " ");
                    usleep(500000);
                    continue;
                }
                
                // Handle Huawei parameter prompts like "{ <cr>|... }:"
                if (preg_match('/\}\s*:\s*$/', $output)) {
                    fwrite($socket, "\r\n");
                    usleep(1000000);
                    continue;
                }
                
                // Check for command completion (prompt at end)
                if (preg_match('/[>#]\s*$/', $output)) {
                    usleep(300000);
                    $extra = @fread($socket, 4096);
                    if (empty($extra)) break;
                    $output .= $extra;
                }
            } else {
                // No data - check idle timeout
                if ((time() - $lastDataTime) > $idleTimeout) {
                    break; // No data for too long, done
                }
            }
            usleep(100000);
        }
        
        // Cleanup
        fwrite($socket, "quit\r\n");
        usleep(200000);
        fwrite($socket, "quit\r\n");
        usleep(200000);
        fclose($socket);
        
        // Clean ANSI escape codes
        $output = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $output);
        $output = preg_replace('/---- More.*?----/', '', $output);
        
        return [
            'success' => true,
            'message' => 'Command executed',
            'output' => $output
        ];
    }
    
    private function executeSSHCommand(string $ip, int $port, string $username, string $password, string $command): array {
        // Use phpseclib3 for reliable SSH connections
        try {
            $ssh = new SSH2($ip, $port);
            $ssh->setTimeout(180); // 3 minutes for large configs
            
            if (!$ssh->login($username, $password)) {
                return ['success' => false, 'message' => 'SSH authentication failed'];
            }
            
            // Enable interactive mode for Huawei OLT
            $ssh->enablePTY();
            
            // Send setup commands
            $ssh->write("screen-length 0 temporary\n");
            usleep(500000);
            $ssh->read('/[>#]/', SSH2::READ_REGEX);
            
            // Send the actual command
            $ssh->write($command . "\n");
            usleep(2000000);
            
            // Read output until prompt
            $output = '';
            $startTime = time();
            while ((time() - $startTime) < 180) { // 3 minutes for large configs
                $chunk = $ssh->read('/[>#]/', SSH2::READ_REGEX);
                $output .= $chunk;
                
                // Handle pagination
                if (preg_match('/----\s*More\s*----/i', $chunk)) {
                    $ssh->write(" ");
                    usleep(500000);
                    continue;
                }
                
                break;
            }
            
            // Clean output
            $output = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $output);
            $output = preg_replace('/---- More.*?----/', '', $output);
            
            return [
                'success' => true,
                'message' => 'Command executed via SSH',
                'output' => $output
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'SSH error: ' . $e->getMessage()];
        }
    }
    
    public function testFullConnection(int $oltId): array {
        set_time_limit(120);
        
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        $results = [
            'olt_id' => $oltId,
            'olt_name' => $olt['name'],
            'ip_address' => $olt['ip_address'],
            'snmp' => ['success' => false, 'message' => 'Not tested'],
            'cli' => ['success' => false, 'message' => 'Not tested'],
            'snmp_power' => ['success' => false, 'message' => 'Not tested'],
            'snmp_serials' => ['success' => false, 'message' => 'Not tested'],
        ];
        
        // Test 1: Basic SNMP connectivity
        $snmpTest = $this->testSNMPConnection($olt['ip_address'], $olt['snmp_community'] ?? 'public', (int)($olt['snmp_port'] ?? 161));
        $results['snmp'] = $snmpTest;
        
        // Test 2: CLI connectivity (Telnet/SSH)
        $cliTest = $this->executeCommand($oltId, 'display version');
        $results['cli'] = [
            'success' => $cliTest['success'],
            'message' => $cliTest['success'] ? 'CLI connection successful' : ($cliTest['message'] ?? 'CLI connection failed'),
            'type' => $olt['connection_type']
        ];
        
        // Test 3: SNMP optical power OIDs
        if ($results['snmp']['success']) {
            $powerTest = $this->bulkPollOpticalPowerViaSNMP($oltId);
            $results['snmp_power'] = [
                'success' => $powerTest['success'],
                'message' => $powerTest['success'] 
                    ? "Found {$powerTest['count']} ONU power readings" 
                    : ($powerTest['error'] ?? 'No power data'),
                'count' => $powerTest['count'] ?? 0
            ];
            
            // Test 4: SNMP serial number OIDs
            $onuTest = $this->getONUListViaSNMP($oltId);
            $results['snmp_serials'] = [
                'success' => $onuTest['success'],
                'message' => $onuTest['success'] 
                    ? "Found " . count($onuTest['onus'] ?? []) . " ONUs via SNMP" 
                    : ($onuTest['error'] ?? 'No ONU data'),
                'count' => count($onuTest['onus'] ?? [])
            ];
        }
        
        // Overall success
        $results['overall_success'] = $results['snmp']['success'] && $results['cli']['success'];
        $results['recommendation'] = $this->getConnectionRecommendation($results);
        
        // Log the test
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'full_connection_test',
            'status' => $results['overall_success'] ? 'success' : 'warning',
            'message' => $results['recommendation'],
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $results;
    }
    
    private function getConnectionRecommendation(array $results): string {
        if ($results['snmp']['success'] && $results['cli']['success']) {
            if ($results['snmp_serials']['count'] > 0) {
                return 'Full connectivity. Use "Sync from OLT" for best results.';
            } else {
                return 'SNMP connected but no serial data. Use CLI sync method.';
            }
        } elseif ($results['cli']['success'] && !$results['snmp']['success']) {
            return 'CLI only. Configure SNMP on OLT for power monitoring.';
        } elseif ($results['snmp']['success'] && !$results['cli']['success']) {
            return 'SNMP only. Check Telnet/SSH credentials and port.';
        }
        return 'Connection failed. Check IP, credentials, and network access.';
    }
    
    public function authorizeONU(int $onuId, int $profileId, string $authMethod = 'sn', string $loid = '', string $loidPassword = '', array $options = []): array {
        $onu = $this->getONU($onuId);
        $profile = $this->getServiceProfile($profileId);
        
        if (!$onu || !$profile) {
            return ['success' => false, 'message' => 'ONU or Profile not found'];
        }
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $oltId = $onu['olt_id'];
        
        // Build auth part based on authentication method
        switch ($authMethod) {
            case 'loid':
                if (empty($loid)) {
                    return ['success' => false, 'message' => 'LOID value is required'];
                }
                $authPart = "loid-auth loid {$loid}";
                if (!empty($loidPassword)) {
                    $authPart .= " password {$loidPassword}";
                }
                break;
            case 'mac':
                $mac = $onu['mac_address'] ?? '';
                if (empty($mac)) {
                    return ['success' => false, 'message' => 'MAC address not available'];
                }
                $cleanMac = preg_replace('/[^a-fA-F0-9]/', '', $mac);
                if (strlen($cleanMac) !== 12) {
                    return ['success' => false, 'message' => 'Invalid MAC address format'];
                }
                $huaweiMac = strtolower(substr($cleanMac, 0, 4) . '-' . substr($cleanMac, 4, 4) . '-' . substr($cleanMac, 8, 4));
                $authPart = "mac-auth mac {$huaweiMac}";
                break;
            case 'sn':
            default:
                $authPart = "sn-auth {$onu['sn']}";
                break;
        }
        
        // Description for the ONU (use name or generate SNS code)
        $description = $options['description'] ?? $onu['name'] ?? '';
        if (empty($description)) {
            $description = $this->generateNextSNSCode($oltId);
        }
        
        // Build CLI script with newlines for multi-command execution
        // Huawei MA5680T/MA5683T requires interface context for ont add
        $cliScript = "interface gpon {$frame}/{$slot}\r\nont add {$port} {$authPart} omci ont-lineprofile-id {$profile['line_profile']} ont-srvprofile-id {$profile['srv_profile']} desc \"{$description}\"\r\nquit";
        
        // Execute the authorization command
        $result = $this->executeCommand($oltId, $cliScript);
        $output = $result['output'] ?? '';
        
        // Parse assigned ONU ID from response: "ONTID :1", "ONT-ID=1", "Number:1"
        $assignedOnuId = null;
        if (preg_match('/(?:ONTID|ONT-ID|Number)\s*[:\=]\s*(\d+)/i', $output, $m)) {
            $assignedOnuId = (int)$m[1];
        }
        
        // Check for errors in response
        $hasError = preg_match('/(?:Failure|Error:|failed|Invalid|Wrong parameter)/i', $output);
        
        if (!$result['success'] || $hasError) {
            $this->addLog([
                'olt_id' => $oltId, 'onu_id' => $onuId, 'action' => 'authorize',
                'status' => 'failed', 'message' => "Authorization failed for {$onu['sn']}",
                'command_sent' => $cliScript, 'command_response' => $output,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return ['success' => false, 'message' => 'Authorization failed: ' . substr($output, 0, 200), 'output' => $output];
        }
        
        // Update ONU record
        $updateData = [
            'is_authorized' => true,
            'service_profile_id' => $profileId,
            'line_profile' => $profile['line_profile'],
            'srv_profile' => $profile['srv_profile'],
            'name' => $description,
            'status' => 'online'
        ];
        if ($assignedOnuId !== null) {
            $updateData['onu_id'] = $assignedOnuId;
        }
        $this->updateONU($onuId, $updateData);
        
        // Create service-port if VLAN is specified
        $vlanId = $options['vlan_id'] ?? $profile['default_vlan'] ?? null;
        $servicePortResult = null;
        if ($vlanId && $assignedOnuId !== null) {
            $gemPort = $options['gem_port'] ?? 1;
            $spCmd = "service-port vlan {$vlanId} gpon {$frame}/{$slot}/{$port} ont {$assignedOnuId} gemport {$gemPort} multi-service user-vlan rx-cttr 6 tx-cttr 6";
            $servicePortResult = $this->executeCommand($oltId, $spCmd);
            $output .= "\n" . ($servicePortResult['output'] ?? '');
        }
        
        // Configure TR-069 via OMCI if enabled in profile or options
        $tr069Vlan = $options['tr069_vlan'] ?? $profile['tr069_vlan'] ?? null;
        $tr069ProfileId = $options['tr069_profile_id'] ?? $profile['tr069_profile_id'] ?? null;
        
        if ($tr069Vlan && $assignedOnuId !== null) {
            // Enter interface context for TR-069 configuration
            $tr069Script = "interface gpon {$frame}/{$slot}\r\n";
            
            // Configure native VLAN for TR-069 on ONU
            // ont port native-vlan <port> <onu_id> eth <eth_port> vlan <vlan> priority <priority>
            $tr069Script .= "ont port native-vlan {$port} {$assignedOnuId} eth 1 vlan {$tr069Vlan} priority 0\r\n";
            
            // Configure IP mode for TR-069 (DHCP or static)
            // ont ipconfig <port> <onu_id> ip-index 0 dhcp vlan <vlan>
            $tr069Script .= "ont ipconfig {$port} {$assignedOnuId} ip-index 0 dhcp vlan {$tr069Vlan}\r\n";
            
            // Assign TR-069 server profile if specified
            if ($tr069ProfileId) {
                $tr069Script .= "ont tr069-server-config {$port} {$assignedOnuId} profile-id {$tr069ProfileId}\r\n";
            }
            
            $tr069Script .= "quit";
            
            $tr069Result = $this->executeCommand($oltId, $tr069Script);
            $output .= "\n[TR-069 Config]\n" . ($tr069Result['output'] ?? '');
            
            // Create service-port for TR-069 VLAN (gemport 2 typically for TR-069)
            $tr069GemPort = $options['tr069_gem_port'] ?? 2;
            $tr069SpCmd = "service-port vlan {$tr069Vlan} gpon {$frame}/{$slot}/{$port} ont {$assignedOnuId} gemport {$tr069GemPort} multi-service user-vlan rx-cttr 6 tx-cttr 6";
            $tr069SpResult = $this->executeCommand($oltId, $tr069SpCmd);
            $output .= "\n" . ($tr069SpResult['output'] ?? '');
        }
        
        $this->addLog([
            'olt_id' => $oltId, 'onu_id' => $onuId, 'action' => 'authorize',
            'status' => 'success',
            'message' => "ONU {$onu['sn']} authorized as {$description}" . ($assignedOnuId ? " (ONU ID: {$assignedOnuId})" : '') . ($tr069Vlan ? " with TR-069" : ''),
            'command_sent' => $cliScript,
            'command_response' => $output,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'message' => "ONU authorized successfully" . ($assignedOnuId ? " as ONU ID {$assignedOnuId}" : '') . ($tr069Vlan ? " with TR-069 configured" : ''),
            'onu_id' => $assignedOnuId,
            'description' => $description,
            'output' => $output,
            'tr069_configured' => !empty($tr069Vlan)
        ];
    }
    
    private function generateNextSNSCode(int $oltId): string {
        // Find highest SNS number for this OLT
        $stmt = $this->db->prepare("
            SELECT name FROM huawei_onus 
            WHERE olt_id = ? AND name LIKE 'SNS%' 
            ORDER BY name DESC LIMIT 1
        ");
        $stmt->execute([$oltId]);
        $lastName = $stmt->fetchColumn();
        
        $nextNum = 1;
        if ($lastName && preg_match('/SNS(\d+)/i', $lastName, $m)) {
            $nextNum = (int)$m[1] + 1;
        }
        
        return 'SNS' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }
    
    public function markAllONUsAuthorized(int $oltId): array {
        $stmt = $this->db->prepare("
            UPDATE huawei_onus 
            SET is_authorized = TRUE, updated_at = CURRENT_TIMESTAMP
            WHERE olt_id = ? AND (is_authorized = FALSE OR is_authorized IS NULL)
        ");
        $stmt->execute([$oltId]);
        $count = $stmt->rowCount();
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'mark_all_authorized',
            'status' => 'success',
            'message' => "Marked {$count} ONUs as authorized",
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return ['success' => true, 'count' => $count];
    }
    
    public function authorizeONUWithSmartOLT(int $onuId, int $profileId, string $authMethod = 'sn', string $loid = '', string $loidPassword = '', array $smartoltData = []): array {
        $localResult = $this->authorizeONU($onuId, $profileId, $authMethod, $loid, $loidPassword);
        
        if (!$localResult['success']) {
            return $localResult;
        }
        
        if (empty($smartoltData) || empty($smartoltData['sync_to_smartolt'])) {
            return $localResult;
        }
        
        require_once __DIR__ . '/SmartOLT.php';
        $smartolt = new \SmartOLT($this->db);
        
        $onu = $this->getONU($onuId);
        
        $smartoltPayload = [
            'sn' => $onu['sn'],
            'name' => $smartoltData['name'] ?? $onu['name'] ?? $onu['sn'],
            'olt_id' => $smartoltData['smartolt_olt_id'] ?? null,
            'onu_type' => $smartoltData['onu_type'] ?? 'bridge',
            'zone' => $smartoltData['zone'] ?? null,
            'odb' => $smartoltData['odb'] ?? null,
            'vlan' => $smartoltData['vlan'] ?? null,
            'speed_profile' => $smartoltData['speed_profile'] ?? null,
        ];
        
        if (!empty($smartoltPayload['olt_id'])) {
            $smartoltResult = $smartolt->authorizeONU($smartoltPayload);
            
            $localResult['smartolt_sync'] = $smartoltResult['status'] ?? false;
            $localResult['smartolt_message'] = $smartoltResult['message'] ?? ($smartoltResult['error'] ?? 'Unknown');
            
            $this->addLog([
                'olt_id' => $onu['olt_id'],
                'onu_id' => $onuId,
                'action' => 'smartolt_sync',
                'status' => ($smartoltResult['status'] ?? false) ? 'success' : 'failed',
                'message' => "SmartOLT sync: " . ($smartoltResult['message'] ?? $smartoltResult['error'] ?? 'Unknown'),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
        }
        
        return $localResult;
    }
    
    public function rebootONU(int $onuId): array {
        $onu = $this->getONU($onuId);
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        $command = "ont reboot {$onu['frame']}/{$onu['slot']}/{$onu['port']} {$onu['onu_id']}";
        $result = $this->executeCommand($onu['olt_id'], $command);
        
        $this->addLog([
            'olt_id' => $onu['olt_id'],
            'onu_id' => $onuId,
            'action' => 'reboot',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['success'] ? "ONU {$onu['sn']} rebooted" : $result['message'],
            'command_sent' => $command,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    public function deleteONUFromOLT(int $onuId): array {
        $onu = $this->getONU($onuId);
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        $command = "ont delete {$onu['frame']}/{$onu['slot']}/{$onu['port']} {$onu['onu_id']}";
        $result = $this->executeCommand($onu['olt_id'], $command);
        
        if ($result['success']) {
            $this->deleteONU($onuId);
        }
        
        $this->addLog([
            'olt_id' => $onu['olt_id'],
            'onu_id' => $onuId,
            'action' => 'delete',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['success'] ? "ONU {$onu['sn']} deleted" : $result['message'],
            'command_sent' => $command,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    public function resetONUConfig(int $onuId): array {
        $onu = $this->getONU($onuId);
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        $command = "ont reset {$onu['frame']}/{$onu['slot']}/{$onu['port']} {$onu['onu_id']}";
        $result = $this->executeCommand($onu['olt_id'], $command);
        
        $this->addLog([
            'olt_id' => $onu['olt_id'],
            'onu_id' => $onuId,
            'action' => 'reset_config',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['success'] ? "ONU {$onu['sn']} configuration reset" : $result['message'],
            'command_sent' => $command,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    public function configureONUService(int $onuId, array $config): array {
        $onu = $this->getONU($onuId);
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuIdNum = $onu['onu_id'];
        $oltId = $onu['olt_id'];
        
        $allOutput = '';
        $commandsExecuted = [];
        $overallSuccess = true;
        
        // Configure IP mode (DHCP or static) - requires interface context
        if (isset($config['ip_mode'])) {
            $vlan = $config['vlan_id'] ?? 69;
            $priority = $config['vlan_priority'] ?? 0;
            
            // Enter interface context, configure, then exit
            $interfaceCmd = "interface gpon {$frame}/{$slot}";
            $configCmd = "ont ipconfig {$port} {$onuIdNum} {$config['ip_mode']} vlan {$vlan} priority {$priority}";
            $batchedCommand = "{$interfaceCmd}\n{$configCmd}\nquit";
            
            $result = $this->executeCommand($oltId, $batchedCommand);
            $commandsExecuted[] = $batchedCommand;
            $allOutput .= ($result['output'] ?? '') . "\n";
            
            if (!$result['success']) {
                $overallSuccess = false;
                $this->addLog([
                    'olt_id' => $oltId, 'onu_id' => $onuId, 'action' => 'configure_ip_mode',
                    'status' => 'failed', 'message' => 'IP mode configuration failed',
                    'command_sent' => $batchedCommand, 'command_response' => $result['output'] ?? '',
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return ['success' => false, 'message' => 'IP mode configuration failed', 'output' => $allOutput];
            }
        }
        
        // Configure service port (VLAN binding) - global command, no interface context needed
        if (isset($config['service_vlan'])) {
            $gemPort = $config['gem_port'] ?? 1;
            $multiService = $config['multi_service'] ?? 'user-vlan';
            $rxTraffic = $config['rx_traffic_table'] ?? 6;
            $txTraffic = $config['tx_traffic_table'] ?? 6;
            
            $spCmd = "service-port vlan {$config['service_vlan']} gpon {$frame}/{$slot}/{$port} ont {$onuIdNum} gemport {$gemPort} multi-service {$multiService} rx-cttr {$rxTraffic} tx-cttr {$txTraffic}";
            
            $result = $this->executeCommand($oltId, $spCmd);
            $commandsExecuted[] = $spCmd;
            $allOutput .= ($result['output'] ?? '') . "\n";
            
            if (!$result['success']) {
                $overallSuccess = false;
                $this->addLog([
                    'olt_id' => $oltId, 'onu_id' => $onuId, 'action' => 'configure_service_port',
                    'status' => 'failed', 'message' => 'Service port configuration failed',
                    'command_sent' => $spCmd, 'command_response' => $result['output'] ?? '',
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return ['success' => false, 'message' => 'Service port configuration failed', 'output' => $allOutput];
            }
        }
        
        // Configure bandwidth profile - requires interface context
        if (isset($config['traffic_table_index'])) {
            $interfaceCmd = "interface gpon {$frame}/{$slot}";
            $trafficCmd = "ont traffic-table-index {$port} {$onuIdNum} {$config['traffic_table_index']}";
            $batchedCommand = "{$interfaceCmd}\n{$trafficCmd}\nquit";
            
            $result = $this->executeCommand($oltId, $batchedCommand);
            $commandsExecuted[] = $batchedCommand;
            $allOutput .= ($result['output'] ?? '') . "\n";
            
            if (!$result['success']) {
                $overallSuccess = false;
            }
        }
        
        if (empty($commandsExecuted)) {
            return ['success' => false, 'message' => 'No configuration parameters provided'];
        }
        
        $fullCommand = implode("\n---\n", $commandsExecuted);
        $this->addLog([
            'olt_id' => $oltId,
            'onu_id' => $onuId,
            'action' => 'configure_service',
            'status' => $overallSuccess ? 'success' : 'partial',
            'message' => $overallSuccess ? "ONU {$onu['sn']} service configured" : 'Some configurations may have failed',
            'command_sent' => $fullCommand,
            'command_response' => $allOutput,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => $overallSuccess,
            'message' => $overallSuccess ? 'Service configured successfully' : 'Some configurations may have failed',
            'output' => $allOutput,
            'commands_executed' => count($commandsExecuted)
        ];
    }
    
    public function getONUServicePorts(int $onuId): array {
        $onu = $this->getONU($onuId);
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        $command = "display service-port port {$onu['frame']}/{$onu['slot']}/{$onu['port']} ont {$onu['onu_id']}";
        $result = $this->executeCommand($onu['olt_id'], $command);
        
        if (!$result['success']) {
            return $result;
        }
        
        $servicePorts = [];
        $lines = explode("\n", $result['output'] ?? '');
        foreach ($lines as $line) {
            if (preg_match('/^\s*(\d+)\s+\d+\s+gpon\s+[\d\/]+\s+(\d+)\s+(\d+)\s+.*vlan\s+(\d+)/i', $line, $m)) {
                $servicePorts[] = [
                    'index' => (int)$m[1],
                    'gem_port' => (int)$m[2],
                    'user_vlan' => (int)$m[3],
                    'service_vlan' => (int)$m[4]
                ];
            }
        }
        
        return ['success' => true, 'service_ports' => $servicePorts, 'raw' => $result['output']];
    }
    
    public function deleteServicePort(int $oltId, int $servicePortIndex): array {
        $command = "undo service-port {$servicePortIndex}";
        $result = $this->executeCommand($oltId, $command);
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'delete_service_port',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['success'] ? "Service port {$servicePortIndex} deleted" : 'Delete failed',
            'command_sent' => $command,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    public function updateONUDescription(int $onuId, string $description): array {
        $onu = $this->getONU($onuId);
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        $cleanDesc = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $description);
        $cleanDesc = substr(trim($cleanDesc), 0, 64);
        
        $command = "interface gpon {$onu['frame']}/{$onu['slot']}";
        $this->executeCommand($onu['olt_id'], $command);
        
        $descCommand = "ont modify {$onu['port']} {$onu['onu_id']} desc \"{$cleanDesc}\"";
        $result = $this->executeCommand($onu['olt_id'], $descCommand);
        
        $this->executeCommand($onu['olt_id'], "quit");
        
        if ($result['success']) {
            $this->updateONU($onuId, ['description' => $description]);
        }
        
        $this->addLog([
            'olt_id' => $onu['olt_id'],
            'onu_id' => $onuId,
            'action' => 'update_description',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['success'] ? "ONU description updated to: {$cleanDesc}" : 'Update failed',
            'command_sent' => $descCommand,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    public function changeONUServiceProfile(int $onuId, int $newProfileId): array {
        $onu = $this->getONU($onuId);
        $profile = $this->getServiceProfile($newProfileId);
        
        if (!$onu || !$profile) {
            return ['success' => false, 'message' => 'ONU or Profile not found'];
        }
        
        // First delete the ONU, then re-add with new profile
        $deleteResult = $this->deleteONUFromOLT($onuId);
        if (!$deleteResult['success']) {
            return ['success' => false, 'message' => 'Failed to remove ONU for re-provisioning'];
        }
        
        // Re-add the ONU to the database
        $newOnuId = $this->addONU([
            'olt_id' => $onu['olt_id'],
            'sn' => $onu['sn'],
            'frame' => $onu['frame'],
            'slot' => $onu['slot'],
            'port' => $onu['port'],
            'onu_id' => $onu['onu_id'],
            'description' => $onu['description'],
            'status' => 'unconfigured',
            'is_authorized' => false
        ]);
        
        // Authorize with new profile
        return $this->authorizeONU($newOnuId, $newProfileId);
    }
    
    public function getTrafficTables(int $oltId): array {
        $command = "display traffic table ip";
        $result = $this->executeCommand($oltId, $command);
        
        if (!$result['success']) {
            return $result;
        }
        
        $tables = [];
        $lines = explode("\n", $result['output'] ?? '');
        foreach ($lines as $line) {
            if (preg_match('/^\s*(\d+)\s+(\S+)\s+(\d+)\s+(\d+)/i', $line, $m)) {
                $tables[] = [
                    'index' => (int)$m[1],
                    'name' => $m[2],
                    'cir' => (int)$m[3], // Committed Information Rate (kbps)
                    'pir' => (int)$m[4]  // Peak Information Rate (kbps)
                ];
            }
        }
        
        return ['success' => true, 'tables' => $tables, 'raw' => $result['output']];
    }
    
    // ==================== Board & VLAN Management ====================
    
    public function getBoardInfo(int $oltId): array {
        $command = "display board 0";
        $result = $this->executeCommand($oltId, $command);
        
        if (!$result['success']) {
            return $result;
        }
        
        $boards = [];
        $lines = explode("\n", $result['output'] ?? '');
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(\d{1,2})\s+(H\d{3}[A-Z0-9]+)\s+(\S+)/i', $line, $matches)) {
                $status = $matches[3];
                $online = '';
                if (preg_match('/(Online|Offline)\s*$/i', $line, $onlineMatch)) {
                    $online = $onlineMatch[1];
                }
                $boards[] = [
                    'slot' => $matches[1],
                    'board_name' => $matches[2],
                    'status' => $status,
                    'subtype' => '',
                    'online' => $online
                ];
            }
        }
        
        return ['success' => true, 'boards' => $boards, 'raw' => $result['output']];
    }
    
    public function getVLANs(int $oltId): array {
        $command = "display vlan all";
        $result = $this->executeCommand($oltId, $command);
        
        if (!$result['success']) {
            return $result;
        }
        
        $vlans = $this->parseVLANOutput($result['output'] ?? '');
        return ['success' => true, 'vlans' => $vlans, 'raw' => $result['output']];
    }
    
    public function parseVLANOutput(string $output): array {
        $vlans = [];
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line) || preg_match('/^[-=]+$/', $line) || 
                stripos($line, 'display') !== false ||
                stripos($line, 'Total') !== false ||
                stripos($line, 'Command') !== false ||
                stripos($line, 'VLAN   Type') !== false ||
                stripos($line, 'Note :') !== false) {
                continue;
            }
            
            if (preg_match('/^\s*(\d{1,4})\s+(smart|mux|standard|super)\s+(\w+)\s+(\d+)\s+(\d+)\s+(-|\d+)/i', $line, $m)) {
                $vlans[] = [
                    'vlan_id' => (int)$m[1],
                    'vlan_type' => strtolower($m[2]),
                    'attribute' => strtolower($m[3]),
                    'standard_port_count' => (int)$m[4],
                    'service_port_count' => (int)$m[5],
                    'vlan_connect_count' => $m[6] === '-' ? 0 : (int)$m[6]
                ];
            }
        }
        
        return $vlans;
    }
    
    public function parseVLANConfig(string $config): array {
        $result = [
            'vlans' => [],
            'vlan_descriptions' => [],
            'port_vlans' => [],
            'gpon_ports' => []
        ];
        
        $lines = explode("\n", $config);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (preg_match('/^vlan\s+(\d+)(?:\s+to\s+(\d+))?\s+(smart|mux|standard|super)/i', $line, $m)) {
                $startVlan = (int)$m[1];
                $endVlan = isset($m[2]) && !empty($m[2]) ? (int)$m[2] : $startVlan;
                $type = strtolower($m[3]);
                
                for ($v = $startVlan; $v <= $endVlan; $v++) {
                    $result['vlans'][$v] = ['vlan_id' => $v, 'vlan_type' => $type];
                }
            }
            
            if (preg_match('/^vlan\s+desc\s+(\d+)\s+description\s+"([^"]+)"/i', $line, $m)) {
                $result['vlan_descriptions'][(int)$m[1]] = $m[2];
            }
            
            if (preg_match('/^port\s+vlan\s+(\d+)(?:\s+to\s+(\d+))?\s+(\d+)\/(\d+)\s+(\d+)/i', $line, $m)) {
                $startVlan = (int)$m[1];
                $endVlan = isset($m[2]) && !empty($m[2]) ? (int)$m[2] : $startVlan;
                $frame = (int)$m[3];
                $slot = (int)$m[4];
                $port = (int)$m[5];
                $portName = "{$frame}/{$slot}/{$port}";
                
                if (!isset($result['port_vlans'][$portName])) {
                    $result['port_vlans'][$portName] = [];
                }
                
                for ($v = $startVlan; $v <= $endVlan; $v++) {
                    $result['port_vlans'][$portName][] = $v;
                }
            }
            
            if (preg_match('/^port\s+(\d+)\s+ont-auto-find\s+(enable|disable)/i', $line, $m)) {
                $result['gpon_ports'][(int)$m[1]] = [
                    'port' => (int)$m[1],
                    'auto_find' => strtolower($m[2]) === 'enable'
                ];
            }
        }
        
        foreach ($result['vlan_descriptions'] as $vlanId => $desc) {
            if (isset($result['vlans'][$vlanId])) {
                $result['vlans'][$vlanId]['description'] = $desc;
                $result['vlans'][$vlanId]['is_management'] = (stripos($desc, 'MGNT') !== false || stripos($desc, 'management') !== false);
            }
        }
        
        return $result;
    }
    
    public function getUplinkVLANs(int $oltId, string $portName): array {
        $stmt = $this->db->prepare("
            SELECT v.* FROM huawei_vlans v
            JOIN huawei_port_vlans pv ON v.id = pv.vlan_id
            WHERE pv.olt_id = ? AND pv.port_name = ?
            ORDER BY v.vlan_id
        ");
        $stmt->execute([$oltId, $portName]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function parseLineProfiles(string $config): array {
        $profiles = [];
        $currentProfile = null;
        $lines = explode("\n", $config);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (preg_match('/ont-lineprofile\s+gpon\s+profile-id\s+(\d+)\s+profile-name\s+"([^"]+)"/i', $line, $m)) {
                if ($currentProfile !== null) {
                    $profiles[] = $currentProfile;
                }
                $currentProfile = [
                    'profile_id' => (int)$m[1],
                    'profile_name' => $m[2],
                    'tr069_enabled' => false,
                    'fec_upstream' => false,
                    'mapping_mode' => 'vlan',
                    'tconts' => [],
                    'gems' => [],
                    'gem_mappings' => []
                ];
                continue;
            }
            
            if ($currentProfile === null) continue;
            
            if (preg_match('/tr069-management\s+(enable|disable)/i', $line, $m)) {
                $currentProfile['tr069_enabled'] = strtolower($m[1]) === 'enable';
            }
            
            if (preg_match('/fec-upstream\s+(enable|disable)/i', $line, $m)) {
                $currentProfile['fec_upstream'] = strtolower($m[1]) === 'enable';
            }
            
            if (preg_match('/mapping-mode\s+(\w+)/i', $line, $m)) {
                $currentProfile['mapping_mode'] = strtolower($m[1]);
            }
            
            if (preg_match('/tcont\s+(\d+)\s+dba-profile-id\s+(\d+)/i', $line, $m)) {
                $currentProfile['tconts'][(int)$m[1]] = [
                    'tcont_id' => (int)$m[1],
                    'dba_profile_id' => (int)$m[2]
                ];
            }
            
            if (preg_match('/gem\s+add\s+(\d+)\s+(\w+)\s+tcont\s+(\d+)/i', $line, $m)) {
                $currentProfile['gems'][(int)$m[1]] = [
                    'gem_id' => (int)$m[1],
                    'type' => strtolower($m[2]),
                    'tcont_id' => (int)$m[3]
                ];
            }
            
            if (preg_match('/gem\s+mapping\s+(\d+)\s+(\d+)\s+vlan\s+(\d+)/i', $line, $m)) {
                $currentProfile['gem_mappings'][] = [
                    'gem_id' => (int)$m[1],
                    'index' => (int)$m[2],
                    'type' => 'vlan',
                    'vlan_id' => (int)$m[3]
                ];
            }
            
            if (preg_match('/gem\s+mapping\s+(\d+)\s+(\d+)\s+priority\s+(\d+)/i', $line, $m)) {
                $currentProfile['gem_mappings'][] = [
                    'gem_id' => (int)$m[1],
                    'index' => (int)$m[2],
                    'type' => 'priority',
                    'priority' => (int)$m[3]
                ];
            }
            
            if (preg_match('/^\s*quit\s*$/i', $line)) {
                if ($currentProfile !== null) {
                    $profiles[] = $currentProfile;
                    $currentProfile = null;
                }
            }
        }
        
        if ($currentProfile !== null) {
            $profiles[] = $currentProfile;
        }
        
        return $profiles;
    }
    
    public function generateLineProfileScript(array $profile): string {
        $lines = [];
        
        $profileId = $profile['profile_id'] ?? 0;
        $profileName = $profile['profile_name'] ?? "line-profile_{$profileId}";
        
        $lines[] = "ont-lineprofile gpon profile-id {$profileId} profile-name \"{$profileName}\"";
        
        if (!empty($profile['fec_upstream'])) {
            $lines[] = "  fec-upstream enable";
        }
        
        if (!empty($profile['tr069_enabled'])) {
            $lines[] = "  tr069-management enable";
        }
        
        if (!empty($profile['mapping_mode']) && $profile['mapping_mode'] !== 'vlan') {
            $lines[] = "  mapping-mode {$profile['mapping_mode']}";
        }
        
        if (!empty($profile['tconts'])) {
            foreach ($profile['tconts'] as $tcont) {
                $lines[] = "  tcont {$tcont['tcont_id']} dba-profile-id {$tcont['dba_profile_id']}";
            }
        }
        
        if (!empty($profile['gems'])) {
            foreach ($profile['gems'] as $gem) {
                $type = $gem['type'] ?? 'eth';
                $lines[] = "  gem add {$gem['gem_id']} {$type} tcont {$gem['tcont_id']}";
            }
        }
        
        if (!empty($profile['gem_mappings'])) {
            foreach ($profile['gem_mappings'] as $mapping) {
                if ($mapping['type'] === 'vlan') {
                    $lines[] = "  gem mapping {$mapping['gem_id']} {$mapping['index']} vlan {$mapping['vlan_id']}";
                } else {
                    $lines[] = "  gem mapping {$mapping['gem_id']} {$mapping['index']} priority {$mapping['priority']}";
                }
            }
        }
        
        $lines[] = "  commit";
        $lines[] = "  quit";
        
        return implode("\n", $lines);
    }
    
    public function generateServiceProfileScript(array $profile): string {
        $lines = [];
        
        $profileId = $profile['profile_id'] ?? 0;
        $profileName = $profile['profile_name'] ?? "srv-profile_{$profileId}";
        
        $lines[] = "ont-srvprofile gpon profile-id {$profileId} profile-name \"{$profileName}\"";
        
        if (!empty($profile['ont_ports'])) {
            foreach ($profile['ont_ports'] as $port) {
                $portType = $port['type'] ?? 'eth';
                $portCount = $port['count'] ?? 1;
                $lines[] = "  ont-port {$portType} {$portCount}";
            }
        }
        
        if (!empty($profile['port_vlans'])) {
            foreach ($profile['port_vlans'] as $pv) {
                $portType = $pv['type'] ?? 'eth';
                $portNum = $pv['port'] ?? 1;
                $lines[] = "  port vlan {$portType} {$portNum} {$pv['vlan_id']}";
            }
        }
        
        $lines[] = "  commit";
        $lines[] = "  quit";
        
        return implode("\n", $lines);
    }
    
    public function generateONUProvisionScript(array $onu): string {
        $lines = [];
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'] ?? 0;
        $port = $onu['port'] ?? 0;
        $onuId = $onu['onu_id'] ?? 0;
        $sn = $onu['sn'] ?? '';
        $lineProfileId = $onu['line_profile_id'] ?? 2;
        $srvProfileId = $onu['srv_profile_id'] ?? 2;
        $description = $onu['description'] ?? '';
        $vlanId = $onu['vlan_id'] ?? 69;
        $vlanPriority = $onu['vlan_priority'] ?? 2;
        $ipMode = $onu['ip_mode'] ?? 'dhcp';
        $tr069ProfileId = $onu['tr069_profile_id'] ?? null;
        
        $lines[] = "interface gpon {$frame}/{$slot}";
        $lines[] = "  ont add {$port} {$onuId} sn-auth \"{$sn}\" omci ont-lineprofile-id {$lineProfileId} ont-srvprofile-id {$srvProfileId} desc \"{$description}\"";
        $lines[] = "  ont ipconfig {$port} {$onuId} {$ipMode} vlan {$vlanId} priority {$vlanPriority}";
        
        if ($tr069ProfileId !== null) {
            $lines[] = "  ont tr069-server-config {$port} {$onuId} profile-id {$tr069ProfileId}";
        }
        
        $lines[] = "  quit";
        
        if (!empty($onu['service_ports'])) {
            foreach ($onu['service_ports'] as $sp) {
                $spVlan = $sp['vlan_id'] ?? $vlanId;
                $gemId = $sp['gem_id'] ?? 1;
                $multiService = $sp['multi_service'] ?? 'user-vlan';
                $rxTraffic = $sp['rx_traffic'] ?? 'table';
                $txTraffic = $sp['tx_traffic'] ?? 'table';
                
                $lines[] = "service-port vlan {$spVlan} gpon {$frame}/{$slot}/{$port} ont {$onuId} gemport {$gemId} multi-service {$multiService} rx-cttr {$rxTraffic} tx-cttr {$txTraffic}";
            }
        } else {
            $lines[] = "service-port vlan {$vlanId} gpon {$frame}/{$slot}/{$port} ont {$onuId} gemport 1 multi-service user-vlan rx-cttr 6 tx-cttr 6";
        }
        
        return implode("\n", $lines);
    }
    
    public function generateBulkONUScript(array $onus): string {
        $scripts = [];
        foreach ($onus as $onu) {
            $scripts[] = $this->generateONUProvisionScript($onu);
        }
        return implode("\n\n", $scripts);
    }
    
    public function syncVLANsFromOLT(int $oltId): array {
        $result = $this->getVLANs($oltId);
        if (!$result['success']) {
            return $result;
        }
        
        $synced = 0;
        foreach ($result['vlans'] as $vlan) {
            $stmt = $this->db->prepare("
                INSERT INTO huawei_vlans (olt_id, vlan_id, vlan_type, attribute, standard_port_count, 
                                          service_port_count, vlan_connect_count, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (olt_id, vlan_id) DO UPDATE SET
                    vlan_type = EXCLUDED.vlan_type,
                    attribute = EXCLUDED.attribute,
                    standard_port_count = EXCLUDED.standard_port_count,
                    service_port_count = EXCLUDED.service_port_count,
                    vlan_connect_count = EXCLUDED.vlan_connect_count,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $oltId,
                $vlan['vlan_id'],
                $vlan['vlan_type'],
                $vlan['attribute'],
                $vlan['standard_port_count'],
                $vlan['service_port_count'],
                $vlan['vlan_connect_count']
            ]);
            $synced++;
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'sync_vlans',
            'status' => 'success',
            'message' => "Synced {$synced} VLANs from OLT",
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return ['success' => true, 'synced' => $synced, 'vlans' => $result['vlans']];
    }
    
    public function getCachedVLANs(int $oltId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM huawei_vlans 
            WHERE olt_id = ? AND is_active = TRUE 
            ORDER BY vlan_id
        ");
        $stmt->execute([$oltId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function createVLAN(int $oltId, int $vlanId, string $description = '', string $type = 'smart', array $options = []): array {
        if ($vlanId < 1 || $vlanId > 4094) {
            return ['success' => false, 'message' => 'Invalid VLAN ID (1-4094)'];
        }
        
        // Huawei command to create VLAN
        $command = "vlan {$vlanId} {$type}";
        $result = $this->executeCommand($oltId, $command);
        
        // If description provided, set it separately
        if ($result['success'] && !empty($description)) {
            // Clean description - remove special chars, limit length
            $cleanDesc = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $description);
            $cleanDesc = substr(trim($cleanDesc), 0, 32);
            if (!empty($cleanDesc)) {
                $descCommand = "vlan desc {$vlanId} description \"{$cleanDesc}\"";
                $this->executeCommand($oltId, $descCommand);
            }
        }
        
        // Configure VLAN features on OLT
        if ($result['success']) {
            // Multicast VLAN for IPTV
            if (!empty($options['is_multicast'])) {
                $this->executeCommand($oltId, "multicast-vlan {$vlanId}");
            }
            
            // DHCP Snooping
            if (!empty($options['dhcp_snooping'])) {
                $this->executeCommand($oltId, "dhcp snooping enable vlan {$vlanId}");
            }
        }
        
        // Also sync the new VLAN to local cache
        if ($result['success']) {
            $isMulticast = !empty($options['is_multicast']) ? 't' : 'f';
            $isVoip = !empty($options['is_voip']) ? 't' : 'f';
            $isTr069 = !empty($options['is_tr069']) ? 't' : 'f';
            $dhcpSnooping = !empty($options['dhcp_snooping']) ? 't' : 'f';
            $lanToLan = !empty($options['lan_to_lan']) ? 't' : 'f';
            
            $stmt = $this->db->prepare("
                INSERT INTO huawei_vlans (olt_id, vlan_id, vlan_type, description, is_multicast, is_voip, is_tr069, dhcp_snooping, lan_to_lan, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (olt_id, vlan_id) DO UPDATE SET
                    vlan_type = EXCLUDED.vlan_type,
                    description = EXCLUDED.description,
                    is_multicast = EXCLUDED.is_multicast,
                    is_voip = EXCLUDED.is_voip,
                    is_tr069 = EXCLUDED.is_tr069,
                    dhcp_snooping = EXCLUDED.dhcp_snooping,
                    lan_to_lan = EXCLUDED.lan_to_lan,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$oltId, $vlanId, $type, $description, $isMulticast, $isVoip, $isTr069, $dhcpSnooping, $lanToLan]);
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'create_vlan',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['success'] ? "VLAN {$vlanId} created" : ($result['message'] ?? 'Failed'),
            'command_sent' => $command,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    public function deleteVLAN(int $oltId, int $vlanId): array {
        if ($vlanId < 1 || $vlanId > 4094) {
            return ['success' => false, 'message' => 'Invalid VLAN ID'];
        }
        
        $command = "undo vlan {$vlanId}";
        $result = $this->executeCommand($oltId, $command);
        
        // Remove from local cache
        if ($result['success']) {
            $stmt = $this->db->prepare("DELETE FROM huawei_vlans WHERE olt_id = ? AND vlan_id = ?");
            $stmt->execute([$oltId, $vlanId]);
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'delete_vlan',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['success'] ? "VLAN {$vlanId} deleted" : ($result['message'] ?? 'Failed'),
            'command_sent' => $command,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    public function updateVLANDescription(int $oltId, int $vlanId, string $description): array {
        if ($vlanId < 1 || $vlanId > 4094) {
            return ['success' => false, 'message' => 'Invalid VLAN ID'];
        }
        
        // Clean description
        $cleanDesc = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $description);
        $cleanDesc = substr(trim($cleanDesc), 0, 32);
        
        $command = empty($cleanDesc) 
            ? "undo vlan desc {$vlanId}" 
            : "vlan desc {$vlanId} description \"{$cleanDesc}\"";
        
        $result = $this->executeCommand($oltId, $command);
        
        // Update local cache
        if ($result['success']) {
            $stmt = $this->db->prepare("UPDATE huawei_vlans SET description = ?, updated_at = CURRENT_TIMESTAMP WHERE olt_id = ? AND vlan_id = ?");
            $stmt->execute([$description, $oltId, $vlanId]);
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'update_vlan_desc',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['success'] ? "VLAN {$vlanId} description updated" : ($result['message'] ?? 'Failed'),
            'command_sent' => $command,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    public function addVLANToUplink(int $oltId, string $portName, int $vlanId, string $mode = 'tag'): array {
        if ($vlanId < 1 || $vlanId > 4094) {
            return ['success' => false, 'message' => 'Invalid VLAN ID'];
        }
        
        // Parse port name (e.g., "0/8/0" -> frame=0, slot=8, port=0)
        $parts = explode('/', $portName);
        if (count($parts) !== 3) {
            return ['success' => false, 'message' => 'Invalid port format (use frame/slot/port)'];
        }
        
        $frame = $parts[0];
        $slot = $parts[1];
        $port = $parts[2];
        
        // Huawei MA5683T command: port vlan {vlan_id} {frame}/{slot} {port}
        $command = "port vlan {$vlanId} {$frame}/{$slot} {$port}";
        $result = $this->executeCommand($oltId, $command);
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'add_vlan_uplink',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['success'] ? "VLAN {$vlanId} added to uplink {$portName}" : ($result['message'] ?? 'Failed'),
            'command_sent' => $command,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    public function getPONPorts(int $oltId): array {
        $command = "display board 0";
        $result = $this->executeCommand($oltId, $command);
        
        if (!$result['success']) {
            return $result;
        }
        
        $ports = [];
        $gponSlots = [];
        $lines = explode("\n", $result['output'] ?? '');
        foreach ($lines as $line) {
            if (preg_match('/^(\d{1,2})\s+(H\d{3}[A-Z0-9]*GP[A-Z0-9]*)/i', $line, $matches)) {
                $gponSlots[] = (int)$matches[1];
            }
        }
        
        if (empty($gponSlots)) {
            $gponSlots = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16];
        }
        
        foreach ($gponSlots as $slot) {
            for ($portNum = 0; $portNum <= 15; $portNum++) {
                $portName = "0/{$slot}/{$portNum}";
                $ports[] = [
                    'port' => $portName,
                    'type' => 'GPON',
                    'admin' => 'enable',
                    'status' => 'online'
                ];
            }
        }
        
        $onuCounts = $this->getONUCountsByPort($oltId);
        $filteredPorts = [];
        foreach ($ports as $port) {
            $count = $onuCounts[$port['port']] ?? 0;
            if ($count > 0) {
                $port['onu_count'] = $count;
                $filteredPorts[] = $port;
            }
        }
        
        if (empty($filteredPorts)) {
            foreach ($gponSlots as $slot) {
                for ($portNum = 0; $portNum <= 7; $portNum++) {
                    $portName = "0/{$slot}/{$portNum}";
                    $filteredPorts[] = [
                        'port' => $portName,
                        'type' => 'GPON',
                        'admin' => 'enable',
                        'status' => 'online',
                        'onu_count' => 0
                    ];
                }
            }
        }
        
        return ['success' => true, 'ports' => $filteredPorts, 'raw' => $result['output']];
    }
    
    public function getONUCountsByPort(int $oltId): array {
        $stmt = $this->db->prepare("
            SELECT CONCAT(frame, '/', slot, '/', port) as pon_port, COUNT(*) as count
            FROM huawei_onus 
            WHERE olt_id = ?
            GROUP BY frame, slot, port
        ");
        $stmt->execute([$oltId]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['pon_port']] = (int)$row['count'];
        }
        return $counts;
    }
    
    public function getServicePortInfo(int $oltId, int $frame, int $slot, int $port): array {
        $command = "display service-port port {$frame}/{$slot}/{$port}";
        return $this->executeCommand($oltId, $command);
    }
    
    public function getONUSingleInfo(int $oltId, int $frame, int $slot, int $port, int $onuId): array {
        $command = "display ont info {$frame}/{$slot}/{$port} {$onuId}";
        return $this->executeCommand($oltId, $command);
    }
    
    // ==================== Cached Data Management ====================
    
    public function getCachedBoards(int $oltId): array {
        $stmt = $this->db->prepare("SELECT * FROM huawei_olt_boards WHERE olt_id = ? ORDER BY slot");
        $stmt->execute([$oltId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    
    public function getCachedPONPorts(int $oltId): array {
        $stmt = $this->db->prepare("SELECT * FROM huawei_olt_pon_ports WHERE olt_id = ? ORDER BY port_name");
        $stmt->execute([$oltId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getCachedUplinks(int $oltId): array {
        $stmt = $this->db->prepare("SELECT * FROM huawei_olt_uplinks WHERE olt_id = ? ORDER BY port_name");
        $stmt->execute([$oltId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function syncBoardsFromOLT(int $oltId): array {
        $result = $this->getBoardInfo($oltId);
        if (!$result['success']) {
            return $result;
        }
        
        $this->db->prepare("DELETE FROM huawei_olt_boards WHERE olt_id = ?")->execute([$oltId]);
        
        $stmt = $this->db->prepare("
            INSERT INTO huawei_olt_boards (olt_id, slot, board_name, status, online_status)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($result['boards'] as $board) {
            $stmt->execute([
                $oltId,
                $board['slot'],
                $board['board_name'],
                $board['status'],
                $board['online'] ?? null
            ]);
        }
        
        $this->db->prepare("UPDATE huawei_olts SET boards_synced_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$oltId]);
        
        return ['success' => true, 'count' => count($result['boards']), 'raw' => $result['raw']];
    }
    
    
    public function syncPONPortsFromOLT(int $oltId): array {
        $result = $this->getPONPorts($oltId);
        if (!$result['success']) {
            return $result;
        }
        
        $this->db->prepare("DELETE FROM huawei_olt_pon_ports WHERE olt_id = ?")->execute([$oltId]);
        
        $stmt = $this->db->prepare("
            INSERT INTO huawei_olt_pon_ports (olt_id, port_name, port_type, admin_status, oper_status, onu_count)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($result['ports'] as $port) {
            $stmt->execute([
                $oltId,
                $port['port'],
                $port['type'] ?? 'GPON',
                $port['admin'] ?? 'enable',
                $port['status'],
                $port['onu_count'] ?? 0
            ]);
        }
        
        $this->db->prepare("UPDATE huawei_olts SET ports_synced_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$oltId]);
        
        return ['success' => true, 'count' => count($result['ports']), 'raw' => $result['raw']];
    }
    
    public function syncUplinksFromOLT(int $oltId): array {
        $command = "display board 0";
        $result = $this->executeCommand($oltId, $command);
        
        if (!$result['success']) {
            return $result;
        }
        
        $uplinks = [];
        $lines = explode("\n", $result['output'] ?? '');
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(\d{1,2})\s+(H\d{3}[A-Z0-9]*(GI|XG|GE|X2)[A-Z0-9]*)\s+(\S+)/i', $line, $matches)) {
                $slot = (int)$matches[1];
                $boardName = $matches[2];
                $status = $matches[4];
                
                $portCount = 2;
                if (stripos($boardName, 'X2') !== false) $portCount = 2;
                elseif (stripos($boardName, 'X4') !== false) $portCount = 4;
                elseif (stripos($boardName, 'X8') !== false) $portCount = 8;
                elseif (stripos($boardName, 'GI') !== false) $portCount = 4;
                
                for ($p = 0; $p < $portCount; $p++) {
                    $uplinks[] = [
                        'port' => "0/{$slot}/{$p}",
                        'board' => $boardName,
                        'mode' => 'trunk',
                        'pvid' => 1
                    ];
                }
            }
        }
        
        $this->db->prepare("DELETE FROM huawei_olt_uplinks WHERE olt_id = ?")->execute([$oltId]);
        
        $stmt = $this->db->prepare("
            INSERT INTO huawei_olt_uplinks (olt_id, port_name, port_type, vlan_mode, pvid)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($uplinks as $uplink) {
            $stmt->execute([
                $oltId,
                $uplink['port'],
                $uplink['board'] ?? 'GE',
                $uplink['mode'],
                $uplink['pvid']
            ]);
        }
        
        $this->db->prepare("UPDATE huawei_olts SET uplinks_synced_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$oltId]);
        
        return ['success' => true, 'count' => count($uplinks), 'raw' => $result['output']];
    }
    
    public function syncAllFromOLT(int $oltId): array {
        // Extend timeout for full sync operation
        set_time_limit(300);
        
        $results = [
            'system' => $this->syncSystemInfoFromOLT($oltId),
            'boards' => $this->syncBoardsFromOLT($oltId),
            'vlans' => $this->syncVLANsFromOLT($oltId),
            'ports' => $this->syncPONPortsFromOLT($oltId),
            'uplinks' => $this->syncUplinksFromOLT($oltId)
        ];
        
        $success = true;
        $message = [];
        foreach ($results as $type => $result) {
            if ($result['success']) {
                $count = $result['count'] ?? 1;
                $message[] = ucfirst($type) . ": {$count}";
            } else {
                $success = false;
                $message[] = ucfirst($type) . ": Failed";
            }
        }
        
        return ['success' => $success, 'message' => implode(', ', $message), 'details' => $results];
    }
    
    public function syncSystemInfoFromOLT(int $oltId): array {
        $command = "display version";
        $result = $this->executeCommand($oltId, $command);
        
        if (!$result['success']) {
            return $result;
        }
        
        $firmware = '';
        $hardware = '';
        $software = '';
        $uptime = '';
        
        $lines = explode("\n", $result['output'] ?? '');
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/VERSION\s*[:\s]+(.+)/i', $line, $m)) {
                $software = trim($m[1]);
            } elseif (preg_match('/PATCH\s*[:\s]+(.+)/i', $line, $m)) {
                $firmware = trim($m[1]);
            } elseif (preg_match('/PRODUCT\s*[:\s]+(.+)/i', $line, $m)) {
                $hardware = trim($m[1]);
            } elseif (preg_match('/Uptime\s*[:\s]+(.+)/i', $line, $m)) {
                $uptime = trim($m[1]);
            } elseif (preg_match('/MA\d{4}[A-Z]?/i', $line, $m)) {
                if (empty($hardware)) $hardware = $m[0];
            }
        }
        
        $stmt = $this->db->prepare("
            UPDATE huawei_olts SET 
                firmware_version = ?,
                hardware_model = ?,
                software_version = ?,
                uptime = ?,
                system_synced_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$firmware, $hardware, $software, $uptime, $oltId]);
        
        return ['success' => true, 'count' => 1, 'raw' => $result['output']];
    }
    
    // ==================== Service Templates ====================
    
    public function getServiceTemplates(): array {
        $stmt = $this->db->query("SELECT * FROM huawei_service_templates ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getServiceTemplate(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM huawei_service_templates WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createServiceTemplate(array $data): array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO huawei_service_templates 
                (name, description, downstream_bandwidth, upstream_bandwidth, bandwidth_unit, 
                 vlan_id, vlan_mode, qos_profile, iptv_enabled, voip_enabled, tr069_enabled, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['downstream_bandwidth'] ?? 100,
                $data['upstream_bandwidth'] ?? 50,
                $data['bandwidth_unit'] ?? 'mbps',
                $data['vlan_id'] ?? null,
                $data['vlan_mode'] ?? 'tag',
                $data['qos_profile'] ?? '',
                $data['iptv_enabled'] ?? false,
                $data['voip_enabled'] ?? false,
                $data['tr069_enabled'] ?? false,
                $data['is_default'] ?? false
            ]);
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function updateServiceTemplate(int $id, array $data): array {
        try {
            $stmt = $this->db->prepare("
                UPDATE huawei_service_templates SET
                    name = ?, description = ?, downstream_bandwidth = ?, upstream_bandwidth = ?,
                    bandwidth_unit = ?, vlan_id = ?, vlan_mode = ?, qos_profile = ?,
                    iptv_enabled = ?, voip_enabled = ?, tr069_enabled = ?, is_default = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['downstream_bandwidth'] ?? 100,
                $data['upstream_bandwidth'] ?? 50,
                $data['bandwidth_unit'] ?? 'mbps',
                $data['vlan_id'] ?? null,
                $data['vlan_mode'] ?? 'tag',
                $data['qos_profile'] ?? '',
                $data['iptv_enabled'] ?? false,
                $data['voip_enabled'] ?? false,
                $data['tr069_enabled'] ?? false,
                $data['is_default'] ?? false,
                $id
            ]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteServiceTemplate(int $id): array {
        try {
            $stmt = $this->db->prepare("DELETE FROM huawei_service_templates WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ==================== Port Configuration ====================
    
    public function enablePort(int $oltId, string $portName, bool $enable = true): array {
        $command = $enable ? "undo shutdown" : "shutdown";
        
        if (preg_match('/^(\d+)\/(\d+)\/(\d+)$/', $portName, $m)) {
            $fullCommand = "interface gpon {$portName}\n{$command}\nquit";
        } else {
            return ['success' => false, 'message' => 'Invalid port name'];
        }
        
        $result = $this->executeCommand($oltId, $fullCommand);
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => $enable ? 'enable_port' : 'disable_port',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => ($enable ? 'Enabled' : 'Disabled') . " port {$portName}",
            'command_sent' => $fullCommand,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    public function assignPortVLAN(int $oltId, string $portName, int $vlanId, string $mode = 'tag'): array {
        $command = "interface gpon {$portName}\nport vlan {$vlanId} {$mode}\nquit";
        $result = $this->executeCommand($oltId, $command);
        
        if ($result['success']) {
            $stmt = $this->db->prepare("
                INSERT INTO huawei_port_vlans (olt_id, port_name, port_type, vlan_id, vlan_mode)
                VALUES (?, ?, 'pon', ?, ?)
                ON CONFLICT (olt_id, port_name, vlan_id) DO UPDATE SET vlan_mode = EXCLUDED.vlan_mode
            ");
            try {
                $stmt->execute([$oltId, $portName, $vlanId, $mode]);
            } catch (\Exception $e) {
                $stmt = $this->db->prepare("
                    INSERT INTO huawei_port_vlans (olt_id, port_name, port_type, vlan_id, vlan_mode)
                    VALUES (?, ?, 'pon', ?, ?)
                ");
                $stmt->execute([$oltId, $portName, $vlanId, $mode]);
            }
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'assign_port_vlan',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => "Assigned VLAN {$vlanId} to port {$portName}",
            'command_sent' => $command,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    public function getPortVLANs(int $oltId, string $portName): array {
        $stmt = $this->db->prepare("SELECT * FROM huawei_port_vlans WHERE olt_id = ? AND port_name = ?");
        $stmt->execute([$oltId, $portName]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== Uplink Configuration ====================
    
    public function configureUplink(int $oltId, string $portName, array $config): array {
        $commands = ["interface eth {$portName}"];
        
        if (isset($config['vlan_mode'])) {
            $commands[] = "port link-type {$config['vlan_mode']}";
        }
        if (isset($config['pvid'])) {
            $commands[] = "port default vlan {$config['pvid']}";
        }
        if (isset($config['allowed_vlans']) && $config['vlan_mode'] === 'trunk') {
            $commands[] = "port trunk allow-pass vlan {$config['allowed_vlans']}";
        }
        if (isset($config['description'])) {
            $commands[] = "description {$config['description']}";
        }
        
        $commands[] = "quit";
        $fullCommand = implode("\n", $commands);
        
        $result = $this->executeCommand($oltId, $fullCommand);
        
        if ($result['success']) {
            $stmt = $this->db->prepare("
                UPDATE huawei_olt_uplinks SET 
                    vlan_mode = COALESCE(?, vlan_mode),
                    pvid = COALESCE(?, pvid),
                    allowed_vlans = COALESCE(?, allowed_vlans),
                    description = COALESCE(?, description)
                WHERE olt_id = ? AND port_name = ?
            ");
            $stmt->execute([
                $config['vlan_mode'] ?? null,
                $config['pvid'] ?? null,
                $config['allowed_vlans'] ?? null,
                $config['description'] ?? null,
                $oltId,
                $portName
            ]);
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'configure_uplink',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => "Configured uplink {$portName}",
            'command_sent' => $fullCommand,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    public function getONUsBySlotPort(int $oltId): array {
        $stmt = $this->db->prepare("
            SELECT frame, slot, port, COUNT(*) as count, 
                   SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online
            FROM huawei_onus WHERE olt_id = ?
            GROUP BY frame, slot, port
            ORDER BY frame, slot, port
        ");
        $stmt->execute([$oltId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getBoardTypeCategory(string $boardName): string {
        $boardName = strtoupper($boardName);
        if (preg_match('/GP[A-Z0-9]*[DF]/', $boardName)) return 'gpon';
        if (preg_match('/EP[A-Z0-9]*/', $boardName)) return 'epon';
        if (preg_match('/(GI|XG|GE|X2|X4|X8)/', $boardName)) return 'uplink';
        if (preg_match('/(SCUN|SCUK|SCUA|MCUD)/', $boardName)) return 'control';
        if (preg_match('/(PRTE|PILA|PRAM)/', $boardName)) return 'power';
        return 'other';
    }
}
