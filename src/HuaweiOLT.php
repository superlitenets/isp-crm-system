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
    
    private function castBoolean($value): bool {
        if ($value === '' || $value === null || $value === false || $value === 0 || $value === '0') {
            return false;
        }
        if ($value === true || $value === 1) {
            return true;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }
        return (bool)$value;
    }
    
    private function getGenieACSUrl(): ?string {
        $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'genieacs_url'");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $url = $result['setting_value'] ?? null;
        return !empty($url) ? rtrim($url, '/') : null;
    }
    
    // ==================== OLT Management ====================
    
    public function getOLTs(bool $activeOnly = true): array {
        $sql = "SELECT o.*, b.name as branch_name, b.whatsapp_group as branch_whatsapp_group 
                FROM huawei_olts o 
                LEFT JOIN branches b ON o.branch_id = b.id";
        if ($activeOnly) {
            $sql .= " WHERE o.is_active = TRUE";
        }
        $sql .= " ORDER BY o.name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getOLT(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT o.*, b.name as branch_name, b.whatsapp_group as branch_whatsapp_group 
            FROM huawei_olts o 
            LEFT JOIN branches b ON o.branch_id = b.id 
            WHERE o.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    public function getStats(): array {
        $stats = $this->db->query("
            SELECT 
                COUNT(*) as total_onus,
                COUNT(*) FILTER (WHERE is_authorized = TRUE) as authorized_onus,
                COUNT(*) FILTER (WHERE is_authorized = FALSE) as unconfigured_onus,
                COUNT(*) FILTER (WHERE status = 'online') as online_onus,
                COUNT(*) FILTER (WHERE status = 'offline') as offline_onus,
                COUNT(*) FILTER (WHERE status = 'los') as los_onus
            FROM huawei_onus
        ")->fetch(\PDO::FETCH_ASSOC);
        
        return [
            'total_onus' => (int)($stats['total_onus'] ?? 0),
            'authorized_onus' => (int)($stats['authorized_onus'] ?? 0),
            'unconfigured_onus' => (int)($stats['unconfigured_onus'] ?? 0),
            'online_onus' => (int)($stats['online_onus'] ?? 0),
            'offline_onus' => (int)($stats['offline_onus'] ?? 0),
            'los_onus' => (int)($stats['los_onus'] ?? 0)
        ];
    }
    
    public function addOLT(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO huawei_olts (name, ip_address, port, connection_type, username, password_encrypted, 
                                     snmp_read_community, snmp_write_community, snmp_version, snmp_port, vendor, model, location, is_active, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $this->castBoolean($data['is_active'] ?? true),
            !empty($data['branch_id']) ? (int)$data['branch_id'] : null
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateOLT(int $id, array $data): bool {
        $fields = ['name', 'ip_address', 'port', 'connection_type', 'username', 
                   'snmp_read_community', 'snmp_write_community', 'snmp_version', 'snmp_port', 'vendor', 'model', 'location'];
        $intFields = ['branch_id'];
        $booleanFields = ['is_active'];
        $updates = [];
        $params = [];
        
        foreach ($intFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = !empty($data[$field]) ? (int)$data[$field] : null;
            }
        }
        
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
        \exec($cmd, $output, $returnCode);
        
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
        \exec($cmd, $output, $returnCode);
        
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
            $firmwareVersion = null;
            $softwareVersion = null;
            $uptime = null;
            
            if (!empty($info['sysDescr'])) {
                if (preg_match('/Version\s+([^\s,]+)/i', $info['sysDescr'], $m)) {
                    $softwareVersion = $m[1];
                }
                if (preg_match('/MA\d+[A-Z]?/i', $info['sysDescr'], $m)) {
                    $firmwareVersion = $m[0];
                }
            }
            
            if (!empty($info['sysUpTime'])) {
                $uptimeStr = $info['sysUpTime'];
                if (preg_match('/\((\d+)\)/', $uptimeStr, $m)) {
                    $ticks = (int)$m[1];
                    $seconds = (int)($ticks / 100);
                    $days = (int)floor($seconds / 86400);
                    $hours = (int)floor(($seconds % 86400) / 3600);
                    $mins = (int)floor(($seconds % 3600) / 60);
                    $uptime = "{$days}d {$hours}h {$mins}m";
                } else {
                    $uptime = $uptimeStr;
                }
            }
            
            $stmt = $this->db->prepare("
                UPDATE huawei_olts SET 
                    last_sync_at = CURRENT_TIMESTAMP, 
                    last_status = 'online',
                    snmp_last_poll = CURRENT_TIMESTAMP,
                    snmp_status = 'online',
                    snmp_sys_name = ?,
                    snmp_sys_descr = ?,
                    snmp_sys_uptime = ?,
                    snmp_sys_location = ?,
                    software_version = COALESCE(?, software_version),
                    firmware_version = COALESCE(?, firmware_version),
                    uptime = COALESCE(?, uptime)
                WHERE id = ?
            ");
            $stmt->execute([
                $info['sysName'] ?? null,
                $info['sysDescr'] ?? null,
                $uptime,
                $info['sysLocation'] ?? null,
                $softwareVersion,
                $firmwareVersion,
                $uptime,
                $oltId
            ]);
            
            return ['success' => true, 'info' => $info, 'parsed' => [
                'software_version' => $softwareVersion,
                'firmware_version' => $firmwareVersion,
                'uptime' => $uptime
            ]];
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
        // .43.1.2 = hwGponDeviceOntDespt (Description)
        // .43.1.10 = hwGponDeviceOntEquipmentId (Equipment/Model ID like "HG8546M")
        // .46.1.15 = hwGponDeviceOntControlRunStatus (Status)
        // .51.1.4 = hwGponOntOpticalDdmRxPower (RX Power in 0.01dBm)
        // .51.1.5 = hwGponOntOpticalDdmTxPower (TX Power in 0.01dBm)
        // .46.1.20 = hwGponDeviceOntControlRanging (Distance in meters)
        $huaweiONTSerialBase = '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.9';
        $huaweiONTStatusBase = '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.15';
        $huaweiONTDescBase = '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.2';
        $huaweiONTEquipIdBase = '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.10';
        $huaweiONTRxPowerBase = '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.4';
        $huaweiONTTxPowerBase = '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.5';
        $huaweiONTDistanceBase = '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.20';
        
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
        $equipIds = @snmprealwalk($host, $community, $huaweiONTEquipIdBase, 10000000, 2);
        $rxPowers = @snmprealwalk($host, $community, $huaweiONTRxPowerBase, 10000000, 2);
        $txPowers = @snmprealwalk($host, $community, $huaweiONTTxPowerBase, 10000000, 2);
        $distances = @snmprealwalk($host, $community, $huaweiONTDistanceBase, 10000000, 2);
        
        error_log("SNMP Bulk Fetch: equipIds=" . ($equipIds !== false ? count($equipIds) : 'FAILED') . 
                  ", rxPowers=" . ($rxPowers !== false ? count($rxPowers) : 'FAILED') .
                  ", distances=" . ($distances !== false ? count($distances) : 'FAILED'));
        
        $onus = [];
        $debugCount = 0;
        foreach ($serials as $oid => $serial) {
            // Extract index from OID - handle both "1.3.6..." and "iso.3.6..." formats
            // Find the last occurrence of ".9." (table column) or extract last 2-3 numeric parts
            if (preg_match('/\.43\.1\.9\.(.+)$/', $oid, $m)) {
                $indexPart = $m[1];
            } else {
                // Fallback to old method
                $indexPart = substr($oid, strlen($huaweiONTSerialBase) + 1);
            }
            $parts = explode('.', $indexPart);
            
            // Log first 5 entries for debugging
            if ($debugCount < 5) {
                error_log("SNMP ONU #{$debugCount}: OID={$oid}, index={$indexPart}, parts=" . json_encode($parts) . ", serial=" . $this->cleanSnmpValue($serial));
                $debugCount++;
            }
            
            $frame = 0;
            $slot = 0;
            $port = 0;
            $onuId = 0;
            
            // MA5680T OID index format: ifIndex.onuId (2-part)
            // The OID table number (9) should NOT be part of the index
            if (count($parts) >= 4) {
                // 4-part index: frame.slot.port.onu_id (e.g., 0.1.3.12)
                $frame = (int)$parts[0];
                $slot = (int)$parts[1];
                $port = (int)$parts[2];
                $onuId = (int)$parts[3];
            } elseif (count($parts) == 3 && (int)$parts[0] <= 15) {
                // 3-part index where first part might be table column: skip it
                // Format: tableCol.ifIndex.onuId - use parts[1] as ifIndex, parts[2] as onuId
                $ifIndex = (int)$parts[1];
                $onuId = (int)$parts[2];
                
                // Decode ifIndex (e.g., 4194320384 = 0xFA004000)
                if ($ifIndex > 0xFFFFFF) {
                    $ponIndex = $ifIndex & 0xFFFFFF;
                } else {
                    $ponIndex = $ifIndex;
                }
                
                // MA5683T ponIndex bit layout: (slot << 13) | (port << 8)
                $slot = ($ponIndex >> 13) & 0x1F;
                $port = ($ponIndex >> 8) & 0x1F;
                
                error_log("3-part decode: ifIndex={$ifIndex}, ponIndex={$ponIndex}, slot={$slot}, port={$port}, onuId={$onuId}");
            } elseif (count($parts) >= 2) {
                // 2-part index: ifIndex.onuId (Huawei uses ifIndex encoding)
                $ifIndex = (int)$parts[0];
                $onuId = (int)$parts[1];
                
                // Huawei MA5683T/MA5680T ifIndex decoding
                // Format: 0xFA000000 | ponIndex where ponIndex encodes frame/slot/port
                // The exact bit layout varies by firmware version
                
                if ($ifIndex > 0xFFFFFF) {
                    // Strip interface type prefix (0xFA...) to get ponIndex
                    $ponIndex = $ifIndex & 0xFFFFFF;
                } else {
                    $ponIndex = $ifIndex;
                }
                
                // Log for debugging
                error_log("ifIndex decoding: ifIndex={$ifIndex} (0x" . dechex($ifIndex) . "), ponIndex={$ponIndex} (0x" . dechex($ponIndex) . ")");
                
                // Method 1: Common MA5683T format - (slot << 13) | (port << 8)
                // slot in bits 13-17, port in bits 8-12
                $slot1 = ($ponIndex >> 13) & 0x1F;
                $port1 = ($ponIndex >> 8) & 0x1F;
                $frame1 = 0;
                
                // Method 2: Alternative - slot in high byte, port in middle nibble
                $slot2 = ($ponIndex >> 16) & 0xFF;
                $port2 = ($ponIndex >> 8) & 0xFF;
                $frame2 = 0;
                
                // Method 3: Another variant - ponIndex = slot*256 + port
                $slot3 = (int)floor($ponIndex / 256);
                $port3 = $ponIndex % 256;
                
                // Method 4: ponIndex = frame*8192 + slot*256 + port  
                $frame4 = (int)floor($ponIndex / 8192);
                $remainder = $ponIndex % 8192;
                $slot4 = (int)floor($remainder / 256);
                $port4 = $remainder % 256;
                
                // Method 5: Simple nibble-based - slot in high nibble, port in low nibble of lower 16 bits
                $slot5 = ($ponIndex >> 12) & 0xF;
                $port5 = ($ponIndex >> 8) & 0xF;
                
                error_log("Decode attempts: M1[s={$slot1},p={$port1}] M2[s={$slot2},p={$port2}] M3[s={$slot3},p={$port3}] M4[f={$frame4},s={$slot4},p={$port4}] M5[s={$slot5},p={$port5}]");
                
                // Pick the first method that gives valid values
                // For small ponIndex values (like 9), M3/M4 work best: ponIndex = port directly
                if ($ponIndex <= 15) {
                    // Small ponIndex - it's likely just the port number directly
                    $frame = 0;
                    $slot = 0;
                    $port = $ponIndex;
                } elseif ($slot1 >= 0 && $slot1 <= 21 && $port1 <= 15 && ($slot1 > 0 || $port1 > 0)) {
                    $frame = $frame1;
                    $slot = $slot1;
                    $port = $port1;
                } elseif ($slot2 >= 0 && $slot2 <= 21 && $port2 <= 15 && ($slot2 > 0 || $port2 > 0)) {
                    $frame = $frame2;
                    $slot = $slot2;
                    $port = $port2;
                } elseif ($slot4 <= 21 && $port4 <= 15 && $frame4 <= 7) {
                    $frame = $frame4;
                    $slot = $slot4;
                    $port = $port4;
                } elseif ($slot3 <= 21 && $port3 <= 15) {
                    $frame = 0;
                    $slot = $slot3;
                    $port = $port3;
                } else {
                    // All methods failed - keep zeros and try to extract from description later
                    $frame = 0;
                    $slot = 0;
                    $port = 0;
                }
                
                // Cap onu_id at 128
                if ($onuId > 128) $onuId = $onuId % 128;
            } elseif (count($parts) == 1) {
                // Single part - might be just ifIndex, try to decode
                $ifIndex = (int)$parts[0];
                $ponIndex = $ifIndex > 0xFFFFFF ? ($ifIndex & 0xFFFFFF) : $ifIndex;
                $slot = ($ponIndex >> 13) & 0x1F;
                $port = ($ponIndex >> 8) & 0x1F;
                $onuId = 0; // Unknown
            } else {
                continue; // Skip invalid entries
            }
            
            $statusOid = $huaweiONTStatusBase . '.' . $indexPart;
            $status = isset($statuses[$statusOid]) ? $this->parseONUStatus((int)$this->cleanSnmpValue($statuses[$statusOid])) : 'unknown';
            
            $descOid = $huaweiONTDescBase . '.' . $indexPart;
            $desc = isset($descriptions[$descOid]) ? $this->cleanSnmpValue($descriptions[$descOid]) : '';
            
            // Equipment ID (model like "HG8546M")
            $equipIdOid = $huaweiONTEquipIdBase . '.' . $indexPart;
            $equipId = isset($equipIds[$equipIdOid]) ? $this->cleanSnmpValue($equipIds[$equipIdOid]) : '';
            
            // RX/TX Power (in 0.01 dBm units, need to divide by 100)
            // Use status table index format for optical data (.46 instead of .43)
            $statusIndex = $indexPart;
            $rxPowerOid = $huaweiONTRxPowerBase . '.' . $statusIndex;
            $txPowerOid = $huaweiONTTxPowerBase . '.' . $statusIndex;
            $distanceOid = $huaweiONTDistanceBase . '.' . $statusIndex;
            
            $rxPowerRaw = isset($rxPowers[$rxPowerOid]) ? (int)$this->cleanSnmpValue($rxPowers[$rxPowerOid]) : null;
            $txPowerRaw = isset($txPowers[$txPowerOid]) ? (int)$this->cleanSnmpValue($txPowers[$txPowerOid]) : null;
            $distanceRaw = isset($distances[$distanceOid]) ? (int)$this->cleanSnmpValue($distances[$distanceOid]) : null;
            
            // Convert power from 0.01 dBm to dBm (divide by 100)
            $rxPower = ($rxPowerRaw !== null && $rxPowerRaw != 0 && $rxPowerRaw != 2147483647) ? round($rxPowerRaw / 100, 2) : null;
            $txPower = ($txPowerRaw !== null && $txPowerRaw != 0 && $txPowerRaw != 2147483647) ? round($txPowerRaw / 100, 2) : null;
            $distance = ($distanceRaw !== null && $distanceRaw >= 0 && $distanceRaw < 100000) ? $distanceRaw : null;
            
            // If slot/port are still 0, try to extract from description
            // SmartOLT often stores location in description like "SNS001328_zone_name_0/9/5" or "_0_9_5_"
            if ($slot == 0 && $port == 0 && !empty($desc)) {
                // Try pattern: frame/slot/port (e.g., "0/9/5")
                if (preg_match('/(\d+)\/(\d+)\/(\d+)/', $desc, $locMatch)) {
                    $frame = (int)$locMatch[1];
                    $slot = (int)$locMatch[2];
                    $port = (int)$locMatch[3];
                    error_log("Extracted location from desc pattern /: {$frame}/{$slot}/{$port}");
                }
                // Try pattern: _frame_slot_port_ (e.g., "_0_9_5_")
                elseif (preg_match('/_(\d+)_(\d+)_(\d+)/', $desc, $locMatch)) {
                    $frame = (int)$locMatch[1];
                    $slot = (int)$locMatch[2];
                    $port = (int)$locMatch[3];
                    error_log("Extracted location from desc pattern _: {$frame}/{$slot}/{$port}");
                }
            }
            
            $onus[] = [
                'sn' => $this->cleanSnmpValue($serial),
                'frame' => $frame,
                'slot' => $slot,
                'port' => $port,
                'onu_id' => $onuId,
                'status' => $status,
                'description' => $desc,
                'index' => $indexPart,
                'equipment_id' => $equipId,
                'rx_power' => $rxPower,
                'tx_power' => $txPower,
                'distance' => $distance
            ];
        }
        
        error_log("Parsed " . count($onus) . " ONUs from SNMP");
        
        return ['success' => true, 'onus' => $onus, 'count' => count($onus)];
    }
    
    /**
     * Discover unconfigured/autofind ONUs via SNMP (like SmartOLT does)
     * Uses hwGponOntAutoFindTable OID: 1.3.6.1.4.1.2011.6.128.1.1.2.52
     */
    public function getUnconfiguredONUsViaSNMP(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        if (!function_exists('snmprealwalk')) {
            return ['success' => false, 'error' => 'PHP SNMP extension not installed'];
        }
        
        $community = $olt['snmp_community'] ?? $olt['snmp_read_community'] ?? 'public';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        
        if (function_exists('snmp_set_quick_print')) {
            snmp_set_quick_print(true);
        }
        if (function_exists('snmp_set_valueretrieval')) {
            snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
        }
        
        // hwGponOntAutoFindTable - contains unconfigured ONUs
        // .52.1.2 = hwGponDeviceOntAutoFindOnuType (ONU type/equipment ID)
        // .52.1.3 = hwGponDeviceOntAutoFindSerialNumber
        // .52.1.4 = hwGponDeviceOntAutoFindPassword (LOID if used)
        $autofindBase = '1.3.6.1.4.1.2011.6.128.1.1.2.52';
        $autofindSerialOid = $autofindBase . '.1.3';  // Serial number
        $autofindTypeOid = $autofindBase . '.1.2';    // ONU type/equipment ID
        
        $serials = @snmprealwalk($host, $community, $autofindSerialOid, 15000000, 2);
        
        if ($serials === false || empty($serials)) {
            // Try alternative OID structure
            $altSerialOid = $autofindBase . '.1.1';
            $serials = @snmprealwalk($host, $community, $altSerialOid, 15000000, 2);
        }
        
        if ($serials === false || empty($serials)) {
            return ['success' => false, 'error' => 'No unconfigured ONUs found via SNMP or autofind table not accessible'];
        }
        
        $types = @snmprealwalk($host, $community, $autofindTypeOid, 10000000, 2);
        
        $unconfigured = [];
        foreach ($serials as $oid => $serial) {
            // Parse index from OID: base.column.frame.slot.port.index
            $indexPart = substr($oid, strlen($autofindSerialOid) + 1);
            $parts = explode('.', $indexPart);
            
            $frame = 0;
            $slot = 0;
            $port = 0;
            $index = 0;
            
            if (count($parts) >= 4) {
                $frame = (int)$parts[0];
                $slot = (int)$parts[1];
                $port = (int)$parts[2];
                $index = (int)$parts[3];
            } elseif (count($parts) >= 2) {
                // ifIndex.index format - decode ifIndex
                $ifIndex = (int)$parts[0];
                $index = (int)$parts[1];
                
                if ($ifIndex > 0xFFFFFF) {
                    $ponIndex = $ifIndex & 0xFFFFFF;
                } else {
                    $ponIndex = $ifIndex;
                }
                
                if ($ponIndex > 0) {
                    $port = ($ponIndex >> 13) & 0x7;
                    $slot = ($ponIndex >> 8) & 0x1F;
                    $frame = $ponIndex & 0xFF;
                    
                    if ($frame > 7) {
                        $slot = (int)floor($ponIndex / 256);
                        $port = $ponIndex % 256;
                        $frame = 0;
                        
                        if ($slot > 21 || $port > 15) {
                            $slot = (int)floor($ponIndex / 8);
                            $port = $ponIndex % 8;
                        }
                    }
                }
            }
            
            // Get ONU type if available
            $typeOid = $autofindTypeOid . '.' . $indexPart;
            $onuType = isset($types[$typeOid]) ? $this->cleanSnmpValue($types[$typeOid]) : '';
            
            $cleanSerial = $this->cleanSnmpValue($serial);
            if (empty($cleanSerial)) continue;
            
            $unconfigured[] = [
                'sn' => $cleanSerial,
                'frame' => $frame,
                'slot' => $slot,
                'port' => $port,
                'index' => $index,
                'onu_type' => $onuType,
                'fsp' => "{$frame}/{$slot}/{$port}"
            ];
        }
        
        error_log("SNMP Autofind: Found " . count($unconfigured) . " unconfigured ONUs on OLT {$oltId}");
        
        return ['success' => true, 'onus' => $unconfigured, 'count' => count($unconfigured)];
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
        // MA5800 series: ponIndex = 4194304000 + slot*8192 + port*256
        // Legacy: ponIndex = frame*8192 + slot*256 + port
        $ponIndex = 4194304000 + $slot * 8192 + $port * 256;
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
        // hwGponDeviceOntDistance OID (meters)
        $distanceOID = "1.3.6.1.4.1.2011.6.128.1.1.2.46.1.20.{$indexSuffix}";
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
        $p = (int)$port;
        $o = (int)$onuId;
        $command = "display ont info {$frame}/{$slot} " . $p . " " . $o;
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
        // "ONT distance(m)        : 10"
        if (preg_match('/(?:Distance|ONT distance|Ont distance|ONU distance)\s*\(?m?\)?\s*:\s*(\d+)/i', $output, $m)) {
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
        
        // Huawei requires entering GPON interface context first for optical-info
        // Commands must be sent completely separately - OLT can't handle chained commands
        $p = (int)$port;
        $o = (int)$onuId;
        
        $output = '';
        
        // First command set: Get ont info (distance, status, IP)
        $this->executeCommand($oltId, "interface gpon {$frame}/{$slot}");
        $infoCmd = "display ont info " . $p . " " . $o;
        $infoResult = $this->executeCommand($oltId, $infoCmd);
        if ($infoResult['success']) {
            $output = $infoResult['output'] ?? '';
        }
        $this->executeCommand($oltId, "quit");
        
        // Second command set: Get optical info (fresh context)
        $this->executeCommand($oltId, "interface gpon {$frame}/{$slot}");
        $opticalCmd = "display ont optical-info " . $p . " " . $o;
        $opticalResult = $this->executeCommand($oltId, $opticalCmd);
        if ($opticalResult['success']) {
            $output .= "\n" . ($opticalResult['output'] ?? '');
        }
        $this->executeCommand($oltId, "quit");
        
        if (empty($output)) {
            return ['success' => false, 'error' => 'CLI commands failed'];
        }
        
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
        // Format: "ONT distance(m)         : 10"
        if (preg_match('/(?:Distance|ONT distance|Ont distance)\s*\(?m?\)?\s*:\s*(\d+)/i', $output, $m)) {
            $distance = (int)$m[1];
        }
        if (preg_match('/Run state\s*:\s*(\w+)/i', $output, $m)) {
            $status = strtolower($m[1]);
        }
        
        // Parse ONT management IP (for TR-069/ACS detection)
        // Format: ONT IP 0 address/mask   : 10.97.127.32/16
        $ontIp = null;
        if (preg_match('/ONT\s+IP\s*\d*\s*address\/mask\s*:\s*([\d.]+)/i', $output, $m)) {
            $ip = $m[1];
            if ($ip && $ip !== '0.0.0.0') {
                $ontIp = $ip;
            }
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
                'ont_ip' => $ontIp,
            ],
            'debug' => [
                'method' => 'cli',
                'interface_cmd' => $interfaceCmd,
                'optical_cmd' => $opticalCmd,
                'info_cmd' => $infoCmd,
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
        
        // Cache for zones to avoid repeated queries
        $zoneCache = [];
        
        foreach ($result['onus'] as $onu) {
            $existing = $this->getONUBySN($onu['sn']);
            
            // Parse SmartOLT description format: NAME_zone_ZONENAME_descr_ADDRESS_authd_DATE
            // SmartOLT stores this format in the ONU description field which appears in SNMP serial OID
            $snField = $onu['sn'] ?? '';
            $desc = $onu['description'] ?? '';
            $onuName = '';
            $zoneName = '';
            $address = '';
            $cleanSn = $snField; // Keep original for matching, but extract clean SN
            
            // Parse from SN field first (SmartOLT stores description in serial field)
            // Format: SNS001328_zone_DYKAAN_descr_Abdul_Swabulu_authd_20251016
            if (!empty($snField) && strpos($snField, '_zone_') !== false) {
                // Extract zone name (everything between _zone_ and next _descr_ or _authd_ or end)
                if (preg_match('/_zone_([^_]+(?:_[^_]+)*?)(?:_descr_|_authd_|$)/i', $snField, $zm)) {
                    $zoneName = trim($zm[1]);
                }
                // Extract address/customer name from _descr_ section
                if (preg_match('/_descr_(.+?)(?:_authd_|$)/i', $snField, $dm)) {
                    $address = str_replace('_', ' ', trim($dm[1]));
                }
                // Extract clean SN (first part before _zone_)
                if (preg_match('/^([A-Z0-9]+)_zone_/i', $snField, $snm)) {
                    $cleanSn = trim($snm[1]);
                    $onuName = $cleanSn;
                }
            }
            // Fallback: parse from description field
            elseif (!empty($desc)) {
                if (preg_match('/_zone_([^_]+(?:_[^_]+)*?)(?:_descr_|_authd_|$)/i', $desc, $zm)) {
                    $zoneName = trim($zm[1]);
                }
                if (preg_match('/_descr_(.+?)(?:_authd_|$)/i', $desc, $dm)) {
                    $address = str_replace('_', ' ', trim($dm[1]));
                }
                $parts = explode('_', $desc);
                $onuName = trim($parts[0]);
            }
            
            if (empty($onuName)) {
                $onuName = "ONU {$onu['slot']}/{$onu['port']}:{$onu['onu_id']}";
            }
            
            // Debug logging for zone parsing
            if (!empty($zoneName)) {
                error_log("SNMP Sync: Parsed zone '{$zoneName}' from SN '{$snField}'");
            }
            
            // Detect ONU type from equipment_id (model from SNMP like "HG8546M")
            $onuTypeId = null;
            $equipmentId = $onu['equipment_id'] ?? '';
            if (!empty($equipmentId)) {
                // Match by model name (case-insensitive)
                $stmt = $this->db->prepare("SELECT id FROM huawei_onu_types WHERE LOWER(model) = LOWER(?) OR LOWER(name) = LOWER(?) LIMIT 1");
                $stmt->execute([$equipmentId, $equipmentId]);
                $typeRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                $onuTypeId = $typeRow['id'] ?? null;
            }
            
            // Match or create zone
            $zoneId = null;
            if (!empty($zoneName)) {
                if (isset($zoneCache[$zoneName])) {
                    $zoneId = $zoneCache[$zoneName];
                } else {
                    $stmt = $this->db->prepare("SELECT id FROM huawei_zones WHERE LOWER(name) = LOWER(?) LIMIT 1");
                    $stmt->execute([$zoneName]);
                    $zoneRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($zoneRow) {
                        $zoneId = $zoneRow['id'];
                    } else {
                        // Auto-create zone
                        try {
                            $stmt = $this->db->prepare("INSERT INTO huawei_zones (name, is_active, created_at) VALUES (?, true, NOW()) RETURNING id");
                            $stmt->execute([$zoneName]);
                            $newZone = $stmt->fetch(\PDO::FETCH_ASSOC);
                            $zoneId = $newZone['id'] ?? null;
                        } catch (\Exception $e) {
                            // Zone might already exist due to race condition
                            $stmt = $this->db->prepare("SELECT id FROM huawei_zones WHERE LOWER(name) = LOWER(?) LIMIT 1");
                            $stmt->execute([$zoneName]);
                            $zoneRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                            $zoneId = $zoneRow['id'] ?? null;
                        }
                    }
                    $zoneCache[$zoneName] = $zoneId;
                }
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
            
            // Only set these if we found values (don't overwrite existing data with nulls)
            if ($zoneId) $data['zone_id'] = $zoneId;
            if ($onuTypeId) $data['onu_type_id'] = $onuTypeId;
            if (!empty($address)) $data['address'] = $address;
            
            // Add optical power and distance from SNMP
            if ($onu['rx_power'] !== null) $data['rx_power'] = $onu['rx_power'];
            if ($onu['tx_power'] !== null) $data['tx_power'] = $onu['tx_power'];
            if ($onu['distance'] !== null) $data['distance'] = $onu['distance'];
            
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
        
        @fwrite($socket, "quit\r\n"); usleep(200000);
        @fwrite($socket, "quit\r\n"); usleep(200000);
        @fclose($socket);
        
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
     * Read from socket until one of the expected strings is found
     */
    private function readUntilOLT($socket, array $expect, int $timeout = 10): string {
        $buffer = '';
        $startTime = time();
        stream_set_timeout($socket, 1);
        
        while ((time() - $startTime) < $timeout) {
            $char = @fread($socket, 1024);
            if ($char !== false && $char !== '') {
                $buffer .= $char;
                foreach ($expect as $str) {
                    if (stripos($buffer, $str) !== false) {
                        return $buffer;
                    }
                }
            }
            usleep(50000);
        }
        return $buffer;
    }
    
    /**
     * Drain remaining data from socket
     */
    private function drainSocketOLT($socket): string {
        $buffer = '';
        stream_set_timeout($socket, 1);
        while (true) {
            $data = @fread($socket, 4096);
            if ($data === false || $data === '') {
                break;
            }
            $buffer .= $data;
        }
        return $buffer;
    }
    
    /**
     * Get live data for a single ONU - returns cached data from database (fast, non-blocking)
     * For live OLT queries, use triggerBackgroundRefresh() then poll the database
     */
    public function getSingleONULiveData(int $oltId, int $frame, ?int $slot, ?int $port, ?int $onuId, string $sn = ''): array {
        // FAST PATH: Return cached data from database instead of blocking CLI queries
        // The background SNMP polling worker keeps the database up to date
        
        $onu = null;
        if (!empty($sn)) {
            $stmt = $this->db->prepare("SELECT * FROM huawei_onus WHERE sn = ?");
            $stmt->execute([$sn]);
            $onu = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        if (!$onu && $oltId && $slot !== null && $port !== null && $onuId !== null) {
            $stmt = $this->db->prepare("SELECT * FROM huawei_onus WHERE olt_id = ? AND frame = ? AND slot = ? AND port = ? AND onu_id = ?");
            $stmt->execute([$oltId, $frame, $slot, $port, $onuId]);
            $onu = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found in database'];
        }
        
        // Trigger a background refresh via the OLT Session Service (non-blocking)
        $this->triggerBackgroundRefresh($oltId, $onu['id']);
        
        // Return cached data immediately
        return [
            'success' => true,
            'onu' => [
                'status' => $onu['status'] ?? 'unknown',
                'rx_power' => $onu['rx_power'] !== null ? (float)$onu['rx_power'] : null,
                'tx_power' => $onu['tx_power'] !== null ? (float)$onu['tx_power'] : null,
                'distance' => $onu['distance'] !== null ? (int)$onu['distance'] : null,
                'name' => $onu['name'] ?? '',
                'tr069_ip' => $onu['tr069_ip'] ?? null,
                'updated_at' => $onu['updated_at'] ?? null,
                'cached' => true
            ]
        ];
    }
    
    /**
     * Trigger a background refresh for a specific ONU via the OLT Session Service
     * This is non-blocking - the service will update the database asynchronously
     */
    private function triggerBackgroundRefresh(int $oltId, int $onuDbId): void {
        try {
            $serviceUrl = getenv('OLT_SERVICE_URL') ?: 'http://localhost:3002';
            $ch = curl_init("{$serviceUrl}/refresh-onu");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['oltId' => $oltId, 'onuDbId' => $onuDbId]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT_MS => 500, // Very short timeout - fire and forget
                CURLOPT_CONNECTTIMEOUT_MS => 200
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            // Ignore errors - this is best-effort background refresh
        }
    }
    
    /**
     * Get live data for a single ONU via CLI (BLOCKING - use only for manual refresh)
     * This method makes synchronous OLT queries and should not be used for Live Mode
     */
    public function getSingleONULiveDataCLI(int $oltId, int $frame, ?int $slot, ?int $port, ?int $onuId, string $sn = ''): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        if ($slot === null || $port === null || $onuId === null) {
            return ['success' => false, 'error' => 'Slot, port and ONU ID required'];
        }
        
        set_time_limit(180);
        
        // Use OLT Session Service for CLI commands (works through VPN)
        // Execute commands sequentially - each waits for its prompt
        $fullOutput = '';
        
        // Ensure proper spacing for CLI commands
        $p = (int)$port;
        $o = (int)$onuId;
        
        // 1. Enter interface context
        $interfaceCmd = "interface gpon {$frame}/{$slot}";
        $this->executeViaService($oltId, $interfaceCmd, 30000);
        
        // 2. Get optical info
        $opticalCmd = "display ont optical-info " . $p . " " . $o;
        $opticalResult = $this->executeViaService($oltId, $opticalCmd, 30000);
        $opticalOutput = '';
        if ($opticalResult['success']) {
            $opticalOutput = $opticalResult['output'] ?? '';
            $opticalOutput = preg_replace('/\x1b\[[0-9;]*[A-Za-z]|\[[\d;]*[A-Za-z]/', '', $opticalOutput);
            $fullOutput .= $opticalOutput;
        }
        
        // 3. Get ONU info
        $infoCmd = "display ont info " . $p . " " . $o;
        $infoResult = $this->executeViaService($oltId, $infoCmd, 30000);
        $infoOutput = '';
        if ($infoResult['success']) {
            $infoOutput = $infoResult['output'] ?? '';
            $infoOutput = preg_replace('/\x1b\[[0-9;]*[A-Za-z]|\[[\d;]*[A-Za-z]/', '', $infoOutput);
            $fullOutput .= $infoOutput;
        }
        
        // 4. Get WAN info (for Management IP)
        $wanCmd = "display ont wan-info " . $p . " " . $o;
        $wanResult = $this->executeViaService($oltId, $wanCmd, 30000);
        $wanOutput = '';
        if ($wanResult['success']) {
            $wanOutput = $wanResult['output'] ?? '';
            $wanOutput = preg_replace('/\x1b\[[0-9;]*[A-Za-z]|\[[\d;]*[A-Za-z]/', '', $wanOutput);
            $fullOutput .= $wanOutput;
        }
        
        // 5. Exit interface context
        $this->executeViaService($oltId, "quit", 5000);
        
        // Parse optical power - ONU's Rx power (what ONU receives from OLT)
        $rxPower = null;
        // Parse: Rx optical power(dBm)                  : -0.68
        if (preg_match('/Rx\s+optical\s+power\s*\([^)]*\)\s*:\s*([-\d.]+)/i', $opticalOutput, $m)) {
            $rxPower = (float)$m[1];
        }
        
        // Parse ONU's Tx power (what ONU sends to OLT)
        $txPower = null;
        if (preg_match('/Tx\s+optical\s+power\s*\([^)]*\)\s*:\s*([-\d.]+)/i', $opticalOutput, $m)) {
            $txPower = (float)$m[1];
        }
        
        // Parse status from info output - try multiple patterns
        $status = 'offline';
        // Try "Run state : online" pattern
        if (preg_match('/Run\s+state\s*:\s*(\w+)/i', $infoOutput, $m)) {
            $state = strtolower($m[1]);
            if ($state === 'online') {
                $status = 'online';
            } elseif (stripos($state, 'los') !== false) {
                $status = 'los';
            }
        }
        // Try from optical output - "ONT online duration" or output structure indicates online
        if ($status === 'offline' && preg_match('/ONT\s+online\s+duration|online\s+duration/i', $opticalOutput)) {
            $status = 'online';
        }
        // Also check for "Last up time" pattern in info output
        if ($status === 'offline' && preg_match('/Last\s+up\s+time\s*:/i', $infoOutput)) {
            $status = 'online';
        }
        // Also try optical output which may indicate online status
        if ($status === 'offline' && $rxPower !== null && $rxPower > -35) {
            // If we got valid RX power, ONU is online
            $status = 'online';
        }
        
        // Parse distance from info output
        $distance = null;
        if (preg_match('/(?:Distance|Ont distance)\s*\(?m?\)?\s*:\s*(\d+)/i', $infoOutput, $m)) {
            $distance = (int)$m[1];
        }
        
        // Get name/description
        $name = '';
        if (preg_match('/Name\s*:\s*(.+)/i', $infoOutput, $m)) {
            $name = trim($m[1]);
        }
        
        // Parse TR-069 WAN IP from wan-info output
        // Format: IPv4 address : 10.97.132.28
        $tr069Ip = null;
        if (preg_match('/IPv4\s+address\s*:\s*([\d.]+)/i', $wanOutput, $m)) {
            $ip = $m[1];
            if ($ip && $ip !== '0.0.0.0') {
                $tr069Ip = $ip;
            }
        }
        
        // Parse ONT IP from ont info output (management IP assigned by OLT)
        // Format: ONT IP 0 address/mask   : 10.97.127.32/16
        $ontIp = null;
        if (preg_match('/ONT\s+IP\s*\d*\s*address\/mask\s*:\s*([\d.]+)/i', $infoOutput, $m)) {
            $ip = $m[1];
            if ($ip && $ip !== '0.0.0.0') {
                $ontIp = $ip;
            }
        }
        
        // Save the optical data to database if we got valid readings
        $stmt = $this->db->prepare("SELECT id FROM huawei_onus WHERE sn = ?");
        $stmt->execute([$sn]);
        $dbOnu = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($dbOnu) {
            if ($rxPower !== null || $txPower !== null) {
                $this->updateONUOpticalInDB($dbOnu['id'], $rxPower, $txPower, $distance);
            }
            if ($status) {
                $this->updateONUStatus($dbOnu['id'], $status);
            }
            // Update TR-069 IP if found from WAN info
            if ($tr069Ip) {
                $this->updateONU($dbOnu['id'], ['tr069_ip' => $tr069Ip]);
            }
            // Update ONT management IP from ont info (used by ACS for TR-069)
            if ($ontIp) {
                $this->updateONU($dbOnu['id'], ['ip_address' => $ontIp]);
                // Also update tr069_ip if not already set
                if (!$tr069Ip) {
                    $this->updateONU($dbOnu['id'], ['tr069_ip' => $ontIp]);
                }
            }
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
                'distance' => $distance,
                'tr069_ip' => $tr069Ip ?: $ontIp,
                'ont_ip' => $ontIp,
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
        
        // If no ONUs parsed from config, fall back to summary data
        if (empty($parsed) && !empty($summaryOnus)) {
            $this->addLog([
                'olt_id' => $oltId,
                'action' => 'sync_cli',
                'status' => 'info',
                'message' => 'Using summary data - config parsing returned no ONUs',
                'details' => 'Config lines: ' . count($lines) . ', Summary ONUs: ' . count($summaryOnus),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            // Use summary data instead
            foreach ($summaryOnus as $onu) {
                try {
                    $existing = $this->getONUBySN($onu['sn']);
                    
                    $onuName = "ONU {$onu['frame']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_id']}";
                    
                    $this->addONU([
                        'olt_id' => $oltId,
                        'sn' => $onu['sn'],
                        'name' => $onuName,
                        'frame' => $onu['frame'],
                        'slot' => $onu['slot'],
                        'port' => $onu['port'],
                        'onu_id' => $onu['onu_id'],
                        'is_authorized' => true,
                        'status' => $onu['status'] ?? 'online',
                    ]);
                    
                    if ($existing) {
                        $updated++;
                    } else {
                        $added++;
                    }
                    $parsed[] = $onu;
                } catch (\Exception $e) {
                    $errors[] = "Failed for {$onu['sn']}: " . $e->getMessage();
                }
            }
        }
        
        // Still no ONUs found
        if (empty($parsed)) {
            $this->addLog([
                'olt_id' => $oltId,
                'action' => 'sync_cli',
                'status' => 'warning',
                'message' => 'No ONUs found in configuration or summary',
                'details' => 'Config lines: ' . count($lines) . ', Output size: ' . strlen($output),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'error' => 'No ONUs found. Make sure ONUs are authorized on the OLT.',
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
    
    public function refreshAllONUOpticalViaCLI(int $oltId): array {
        $onus = $this->getONUs(['olt_id' => $oltId]);
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
            'method' => 'cli'
        ];
    }
    
    public function refreshAllONUOpticalViaSNMP(int $oltId): array {
        if (!function_exists('snmprealwalk')) {
            return ['success' => false, 'error' => 'PHP SNMP extension not installed. Install with: apt install php-snmp'];
        }
        
        $onus = $this->getONUs(['olt_id' => $oltId]);
        $bulkResult = $this->bulkPollOpticalPowerViaSNMP($oltId);
        
        if (!$bulkResult['success']) {
            return ['success' => false, 'error' => $bulkResult['error'] ?? 'SNMP walk failed'];
        }
        
        if (empty($bulkResult['data'])) {
            return ['success' => false, 'error' => 'No optical data received via SNMP. Check SNMP port (161) is accessible and community string is correct.'];
        }
        
        $updated = 0;
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
                    $updated++;
                }
            }
        }
        
        return [
            'success' => true,
            'updated' => $updated,
            'total' => count($onus),
            'snmp_records' => count($bulkResult['data']),
            'method' => 'snmp_bulk'
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
        
        // Walk distance table (hwGponDeviceOntDistance .46.1.20 - in meters)
        $distanceOid = '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.20';
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
            
            // MA5800 series uses ifIndex-based ponIndex starting at 4194304000
            // ponIndex = 4194304000 + slot*8192 + port*256
            if ($ponIndex >= 4194304000) {
                $offset = $ponIndex - 4194304000;
                $slot = intdiv($offset, 8192);
                $port = intdiv($offset % 8192, 256);
            } else {
                // Legacy format: ponIndex = frame*8192 + slot*256 + port
                $frame = intdiv($ponIndex, 8192);
                $remainder = $ponIndex % 8192;
                $slot = intdiv($remainder, 256);
                $port = $remainder % 256;
            }
            
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
    
    private function findONULocationBySN(int $oltId, string $sn): ?array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return null;
        }
        
        // Try CLI command to find ONU by SN
        $command = "display ont info by-sn {$sn}";
        $result = $this->executeCommand($oltId, $command);
        
        if (!$result['success'] || empty($result['output'])) {
            return null;
        }
        
        $output = $result['output'];
        
        if (strpos($output, 'Failure') !== false) {
            return null;
        }
        
        // Parse output: F/S/P:ONU-ID format
        // Example output:
        // F/S/P       ONU      SN                   ...
        // 0/1/0       1        HWTC12345678         ...
        if (preg_match('/(\d+)\/(\d+)\/(\d+)\s+(\d+)\s+' . preg_quote($sn, '/') . '/i', $output, $matches)) {
            return [
                'frame' => (int)$matches[1],
                'slot' => (int)$matches[2],
                'port' => (int)$matches[3],
                'onu_id' => (int)$matches[4]
            ];
        }
        
        // Try alternative format
        if (preg_match('/Frame\/Slot\/Port\s*:\s*(\d+)\/(\d+)\/(\d+).*?ONT-ID\s*:\s*(\d+)/s', $output, $matches)) {
            return [
                'frame' => (int)$matches[1],
                'slot' => (int)$matches[2],
                'port' => (int)$matches[3],
                'onu_id' => (int)$matches[4]
            ];
        }
        
        return null;
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
        
        // If ONU ID is not set, try to find it from the OLT using the serial number
        if ($onu['onu_id'] === null || $onu['slot'] === null || $onu['port'] === null) {
            if (!empty($onu['sn']) && !empty($onu['olt_id'])) {
                $location = $this->findONULocationBySN($onu['olt_id'], $onu['sn']);
                if ($location) {
                    // Update the database with the found location
                    $updateStmt = $this->db->prepare("
                        UPDATE huawei_onus 
                        SET frame = ?, slot = ?, port = ?, onu_id = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $updateStmt->execute([
                        $location['frame'], 
                        $location['slot'], 
                        $location['port'], 
                        $location['onu_id'], 
                        $onuId
                    ]);
                    $onu = array_merge($onu, $location);
                } else {
                    return ['success' => false, 'error' => 'ONU ID not set. Please sync ONUs from OLT first.'];
                }
            } else {
                return ['success' => false, 'error' => 'ONU ID not set and cannot lookup (missing SN or OLT ID)'];
            }
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
        
        // Priority: CLI first (reliable via OLT Session Service), SNMP as fallback
        $optical = $this->getONUOpticalInfoViaCLI(
            $onu['olt_id'],
            $frame,
            $slot,
            $port,
            $onuIdNum
        );
        
        // Fall back to SNMP only if CLI fails or returns no data
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
        $status = $optical['optical']['status'] ?? null;
        $ontIp = $optical['optical']['ont_ip'] ?? null;
        
        $this->updateONUOpticalInDB($onuId, $optical['optical']['rx_power'], $optical['optical']['tx_power'], $distance);
        
        // Also update status if available
        if ($status) {
            $this->updateONUStatus($onuId, $status);
        }
        
        // Update ONT management IP if found (used by ACS for TR-069)
        if ($ontIp) {
            $this->updateONU($onuId, ['ip_address' => $ontIp, 'tr069_ip' => $ontIp]);
        }
        
        return [
            'success' => true,
            'rx_power' => $optical['optical']['rx_power'],
            'tx_power' => $optical['optical']['tx_power'],
            'distance' => $distance,
            'status' => $status,
            'ont_ip' => $ontIp
        ];
    }
    
    private function updateONUStatus(int $onuId, string $status): void {
        // Get current status before update to detect LOS events
        $stmt = $this->db->prepare("SELECT o.*, olt.id as olt_id FROM huawei_onus o LEFT JOIN huawei_olts olt ON o.olt_id = olt.id WHERE o.id = ?");
        $stmt->execute([$onuId]);
        $onu = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$onu) {
            return; // ONU not found, nothing to update
        }
        
        $previousStatus = $onu['status'] ?? 'unknown';
        
        // Track online_since for uptime calculation
        if ($status === 'online' && $previousStatus !== 'online') {
            // ONU just came online - set online_since timestamp
            $updateStmt = $this->db->prepare("UPDATE huawei_onus SET status = ?, online_since = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$status, $onuId]);
        } elseif ($status !== 'online' && $previousStatus === 'online') {
            // ONU went offline - clear online_since
            $updateStmt = $this->db->prepare("UPDATE huawei_onus SET status = ?, online_since = NULL, uptime = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$status, $onuId]);
        } else {
            // Regular status update
            $updateStmt = $this->db->prepare("UPDATE huawei_onus SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$status, $onuId]);
        }
        
        // Send LOS notification if status changed to 'los' from a non-los state
        if ($status === 'los' && $previousStatus !== 'los') {
            $oltId = $onu['olt_id'] ?? null;
            if ($oltId) {
                $olt = $this->getOLT($oltId);
                if ($olt && !empty($olt['branch_whatsapp_group'])) {
                    $this->sendLosNotification($onu, $olt, $previousStatus);
                } else {
                    error_log("OMS LOS Alert: ONU {$onu['sn']} went LOS but OLT has no branch/WhatsApp group configured");
                }
            }
        }
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
        // Priority: SNMP first (less OLT load, no session conflicts), CLI as fallback
        $snmpResult = $this->discoverUnconfiguredONUsViaSNMP($oltId);
        if ($snmpResult['success'] && !empty($snmpResult['onus'])) {
            return $snmpResult;
        }
        
        // Fall back to CLI only if SNMP fails (e.g., no SNMP extension, wrong community)
        return $this->discoverUnconfiguredONUsViaCLI($oltId);
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
        $currentOnu = null;
        
        // Parse multi-line ONU entries (each ONU may have SN, EQID, SoftwareVer on separate lines)
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Parse port header: "F/S/P : 0/1/0" or "Port : 0/1/0"
            if (preg_match('/(?:Port|F\/S\/P)\s*:\s*(\d+)\/(\d+)\/(\d+)/i', $line, $m)) {
                // Save previous ONU before changing port
                if ($currentOnu && !empty($currentOnu['sn'])) {
                    $unconfigured[] = $currentOnu;
                    $added += $this->saveDiscoveredONU($currentOnu, $oltId, $olt);
                }
                $currentOnu = null;
                $currentFrame = (int)$m[1];
                $currentSlot = (int)$m[2];
                $currentPort = (int)$m[3];
                continue;
            }
            
            // New ONU entry starts with index and F/S/P (table format)
            if (preg_match('/^\s*(\d+)\s+(\d+\/\s*\d+\/\s*\d+)/i', $line, $m)) {
                // Save previous ONU
                if ($currentOnu && !empty($currentOnu['sn'])) {
                    $unconfigured[] = $currentOnu;
                    $added += $this->saveDiscoveredONU($currentOnu, $oltId, $olt);
                }
                $fsp = preg_replace('/\s/', '', $m[2]);
                $parts = explode('/', $fsp);
                $currentOnu = [
                    'index' => (int)$m[1],
                    'frame' => (int)($parts[0] ?? $currentFrame),
                    'slot' => (int)($parts[1] ?? $currentSlot),
                    'port' => (int)($parts[2] ?? $currentPort),
                    'method' => 'cli'
                ];
                continue;
            }
            
            // Table format: Number | SN/MAC | Password/LOID | Type | Auth mode
            if (preg_match('/^\s*(\d+)\s+([A-Fa-f0-9]{8,16})\s+/i', $line, $m)) {
                // Save previous ONU
                if ($currentOnu && !empty($currentOnu['sn'])) {
                    $unconfigured[] = $currentOnu;
                    $added += $this->saveDiscoveredONU($currentOnu, $oltId, $olt);
                }
                $currentOnu = [
                    'index' => (int)$m[1],
                    'sn' => strtoupper($m[2]),
                    'frame' => $currentFrame,
                    'slot' => $currentSlot,
                    'port' => $currentPort,
                    'method' => 'cli'
                ];
                continue;
            }
            
            // Parse additional fields for current ONU
            if ($currentOnu) {
                // SN line: "SN : HWTC12345678" or "Ont SN : HWTC12345678"
                if (preg_match('/(?:Ont\s+)?SN\s*:\s*([A-Fa-f0-9]{8,16})/i', $line, $m)) {
                    $currentOnu['sn'] = strtoupper($m[1]);
                }
                // EQID line: "EQID : EchoLife-HG8145V5" or "EquipmentID : HG8010H"
                if (preg_match('/(?:EQID|EquipmentID)\s*:\s*(\S+)/i', $line, $m)) {
                    $currentOnu['eqid'] = $m[1];
                }
                // Software version: "SoftwareVer : V5R020C00S125"
                if (preg_match('/SoftwareVer\s*:\s*(\S+)/i', $line, $m)) {
                    $currentOnu['software_ver'] = $m[1];
                }
                // Product ID: "OnuProductID : EG8145V5"
                if (preg_match('/OnuProductID\s*:\s*(\S+)/i', $line, $m)) {
                    $currentOnu['product_id'] = $m[1];
                }
            }
        }
        
        // Save last ONU
        if ($currentOnu && !empty($currentOnu['sn'])) {
            $unconfigured[] = $currentOnu;
            $added += $this->saveDiscoveredONU($currentOnu, $oltId, $olt);
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
    
    /**
     * Save a discovered ONU to the database with EQID/type matching
     * @return int 1 if new ONU was added, 0 if already existed
     */
    private function saveDiscoveredONU(array $onuData, int $oltId, array $olt): int {
        $sn = $onuData['sn'] ?? '';
        if (empty($sn)) return 0;
        
        $existing = $this->getONUBySN($sn);
        if ($existing && $existing['is_authorized']) {
            return 0; // Already authorized, don't overwrite
        }
        
        // Determine ONU type from EQID if available
        $onuType = $onuData['eqid'] ?? $onuData['product_id'] ?? '';
        $onuTypeId = null;
        
        if ($onuType) {
            $onuTypeId = $this->matchOnuTypeByEqid($onuType);
        }
        
        $data = [
            'olt_id' => $oltId,
            'sn' => $sn,
            'frame' => $onuData['frame'] ?? 0,
            'slot' => $onuData['slot'] ?? 0,
            'port' => $onuData['port'] ?? 0,
            'onu_type' => $onuType,
            'status' => 'unconfigured',
            'is_authorized' => false,
        ];
        
        $this->addONU($data);
        
        // Also update discovery log with equipment_id and matched type
        if ($onuType || $onuTypeId) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO onu_discovery_log (olt_id, serial_number, frame_slot_port, equipment_id, onu_type_id, last_seen_at)
                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT (olt_id, serial_number) 
                    DO UPDATE SET 
                        last_seen_at = CURRENT_TIMESTAMP,
                        frame_slot_port = EXCLUDED.frame_slot_port,
                        equipment_id = COALESCE(EXCLUDED.equipment_id, onu_discovery_log.equipment_id),
                        onu_type_id = COALESCE(EXCLUDED.onu_type_id, onu_discovery_log.onu_type_id)
                ");
                $fsp = "{$data['frame']}/{$data['slot']}/{$data['port']}";
                $stmt->execute([$oltId, $sn, $fsp, $onuType ?: null, $onuTypeId]);
            } catch (\Exception $e) {
                // Discovery log table may not exist yet, ignore
            }
        }
        
        // Send notification only for new ONUs
        if (!$existing) {
            $this->sendNewOnuNotification($data, $olt);
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Match EQID string to a known ONU type in the database
     */
    private function matchOnuTypeByEqid(string $eqid): ?int {
        if (empty($eqid)) return null;
        
        $normalizedEqid = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $eqid));
        
        try {
            $stmt = $this->db->query("SELECT id, model, model_aliases FROM huawei_onu_types WHERE is_active = true");
            $types = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($types as $type) {
                $modelNorm = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $type['model'] ?? ''));
                if ($modelNorm && strlen($modelNorm) >= 5 && strpos($normalizedEqid, $modelNorm) !== false) {
                    return (int)$type['id'];
                }
                
                if (!empty($type['model_aliases'])) {
                    $aliases = array_map(function($a) {
                        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($a)));
                    }, explode(',', $type['model_aliases']));
                    
                    foreach ($aliases as $alias) {
                        if ($alias && strlen($alias) >= 5 && strpos($normalizedEqid, $alias) !== false) {
                            return (int)$type['id'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }
        
        return null;
    }
    
    /**
     * Get discovered ONUs from database (auto-populated by OLT Session Manager)
     * These are ONUs found by the background discovery service
     */
    public function getDiscoveredONUs(?int $oltId = null, bool $pendingOnly = true): array {
        $sql = "
            SELECT d.*, 
                   o.name as olt_name, o.ip_address as olt_ip,
                   t.model as onu_model, t.name as onu_type_name,
                   b.name as branch_name, b.code as branch_code
            FROM onu_discovery_log d
            LEFT JOIN huawei_olts o ON d.olt_id = o.id
            LEFT JOIN huawei_onu_types t ON d.onu_type_id = t.id
            LEFT JOIN branches b ON o.branch_id = b.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($oltId) {
            $sql .= " AND d.olt_id = ?";
            $params[] = $oltId;
        }
        
        if ($pendingOnly) {
            // Only show pending entries from the last 2 hours
            $sql .= " AND d.authorized = false AND d.last_seen_at > NOW() - INTERVAL '2 hours'";
        }
        
        $sql .= " ORDER BY d.last_seen_at DESC";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Mark a discovered ONU as authorized (after provisioning)
     */
    public function markDiscoveredONUAuthorized(int $discoveryId): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE onu_discovery_log 
                SET authorized = true, authorized_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            return $stmt->execute([$discoveryId]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Cleanup stale discovery entries and unauthorized ONUs
     */
    public function cleanupStalePendingONUs(int $hoursOld = 2): array {
        $cleaned = ['discovery_log' => 0, 'unauthorized_onus' => 0];
        
        try {
            // Delete old unauthorized discovery entries
            $stmt = $this->db->prepare("
                DELETE FROM onu_discovery_log 
                WHERE authorized = FALSE 
                AND last_seen_at < NOW() - INTERVAL '1 hour' * ?
            ");
            $stmt->execute([$hoursOld]);
            $cleaned['discovery_log'] = $stmt->rowCount();
            
            // Delete unauthorized ONUs that haven't been seen/updated in a while
            // Only delete if they're offline and have no customer linked
            $stmt = $this->db->prepare("
                DELETE FROM huawei_onus 
                WHERE is_authorized = FALSE 
                AND customer_id IS NULL
                AND updated_at < NOW() - INTERVAL '1 hour' * ?
            ");
            $stmt->execute([$hoursOld]);
            $cleaned['unauthorized_onus'] = $stmt->rowCount();
            
        } catch (\Exception $e) {
            // Tables may not exist
        }
        
        return $cleaned;
    }

    /**
     * Clear a specific discovery entry (mark as no longer pending)
     */
    public function clearDiscoveryEntry(int $discoveryId): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM onu_discovery_log WHERE id = ?");
            return $stmt->execute([$discoveryId]);
        } catch (\Exception $e) {
            return false;
        }
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
        
        // Mark discovery entries NOT in current scan as authorized (externally authorized via SmartOLT etc)
        $markedAuthorized = 0;
        if (!empty($unconfigured)) {
            $foundSNs = array_column($unconfigured, 'sn');
            try {
                // Get all unauthorized discovery entries for this OLT
                $stmt = $this->db->prepare("
                    SELECT id, serial_number FROM onu_discovery_log 
                    WHERE olt_id = ? AND authorized = FALSE
                ");
                $stmt->execute([$oltId]);
                $existingEntries = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                foreach ($existingEntries as $entry) {
                    if (!in_array($entry['serial_number'], $foundSNs)) {
                        // This SN was in discovery but no longer in autofind = authorized externally
                        $this->db->prepare("
                            UPDATE onu_discovery_log 
                            SET authorized = TRUE, authorized_at = CURRENT_TIMESTAMP 
                            WHERE id = ?
                        ")->execute([$entry['id']]);
                        $markedAuthorized++;
                    }
                }
            } catch (\Exception $e) {
                error_log("Failed to cleanup stale discovery entries: " . $e->getMessage());
            }
        } else {
            // No unconfigured ONUs found - mark ALL discovery entries for this OLT as authorized
            try {
                $stmt = $this->db->prepare("
                    UPDATE onu_discovery_log 
                    SET authorized = TRUE, authorized_at = CURRENT_TIMESTAMP 
                    WHERE olt_id = ? AND authorized = FALSE
                ");
                $stmt->execute([$oltId]);
                $markedAuthorized = $stmt->rowCount();
            } catch (\Exception $e) {
                error_log("Failed to cleanup all discovery entries: " . $e->getMessage());
            }
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'discover_unconfigured_snmp',
            'status' => 'success',
            'message' => "Found " . count($unconfigured) . " unconfigured ONUs via SNMP, added {$added} new" . ($markedAuthorized > 0 ? ", cleared {$markedAuthorized} stale" : ''),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'onus' => $unconfigured,
            'count' => count($unconfigured),
            'added' => $added,
            'cleared' => $markedAuthorized,
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
        // Build filter conditions
        $conditions = " WHERE 1=1";
        $params = [];
        
        if (!empty($filters['olt_id'])) {
            $conditions .= " AND o.olt_id = ?";
            $params[] = $filters['olt_id'];
        }
        
        if (!empty($filters['status'])) {
            $conditions .= " AND o.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $conditions .= " AND (o.sn ILIKE ? OR o.name ILIKE ? OR o.description ILIKE ? OR c.name ILIKE ?)";
            $term = "%{$filters['search']}%";
            $params = array_merge($params, [$term, $term, $term, $term]);
        }
        
        if (isset($filters['is_authorized'])) {
            // PostgreSQL needs string 'true'/'false' for boolean params via PDO
            $boolVal = $this->castBoolean($filters['is_authorized']) ? 'true' : 'false';
            $conditions .= " AND o.is_authorized = ?::boolean";
            $params[] = $boolVal;
        }
        
        $orderBy = " ORDER BY olt.name, o.frame, o.slot, o.port, o.onu_id";
        
        // Try enhanced query with ONU types (may fail if tables don't exist yet)
        try {
            $sql = "SELECT o.*, olt.name as olt_name, c.name as customer_name, sp.name as profile_name,
                           ot.name as onu_type_name, ot.model as onu_type_model, ot.eth_ports as type_eth_ports, 
                           ot.pots_ports as type_pots_ports, ot.wifi_capable as type_wifi, ot.default_mode as type_default_mode,
                           dl.equipment_id as discovered_eqid, dl.onu_type_id as discovered_onu_type_id,
                           z.name as zone_name
                    FROM huawei_onus o
                    LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
                    LEFT JOIN customers c ON o.customer_id = c.id
                    LEFT JOIN huawei_service_profiles sp ON o.service_profile_id = sp.id
                    LEFT JOIN huawei_onu_types ot ON o.onu_type_id = ot.id
                    LEFT JOIN onu_discovery_log dl ON o.sn = dl.serial_number AND o.olt_id = dl.olt_id
                    LEFT JOIN huawei_zones z ON o.zone_id = z.id"
                    . $conditions . $orderBy;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Fall back to basic query without optional tables
            $sql = "SELECT o.*, olt.name as olt_name, c.name as customer_name, sp.name as profile_name,
                           NULL as onu_type_name, NULL as onu_type_model, NULL as type_eth_ports,
                           NULL as type_pots_ports, NULL as type_wifi, NULL as type_default_mode,
                           NULL as discovered_eqid, NULL as discovered_onu_type_id, NULL as zone_name
                    FROM huawei_onus o
                    LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
                    LEFT JOIN customers c ON o.customer_id = c.id
                    LEFT JOIN huawei_service_profiles sp ON o.service_profile_id = sp.id"
                    . $conditions . $orderBy;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
    }
    
    public function getONU(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT o.*, olt.name as olt_name, c.name as customer_name, sp.name as profile_name,
                   ot.model as onu_type_model, dl.equipment_id as discovered_eqid
            FROM huawei_onus o
            LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN huawei_service_profiles sp ON o.service_profile_id = sp.id
            LEFT JOIN huawei_onu_types ot ON o.onu_type_id = ot.id
            LEFT JOIN onu_discovery_log dl ON o.sn = dl.serial_number AND o.olt_id = dl.olt_id
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
                   'tr069_profile_id', 'zone', 'zone_id', 'area', 'customer_name', 'auth_date',
                   'phone', 'address', 'latitude', 'longitude', 'installation_date',
                   'pppoe_username', 'pppoe_password', 'onu_type_id', 'tr069_status', 'tr069_ip'];
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
    
    public function deleteONU(int $id, bool $deauthorizeOnOLT = true): array {
        // Get ONU details first
        $onu = $this->getONU($id);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        $deauthResult = null;
        
        // Try to deauthorize on OLT if requested
        if ($deauthorizeOnOLT && $onu['olt_id'] && $onu['frame'] !== null && $onu['slot'] !== null && $onu['port'] !== null && $onu['onu_id']) {
            $deauthResult = $this->deleteONUFromOLT($id);
        }
        
        // Reset discovery log entry so ONU reappears in discovery
        if (!empty($onu['sn'])) {
            try {
                $stmt = $this->db->prepare("
                    UPDATE onu_discovery_log 
                    SET authorized = false, authorized_at = NULL, last_seen_at = NOW()
                    WHERE serial_number = ?
                ");
                $stmt->execute([$onu['sn']]);
            } catch (\Exception $e) {
                // Table may not exist, ignore
            }
        }
        
        // Delete from database
        $stmt = $this->db->prepare("DELETE FROM huawei_onus WHERE id = ?");
        $deleted = $stmt->execute([$id]);
        
        $this->addLog([
            'olt_id' => $onu['olt_id'],
            'action' => 'delete_onu',
            'status' => 'success',
            'message' => "Deleted ONU {$onu['sn']} from database" . ($deauthResult ? ", OLT deauth: " . ($deauthResult['success'] ? 'OK' : 'Failed') : ''),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => $deleted,
            'deauthorized' => $deauthResult['success'] ?? false,
            'deauth_message' => $deauthResult['message'] ?? null
        ];
    }
    
    public function deleteAllONUs(?int $oltId = null, bool $deauthorizeOnOLT = false): array {
        try {
            // Get all ONUs to be deleted (for resetting discovery log)
            $sql = "SELECT sn FROM huawei_onus WHERE sn IS NOT NULL";
            if ($oltId) {
                $sql .= " AND olt_id = ?";
                $snStmt = $this->db->prepare($sql);
                $snStmt->execute([$oltId]);
            } else {
                $snStmt = $this->db->query($sql);
            }
            $serials = $snStmt->fetchAll(\PDO::FETCH_COLUMN);
            
            // Reset discovery log for all these serials
            if (!empty($serials)) {
                try {
                    $placeholders = implode(',', array_fill(0, count($serials), '?'));
                    $stmt = $this->db->prepare("
                        UPDATE onu_discovery_log 
                        SET authorized = false, authorized_at = NULL 
                        WHERE serial_number IN ({$placeholders})
                    ");
                    $stmt->execute($serials);
                } catch (\Exception $e) {
                    // Table may not exist
                }
            }
            
            // Delete from database
            if ($oltId) {
                $stmt = $this->db->prepare("DELETE FROM huawei_onus WHERE olt_id = ?");
                $stmt->execute([$oltId]);
                $count = $stmt->rowCount();
            } else {
                $stmt = $this->db->query("DELETE FROM huawei_onus");
                $count = $stmt->rowCount();
            }
            
            $this->addLog([
                'olt_id' => $oltId,
                'action' => 'delete_all_onus',
                'status' => 'success',
                'message' => "Deleted {$count} ONUs" . ($oltId ? " for OLT #{$oltId}" : "") . ", reset " . count($serials) . " discovery entries",
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return ['success' => true, 'count' => $count, 'discovery_reset' => count($serials)];
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
    
    /**
     * SmartOLT-style: Get OLT service profile ID by ONU type name
     * SmartOLT uses ONU model names (HG8546M, HG8145V5) as service profile names
     */
    public function getOltSrvProfileByOnuType(int $oltId, string $onuType): ?array {
        // Query the OLT for its service profiles
        $result = $this->executeCommand($oltId, 'display ont-srvprofile gpon all');
        if (!$result['success']) return null;
        
        $output = $result['output'] ?? '';
        $lines = explode("\n", $output);
        
        // Parse profiles looking for matching ONU type
        // Format: "Profile-ID  Profile-name      Binding times"
        foreach ($lines as $line) {
            if (preg_match('/^\s*(\d+)\s+(\S+)\s+(\d+)\s*$/', trim($line), $matches)) {
                $profileId = (int)$matches[1];
                $profileName = $matches[2];
                $bindingCount = (int)$matches[3];
                
                // Check if profile name matches ONU type (case-insensitive)
                if (strcasecmp($profileName, $onuType) === 0) {
                    return [
                        'id' => $profileId,
                        'name' => $profileName,
                        'binding_count' => $bindingCount
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get ONU type configuration from huawei_onu_types table
     */
    public function getOnuTypeConfig(string $onuType): ?array {
        // First try exact match
        $stmt = $this->db->prepare("SELECT * FROM huawei_onu_types WHERE LOWER(model) = LOWER(?) AND is_active = true LIMIT 1");
        $stmt->execute([$onuType]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result) return $result;
        
        // Try partial match
        $stmt = $this->db->prepare("SELECT * FROM huawei_onu_types WHERE LOWER(model) LIKE LOWER(?) AND is_active = true LIMIT 1");
        $stmt->execute(['%' . $onuType . '%']);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Create a service profile on the OLT for the given ONU type
     * Uses the ONU type's port configuration (ETH, POTS, WiFi)
     */
    public function createOltSrvProfileForOnuType(int $oltId, string $onuType): ?array {
        // Get ONU type configuration
        $onuTypeConfig = $this->getOnuTypeConfig($onuType);
        
        // Default port configuration if not in database
        $ethPorts = $onuTypeConfig['eth_ports'] ?? 4;
        $potsPorts = $onuTypeConfig['pots_ports'] ?? 0;
        $catvPorts = 0; // Most don't have CATV
        
        // Find the next available profile ID on the OLT
        $nextProfileId = $this->getNextAvailableOltSrvProfileId($oltId);
        if ($nextProfileId === null) {
            error_log("Failed to get next available profile ID for OLT {$oltId}");
            return null;
        }
        
        // Build the service profile creation command
        // Format: ont-srvprofile gpon profile-id X profile-name "NAME"
        //         ont-port eth X pots Y catv Z
        $commands = [
            "ont-srvprofile gpon profile-id {$nextProfileId} profile-name {$onuType}",
            "ont-port eth {$ethPorts} pots {$potsPorts} catv {$catvPorts}",
            "commit",
            "quit"
        ];
        
        $cliScript = implode("\r\n", $commands);
        $result = $this->executeCommand($oltId, $cliScript);
        
        if (!$result['success']) {
            error_log("Failed to create service profile {$onuType} on OLT {$oltId}: " . ($result['output'] ?? 'Unknown error'));
            return null;
        }
        
        // Check for errors in output
        $output = $result['output'] ?? '';
        if (preg_match('/(?:Failure|Error:|failed|Invalid)/i', $output)) {
            error_log("Error creating service profile: {$output}");
            return null;
        }
        
        error_log("Created OLT service profile '{$onuType}' with ID {$nextProfileId} (ETH:{$ethPorts}, POTS:{$potsPorts})");
        
        return [
            'id' => $nextProfileId,
            'name' => $onuType,
            'binding_count' => 0
        ];
    }
    
    /**
     * Get next available service profile ID on the OLT
     */
    public function getNextAvailableOltSrvProfileId(int $oltId): ?int {
        $result = $this->executeCommand($oltId, 'display ont-srvprofile gpon all');
        if (!$result['success']) return null;
        
        $usedIds = [0]; // Reserve 0 for default
        $output = $result['output'] ?? '';
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            if (preg_match('/^\s*(\d+)\s+\S+\s+\d+\s*$/', trim($line), $matches)) {
                $usedIds[] = (int)$matches[1];
            }
        }
        
        // Find next available ID (starting from 2)
        sort($usedIds);
        $nextId = 2;
        foreach ($usedIds as $id) {
            if ($id == $nextId) {
                $nextId++;
            } elseif ($id > $nextId) {
                break;
            }
        }
        
        return min($nextId, 255); // Max profile ID is typically 255
    }
    
    /**
     * Get default line profile for an OLT
     * Default is 2 (SMARTOLT_FLEXIBLE_GPON) - can be overridden per OLT
     */
    public function getDefaultOltLineProfile(int $oltId): array {
        // First check if there's a setting for this OLT
        $stmt = $this->db->prepare("SELECT default_line_profile FROM huawei_olts WHERE id = ?");
        $stmt->execute([$oltId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!empty($row['default_line_profile'])) {
            return ['id' => (int)$row['default_line_profile'], 'name' => 'OLT Default'];
        }
        
        // Default to 2 (SMARTOLT_FLEXIBLE_GPON) - most common setup
        return ['id' => 2, 'name' => 'SMARTOLT_FLEXIBLE_GPON'];
    }
    
    /**
     * Find or create a CRM service profile matching the ONU type
     * SmartOLT style: uses ONU model as service profile
     */
    public function findOrCreateProfileByOnuType(int $oltId, string $onuType): ?array {
        if (empty($onuType)) return null;
        
        // First, check if we have a CRM profile with this ONU type name
        $stmt = $this->db->prepare("SELECT * FROM huawei_service_profiles WHERE LOWER(name) = LOWER(?) AND is_active = TRUE LIMIT 1");
        $stmt->execute([$onuType]);
        $crmProfile = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($crmProfile) {
            return $crmProfile;
        }
        
        // Check if this ONU type exists as a service profile on the OLT
        $oltSrvProfile = $this->getOltSrvProfileByOnuType($oltId, $onuType);
        if (!$oltSrvProfile) {
            // ONU type service profile doesn't exist on OLT
            return null;
        }
        
        // Get the default line profile for this OLT
        $lineProfile = $this->getDefaultOltLineProfile($oltId);
        $lineProfileId = $lineProfile ? $lineProfile['id'] : 2; // Fallback to SMARTOLT_FLEXIBLE_GPON
        
        // Create a CRM profile for this ONU type
        $newProfileId = $this->addServiceProfile([
            'name' => $onuType,
            'description' => "Auto-created for ONU type {$onuType}",
            'profile_type' => 'internet',
            'line_profile' => (string)$lineProfileId,
            'srv_profile' => (string)$oltSrvProfile['id'],
            'is_default' => false,
            'is_active' => true
        ]);
        
        return $this->getServiceProfile($newProfileId);
    }
    
    /**
     * Get the equipment ID (ONU type) for an ONU from discovery log
     */
    public function getOnuEquipmentId(int $onuId): ?string {
        $onu = $this->getONU($onuId);
        if (!$onu) return null;
        
        // First check if ONU has equipment_id or discovered_eqid already
        if (!empty($onu['equipment_id'])) {
            return $onu['equipment_id'];
        }
        if (!empty($onu['discovered_eqid'])) {
            return $onu['discovered_eqid'];
        }
        
        // Check ONU type table if onu_type_id is set
        if (!empty($onu['onu_type_id'])) {
            $stmt = $this->db->prepare("SELECT model FROM huawei_onu_types WHERE id = ?");
            $stmt->execute([$onu['onu_type_id']]);
            $typeRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($typeRow && !empty($typeRow['model'])) {
                return $typeRow['model'];
            }
        }
        
        // Check discovery log as fallback
        $stmt = $this->db->prepare("SELECT equipment_id, onu_type_id FROM onu_discovery_log WHERE serial_number = ? ORDER BY last_seen_at DESC LIMIT 1");
        $stmt->execute([$onu['sn']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!empty($row['equipment_id'])) {
            return $row['equipment_id'];
        }
        
        // Also try to get model from onu_type_id in discovery log
        if (!empty($row['onu_type_id'])) {
            $stmt = $this->db->prepare("SELECT model FROM huawei_onu_types WHERE id = ?");
            $stmt->execute([$row['onu_type_id']]);
            $typeRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($typeRow && !empty($typeRow['model'])) {
                return $typeRow['model'];
            }
        }
        
        return null;
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
            'discovered_onus' => 0,
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
        
        try {
            // Only count discovery entries from the last 2 hours as truly pending
            $stats['discovered_onus'] = (int)$this->db->query("
                SELECT COUNT(*) FROM onu_discovery_log 
                WHERE authorized = FALSE 
                AND last_seen_at > NOW() - INTERVAL '2 hours'
            ")->fetchColumn();
        } catch (\Exception $e) {
            $stats['discovered_onus'] = 0;
        }
        
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
    
    public function getTopologyData(?int $oltId = null): array {
        $topology = ['nodes' => [], 'edges' => []];
        
        $oltQuery = "SELECT id, name, ip_address, is_active FROM huawei_olts WHERE is_active = TRUE";
        if ($oltId) {
            $oltQuery .= " AND id = :olt_id";
        }
        $oltQuery .= " ORDER BY name";
        
        $stmt = $this->db->prepare($oltQuery);
        if ($oltId) {
            $stmt->execute([':olt_id' => $oltId]);
        } else {
            $stmt->execute();
        }
        $olts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($olts as $olt) {
            $oltNodeId = 'olt_' . $olt['id'];
            $topology['nodes'][] = [
                'id' => $oltNodeId,
                'label' => $olt['name'],
                'type' => 'olt',
                'title' => "OLT: {$olt['name']}\nIP: {$olt['ip_address']}",
                'ip' => $olt['ip_address']
            ];
            
            $portStmt = $this->db->prepare("
                SELECT DISTINCT COALESCE(frame, 0) || '/' || slot || '/' || port as frame_slot_port,
                       frame, slot, port,
                       COUNT(id) as onu_count,
                       COUNT(*) FILTER (WHERE status = 'online') as online,
                       COUNT(*) FILTER (WHERE status = 'offline') as offline,
                       COUNT(*) FILTER (WHERE status = 'los') as los
                FROM huawei_onus 
                WHERE olt_id = :olt_id AND is_authorized = TRUE AND slot IS NOT NULL AND port IS NOT NULL
                GROUP BY frame, slot, port 
                ORDER BY frame, slot, port
            ");
            $portStmt->execute([':olt_id' => $olt['id']]);
            $ports = $portStmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($ports as $port) {
                $frameSlotPort = $port['frame_slot_port'];
                $portNodeId = 'port_' . $olt['id'] . '_' . str_replace('/', '_', $frameSlotPort);
                $portStatus = $port['los'] > 0 ? 'warning' : ($port['offline'] > 0 ? 'partial' : 'online');
                
                $topology['nodes'][] = [
                    'id' => $portNodeId,
                    'label' => $frameSlotPort,
                    'type' => 'port',
                    'title' => "Port: {$frameSlotPort}\nONUs: {$port['onu_count']}\nOnline: {$port['online']}, Offline: {$port['offline']}, LOS: {$port['los']}",
                    'status' => $portStatus,
                    'onu_count' => $port['onu_count'],
                    'online' => $port['online'],
                    'offline' => $port['offline'],
                    'los' => $port['los']
                ];
                
                $topology['edges'][] = [
                    'from' => $oltNodeId,
                    'to' => $portNodeId
                ];
                
                $onuStmt = $this->db->prepare("
                    SELECT id, name, sn, status, onu_id, rx_power, tx_power,
                           (SELECT c.name FROM customers c WHERE c.id = o.customer_id) as customer_name
                    FROM huawei_onus o
                    WHERE olt_id = :olt_id AND COALESCE(frame, 0) = :frame AND slot = :slot AND port = :port AND is_authorized = TRUE
                    ORDER BY onu_id
                ");
                $onuStmt->execute([':olt_id' => $olt['id'], ':frame' => $port['frame'] ?? 0, ':slot' => $port['slot'], ':port' => $port['port']]);
                $onus = $onuStmt->fetchAll(\PDO::FETCH_ASSOC);
                
                foreach ($onus as $onu) {
                    $onuNodeId = 'onu_' . $onu['id'];
                    $topology['nodes'][] = [
                        'id' => $onuNodeId,
                        'label' => $onu['name'] ?: "ONU #{$onu['onu_id']}",
                        'type' => 'onu',
                        'status' => $onu['status'],
                        'title' => "ONU: " . ($onu['name'] ?: $onu['sn']) . "\nS/N: {$onu['sn']}\nStatus: {$onu['status']}\nRx: " . ($onu['rx_power'] ?? 'N/A') . " dBm" . ($onu['customer_name'] ? "\nCustomer: {$onu['customer_name']}" : ''),
                        'serial' => $onu['sn'],
                        'rx_power' => $onu['rx_power'],
                        'customer' => $onu['customer_name'],
                        'db_id' => $onu['id']
                    ];
                    
                    $topology['edges'][] = [
                        'from' => $portNodeId,
                        'to' => $onuNodeId
                    ];
                }
            }
        }
        
        return $topology;
    }
    
    // ==================== Provisioning Logs ====================
    
    public function addLog(array $data): int {
        // Validate onu_id exists before inserting (to avoid FK violation)
        $onuId = $data['onu_id'] ?? null;
        if ($onuId !== null) {
            try {
                $checkStmt = $this->db->prepare("SELECT id FROM huawei_onus WHERE id = ?");
                $checkStmt->execute([$onuId]);
                if (!$checkStmt->fetch()) {
                    $onuId = null; // ONU doesn't exist, set to null
                }
            } catch (\Exception $e) {
                $onuId = null;
            }
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO huawei_provisioning_logs (olt_id, onu_id, action, status, message, details, command_sent, command_response, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['olt_id'] ?? null,
            $onuId,
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
    
    public function executeCommand(int $oltId, string $command, bool $forceDirectConnection = false): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'message' => 'OLT not found'];
        }
        
        // For Telnet connections, ONLY use the OLT Session Service (no direct fallback)
        if ($olt['connection_type'] === 'telnet') {
            if (!$this->isOLTServiceAvailable()) {
                return ['success' => false, 'message' => 'OLT Session Service not available. Please ensure it is running.'];
            }
            return $this->executeViaService($oltId, $command);
        }
        
        // SSH connections use direct connection
        if ($olt['connection_type'] === 'ssh') {
            $password = !empty($olt['password_encrypted']) ? $this->decrypt($olt['password_encrypted']) : '';
            $result = $this->executeSSHCommand($olt['ip_address'], $olt['port'], $olt['username'], $password, $command);
            
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
        
        return ['success' => false, 'message' => 'Unsupported connection type'];
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
                    @fwrite($socket, " ");
                    usleep(500000);
                    continue;
                }
                
                // Handle Huawei parameter prompts like "{ <cr>|... }:"
                if (preg_match('/\}\s*:\s*$/', $output)) {
                    @fwrite($socket, "\r\n");
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
        
        // Cleanup - suppress errors as socket may already be closed
        @fwrite($socket, "quit\r\n");
        usleep(200000);
        @fwrite($socket, "quit\r\n");
        usleep(200000);
        @fclose($socket);
        
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
    
    // ==================== STABLE SSH PROVISIONING (SmartOLT-like) ====================
    
    /**
     * Stable SSH-based ONU provisioning - SmartOLT-style
     * Uses single-line commands for reliability, auto gemport detection, and safe rollback
     */
    public function provisionONUViaSSH(int $onuDbId, int $profileId, array $options = []): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'status' => 'FAILED', 'error' => 'ONU not found'];
        }
        
        $oltId = $onu['olt_id'];
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'status' => 'FAILED', 'error' => 'OLT not found'];
        }
        
        // Check if OLT supports SSH
        $sshPort = $olt['ssh_port'] ?? 22;
        $password = $this->decryptPassword($olt['password']);
        
        // Get profiles
        $equipmentId = $this->getOnuEquipmentId($onuDbId);
        $lineProfile = $this->getDefaultOltLineProfile($oltId);
        $lineProfileId = $lineProfile['id'];
        $srvProfileId = null;
        
        if ($equipmentId) {
            $oltSrvProfile = $this->getOltSrvProfileByOnuType($oltId, $equipmentId);
            if (!$oltSrvProfile) {
                $oltSrvProfile = $this->createOltSrvProfileForOnuType($oltId, $equipmentId);
            }
            if ($oltSrvProfile) {
                $srvProfileId = $oltSrvProfile['id'];
            }
        }
        
        if ($srvProfileId === null && $profileId > 0) {
            $crmProfile = $this->getServiceProfile($profileId);
            if ($crmProfile) {
                $lineProfileId = !empty($crmProfile['line_profile']) ? (int)$crmProfile['line_profile'] : $lineProfileId;
                $srvProfileId = !empty($crmProfile['srv_profile']) ? (int)$crmProfile['srv_profile'] : null;
            }
        }
        
        if ($srvProfileId === null) {
            return ['success' => false, 'status' => 'FAILED', 'error' => "No service profile for ONU type '{$equipmentId}'"];
        }
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $serial = $onu['sn'];
        $description = $options['description'] ?? $onu['name'] ?? $this->generateNextSNSCode($oltId);
        $vlanId = $options['vlan_id'] ?? null;
        
        // Find next ONU ID
        $ontId = $onu['onu_id'];
        if (empty($ontId)) {
            $ontId = $this->findNextAvailableOnuId($oltId, $frame, $slot, $port);
        }
        
        try {
            $ssh = new SSH2($olt['ip_address'], $sshPort);
            $ssh->setTimeout(30);
            
            if (!$ssh->login($olt['username'], $password)) {
                return ['success' => false, 'status' => 'FAILED', 'error' => 'SSH authentication failed'];
            }
            
            $ssh->enablePTY();
            
            // Detect prompt
            $banner = $ssh->read('/[#>]/', SSH2::READ_REGEX);
            $prompt = str_contains($banner, '>') ? '>' : '#';
            
            // Helper to execute single command
            $exec = function(string $cmd) use ($ssh, $prompt): string {
                $ssh->write(trim($cmd) . "\n");
                usleep(200000); // 200ms rate limit
                $data = $ssh->read('/[#>]/', SSH2::READ_REGEX);
                return $data !== false ? substr($data, 0, 50000) : '';
            };
            
            // Helper to check for errors
            $ok = function(string $output): bool {
                return !preg_match('/(error|failure|invalid|denied)/i', $output);
            };
            
            $debugLog = '';
            
            // Disable paging
            $exec("screen-length 0 temporary");
            
            // Enter config mode
            $exec("enable");
            $exec("config");
            
            // STAGE 1: Add ONT
            $out = $exec("interface gpon {$frame}/{$slot}");
            $debugLog .= "[Interface] " . $out . "\n";
            
            $ontCmd = "ont add {$port} {$ontId} sn-auth {$serial} omci ont-lineprofile-id {$lineProfileId} ont-srvprofile-id {$srvProfileId} desc \"{$description}\"";
            $out = $exec($ontCmd);
            $debugLog .= "[ONT Add] " . $out . "\n";
            
            $exec("quit");
            
            if (!$ok($out)) {
                return [
                    'success' => false, 'status' => 'FAILED',
                    'error' => 'ONT authorization failed',
                    'debug' => substr($debugLog, 0, 2000)
                ];
            }
            
            // Parse assigned ONT ID
            if (preg_match('/(?:ONTID|ONT-ID|Number)\s*[:\=]\s*(\d+)/i', $out, $m)) {
                $ontId = (int)$m[1];
            }
            
            // STAGE 2: Bind VLAN (if provided)
            if ($vlanId) {
                $out = $exec("interface gpon {$frame}/{$slot}");
                $out = $exec("ont port native-vlan {$port} {$ontId} eth 1 vlan {$vlanId} priority 0");
                $debugLog .= "[Native VLAN] " . $out . "\n";
                $exec("quit");
                
                if (!$ok($out)) {
                    // Rollback ONT
                    $exec("interface gpon {$frame}/{$slot}");
                    $exec("ont delete {$port} {$ontId}");
                    $exec("quit");
                    return [
                        'success' => false, 'status' => 'FAILED',
                        'error' => 'VLAN binding failed',
                        'debug' => substr($debugLog, 0, 2000)
                    ];
                }
            }
            
            // STAGE 3: Service-port (if VLAN provided)
            $gemPort = 1;
            $servicePortId = null;
            
            if ($vlanId) {
                // Auto-detect next gemport
                $out = $exec("display ont interface {$frame}/{$slot} {$port} {$ontId}");
                preg_match_all('/gemport (\d+)/i', $out, $matches);
                $usedGemports = array_map('intval', $matches[1] ?? []);
                while (in_array($gemPort, $usedGemports)) {
                    $gemPort++;
                }
                
                // Create service-port with auto-assigned ID
                $inboundIndex = $options['inbound_traffic_index'] ?? 8;
                $outboundIndex = $options['outbound_traffic_index'] ?? 9;
                
                $spCmd = "service-port vlan {$vlanId} gpon {$frame}/{$slot}/{$port} ont {$ontId} gemport {$gemPort} multi-service user-vlan {$vlanId} tag-transform translate inbound traffic-table index {$inboundIndex} outbound traffic-table index {$outboundIndex}";
                $out = $exec($spCmd);
                $debugLog .= "[Service-Port] " . $out . "\n";
                
                // Extract service-port ID
                if (preg_match('/service-port\s+(\d+)/i', $out, $m)) {
                    $servicePortId = (int)$m[1];
                }
                
                if (!$ok($out)) {
                    // Rollback
                    if ($servicePortId) {
                        $exec("undo service-port {$servicePortId}");
                    }
                    $exec("interface gpon {$frame}/{$slot}");
                    $exec("ont delete {$port} {$ontId}");
                    $exec("quit");
                    
                    return [
                        'success' => false, 'status' => 'FAILED',
                        'error' => 'Service-port failed',
                        'debug' => substr($debugLog, 0, 2000)
                    ];
                }
            }
            
            // Update ONU record
            $updateData = [
                'is_authorized' => true,
                'onu_id' => $ontId,
                'line_profile' => $lineProfileId,
                'srv_profile' => $srvProfileId,
                'name' => $description,
                'status' => 'online'
            ];
            if ($vlanId) {
                $updateData['vlan_id'] = $vlanId;
            }
            $this->updateONU($onuDbId, $updateData);
            
            // Mark discovery log as authorized
            try {
                $stmt = $this->db->prepare("UPDATE onu_discovery_log SET authorized = true, authorized_at = CURRENT_TIMESTAMP WHERE serial_number = ? AND olt_id = ?");
                $stmt->execute([$serial, $oltId]);
            } catch (\Exception $e) {}
            
            // Log success
            $this->addLog([
                'olt_id' => $oltId, 'onu_id' => $onuDbId, 'action' => 'provision_ssh',
                'status' => 'success',
                'message' => "SSH provisioning complete: ONU {$serial} as ID {$ontId}" . ($vlanId ? ", VLAN {$vlanId}" : ''),
                'command_sent' => $ontCmd, 'command_response' => $debugLog,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => true,
                'status' => 'ACTIVE',
                'message' => "ONU provisioned successfully via SSH",
                'onu_id' => $ontId,
                'gemport' => $gemPort,
                'vlan_id' => $vlanId,
                'service_port_id' => $servicePortId,
                'description' => $description,
                'debug' => substr($debugLog, 0, 2000)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'error' => 'SSH error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Deprovision ONU via SSH
     */
    public function deprovisionONUViaSSH(int $onuDbId): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        $olt = $this->getOLT($onu['olt_id']);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        $sshPort = $olt['ssh_port'] ?? 22;
        $password = $this->decryptPassword($olt['password']);
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $ontId = $onu['onu_id'];
        
        try {
            $ssh = new SSH2($olt['ip_address'], $sshPort);
            $ssh->setTimeout(30);
            
            if (!$ssh->login($olt['username'], $password)) {
                return ['success' => false, 'error' => 'SSH authentication failed'];
            }
            
            $ssh->enablePTY();
            $ssh->read('/[#>]/', SSH2::READ_REGEX);
            
            $exec = function(string $cmd) use ($ssh): string {
                $ssh->write(trim($cmd) . "\n");
                usleep(200000);
                return $ssh->read('/[#>]/', SSH2::READ_REGEX) ?: '';
            };
            
            $exec("screen-length 0 temporary");
            $exec("enable");
            $exec("config");
            
            // Find and remove service-ports for this ONT
            $out = $exec("display service-port all");
            if (preg_match_all('/(\d+)\s+vlan\s+\d+\s+gpon\s+' . preg_quote("{$frame}/{$slot}/{$port}", '/') . '\s+ont\s+' . $ontId . '/i', $out, $matches)) {
                foreach ($matches[1] as $spId) {
                    $exec("undo service-port {$spId}");
                }
            }
            
            // Delete ONT
            $exec("interface gpon {$frame}/{$slot}");
            $exec("ont delete {$port} {$ontId}");
            $exec("quit");
            
            // Update ONU record
            $this->updateONU($onuDbId, [
                'is_authorized' => false,
                'onu_id' => null,
                'status' => 'offline'
            ]);
            
            return ['success' => true, 'message' => 'ONU deprovisioned via SSH'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'SSH error: ' . $e->getMessage()];
        }
    }
    
    // ==================== Node.js OLT Session Service ====================
    
    private function getOLTServiceUrl(): string {
        return getenv('OLT_SERVICE_URL') ?: 'http://localhost:3002';
    }
    
    private function callOLTService(string $endpoint, array $data = [], string $method = 'POST'): array {
        $url = $this->getOLTServiceUrl() . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => "Service connection failed: {$error}"];
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Invalid response from OLT service'];
        }
        
        return $result;
    }
    
    public function isOLTServiceAvailable(): bool {
        // Use fast timeout check - don't wait long if service is down
        $url = $this->getOLTServiceUrl() . '/health';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);       // 1 second max
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1); // 1 second connect
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode === 200) {
            $result = json_decode($response, true);
            return isset($result['status']) && $result['status'] === 'ok';
        }
        return false;
    }
    
    public function connectToOLTSession(int $oltId, ?string $protocol = null): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        $password = !empty($olt['password_encrypted']) ? $this->decrypt($olt['password_encrypted']) : '';
        
        // Use specified protocol or fall back to OLT's configured protocol, default to telnet
        $useProtocol = $protocol ?? ($olt['cli_protocol'] ?? 'telnet');
        
        $data = [
            'oltId' => (string)$oltId,
            'host' => $olt['ip_address'],
            'port' => (int)$olt['port'],
            'username' => $olt['username'],
            'password' => $password,
            'protocol' => $useProtocol,
            'sshPort' => (int)($olt['ssh_port'] ?? 22)
        ];
        
        $result = $this->callOLTService('/connect', $data);
        
        if ($result['success'] ?? false) {
            $this->addLog([
                'olt_id' => $oltId,
                'action' => 'session_connect',
                'status' => 'success',
                'message' => "Persistent session established via {$useProtocol}",
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
        }
        
        return $result;
    }
    
    public function disconnectOLTSession(int $oltId): array {
        $result = $this->callOLTService('/disconnect', ['oltId' => (string)$oltId]);
        
        if ($result['success'] ?? false) {
            $this->addLog([
                'olt_id' => $oltId,
                'action' => 'session_disconnect',
                'status' => 'success',
                'message' => 'Session disconnected',
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
        }
        
        return $result;
    }
    
    public function getOLTSessionStatus(int $oltId): array {
        return $this->callOLTService("/status/{$oltId}", [], 'GET');
    }
    
    public function getAllOLTSessions(): array {
        return $this->callOLTService('/sessions', [], 'GET');
    }
    
    public function executeViaService(int $oltId, string $command, int $timeout = 30000): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        // Check if session exists, if not establish one
        $status = $this->getOLTSessionStatus($oltId);
        if (!($status['connected'] ?? false)) {
            $connectResult = $this->connectToOLTSession($oltId);
            if (!($connectResult['success'] ?? false)) {
                return ['success' => false, 'error' => 'Failed to establish session: ' . ($connectResult['error'] ?? 'Unknown error')];
            }
        }
        
        // Execute command via service
        $result = $this->callOLTService('/execute', [
            'oltId' => (string)$oltId,
            'command' => $command,
            'timeout' => $timeout
        ]);
        
        // Log the command
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'command_via_service',
            'status' => ($result['success'] ?? false) ? 'success' : 'failed',
            'message' => ($result['success'] ?? false) ? 'Command executed via persistent session' : ($result['error'] ?? 'Failed'),
            'command_sent' => $command,
            'command_response' => substr($result['output'] ?? '', 0, 10000),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        if ($result['success'] ?? false) {
            return [
                'success' => true,
                'message' => 'Command executed via persistent session',
                'output' => $result['output'] ?? ''
            ];
        }
        
        return ['success' => false, 'message' => $result['error'] ?? 'Command execution failed'];
    }
    
    public function executeAsyncViaService(int $oltId, string $command, int $timeout = 30000): array {
        // Quick session check and connect if needed
        $status = $this->getOLTSessionStatusFast($oltId);
        if (!($status['connected'] ?? false)) {
            // Need to connect first - do it quickly
            $connectResult = $this->connectToOLTSession($oltId);
            if (!($connectResult['success'] ?? false)) {
                return ['success' => false, 'error' => 'Failed to establish session: ' . ($connectResult['error'] ?? 'Unknown error')];
            }
        }
        
        // Now fire the async command
        $url = $this->getOLTServiceUrl() . '/execute-async';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);        // 3 seconds max - just queue the command
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // 2 second connect
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'oltId' => (string)$oltId,
            'command' => $command,
            'timeout' => $timeout
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = $response ? json_decode($response, true) : ['success' => false, 'error' => 'No response'];
        
        // Log asynchronously (fire-and-forget logging)
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'command_async',
            'status' => ($result['success'] ?? false) ? 'success' : 'failed',
            'message' => ($result['success'] ?? false) ? 'Command queued for execution' : ($result['error'] ?? 'Failed'),
            'command_sent' => $command,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    }
    
    private function getOLTSessionStatusFast(int $oltId): array {
        $url = $this->getOLTServiceUrl() . '/status/' . $oltId;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response ? json_decode($response, true) : ['connected' => false];
    }
    
    public function executeBatchViaService(int $oltId, array $commands, int $timeout = 30000): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        // Check if session exists
        $status = $this->getOLTSessionStatus($oltId);
        if (!($status['connected'] ?? false)) {
            $connectResult = $this->connectToOLTSession($oltId);
            if (!($connectResult['success'] ?? false)) {
                return ['success' => false, 'error' => 'Failed to establish session'];
            }
        }
        
        return $this->callOLTService('/execute-batch', [
            'oltId' => (string)$oltId,
            'commands' => $commands,
            'timeout' => $timeout
        ]);
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
    
    /**
     * Find the next available ONU ID for a given port
     * Queries the OLT to see which IDs are in use and returns the next available one
     */
    public function findNextAvailableOnuId(int $oltId, int $frame, int $slot, int $port): int {
        // First check database for existing ONUs on this port
        $stmt = $this->db->prepare("
            SELECT onu_id FROM huawei_onus 
            WHERE olt_id = ? AND frame = ? AND slot = ? AND port = ? AND onu_id IS NOT NULL
            ORDER BY onu_id
        ");
        $stmt->execute([$oltId, $frame, $slot, $port]);
        $usedIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        // Also try to get used IDs from OLT via CLI
        $result = $this->executeCommand($oltId, "interface gpon {$frame}/{$slot}\r\ndisplay ont info {$port} all\r\nquit");
        if ($result['success'] && !empty($result['output'])) {
            // Parse ONU IDs from output: "0/1/0  1  HWTC..." format
            preg_match_all('/^\s*\d+\/\d+\/\d+\s+(\d+)\s+/m', $result['output'], $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $id) {
                    if (!in_array((int)$id, $usedIds)) {
                        $usedIds[] = (int)$id;
                    }
                }
            }
        }
        
        // Find the next available ID (starting from 1)
        $nextId = 1;
        sort($usedIds);
        foreach ($usedIds as $id) {
            if ($id == $nextId) {
                $nextId++;
            } else {
                break;
            }
        }
        
        // ONU IDs typically range from 0-127 or 0-255 depending on OLT
        return min($nextId, 127);
    }
    
    public function authorizeONU(int $onuId, int $profileId, string $authMethod = 'sn', string $loid = '', string $loidPassword = '', array $options = []): array {
        $onu = $this->getONU($onuId);
        
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        $oltId = $onu['olt_id'];
        $equipmentId = $this->getOnuEquipmentId($onuId);
        
        // SmartOLT-style provisioning: Use ONU type as service profile
        // Line profile defaults to 2 (SMARTOLT_FLEXIBLE_GPON)
        // Service profile is looked up by ONU type name on the OLT
        
        $lineProfile = $this->getDefaultOltLineProfile($oltId);
        $lineProfileId = $lineProfile['id'];
        
        // Get service profile ID from ONU type
        $srvProfileId = null;
        $autoCreatedProfile = false;
        $usedProfileName = null;
        $profileSource = 'unknown';
        
        if ($equipmentId) {
            $oltSrvProfile = $this->getOltSrvProfileByOnuType($oltId, $equipmentId);
            
            // If service profile doesn't exist on OLT, auto-create it
            if (!$oltSrvProfile) {
                error_log("SmartOLT-style: Service profile '{$equipmentId}' not found on OLT, creating...");
                $oltSrvProfile = $this->createOltSrvProfileForOnuType($oltId, $equipmentId);
                $autoCreatedProfile = true;
            }
            
            if ($oltSrvProfile) {
                $srvProfileId = $oltSrvProfile['id'];
                $usedProfileName = $oltSrvProfile['name'] ?? $equipmentId;
                $profileSource = $autoCreatedProfile ? 'auto-created' : 'olt-matched';
                $msg = $autoCreatedProfile ? "Created and using" : "Using existing";
                error_log("SmartOLT-style: {$msg} ONU type '{$equipmentId}' → srv_profile {$srvProfileId}, line_profile {$lineProfileId}");
            }
        }
        
        // Fallback to CRM profile if ONU type not matched
        if ($srvProfileId === null && $profileId > 0) {
            $crmProfile = $this->getServiceProfile($profileId);
            if ($crmProfile) {
                $lineProfileId = !empty($crmProfile['line_profile']) ? (int)$crmProfile['line_profile'] : $lineProfileId;
                $srvProfileId = !empty($crmProfile['srv_profile']) ? (int)$crmProfile['srv_profile'] : null;
                $usedProfileName = $crmProfile['name'] ?? 'CRM Profile';
                $profileSource = 'crm-fallback';
            }
        }
        
        if ($srvProfileId === null) {
            return ['success' => false, 'message' => "Failed to create service profile for ONU type '{$equipmentId}'. Check OLT connection and try again."];
        }
        
        // Store profile IDs for the command
        $profile = [
            'line_profile' => $lineProfileId,
            'srv_profile' => $srvProfileId,
            'profile_name' => $usedProfileName,
            'profile_source' => $profileSource
        ];
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        
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
        
        // Find the next available ONU ID for this port
        $assignedOnuId = $onu['onu_id'];
        if (empty($assignedOnuId)) {
            $assignedOnuId = $this->findNextAvailableOnuId($oltId, $frame, $slot, $port);
        }
        
        // Build CLI script with newlines for multi-command execution
        // Huawei MA5680T/MA5683T requires interface context for ont add
        // Format: ont add PORT ONU_ID sn-auth SERIAL omci ont-lineprofile-id X ont-srvprofile-id Y desc "DESC"
        $cliScript = "interface gpon {$frame}/{$slot}\r\nont add {$port} {$assignedOnuId} {$authPart} omci ont-lineprofile-id {$profile['line_profile']} ont-srvprofile-id {$profile['srv_profile']} desc \"{$description}\"\r\nquit";
        
        // Execute the authorization command
        $result = $this->executeCommand($oltId, $cliScript);
        $output = $result['output'] ?? '';
        
        // Check if OLT returned a different ONU ID (rare, but possible)
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
        
        // =================================================================
        // BATCHED PROVISIONING: Combine all commands into single script
        // This avoids multiple round trips (authorization + service-ports + TR-069)
        // =================================================================
        
        $batchScript = '';
        $tr069Status = ['attempted' => false, 'success' => false];
        
        // Service-port for internet VLAN (if specified)
        $vlanId = $options['vlan_id'] ?? $profile['default_vlan'] ?? null;
        if ($vlanId && $assignedOnuId !== null) {
            $gemPort = $options['gem_port'] ?? 2;
            $inboundIndex = $options['inbound_traffic_index'] ?? 8;
            $outboundIndex = $options['outbound_traffic_index'] ?? 9;
            $batchScript .= "service-port vlan {$vlanId} gpon {$frame}/{$slot}/{$port} ont {$assignedOnuId} gemport {$gemPort} multi-service user-vlan {$vlanId} tag-transform translate inbound traffic-table index {$inboundIndex} outbound traffic-table index {$outboundIndex}\r\n";
        }
        
        // TR-069 configuration (OMCI + service-port) - all in one batch
        $tr069Vlan = $options['tr069_vlan'] ?? $profile['tr069_vlan'] ?? null;
        if (!$tr069Vlan) {
            $tr069Vlan = $this->getTR069VlanForOlt($oltId);
        }
        $tr069ProfileId = $options['tr069_profile_id'] ?? $profile['tr069_profile_id'] ?? $this->getTR069ProfileId();
        $acsUrl = $this->getTR069AcsUrl();
        
        if ($tr069Vlan && $assignedOnuId !== null) {
            $tr069Priority = $options['tr069_priority'] ?? 2;
            $tr069GemPort = $options['tr069_gem_port'] ?? $profile['tr069_gem_port'] ?? 2;
            $tr069TrafficIndex = $options['tr069_traffic_index'] ?? 7;
            
            // Enter interface context for TR-069 OMCI commands
            $batchScript .= "interface gpon {$frame}/{$slot}\r\n";
            $batchScript .= "ont ipconfig {$port} {$assignedOnuId} dhcp vlan {$tr069Vlan} priority {$tr069Priority}\r\n";
            
            if ($tr069ProfileId) {
                $batchScript .= "ont tr069-server-config {$port} {$assignedOnuId} profile-id {$tr069ProfileId}\r\n";
            } elseif ($acsUrl) {
                $batchScript .= "ont tr069-server-config {$port} {$assignedOnuId} acs-url {$acsUrl}\r\n";
                $batchScript .= "ont tr069-server-config {$port} {$assignedOnuId} periodic-inform enable interval 300\r\n";
            }
            $batchScript .= "quit\r\n";
            
            // Service-port for TR-069 VLAN
            $batchScript .= "service-port vlan {$tr069Vlan} gpon {$frame}/{$slot}/{$port} ont {$assignedOnuId} gemport {$tr069GemPort} multi-service user-vlan {$tr069Vlan} tag-transform translate inbound traffic-table index {$tr069TrafficIndex} outbound traffic-table index {$tr069TrafficIndex}\r\n";
            
            $tr069Status = [
                'attempted' => true,
                'success' => true, // Will be updated if batch fails
                'vlan' => $tr069Vlan,
                'acs_url' => $acsUrl,
                'gem_port' => $tr069GemPort
            ];
        } else {
            $tr069Status = [
                'attempted' => false,
                'success' => false,
                'reason' => !$tr069Vlan ? 'No TR-069 VLAN found (mark a VLAN as TR-069 in OLT VLANs)' : 'ONU ID not assigned',
                'vlan' => null,
                'acs_url' => null
            ];
        }
        
        // Execute all post-authorization commands in single batch
        if (!empty($batchScript)) {
            $batchResult = $this->executeCommand($oltId, $batchScript);
            $batchOutput = $batchResult['output'] ?? '';
            $output .= "\n[Provisioning]\n" . $batchOutput;
            
            // Check for TR-069 specific errors
            if ($tr069Status['attempted'] && preg_match('/(?:Failure|Error:|failed|Invalid)/i', $batchOutput)) {
                $tr069Status['success'] = false;
                $tr069Status['error'] = $batchOutput;
            }
        }
        
        // Build detailed message with profile info
        $profileInfo = $profile['profile_name'] ? "{$profile['profile_name']} (ID:{$profile['srv_profile']})" : "srv-profile {$profile['srv_profile']}";
        $profileSourceLabel = match($profile['profile_source']) {
            'auto-created' => 'auto-created',
            'olt-matched' => 'matched',
            'crm-fallback' => 'CRM fallback',
            default => ''
        };
        
        $statusMessage = "ONU authorized as ID {$assignedOnuId} using {$profileInfo}";
        if ($profileSourceLabel) {
            $statusMessage .= " [{$profileSourceLabel}]";
        }
        
        if (isset($tr069Status['attempted']) && $tr069Status['attempted']) {
            if ($tr069Status['success']) {
                $statusMessage .= ". TR-069 configured (VLAN {$tr069Vlan})";
            } else {
                $statusMessage .= ". TR-069 FAILED - use manual config";
            }
        } else {
            $statusMessage .= ". TR-069 skipped: " . ($tr069Status['reason'] ?? 'not configured');
        }
        
        $this->addLog([
            'olt_id' => $oltId, 'onu_id' => $onuId, 'action' => 'authorize',
            'status' => 'success',
            'message' => "ONU {$onu['sn']} authorized as {$description} (ONU ID: {$assignedOnuId}) using {$profileInfo}" . (isset($tr069Status['success']) && $tr069Status['success'] ? " with TR-069" : ''),
            'command_sent' => $cliScript,
            'command_response' => $output,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        // Mark discovery log entry as authorized
        try {
            $stmt = $this->db->prepare("
                UPDATE onu_discovery_log 
                SET authorized = true, authorized_at = CURRENT_TIMESTAMP 
                WHERE serial_number = ? AND olt_id = ?
            ");
            $stmt->execute([$onu['sn'], $oltId]);
        } catch (\Exception $e) {
            error_log("Failed to update discovery log for {$onu['sn']}: " . $e->getMessage());
        }
        
        return [
            'success' => true,
            'message' => $statusMessage,
            'onu_id' => $assignedOnuId,
            'description' => $description,
            'output' => $output,
            'profile_used' => [
                'name' => $profile['profile_name'],
                'srv_profile_id' => $profile['srv_profile'],
                'line_profile_id' => $profile['line_profile'],
                'source' => $profile['profile_source']
            ],
            'tr069_configured' => isset($tr069Status['success']) && $tr069Status['success'],
            'tr069_status' => $tr069Status ?? ['attempted' => false]
        ];
    }
    
    // ==================== STAGED PROVISIONING (SmartOLT-style) ====================
    
    /**
     * STAGE 1: Authorization + Service Ports ONLY
     * SmartOLT-style provisioning: Authorize ONU first, verify it's online, then proceed
     */
    public function authorizeONUStage1(int $onuId, int $profileId, array $options = []): array {
        $onu = $this->getONU($onuId);
        
        if (!$onu) {
            return ['success' => false, 'stage' => 1, 'message' => 'ONU not found'];
        }
        
        $oltId = $onu['olt_id'];
        $equipmentId = $this->getOnuEquipmentId($onuId);
        
        // Get line and service profiles (SmartOLT-style: ONU type as service profile)
        $lineProfile = $this->getDefaultOltLineProfile($oltId);
        $lineProfileId = $lineProfile['id'];
        
        $srvProfileId = null;
        $usedProfileName = null;
        $profileSource = 'unknown';
        
        if ($equipmentId) {
            $oltSrvProfile = $this->getOltSrvProfileByOnuType($oltId, $equipmentId);
            
            if (!$oltSrvProfile) {
                $oltSrvProfile = $this->createOltSrvProfileForOnuType($oltId, $equipmentId);
            }
            
            if ($oltSrvProfile) {
                $srvProfileId = $oltSrvProfile['id'];
                $usedProfileName = $oltSrvProfile['name'] ?? $equipmentId;
                $profileSource = 'olt-matched';
            }
        }
        
        // Fallback to CRM profile
        if ($srvProfileId === null && $profileId > 0) {
            $crmProfile = $this->getServiceProfile($profileId);
            if ($crmProfile) {
                $lineProfileId = !empty($crmProfile['line_profile']) ? (int)$crmProfile['line_profile'] : $lineProfileId;
                $srvProfileId = !empty($crmProfile['srv_profile']) ? (int)$crmProfile['srv_profile'] : null;
                $usedProfileName = $crmProfile['name'] ?? 'CRM Profile';
                $profileSource = 'crm-fallback';
            }
        }
        
        if ($srvProfileId === null) {
            return ['success' => false, 'stage' => 1, 'message' => "Failed to find/create service profile for ONU type '{$equipmentId}'"];
        }
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        
        // Build auth part (SN auth by default)
        $authPart = "sn-auth {$onu['sn']}";
        
        // Description
        $description = $options['description'] ?? $onu['name'] ?? '';
        if (empty($description)) {
            $description = $this->generateNextSNSCode($oltId);
        }
        
        // Find next available ONU ID
        $assignedOnuId = $onu['onu_id'];
        if (empty($assignedOnuId)) {
            $assignedOnuId = $this->findNextAvailableOnuId($oltId, $frame, $slot, $port);
        }
        
        // ==== STAGE 1A: AUTHORIZE ONU ====
        $cliScript = "interface gpon {$frame}/{$slot}\r\nont add {$port} {$assignedOnuId} {$authPart} omci ont-lineprofile-id {$lineProfileId} ont-srvprofile-id {$srvProfileId} desc \"{$description}\"\r\nquit";
        
        $result = $this->executeCommand($oltId, $cliScript);
        $output = $result['output'] ?? '';
        
        // Check for assigned ONU ID in response
        if (preg_match('/(?:ONTID|ONT-ID|Number)\s*[:\=]\s*(\d+)/i', $output, $m)) {
            $assignedOnuId = (int)$m[1];
        }
        
        // Check for errors
        $hasError = preg_match('/(?:Failure|Error:|failed|Invalid|Wrong parameter)/i', $output);
        
        if (!$result['success'] || $hasError) {
            $this->addLog([
                'olt_id' => $oltId, 'onu_id' => $onuId, 'action' => 'authorize_stage1',
                'status' => 'failed', 'message' => "Stage 1 failed for {$onu['sn']}",
                'command_sent' => $cliScript, 'command_response' => $output,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return ['success' => false, 'stage' => 1, 'message' => 'Authorization failed: ' . substr($output, 0, 200), 'output' => $output];
        }
        
        // Update ONU record with stage 1 data
        $this->updateONU($onuId, [
            'is_authorized' => true,
            'onu_id' => $assignedOnuId,
            'line_profile' => $lineProfileId,
            'srv_profile' => $srvProfileId,
            'name' => $description,
            'status' => 'online',
            'provisioning_stage' => 1
        ]);
        
        // ==== STAGE 1B: CREATE SERVICE-PORT (Internet VLAN) ====
        $vlanId = $options['vlan_id'] ?? null;
        $servicePortOutput = '';
        $servicePortSuccess = false;
        
        if ($vlanId && $assignedOnuId !== null) {
            $gemPort = $options['gem_port'] ?? 1;
            $inboundIndex = $options['inbound_traffic_index'] ?? 8;
            $outboundIndex = $options['outbound_traffic_index'] ?? 9;
            
            $servicePortCmd = "service-port vlan {$vlanId} gpon {$frame}/{$slot}/{$port} ont {$assignedOnuId} gemport {$gemPort} multi-service user-vlan {$vlanId} tag-transform translate inbound traffic-table index {$inboundIndex} outbound traffic-table index {$outboundIndex}";
            
            $spResult = $this->executeCommand($oltId, $servicePortCmd);
            $servicePortOutput = $spResult['output'] ?? '';
            $output .= "\n[Service-Port]\n" . $servicePortOutput;
            
            $servicePortSuccess = $spResult['success'] && !preg_match('/(?:Failure|Error:|failed|Invalid)/i', $servicePortOutput);
            
            if ($servicePortSuccess) {
                $this->updateONU($onuId, ['vlan_id' => $vlanId]);
            }
        }
        
        // ==== STAGE 1C: BIND NATIVE VLAN TO ETH PORT (optional) ====
        if ($vlanId && $assignedOnuId !== null) {
            $nativeVlanCmd = "interface gpon {$frame}/{$slot}\r\nont port native-vlan {$port} {$assignedOnuId} eth 1 vlan {$vlanId} priority 0\r\nquit";
            $this->executeCommand($oltId, $nativeVlanCmd);
        }
        
        // Mark discovery log entry as authorized
        try {
            $stmt = $this->db->prepare("UPDATE onu_discovery_log SET authorized = true, authorized_at = CURRENT_TIMESTAMP WHERE serial_number = ? AND olt_id = ?");
            $stmt->execute([$onu['sn'], $oltId]);
        } catch (\Exception $e) {}
        
        $this->addLog([
            'olt_id' => $oltId, 'onu_id' => $onuId, 'action' => 'authorize_stage1',
            'status' => 'success',
            'message' => "Stage 1 complete: ONU {$onu['sn']} authorized as ID {$assignedOnuId}" . ($vlanId ? ", VLAN {$vlanId}" : ''),
            'command_sent' => $cliScript, 'command_response' => $output,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'stage' => 1,
            'message' => "Stage 1 complete: ONU authorized as ID {$assignedOnuId}" . ($servicePortSuccess ? ", service-port created" : ''),
            'onu_id' => $assignedOnuId,
            'description' => $description,
            'vlan_id' => $vlanId,
            'service_port_success' => $servicePortSuccess,
            'output' => $output,
            'next_stage' => 2
        ];
    }
    
    /**
     * STAGE 1 VERIFY: Check ONU is online before proceeding
     */
    public function verifyONUOnline(int $onuDbId): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu || !$onu['is_authorized']) {
            return ['success' => false, 'online' => false, 'message' => 'ONU not authorized'];
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuId = $onu['onu_id'];
        
        if ($onuId === null) {
            return ['success' => false, 'online' => false, 'message' => 'ONU ID not assigned'];
        }
        
        // Query ONU info to check status (syntax: display ont info frame/slot port ont-id)
        $cmd = "display ont info {$frame}/{$slot} {$port} {$onuId}";
        $result = $this->executeCommand($oltId, $cmd);
        $output = $result['output'] ?? '';
        
        $online = preg_match('/Run state\s*:\s*online/i', $output) || 
                  preg_match('/Control flag\s*:\s*active/i', $output);
        
        return [
            'success' => true,
            'online' => $online,
            'message' => $online ? 'ONU is online' : 'ONU is offline - wait for it to come online before proceeding',
            'output' => $output
        ];
    }
    
    /**
     * STAGE 2: TR-069 Configuration
     * Only run after Stage 1 is complete and ONU is verified online
     */
    public function configureONUStage2TR069(int $onuDbId, array $options = []): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'stage' => 2, 'message' => 'ONU not found'];
        }
        
        if (!$onu['is_authorized'] || empty($onu['onu_id'])) {
            return ['success' => false, 'stage' => 2, 'message' => 'Complete Stage 1 first (ONU must be authorized)'];
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuId = $onu['onu_id'];
        
        // Get TR-069 VLAN
        $tr069Vlan = $options['tr069_vlan'] ?? $this->getTR069VlanForOlt($oltId);
        if (!$tr069Vlan) {
            return ['success' => false, 'stage' => 2, 'message' => 'No TR-069 VLAN configured. Mark a VLAN as TR-069 in OLT VLANs.'];
        }
        
        // Get ACS URL
        $acsUrl = $options['acs_url'] ?? $this->getTR069AcsUrl();
        $tr069ProfileId = $options['tr069_profile_id'] ?? $this->getTR069ProfileId();
        
        $output = '';
        $errors = [];
        
        // Helper to check for real errors
        $hasRealError = function($output) {
            if (empty($output)) return false;
            if (preg_match('/Make configuration repeatedly|already exists|The data already exist/i', $output)) {
                return false;
            }
            return preg_match('/(?:Failure|Error:|failed|Invalid parameter|Unknown command)/i', $output);
        };
        
        // Step 1: Configure IPHOST/WAN with DHCP on TR-069 VLAN
        $tr069Priority = $options['tr069_priority'] ?? 2;
        $cmd1 = "interface gpon {$frame}/{$slot}\r\n";
        $cmd1 .= "ont ipconfig {$port} {$onuId} dhcp vlan {$tr069Vlan} priority {$tr069Priority}\r\n";
        $cmd1 .= "quit";
        $result1 = $this->executeCommand($oltId, $cmd1);
        $output .= "[TR-069 WAN DHCP]\n" . ($result1['output'] ?? '') . "\n";
        if (!$result1['success'] || $hasRealError($result1['output'] ?? '')) {
            $errors[] = "WAN DHCP config failed";
        }
        
        // Step 2: Configure TR-069 server (profile or URL)
        $cmd2 = "interface gpon {$frame}/{$slot}\r\n";
        if ($tr069ProfileId) {
            $cmd2 .= "ont tr069-server-config {$port} {$onuId} profile-id {$tr069ProfileId}\r\n";
        } elseif ($acsUrl) {
            $cmd2 .= "ont tr069-server-config {$port} {$onuId} acs-url {$acsUrl}\r\n";
            $cmd2 .= "ont tr069-server-config {$port} {$onuId} periodic-inform enable interval 300\r\n";
        }
        $cmd2 .= "quit";
        $result2 = $this->executeCommand($oltId, $cmd2);
        $output .= "[TR-069 Server Config]\n" . ($result2['output'] ?? '') . "\n";
        if (!$result2['success'] || $hasRealError($result2['output'] ?? '')) {
            $errors[] = "TR-069 server config failed";
        }
        
        // Step 3: Create service-port for TR-069 VLAN
        $tr069GemPort = $options['tr069_gem_port'] ?? 2;
        $tr069TrafficIndex = $options['tr069_traffic_index'] ?? 7;
        $cmd3 = "service-port vlan {$tr069Vlan} gpon {$frame}/{$slot}/{$port} ont {$onuId} gemport {$tr069GemPort} multi-service user-vlan {$tr069Vlan} tag-transform translate inbound traffic-table index {$tr069TrafficIndex} outbound traffic-table index {$tr069TrafficIndex}";
        $result3 = $this->executeCommand($oltId, $cmd3);
        $output .= "[TR-069 Service-Port]\n" . ($result3['output'] ?? '') . "\n";
        if (!$result3['success'] || $hasRealError($result3['output'] ?? '')) {
            $errors[] = "TR-069 service-port failed";
        }
        
        $success = empty($errors);
        
        if ($success) {
            $this->updateONU($onuDbId, [
                'tr069_status' => 'configured',
                'provisioning_stage' => 2
            ]);
        }
        
        $this->addLog([
            'olt_id' => $oltId, 'onu_id' => $onuDbId, 'action' => 'configure_stage2_tr069',
            'status' => $success ? 'success' : 'failed',
            'message' => $success ? "Stage 2 complete: TR-069 configured on VLAN {$tr069Vlan}" : 'Stage 2 failed: ' . implode(', ', $errors),
            'command_sent' => $cmd1 . "\n" . $cmd2 . "\n" . $cmd3,
            'command_response' => $output,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => $success,
            'stage' => 2,
            'message' => $success ? "Stage 2 complete: TR-069 configured (VLAN {$tr069Vlan})" : 'Stage 2 failed: ' . implode(', ', $errors),
            'tr069_vlan' => $tr069Vlan,
            'acs_url' => $acsUrl,
            'errors' => $errors,
            'output' => $output,
            'next_stage' => $success ? 3 : null
        ];
    }
    
    /**
     * Get current provisioning stage for an ONU
     */
    public function getONUProvisioningStage(int $onuDbId): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['stage' => 0, 'description' => 'ONU not found'];
        }
        
        $stage = (int)($onu['provisioning_stage'] ?? 0);
        
        $stages = [
            0 => ['name' => 'Pending', 'description' => 'Not authorized', 'next_action' => 'Authorize ONU'],
            1 => ['name' => 'Authorized', 'description' => 'ONU authorized, service-port created', 'next_action' => 'Configure TR-069'],
            2 => ['name' => 'TR-069 Ready', 'description' => 'TR-069 configured, waiting for device connection', 'next_action' => 'Configure WAN via GenieACS'],
            3 => ['name' => 'WAN Configured', 'description' => 'WAN/PPPoE configured via TR-069', 'next_action' => 'Configure WiFi'],
            4 => ['name' => 'Complete', 'description' => 'Fully provisioned', 'next_action' => null]
        ];
        
        return [
            'stage' => $stage,
            'is_authorized' => $onu['is_authorized'] ?? false,
            'onu_id' => $onu['onu_id'],
            'tr069_status' => $onu['tr069_status'] ?? 'pending',
            ...$stages[$stage] ?? $stages[0]
        ];
    }
    
    /**
     * Manually configure TR-069 for an already-authorized ONU (fallback method)
     */
    public function configureTR069Manual(int $onuDbId, ?int $tr069Vlan = null, ?string $acsUrl = null, int $gemPort = 2): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        if (!$onu['is_authorized'] || empty($onu['onu_id'])) {
            return ['success' => false, 'message' => 'ONU must be authorized first'];
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuId = $onu['onu_id'];
        
        // Auto-detect TR-069 VLAN if not provided
        if (!$tr069Vlan) {
            $tr069Vlan = $this->getTR069VlanForOlt($oltId);
        }
        if (!$tr069Vlan) {
            return ['success' => false, 'message' => 'No TR-069 VLAN found. Mark a VLAN as TR-069 in OLT VLANs or specify manually.'];
        }
        
        // Get ACS URL from settings if not provided
        if (!$acsUrl) {
            $acsUrl = $this->getTR069AcsUrl();
        }
        if (!$acsUrl) {
            return ['success' => false, 'message' => 'No ACS URL configured. Set it in Settings → TR-069 OMCI Settings.'];
        }
        
        // Get periodic interval from settings
        $periodicInterval = 300;
        try {
            $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'tr069_periodic_interval'");
            $interval = $stmt->fetchColumn();
            if ($interval) {
                $periodicInterval = (int)$interval;
            }
        } catch (\Exception $e) {}
        
        $output = '';
        $errors = [];
        
        // Helper to check for real errors (ignoring "already configured" messages)
        $hasRealError = function($output) {
            if (empty($output)) return false;
            // Ignore "Make configuration repeatedly" - means already configured (OK)
            // Ignore "already exists" - means already configured (OK)
            if (preg_match('/Make configuration repeatedly|already exists|The data already exist/i', $output)) {
                return false;
            }
            // Check for actual failures
            return preg_match('/(?:Failure|Error:|failed|Invalid parameter|Unknown command)/i', $output);
        };
        
        // Step 1: Create WAN with DHCP on TR-069 VLAN
        // Syntax matches working ONU: ont ipconfig <port> <onuId> dhcp vlan <vlan> priority <priority>
        $cmd1 = "interface gpon {$frame}/{$slot}\r\n";
        $cmd1 .= "ont ipconfig {$port} {$onuId} dhcp vlan {$tr069Vlan} priority 2\r\n";
        $cmd1 .= "quit";
        $result1 = $this->executeCommand($oltId, $cmd1);
        $output .= "[Step 1: WAN DHCP Config]\n" . ($result1['output'] ?? '') . "\n";
        if (!$result1['success'] || $hasRealError($result1['output'] ?? '')) {
            $errors[] = "WAN DHCP config failed";
        }
        
        // Step 2: Push ACS URL and periodic inform
        $cmd2 = "interface gpon {$frame}/{$slot}\r\n";
        $cmd2 .= "ont tr069-server-config {$port} {$onuId} acs-url {$acsUrl}\r\n";
        $cmd2 .= "ont tr069-server-config {$port} {$onuId} periodic-inform enable interval {$periodicInterval}\r\n";
        $cmd2 .= "quit";
        $result2 = $this->executeCommand($oltId, $cmd2);
        $output .= "[Step 2: ACS URL Config]\n" . ($result2['output'] ?? '') . "\n";
        if (!$result2['success'] || $hasRealError($result2['output'] ?? '')) {
            $errors[] = "ACS URL config failed";
        }
        
        // Step 3: Create service-port for TR-069 (use tagged VLAN to match ONU's TR-069 traffic)
        // TR-069 uses traffic-table index 7 (management traffic)
        $cmd3 = "service-port vlan {$tr069Vlan} gpon {$frame}/{$slot}/{$port} ont {$onuId} gemport {$gemPort} multi-service user-vlan {$tr069Vlan} tag-transform translate inbound traffic-table index 7 outbound traffic-table index 7";
        $result3 = $this->executeCommand($oltId, $cmd3);
        $output .= "[Step 3: Service Port]\n" . ($result3['output'] ?? '') . "\n";
        if (!$result3['success'] || $hasRealError($result3['output'] ?? '')) {
            // Not critical if already exists
            if (!preg_match('/already exist/i', $result3['output'] ?? '')) {
                $errors[] = "Service port creation failed";
            }
        }
        
        $success = empty($errors);
        $message = $success 
            ? "TR-069 configured successfully (VLAN: {$tr069Vlan}, ACS: {$acsUrl})"
            : "TR-069 configuration partially failed: " . implode(', ', $errors);
        
        $this->addLog([
            'olt_id' => $oltId,
            'onu_id' => $onuDbId,
            'action' => 'configure_tr069_manual',
            'status' => $success ? 'success' : 'partial',
            'message' => $message,
            'command_response' => $output,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => $success,
            'message' => $message,
            'tr069_vlan' => $tr069Vlan,
            'acs_url' => $acsUrl,
            'errors' => $errors,
            'output' => $output
        ];
    }
    
    /**
     * Configure PPPoE WAN via OMCI and queue TR-069 credentials push
     * Step 1: Configure PPPoE WAN mode on OLT via OMCI
     * Step 2: Create service-port for PPPoE VLAN
     * Step 3: Queue PPPoE credentials for TR-069 push when device connects
     */
    public function configureWANPPPoE(int $onuDbId, array $config): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        if (!$onu['is_authorized'] || empty($onu['onu_id'])) {
            return ['success' => false, 'message' => 'ONU must be authorized first'];
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuId = $onu['onu_id'];
        
        $pppoeVlan = (int)($config['pppoe_vlan'] ?? 902);
        $pppoeUsername = $config['pppoe_username'] ?? '';
        $pppoePassword = $config['pppoe_password'] ?? '';
        $gemPort = (int)($config['gemport'] ?? 2);
        $natEnabled = $config['nat_enabled'] ?? true;
        $priority = (int)($config['priority'] ?? 0);
        
        if (empty($pppoeUsername) || empty($pppoePassword)) {
            return ['success' => false, 'message' => 'PPPoE username and password are required'];
        }
        
        $output = '';
        $errors = [];
        
        // Helper to check for real errors
        $hasRealError = function($output) {
            if (empty($output)) return false;
            if (preg_match('/Make configuration repeatedly|already exists|The data already exist/i', $output)) {
                return false;
            }
            return preg_match('/(?:Failure|Error:|failed|Invalid parameter|Unknown command)/i', $output);
        };
        
        // Step 1: Configure PPPoE WAN via OMCI
        // Note: Huawei OLT uses ont wan-config or ont pppoe-config command
        // Try ont wan-config first (newer syntax)
        $cmd1 = "interface gpon {$frame}/{$slot}\r\n";
        $cmd1 .= "ont wan-config {$port} {$onuId} ip-index 1 profile-id 1 wan-mode pppoe pppoe-proxy enable vlan {$pppoeVlan} priority {$priority}\r\n";
        $cmd1 .= "quit";
        $result1 = $this->executeCommand($oltId, $cmd1);
        $output .= "[Step 1: PPPoE WAN Config]\n" . ($result1['output'] ?? '') . "\n";
        
        // If wan-config fails, try alternative syntax
        if (!$result1['success'] || $hasRealError($result1['output'] ?? '')) {
            // Try ont ipconfig with pppoe mode (alternative for some OLT versions)
            $cmd1Alt = "interface gpon {$frame}/{$slot}\r\n";
            $cmd1Alt .= "ont ipconfig {$port} {$onuId} pppoe vlan {$pppoeVlan} priority {$priority}\r\n";
            $cmd1Alt .= "quit";
            $result1Alt = $this->executeCommand($oltId, $cmd1Alt);
            $output .= "[Step 1 Alt: PPPoE via ipconfig]\n" . ($result1Alt['output'] ?? '') . "\n";
            
            if (!$result1Alt['success'] || $hasRealError($result1Alt['output'] ?? '')) {
                $errors[] = "PPPoE WAN config failed - try manual configuration";
            }
        }
        
        // Step 2: Create service-port for PPPoE VLAN (if not exists)
        // Use traffic-table index syntax (matches SmartOLT) - service VLAN uses index 8/9
        $cmd2 = "service-port vlan {$pppoeVlan} gpon {$frame}/{$slot}/{$port} ont {$onuId} gemport {$gemPort} multi-service user-vlan {$pppoeVlan} tag-transform translate inbound traffic-table index 8 outbound traffic-table index 9";
        $result2 = $this->executeCommand($oltId, $cmd2);
        $output .= "[Step 2: Service Port for PPPoE]\n" . ($result2['output'] ?? '') . "\n";
        
        // Not critical if service port already exists from authorization
        if ($hasRealError($result2['output'] ?? '') && !preg_match('/already exist/i', $result2['output'] ?? '')) {
            $output .= "[Note: Service port may already exist from authorization]\n";
        }
        
        // Step 3: Queue PPPoE credentials for TR-069 push
        // Create pending TR-069 config to be pushed when device connects to ACS
        try {
            // Ensure table exists
            $this->db->exec("CREATE TABLE IF NOT EXISTS huawei_onu_tr069_config (
                onu_id INTEGER PRIMARY KEY,
                config_data TEXT,
                status VARCHAR(20) DEFAULT 'pending',
                error_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP,
                applied_at TIMESTAMP
            )");
            
            $tr069Config = [
                'onu_id' => $onuDbId,
                'wan_vlan' => $pppoeVlan,
                'connection_type' => 'pppoe',
                'pppoe_username' => $pppoeUsername,
                'pppoe_password' => $pppoePassword,
                'nat_enable' => $natEnabled,
                'config_type' => 'wan_pppoe'
            ];
            
            $stmt = $this->db->prepare("
                INSERT INTO huawei_onu_tr069_config (onu_id, config_data, status, error_message, created_at)
                VALUES (?, ?, 'pending', NULL, CURRENT_TIMESTAMP)
                ON CONFLICT (onu_id) DO UPDATE SET
                    config_data = EXCLUDED.config_data,
                    status = 'pending',
                    error_message = NULL,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$onuDbId, json_encode($tr069Config)]);
            $output .= "[Step 3: TR-069 Config Queued]\nPPPoE credentials queued for TR-069 push\n";
        } catch (\Exception $e) {
            $errors[] = "Failed to queue TR-069 config: " . $e->getMessage();
        }
        
        $success = empty($errors);
        $message = $success 
            ? "PPPoE WAN configured via OMCI. Credentials queued for TR-069 push when device connects to ACS."
            : "PPPoE configuration partially failed: " . implode(', ', $errors);
        
        $this->addLog([
            'olt_id' => $oltId,
            'onu_id' => $onuDbId,
            'action' => 'configure_wan_pppoe',
            'status' => $success ? 'success' : 'partial',
            'message' => $message,
            'command_response' => $output,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => $success,
            'message' => $message,
            'pppoe_vlan' => $pppoeVlan,
            'errors' => $errors,
            'output' => $output
        ];
    }
    
    /**
     * Fetch TR-069 IP address from OLT for an ONU
     * Uses display ont wan-info command to get the DHCP-assigned IP
     */
    public function getONUTR069IP(int $onuDbId): ?string {
        $onu = $this->getONU($onuDbId);
        if (!$onu || !$onu['is_authorized'] || empty($onu['onu_id'])) {
            return null;
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuId = $onu['onu_id'];
        
        // Try display ont wan-info command
        $p = (int)$port;
        $o = (int)$onuId;
        $cmd = "interface gpon {$frame}/{$slot}\r\n";
        $cmd .= "display ont wan-info " . $p . " " . $o . "\r\n";
        $cmd .= "quit";
        
        $result = $this->executeCommand($oltId, $cmd);
        $output = $result['output'] ?? '';
        // Strip ANSI escape codes
        $output = preg_replace('/\x1b\[[0-9;]*[A-Za-z]|\[[\d;]*[A-Za-z]/', '', $output);
        
        // Parse IP address from output
        // Format: IPv4 address : 10.97.132.28 (Huawei MA5683T)
        if (preg_match('/IPv4\s+address\s*:\s*([\d.]+)/i', $output, $m)) {
            $ip = $m[1];
            if ($ip && $ip !== '0.0.0.0') {
                // Update the ONU record with the TR-069 IP
                $this->updateONU($onuDbId, ['tr069_ip' => $ip]);
                return $ip;
            }
        }
        
        // Try alternative: display ont ip-host
        $cmd2 = "interface gpon {$frame}/{$slot}\r\n";
        $cmd2 .= "display ont ip-host-config " . $p . " " . $o . "\r\n";
        $cmd2 .= "quit";
        
        $result2 = $this->executeCommand($oltId, $cmd2);
        $output2 = $result2['output'] ?? '';
        
        if (preg_match('/(?:IP|Host)\s*(?:address)?\s*:\s*([\d.]+)/i', $output2, $m)) {
            $ip = $m[1];
            if ($ip && $ip !== '0.0.0.0') {
                $this->updateONU($onuDbId, ['tr069_ip' => $ip]);
                return $ip;
            }
        }
        
        return null;
    }
    
    /**
     * Refresh TR-069 IP for an ONU (called from UI)
     */
    public function refreshONUTR069IP(int $onuDbId): array {
        $ip = $this->getONUTR069IP($onuDbId);
        if ($ip) {
            return ['success' => true, 'tr069_ip' => $ip, 'message' => "TR-069 IP: {$ip}"];
        }
        return ['success' => false, 'message' => 'Could not retrieve TR-069 IP from OLT'];
    }
    
    /**
     * Refresh ONT WAN IP via CLI (OMCI-assigned IP from display ont info)
     * Parses: ONT IP 0 address/mask : 10.97.127.32/16
     * Uses two-step approach to avoid Telnet space-stripping issue
     */
    public function refreshONUOntIP(int $onuDbId): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        $olt = $this->getOLT($onu['olt_id']);
        if (!$olt) {
            return ['success' => false, 'message' => 'OLT not found'];
        }
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = (int)$onu['port'];
        $onuId = (int)$onu['onu_id'];
        
        // Two-step approach to avoid Telnet space-stripping issue:
        // 1. Enter GPON interface context
        // 2. Execute display ont info with port and onu-id separated by space
        
        // Step 1: Enter interface context
        $this->executeCommand($olt['id'], "interface gpon {$frame}/{$slot}");
        
        // Step 2: Execute display ont info (space between port and onu_id works in interface context)
        $result = $this->executeCommand($olt['id'], "display ont info {$port} {$onuId}");
        
        // Step 3: Exit interface context
        $this->executeCommand($olt['id'], "quit");
        
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['error'] ?? $result['message'] ?? 'Failed to execute command'];
        }
        
        $output = $result['output'] ?? '';
        $ontIp = $this->parseOntIpFromCliOutput($output);
        
        if ($ontIp && $ontIp !== 'N/A') {
            $stmt = $this->db->prepare("UPDATE huawei_onus SET ont_ip = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$ontIp, $onuDbId]);
            
            return ['success' => true, 'ont_ip' => $ontIp, 'message' => "WAN IP: {$ontIp}"];
        }
        
        return ['success' => false, 'message' => 'No WAN IP found in OLT response', 'ont_ip' => null];
    }
    
    /**
     * Parse ONT IP from CLI output
     * Matches: ONT IP 0 address/mask : 10.97.127.32/16
     */
    private function parseOntIpFromCliOutput(string $output): ?string {
        if (preg_match('/ONT\s+IP\s+\d+\s+address\/mask\s*:\s*([\d\.]+\/\d+)/i', $output, $m)) {
            return $m[1];
        }
        if (preg_match('/ONT\s+IP\s+\d+\s+address\/mask\s*:\s*([\d\.]+)/i', $output, $m)) {
            return $m[1];
        }
        return null;
    }
    
    /**
     * Configure WAN via TR-069/GenieACS (SmartOLT-style approach)
     * Creates WANConnectionDevice and configures PPPoE/DHCP/Static via GenieACS tasks
     */
    public function configureWANViaTR069(int $onuDbId, array $config): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        $genieacsId = $onu['genieacs_id'] ?? null;
        
        // If genieacs_id not set, try to look up by serial
        if (empty($genieacsId)) {
            require_once __DIR__ . '/GenieACS.php';
            $genieacs = new \App\GenieACS($this->db);
            
            // Try tr069_serial first (GenieACS format), then tr069_device_id, then OLT serial
            $serial = $onu['tr069_serial'] ?? $onu['tr069_device_id'] ?? $onu['sn'] ?? '';
            if (!empty($serial)) {
                $deviceResult = $genieacs->getDeviceBySerial($serial);
                if ($deviceResult['success'] && !empty($deviceResult['device']['_id'])) {
                    $genieacsId = $deviceResult['device']['_id'];
                    // Update the ONU record with the found ID
                    $stmt = $this->db->prepare("UPDATE huawei_onus SET genieacs_id = ? WHERE id = ?");
                    $stmt->execute([$genieacsId, $onuDbId]);
                }
            }
        }
        
        if (empty($genieacsId)) {
            return ['success' => false, 'error' => 'ONU not registered in GenieACS. Configure TR-069 first.'];
        }
        
        $wanMode = $config['wan_mode'] ?? '';
        $serviceVlan = (int)($config['service_vlan'] ?? 0);
        $pppoeUsername = $config['pppoe_username'] ?? '';
        $pppoePassword = $config['pppoe_password'] ?? '';
        
        if (empty($wanMode)) {
            return ['success' => false, 'error' => 'WAN mode is required'];
        }
        
        if ($wanMode === 'pppoe' && (empty($pppoeUsername) || empty($pppoePassword))) {
            return ['success' => false, 'error' => 'PPPoE username and password are required'];
        }
        
        $genieacsUrl = $this->getGenieACSUrl();
        if (!$genieacsUrl) {
            return ['success' => false, 'error' => 'GenieACS URL not configured'];
        }
        
        $errors = [];
        $tasksSent = [];
        
        // Helper function to send task to GenieACS
        $sendTask = function($task) use ($genieacsUrl, $genieacsId) {
            $ch = curl_init("{$genieacsUrl}/devices/" . urlencode($genieacsId) . "/tasks?connection_request");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($task),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 30
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ['code' => $httpCode, 'response' => $response, 'success' => ($httpCode >= 200 && $httpCode < 300)];
        };
        
        // WAN base path - use index 2 for internet WAN (1 is typically management WAN)
        $wanDevicePath = 'InternetGatewayDevice.WANDevice.1';
        $wanConnDeviceIndex = $config['wan_connection_device'] ?? 2;
        $wanConnPath = "{$wanDevicePath}.WANConnectionDevice.{$wanConnDeviceIndex}";
        
        // Step 1: Create WANConnectionDevice if needed (addObject)
        $addConnDeviceTask = [
            'name' => 'addObject',
            'objectName' => "{$wanDevicePath}.WANConnectionDevice."
        ];
        $result = $sendTask($addConnDeviceTask);
        if ($result['success']) {
            $tasksSent[] = 'Create WANConnectionDevice';
            // Parse instance number from response if available
            $respData = json_decode($result['response'], true);
            if (!empty($respData['instanceNumber'])) {
                $wanConnDeviceIndex = $respData['instanceNumber'];
                $wanConnPath = "{$wanDevicePath}.WANConnectionDevice.{$wanConnDeviceIndex}";
            }
        }
        
        // Step 2: Create the appropriate WAN connection type
        if ($wanMode === 'pppoe') {
            // Create WANPPPConnection
            $addPppConnTask = [
                'name' => 'addObject',
                'objectName' => "{$wanConnPath}.WANPPPConnection."
            ];
            $result = $sendTask($addPppConnTask);
            if ($result['success']) {
                $tasksSent[] = 'Create WANPPPConnection';
            } else {
                // Object may already exist, continue with configuration
            }
            
            // Step 3: Configure PPPoE parameters
            $paramValues = [
                ["{$wanConnPath}.WANPPPConnection.1.Enable", true, 'xsd:boolean'],
                ["{$wanConnPath}.WANPPPConnection.1.ConnectionType", 'IP_Routed', 'xsd:string'],
                ["{$wanConnPath}.WANPPPConnection.1.X_HW_ExServiceList", 'INTERNET', 'xsd:string'],
                ["{$wanConnPath}.WANPPPConnection.1.Username", $pppoeUsername, 'xsd:string'],
                ["{$wanConnPath}.WANPPPConnection.1.Password", $pppoePassword, 'xsd:string'],
                ["{$wanConnPath}.WANPPPConnection.1.NATEnabled", true, 'xsd:boolean'],
            ];
            
            // Add VLAN if specified
            if ($serviceVlan > 0) {
                $paramValues[] = ["{$wanConnPath}.WANPPPConnection.1.X_HW_VLAN", $serviceVlan, 'xsd:unsignedInt'];
            }
            
        } elseif ($wanMode === 'dhcp') {
            // Create WANIPConnection
            $addIpConnTask = [
                'name' => 'addObject',
                'objectName' => "{$wanConnPath}.WANIPConnection."
            ];
            $result = $sendTask($addIpConnTask);
            if ($result['success']) {
                $tasksSent[] = 'Create WANIPConnection';
            }
            
            $paramValues = [
                ["{$wanConnPath}.WANIPConnection.1.Enable", true, 'xsd:boolean'],
                ["{$wanConnPath}.WANIPConnection.1.ConnectionType", 'IP_Routed', 'xsd:string'],
                ["{$wanConnPath}.WANIPConnection.1.X_HW_ExServiceList", 'INTERNET', 'xsd:string'],
                ["{$wanConnPath}.WANIPConnection.1.AddressingType", 'DHCP', 'xsd:string'],
                ["{$wanConnPath}.WANIPConnection.1.NATEnabled", true, 'xsd:boolean'],
            ];
            
            if ($serviceVlan > 0) {
                $paramValues[] = ["{$wanConnPath}.WANIPConnection.1.X_HW_VLAN", $serviceVlan, 'xsd:unsignedInt'];
            }
            
        } elseif ($wanMode === 'static') {
            $staticIp = $config['static_ip'] ?? '';
            $subnetMask = $config['subnet_mask'] ?? '255.255.255.0';
            $gateway = $config['gateway'] ?? '';
            $dnsServers = $config['dns_servers'] ?? '8.8.8.8,8.8.4.4';
            
            if (empty($staticIp) || empty($gateway)) {
                return ['success' => false, 'error' => 'Static IP and gateway are required'];
            }
            
            $addIpConnTask = [
                'name' => 'addObject',
                'objectName' => "{$wanConnPath}.WANIPConnection."
            ];
            $sendTask($addIpConnTask);
            
            $paramValues = [
                ["{$wanConnPath}.WANIPConnection.1.Enable", true, 'xsd:boolean'],
                ["{$wanConnPath}.WANIPConnection.1.ConnectionType", 'IP_Routed', 'xsd:string'],
                ["{$wanConnPath}.WANIPConnection.1.X_HW_ExServiceList", 'INTERNET', 'xsd:string'],
                ["{$wanConnPath}.WANIPConnection.1.AddressingType", 'Static', 'xsd:string'],
                ["{$wanConnPath}.WANIPConnection.1.ExternalIPAddress", $staticIp, 'xsd:string'],
                ["{$wanConnPath}.WANIPConnection.1.SubnetMask", $subnetMask, 'xsd:string'],
                ["{$wanConnPath}.WANIPConnection.1.DefaultGateway", $gateway, 'xsd:string'],
                ["{$wanConnPath}.WANIPConnection.1.DNSServers", $dnsServers, 'xsd:string'],
                ["{$wanConnPath}.WANIPConnection.1.NATEnabled", true, 'xsd:boolean'],
            ];
            
            if ($serviceVlan > 0) {
                $paramValues[] = ["{$wanConnPath}.WANIPConnection.1.X_HW_VLAN", $serviceVlan, 'xsd:unsignedInt'];
            }
        } else {
            return ['success' => false, 'error' => "Unknown WAN mode: {$wanMode}"];
        }
        
        // Step 4: Send setParameterValues task
        if (!empty($paramValues)) {
            $task = [
                'name' => 'setParameterValues',
                'parameterValues' => $paramValues
            ];
            
            $result = $sendTask($task);
            if ($result['success']) {
                $tasksSent[] = 'WAN Configuration';
            } else {
                $errors[] = "Failed to send WAN config task (HTTP {$result['code']}): {$result['response']}";
            }
        }
        
        // Update ONU record with WAN configuration
        $updateData = [
            'wan_mode' => $wanMode,
            'vlan_id' => $serviceVlan ?: ($onu['vlan_id'] ?? null)
        ];
        
        if ($wanMode === 'pppoe') {
            $updateData['pppoe_username'] = $pppoeUsername;
            $updateData['pppoe_password'] = $pppoePassword;
        }
        
        $this->updateONU($onuDbId, $updateData);
        
        $this->addLog([
            'olt_id' => $onu['olt_id'],
            'onu_id' => $onuDbId,
            'action' => 'configure_wan_tr069',
            'status' => empty($errors) ? 'success' : 'partial',
            'message' => empty($errors) 
                ? "WAN {$wanMode} configured via TR-069 (VLAN: {$serviceVlan})"
                : "WAN config partial: " . implode(', ', $errors),
            'command_response' => json_encode(['tasks' => $tasksSent, 'params' => array_keys($paramValues)]),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        if (empty($errors)) {
            return [
                'success' => true,
                'message' => "WAN ({$wanMode}) configuration sent via TR-069. The ONU will apply settings on next connection request.",
                'tasks_sent' => $tasksSent
            ];
        }
        
        return [
            'success' => false,
            'error' => implode('; ', $errors),
            'tasks_sent' => $tasksSent
        ];
    }
    
    /**
     * Configure WiFi via TR-069/GenieACS
     * Uses same method as configureWANViaTR069 for consistency
     */
    public function configureWiFiViaTR069(int $onuDbId, array $config): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        $genieacsId = $onu['genieacs_id'] ?? null;
        
        // If genieacs_id not set, try to look up by serial
        if (empty($genieacsId)) {
            require_once __DIR__ . '/GenieACS.php';
            $genieacs = new \App\GenieACS($this->db);
            
            $serial = $onu['tr069_serial'] ?? $onu['tr069_device_id'] ?? $onu['sn'] ?? '';
            if (!empty($serial)) {
                $deviceResult = $genieacs->getDeviceBySerial($serial);
                if ($deviceResult['success'] && !empty($deviceResult['device']['_id'])) {
                    $genieacsId = $deviceResult['device']['_id'];
                    // Update the ONU record with the found ID
                    $stmt = $this->db->prepare("UPDATE huawei_onus SET genieacs_id = ? WHERE id = ?");
                    $stmt->execute([$genieacsId, $onuDbId]);
                }
            }
        }
        
        if (empty($genieacsId)) {
            return ['success' => false, 'error' => 'ONU not registered in GenieACS. Configure TR-069 first.'];
        }
        
        $wlanIndex = (int)($config['wlan_index'] ?? 1);
        $enabled = $config['enabled'] ?? true;
        $ssid = $config['ssid'] ?? '';
        $password = $config['password'] ?? '';
        $channel = (int)($config['channel'] ?? 0);
        $security = $config['security'] ?? 'WPA2-PSK';
        
        if (empty($ssid)) {
            return ['success' => false, 'error' => 'SSID is required'];
        }
        
        $genieacsUrl = $this->getGenieACSUrl();
        if (!$genieacsUrl) {
            return ['success' => false, 'error' => 'GenieACS URL not configured'];
        }
        
        // Build TR-069 parameter values
        $basePath = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIndex}";
        $paramValues = [
            "{$basePath}.Enable" => ['value' => $enabled, 'type' => 'xsd:boolean'],
            "{$basePath}.SSID" => ['value' => $ssid, 'type' => 'xsd:string']
        ];
        
        if (!empty($password)) {
            $paramValues["{$basePath}.PreSharedKey.1.PreSharedKey"] = ['value' => $password, 'type' => 'xsd:string'];
            $paramValues["{$basePath}.KeyPassphrase"] = ['value' => $password, 'type' => 'xsd:string'];
        }
        
        if ($channel > 0) {
            $paramValues["{$basePath}.Channel"] = ['value' => $channel, 'type' => 'xsd:unsignedInt'];
        }
        
        if (!empty($security)) {
            $paramValues["{$basePath}.BeaconType"] = ['value' => $security, 'type' => 'xsd:string'];
        }
        
        // Send setParameterValues task to GenieACS
        $task = [
            'name' => 'setParameterValues',
            'parameterValues' => []
        ];
        
        foreach ($paramValues as $path => $val) {
            $task['parameterValues'][] = [$path, $val['value'], $val['type']];
        }
        
        $ch = curl_init("{$genieacsUrl}/devices/" . urlencode($genieacsId) . "/tasks?connection_request");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($task),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $this->addLog([
            'olt_id' => $onu['olt_id'],
            'onu_id' => $onuDbId,
            'action' => 'configure_wifi_tr069',
            'status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error',
            'message' => "WiFi config: SSID={$ssid}, WLAN={$wlanIndex}",
            'command_response' => json_encode(['http_code' => $httpCode, 'response' => $response]),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => "WiFi configuration sent via TR-069. SSID: {$ssid}",
                'http_code' => $httpCode
            ];
        }
        
        return [
            'success' => false,
            'error' => "Failed to send WiFi config (HTTP {$httpCode}): " . ($curlError ?: $response),
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Configure Ethernet ports via TR-069/GenieACS
     */
    public function configureEthPortsViaTR069(int $onuDbId, array $ports): array {
        return $this->sendTR069Task($onuDbId, 'eth_ports', function($basePath) use ($ports) {
            $params = [];
            foreach ($ports as $i => $enabled) {
                $portNum = $i + 1;
                $params["InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$portNum}.Enable"] = 
                    ['value' => (bool)$enabled, 'type' => 'xsd:boolean'];
            }
            return $params;
        }, 'Ethernet ports configured');
    }
    
    /**
     * Configure LAN/DHCP settings via TR-069/GenieACS
     */
    public function configureLANViaTR069(int $onuDbId, array $config): array {
        return $this->sendTR069Task($onuDbId, 'lan_dhcp', function($basePath) use ($config) {
            $params = [];
            $lanPath = 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
            
            if (isset($config['dhcp_enabled'])) {
                $params["{$lanPath}.DHCPServerEnable"] = ['value' => (bool)$config['dhcp_enabled'], 'type' => 'xsd:boolean'];
            }
            if (!empty($config['ip_start'])) {
                $params["{$lanPath}.MinAddress"] = ['value' => $config['ip_start'], 'type' => 'xsd:string'];
            }
            if (!empty($config['ip_end'])) {
                $params["{$lanPath}.MaxAddress"] = ['value' => $config['ip_end'], 'type' => 'xsd:string'];
            }
            if (!empty($config['lease_time'])) {
                $params["{$lanPath}.DHCPLeaseTime"] = ['value' => (int)$config['lease_time'], 'type' => 'xsd:unsignedInt'];
            }
            return $params;
        }, 'LAN/DHCP configured');
    }
    
    /**
     * Add port forward via TR-069/GenieACS
     */
    public function addPortForwardViaTR069(int $onuDbId, array $config): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) return ['success' => false, 'error' => 'ONU not found'];
        
        $genieacsId = $this->resolveGenieACSId($onuDbId, $onu);
        if (empty($genieacsId)) {
            return ['success' => false, 'error' => 'ONU not registered in GenieACS'];
        }
        
        $genieacsUrl = $this->getGenieACSUrl();
        if (!$genieacsUrl) return ['success' => false, 'error' => 'GenieACS URL not configured'];
        
        // First, create a new PortMapping entry using AddObject
        $addTask = [
            'name' => 'addObject',
            'objectName' => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.PortMapping.'
        ];
        
        $ch = curl_init("{$genieacsUrl}/devices/" . urlencode($genieacsId) . "/tasks?connection_request");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($addTask),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode < 200 || $httpCode >= 300) {
            return ['success' => false, 'error' => "Failed to create port mapping entry (HTTP {$httpCode})"];
        }
        
        // Now set the parameters (using index from response or assuming next available)
        $portIndex = $config['port_index'] ?? 1;
        $basePath = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.PortMapping.{$portIndex}";
        
        $params = [
            "{$basePath}.PortMappingEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$basePath}.ExternalPort" => ['value' => (int)$config['external_port'], 'type' => 'xsd:unsignedInt'],
            "{$basePath}.InternalPort" => ['value' => (int)$config['internal_port'], 'type' => 'xsd:unsignedInt'],
            "{$basePath}.InternalClient" => ['value' => $config['internal_ip'], 'type' => 'xsd:string'],
            "{$basePath}.PortMappingProtocol" => ['value' => strtoupper($config['protocol'] ?? 'TCP'), 'type' => 'xsd:string'],
            "{$basePath}.PortMappingDescription" => ['value' => $config['description'] ?? 'Port Forward', 'type' => 'xsd:string']
        ];
        
        $task = ['name' => 'setParameterValues', 'parameterValues' => []];
        foreach ($params as $path => $val) {
            $task['parameterValues'][] = [$path, $val['value'], $val['type']];
        }
        
        $ch = curl_init("{$genieacsUrl}/devices/" . urlencode($genieacsId) . "/tasks?connection_request");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($task),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode >= 200 && $httpCode < 300) 
            ? ['success' => true, 'message' => 'Port forward added']
            : ['success' => false, 'error' => "Failed to configure port forward (HTTP {$httpCode})"];
    }
    
    /**
     * Change admin password via TR-069/GenieACS
     */
    public function changeAdminPasswordViaTR069(int $onuDbId, string $newPassword): array {
        return $this->sendTR069Task($onuDbId, 'admin_password', function($basePath) use ($newPassword) {
            return [
                'InternetGatewayDevice.UserInterface.X_HW_WebUserInfo.1.UserName' => ['value' => 'admin', 'type' => 'xsd:string'],
                'InternetGatewayDevice.UserInterface.X_HW_WebUserInfo.1.Password' => ['value' => $newPassword, 'type' => 'xsd:string']
            ];
        }, 'Admin password changed');
    }
    
    /**
     * Factory reset ONU via TR-069/GenieACS
     */
    public function factoryResetViaTR069(int $onuDbId): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) return ['success' => false, 'error' => 'ONU not found'];
        
        $genieacsId = $this->resolveGenieACSId($onuDbId, $onu);
        if (empty($genieacsId)) {
            return ['success' => false, 'error' => 'ONU not registered in GenieACS'];
        }
        
        $genieacsUrl = $this->getGenieACSUrl();
        if (!$genieacsUrl) return ['success' => false, 'error' => 'GenieACS URL not configured'];
        
        $task = ['name' => 'factoryReset'];
        
        $ch = curl_init("{$genieacsUrl}/devices/" . urlencode($genieacsId) . "/tasks?connection_request");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($task),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->addLog([
            'olt_id' => $onu['olt_id'],
            'onu_id' => $onuDbId,
            'action' => 'factory_reset_tr069',
            'status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error',
            'message' => 'Factory reset via TR-069',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return ($httpCode >= 200 && $httpCode < 300)
            ? ['success' => true, 'message' => 'Factory reset command sent']
            : ['success' => false, 'error' => "Failed to send factory reset (HTTP {$httpCode})"];
    }
    
    /**
     * Helper: Resolve GenieACS device ID from ONU
     */
    private function resolveGenieACSId(int $onuDbId, array $onu): ?string {
        $genieacsId = $onu['genieacs_id'] ?? null;
        
        if (empty($genieacsId)) {
            require_once __DIR__ . '/GenieACS.php';
            $genieacs = new \App\GenieACS($this->db);
            
            $serial = $onu['tr069_serial'] ?? $onu['tr069_device_id'] ?? $onu['sn'] ?? '';
            if (!empty($serial)) {
                $deviceResult = $genieacs->getDeviceBySerial($serial);
                if ($deviceResult['success'] && !empty($deviceResult['device']['_id'])) {
                    $genieacsId = $deviceResult['device']['_id'];
                    $stmt = $this->db->prepare("UPDATE huawei_onus SET genieacs_id = ? WHERE id = ?");
                    $stmt->execute([$genieacsId, $onuDbId]);
                }
            }
        }
        
        return $genieacsId;
    }
    
    /**
     * Helper: Send TR-069 setParameterValues task
     */
    private function sendTR069Task(int $onuDbId, string $action, callable $paramsBuilder, string $successMsg): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) return ['success' => false, 'error' => 'ONU not found'];
        
        $genieacsId = $this->resolveGenieACSId($onuDbId, $onu);
        if (empty($genieacsId)) {
            return ['success' => false, 'error' => 'ONU not registered in GenieACS'];
        }
        
        $genieacsUrl = $this->getGenieACSUrl();
        if (!$genieacsUrl) return ['success' => false, 'error' => 'GenieACS URL not configured'];
        
        $paramValues = $paramsBuilder('');
        if (empty($paramValues)) {
            return ['success' => false, 'error' => 'No parameters to set'];
        }
        
        $task = ['name' => 'setParameterValues', 'parameterValues' => []];
        foreach ($paramValues as $path => $val) {
            $task['parameterValues'][] = [$path, $val['value'], $val['type']];
        }
        
        $ch = curl_init("{$genieacsUrl}/devices/" . urlencode($genieacsId) . "/tasks?connection_request");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($task),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->addLog([
            'olt_id' => $onu['olt_id'],
            'onu_id' => $onuDbId,
            'action' => "configure_{$action}_tr069",
            'status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error',
            'message' => $successMsg,
            'command_response' => json_encode(['http_code' => $httpCode]),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return ($httpCode >= 200 && $httpCode < 300)
            ? ['success' => true, 'message' => $successMsg]
            : ['success' => false, 'error' => "Failed (HTTP {$httpCode}): {$response}"];
    }
    
    /**
     * Attach a VLAN to an ONU - creates service-port on OLT
     */
    public function attachVlanToONU(int $onuDbId, int $vlanId): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        if (!$onu['is_authorized'] || empty($onu['onu_id'])) {
            return ['success' => false, 'error' => 'ONU must be authorized first'];
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuId = $onu['onu_id'];
        
        // Get current attached VLANs
        $attachedVlans = [];
        if (!empty($onu['attached_vlans'])) {
            $attachedVlans = json_decode($onu['attached_vlans'], true) ?: [];
        } elseif (!empty($onu['vlan_id'])) {
            $attachedVlans = [(int)$onu['vlan_id']];
        }
        
        // Check if already attached
        if (in_array($vlanId, $attachedVlans)) {
            return ['success' => false, 'error' => "VLAN {$vlanId} is already attached to this ONU"];
        }
        
        $output = '';
        $errors = [];
        
        // Determine GEM port based on number of attached VLANs
        $gemPort = count($attachedVlans) + 1;
        
        // Create service-port command
        // service-port vlan {vlan} gpon {frame}/{slot}/{port} ont {onu_id} gemport {gem} multi-service user-vlan {vlan} tag-transform translate
        $cmd = "service-port vlan {$vlanId} gpon {$frame}/{$slot}/{$port} ont {$onuId} gemport {$gemPort} multi-service user-vlan {$vlanId} tag-transform translate inbound traffic-table index 8 outbound traffic-table index 9";
        
        $result = $this->executeCommand($oltId, $cmd);
        $output .= "[Service Port Creation]\n" . ($result['output'] ?? '') . "\n";
        
        // Check for real errors (ignore "already exists")
        $resultOutput = $result['output'] ?? '';
        if (preg_match('/Failure|Error:|failed|Invalid parameter|Unknown command/i', $resultOutput) && 
            !preg_match('/already exist|Make configuration repeatedly/i', $resultOutput)) {
            $errors[] = "Failed to create service-port";
        }
        
        // Update attached_vlans in database
        $attachedVlans[] = $vlanId;
        $stmt = $this->db->prepare("UPDATE huawei_onus SET attached_vlans = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([json_encode($attachedVlans), $onuDbId]);
        
        // If this is the first VLAN, also set it as the primary vlan_id
        if (count($attachedVlans) === 1) {
            $stmt = $this->db->prepare("UPDATE huawei_onus SET vlan_id = ? WHERE id = ?");
            $stmt->execute([$vlanId, $onuDbId]);
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'onu_id' => $onuDbId,
            'action' => 'attach_vlan',
            'status' => empty($errors) ? 'success' : 'error',
            'message' => empty($errors) 
                ? "Attached VLAN {$vlanId} to ONU (GEM port {$gemPort})"
                : "Failed to attach VLAN: " . implode(', ', $errors),
            'command_response' => $output,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        if (empty($errors)) {
            return [
                'success' => true,
                'message' => "VLAN {$vlanId} attached successfully (GEM port {$gemPort})",
                'olt_output' => $output,
                'attached_vlans' => $attachedVlans
            ];
        }
        
        return [
            'success' => false,
            'error' => implode('; ', $errors),
            'olt_output' => $output
        ];
    }
    
    /**
     * Detach a VLAN from an ONU - removes service-port on OLT
     */
    public function detachVlanFromONU(int $onuDbId, int $vlanId): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        if (!$onu['is_authorized'] || empty($onu['onu_id'])) {
            return ['success' => false, 'error' => 'ONU must be authorized first'];
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuId = $onu['onu_id'];
        
        // Get current attached VLANs
        $attachedVlans = [];
        if (!empty($onu['attached_vlans'])) {
            $attachedVlans = json_decode($onu['attached_vlans'], true) ?: [];
        } elseif (!empty($onu['vlan_id'])) {
            $attachedVlans = [(int)$onu['vlan_id']];
        }
        
        // Check if attached
        if (!in_array($vlanId, $attachedVlans)) {
            return ['success' => false, 'error' => "VLAN {$vlanId} is not attached to this ONU"];
        }
        
        $output = '';
        $errors = [];
        
        // First, find the service-port index for this VLAN
        // display service-port port gpon {frame}/{slot}/{port} ont {onu_id}
        $displayCmd = "display service-port port gpon {$frame}/{$slot}/{$port} ont {$onuId}";
        $displayResult = $this->executeCommand($oltId, $displayCmd);
        $displayOutput = $displayResult['output'] ?? '';
        $output .= "[Finding Service Port]\n{$displayOutput}\n";
        
        // Parse service-port index for this VLAN
        // Format: "123  normal  gpon  0/1/0     1   902  common  902..."
        $servicePortIndex = null;
        foreach (explode("\n", $displayOutput) as $line) {
            if (preg_match('/^\s*(\d+)\s+\w+\s+gpon\s+\d+\/\d+\/\d+\s+\d+\s+(\d+)/', $line, $m)) {
                if ((int)$m[2] === $vlanId) {
                    $servicePortIndex = (int)$m[1];
                    break;
                }
            }
        }
        
        if ($servicePortIndex !== null) {
            // Delete service-port by index
            $undoCmd = "undo service-port {$servicePortIndex}";
            $undoResult = $this->executeCommand($oltId, $undoCmd);
            $output .= "[Removing Service Port {$servicePortIndex}]\n" . ($undoResult['output'] ?? '') . "\n";
            
            $undoOutput = $undoResult['output'] ?? '';
            if (preg_match('/Failure|Error:|failed/i', $undoOutput) && 
                !preg_match('/does not exist|not found/i', $undoOutput)) {
                $errors[] = "Failed to remove service-port";
            }
        } else {
            $output .= "[Note: Service port for VLAN {$vlanId} not found on OLT - may have been removed]\n";
        }
        
        // Update attached_vlans in database
        $attachedVlans = array_values(array_filter($attachedVlans, fn($v) => $v !== $vlanId));
        $stmt = $this->db->prepare("UPDATE huawei_onus SET attached_vlans = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([json_encode($attachedVlans), $onuDbId]);
        
        // Update primary vlan_id if this was it
        if ((int)$onu['vlan_id'] === $vlanId) {
            $newPrimaryVlan = !empty($attachedVlans) ? $attachedVlans[0] : null;
            $stmt = $this->db->prepare("UPDATE huawei_onus SET vlan_id = ? WHERE id = ?");
            $stmt->execute([$newPrimaryVlan, $onuDbId]);
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'onu_id' => $onuDbId,
            'action' => 'detach_vlan',
            'status' => empty($errors) ? 'success' : 'error',
            'message' => empty($errors) 
                ? "Detached VLAN {$vlanId} from ONU"
                : "Failed to detach VLAN: " . implode(', ', $errors),
            'command_response' => $output,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        if (empty($errors)) {
            return [
                'success' => true,
                'message' => "VLAN {$vlanId} detached successfully",
                'olt_output' => $output,
                'attached_vlans' => $attachedVlans
            ];
        }
        
        return [
            'success' => false,
            'error' => implode('; ', $errors),
            'olt_output' => $output
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
    
    public function rebootONU(int $onuId, bool $async = true): array {
        $onu = $this->getONU($onuId);
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuIdNum = $onu['onu_id'];
        
        // Huawei MA5683T requires interface context for ont reset
        // The reset command requires "y" confirmation: "Are you sure to reset the ONT(s)? (y/n)[n]:"
        $command = "interface gpon {$frame}/{$slot}\r\nont reset {$port} {$onuIdNum}\r\ny\r\nquit";
        
        if ($async && $this->isOLTServiceAvailable()) {
            // Fast async execution - returns immediately
            $result = $this->executeAsyncViaService($onu['olt_id'], $command);
            
            if ($result['success'] ?? false) {
                // Set ONU status to offline immediately (it will come back online after reboot)
                $stmt = $this->db->prepare("UPDATE huawei_onus SET status = 'offline', uptime = NULL WHERE id = ?");
                $stmt->execute([$onuId]);
            }
            
            $this->addLog([
                'olt_id' => $onu['olt_id'],
                'onu_id' => $onuId,
                'action' => 'reboot',
                'status' => ($result['success'] ?? false) ? 'success' : 'failed',
                'message' => ($result['success'] ?? false) ? "ONU {$onu['sn']} reboot command sent (async)" : ($result['error'] ?? 'Failed'),
                'command_sent' => $command,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => $result['success'] ?? false, 
                'message' => ($result['success'] ?? false) ? "Reboot command sent to ONU {$onu['sn']}" : ('Reboot failed: ' . ($result['error'] ?? 'Unknown error')),
                'async' => true
            ];
        }
        
        // Synchronous execution - waits for result
        $result = $this->executeCommand($onu['olt_id'], $command, true);
        
        // Check for success indicators in output
        $output = $result['output'] ?? '';
        $success = $result['success'] && !preg_match('/(?:Failure|Error:|failed|Invalid|Unknown command)/i', $output);
        
        $this->addLog([
            'olt_id' => $onu['olt_id'],
            'onu_id' => $onuId,
            'action' => 'reboot',
            'status' => $success ? 'success' : 'failed',
            'message' => $success ? "ONU {$onu['sn']} rebooted via direct Telnet" : ($result['message'] ?? 'Reboot command failed'),
            'command_sent' => $command,
            'command_response' => $output,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => $success, 
            'message' => $success ? "ONU {$onu['sn']} rebooted successfully (direct Telnet)" : ('Reboot failed: ' . ($result['message'] ?? 'Unknown error')), 
            'output' => $output,
            'command' => $command
        ];
    }
    
    public function deleteONUFromOLT(int $onuId, bool $async = true): array {
        $onu = $this->getONU($onuId);
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuIdNum = $onu['onu_id'];
        $oltId = $onu['olt_id'];
        
        // Build combined command for delete (with service-port cleanup and confirmation)
        $command = "interface gpon {$frame}/{$slot}\r\nont delete {$port} {$onuIdNum}\r\ny\r\nquit";
        
        if ($async && $this->isOLTServiceAvailable()) {
            // Fast async execution - delete from DB immediately, OLT command runs in background
            // Pass false to avoid infinite recursion (don't call deleteONUFromOLT again)
            $this->deleteONU($onuId, false);
            
            $result = $this->executeAsyncViaService($oltId, $command);
            
            $this->addLog([
                'olt_id' => $oltId,
                'onu_id' => $onuId,
                'action' => 'delete',
                'status' => ($result['success'] ?? false) ? 'success' : 'failed',
                'message' => ($result['success'] ?? false) ? "ONU {$onu['sn']} delete command sent (async)" : ($result['error'] ?? 'Failed'),
                'command_sent' => $command,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => $result['success'] ?? false, 
                'message' => ($result['success'] ?? false) ? "Delete command sent for ONU {$onu['sn']}" : ('Delete failed: ' . ($result['error'] ?? 'Unknown error')),
                'async' => true
            ];
        }
        
        // Synchronous execution
        $allOutput = '';
        
        // Step 1: Find and delete all service-ports for this ONU
        $spCommand = "display service-port port {$frame}/{$slot}/{$port} ont {$onuIdNum}";
        $spResult = $this->executeCommand($oltId, $spCommand);
        $spOutput = $spResult['output'] ?? '';
        $allOutput .= "[Find Service-Ports]\n{$spOutput}\n";
        
        // Parse service-port IDs from output
        $servicePortIds = [];
        if (preg_match_all('/^\s*(\d+)\s+\d+\s+gpon\s+/m', $spOutput, $matches)) {
            $servicePortIds = array_map('intval', $matches[1]);
        }
        
        // Delete each service-port
        foreach ($servicePortIds as $spId) {
            $undoCmd = "undo service-port {$spId}";
            $undoResult = $this->executeCommand($oltId, $undoCmd);
            $allOutput .= "[Delete SP {$spId}]\n" . ($undoResult['output'] ?? '') . "\n";
        }
        
        // Step 2: Delete the ONU
        $result = $this->executeCommand($oltId, $command);
        
        $output = $result['output'] ?? '';
        $allOutput .= "[Delete ONU]\n{$output}";
        $success = $result['success'] && !preg_match('/(?:Failure|Error:|failed|Invalid|Unknown command)/i', $output);
        
        if ($success) {
            // Pass false to avoid infinite recursion (don't call deleteONUFromOLT again)
            $this->deleteONU($onuId, false);
        }
        
        $spCount = count($servicePortIds);
        $message = $success 
            ? "ONU {$onu['sn']} deleted from OLT" . ($spCount > 0 ? " ({$spCount} service-ports removed)" : '')
            : ($result['message'] ?? 'Delete command failed');
        
        $this->addLog([
            'olt_id' => $oltId,
            'onu_id' => $onuId,
            'action' => 'delete',
            'status' => $success ? 'success' : 'failed',
            'message' => $message,
            'command_sent' => $command,
            'command_response' => $allOutput,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return ['success' => $success, 'message' => $message, 'output' => $allOutput, 'service_ports_deleted' => $spCount];
    }
    
    public function resetONUConfig(int $onuId, bool $async = true): array {
        $onu = $this->getONU($onuId);
        if (!$onu) {
            return ['success' => false, 'message' => 'ONU not found'];
        }
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuIdNum = $onu['onu_id'];
        
        // Huawei MA5683T requires interface context (requires "y" confirmation)
        $command = "interface gpon {$frame}/{$slot}\r\nont reset {$port} {$onuIdNum}\r\ny\r\nquit";
        
        if ($async && $this->isOLTServiceAvailable()) {
            // Fast async execution - returns immediately
            $result = $this->executeAsyncViaService($onu['olt_id'], $command);
            
            $this->addLog([
                'olt_id' => $onu['olt_id'],
                'onu_id' => $onuId,
                'action' => 'reset_config',
                'status' => ($result['success'] ?? false) ? 'success' : 'failed',
                'message' => ($result['success'] ?? false) ? "ONU {$onu['sn']} reset command sent (async)" : ($result['error'] ?? 'Failed'),
                'command_sent' => $command,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => $result['success'] ?? false, 
                'message' => ($result['success'] ?? false) ? "Reset command sent to ONU {$onu['sn']}" : ('Reset failed: ' . ($result['error'] ?? 'Unknown error')),
                'async' => true
            ];
        }
        
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
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuIdNum = $onu['onu_id'];
        
        $cleanDesc = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $description);
        $cleanDesc = substr(trim($cleanDesc), 0, 64);
        
        // Combined command with interface context
        $command = "interface gpon {$frame}/{$slot}\r\nont modify {$port} {$onuIdNum} desc \"{$cleanDesc}\"\r\nquit";
        $result = $this->executeCommand($onu['olt_id'], $command);
        
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
                $gemId = $sp['gem_id'] ?? 2;
                $multiService = $sp['multi_service'] ?? 'user-vlan';
                $rxTraffic = $sp['rx_traffic'] ?? 'table';
                $txTraffic = $sp['tx_traffic'] ?? 'table';
                
                $lines[] = "service-port vlan {$spVlan} gpon {$frame}/{$slot}/{$port} ont {$onuId} gemport {$gemId} multi-service {$multiService} rx-cttr {$rxTraffic} tx-cttr {$txTraffic}";
            }
        } else {
            $lines[] = "service-port vlan {$vlanId} gpon {$frame}/{$slot}/{$port} ont {$onuId} gemport 2 multi-service user-vlan rx-cttr 6 tx-cttr 6";
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
    
    public function updateVLANFeatures(int $oltId, int $vlanId, string $description, array $options): array {
        if ($vlanId < 1 || $vlanId > 4094) {
            return ['success' => false, 'message' => 'Invalid VLAN ID'];
        }
        
        $cleanDesc = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $description);
        $cleanDesc = substr(trim($cleanDesc), 0, 32);
        
        if (!empty($cleanDesc)) {
            $command = "vlan desc {$vlanId} description \"{$cleanDesc}\"";
            $this->executeCommand($oltId, $command);
        }
        
        $isMulticast = !empty($options['is_multicast']) ? 't' : 'f';
        $isVoip = !empty($options['is_voip']) ? 't' : 'f';
        $isTr069 = !empty($options['is_tr069']) ? 't' : 'f';
        $dhcpSnooping = !empty($options['dhcp_snooping']) ? 't' : 'f';
        $lanToLan = !empty($options['lan_to_lan']) ? 't' : 'f';
        
        $stmt = $this->db->prepare("
            UPDATE huawei_vlans 
            SET description = ?, 
                is_multicast = ?::boolean, 
                is_voip = ?::boolean, 
                is_tr069 = ?::boolean, 
                dhcp_snooping = ?::boolean, 
                lan_to_lan = ?::boolean,
                updated_at = CURRENT_TIMESTAMP 
            WHERE olt_id = ? AND vlan_id = ?
        ");
        $stmt->execute([
            $description,
            $isMulticast,
            $isVoip,
            $isTr069,
            $dhcpSnooping,
            $lanToLan,
            $oltId,
            $vlanId
        ]);
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'update_vlan_features',
            'status' => 'success',
            'message' => "VLAN {$vlanId} features updated",
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return ['success' => true, 'message' => "VLAN {$vlanId} features updated successfully"];
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
        $p = (int)$port;
        $o = (int)$onuId;
        $command = "display ont info {$frame}/{$slot} " . $p . " " . $o;
        return $this->executeCommand($oltId, $command);
    }
    
    /**
     * Get ONU configuration commands as they would appear on the OLT
     */
    public function getONUConfig(int $onuDbId): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'];
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuId = $onu['onu_id'];
        
        if ($onuId === null) {
            return ['success' => false, 'error' => 'ONU not authorized on OLT'];
        }
        
        $config = [
            'onu' => $onu,
            'ont_config' => null,
            'service_ports' => null,
            'raw' => []
        ];
        
        // Combined command - get both ONT config and service-port in one session
        // Note: From interface gpon frame/slot, syntax is: display current-configuration ont <port> <ont-id>
        $portNum = (int)$port;
        $onuNum = (int)$onuId;
        $combinedCmd = "interface gpon {$frame}/{$slot}\r\ndisplay current-configuration ont " . $portNum . " " . $onuNum . "\r\nquit\r\ndisplay service-port port {$frame}/{$slot}/{$port} ont {$onuId}";
        $result = $this->executeCommand($oltId, $combinedCmd);
        
        if ($result['success']) {
            $output = $result['output'];
            $config['raw']['ont_config'] = $output;
            $config['raw']['service_ports'] = $output;
            $config['ont_config'] = $this->parseOntConfig($output, $frame, $slot, $port, $onuId);
            $config['service_ports'] = $this->parseServicePortConfig($output, $frame, $slot, $port, $onuId);
        }
        
        // Build the full config script
        $script = $this->buildONUConfigScript($config, $frame, $slot, $port, $onuId);
        $config['script'] = $script;
        
        return ['success' => true, 'config' => $config];
    }
    
    private function parseOntConfig(string $output, int $frame, int $slot, int $port, int $onuId): array {
        $config = [
            'ont_add' => null,
            'ont_ipconfig' => null,
            'ont_tr069' => null,
            'ont_port_native_vlan' => [],
            'other' => []
        ];
        
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            
            // ont add command
            if (preg_match('/ont add\s+(\d+)\s+(\d+)\s+(.+)/i', $line, $m)) {
                $config['ont_add'] = $line;
            }
            // ont ipconfig
            elseif (preg_match('/ont ipconfig\s+(\d+)\s+(\d+)\s+(.+)/i', $line, $m)) {
                $config['ont_ipconfig'] = $line;
            }
            // ont tr069-server-config
            elseif (preg_match('/ont tr069-server-config\s+(\d+)\s+(\d+)\s+(.+)/i', $line, $m)) {
                $config['ont_tr069'] = $line;
            }
            // ont port native-vlan
            elseif (preg_match('/ont port native-vlan\s+(.+)/i', $line, $m)) {
                $config['ont_port_native_vlan'][] = $line;
            }
            // other ont commands
            elseif (preg_match('/^ont\s+/i', $line)) {
                $config['other'][] = $line;
            }
        }
        
        return $config;
    }
    
    private function parseServicePortConfig(string $output, int $frame, int $slot, int $port, int $onuId): array {
        $servicePorts = [];
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            $line = trim($line);
            // Match service-port table entries
            // Format: Index VlanID Vlan Attr Port Type Rx/Tx F/S/P VPI VCI ONUId Flow Type Rx/Tx State
            if (preg_match('/^\s*(\d+)\s+(\d+)\s+(\S+)\s+gpon\s+(\d+\/\d+\/\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/i', $line, $m)) {
                $servicePorts[] = [
                    'index' => (int)$m[1],
                    'vlan' => (int)$m[2],
                    'vlan_attr' => $m[3],
                    'port' => $m[4],
                    'gemport' => (int)$m[5],
                    'vpi' => (int)$m[6],
                    'vci' => (int)$m[7],
                    'flow_type' => (int)$m[8]
                ];
            }
        }
        
        return $servicePorts;
    }
    
    private function buildONUConfigScript(array $config, int $frame, int $slot, int $port, int $onuId): string {
        $lines = [];
        $lines[] = "interface gpon {$frame}/{$slot}";
        
        $ontConfig = $config['ont_config'];
        if ($ontConfig) {
            if ($ontConfig['ont_add']) {
                $lines[] = " " . $ontConfig['ont_add'];
            }
            if ($ontConfig['ont_ipconfig']) {
                $lines[] = " " . $ontConfig['ont_ipconfig'];
            }
            if ($ontConfig['ont_tr069']) {
                $lines[] = " " . $ontConfig['ont_tr069'];
            }
            foreach ($ontConfig['ont_port_native_vlan'] as $nv) {
                $lines[] = " " . $nv;
            }
            foreach ($ontConfig['other'] as $other) {
                $lines[] = " " . $other;
            }
        }
        
        $lines[] = "quit";
        
        // Add service-port commands from raw output if available
        if (!empty($config['raw']['service_ports'])) {
            // Extract service-port commands from display output - we need to reconstruct them
            $rawSp = $config['raw']['service_ports'];
            $spLines = explode("\n", $rawSp);
            foreach ($spLines as $spLine) {
                $spLine = trim($spLine);
                // Look for the table rows with service port data
                if (preg_match('/^\s*(\d+)\s+(\d+)\s+\S+\s+gpon/i', $spLine, $m)) {
                    // We found a service port entry, but we need the full command
                    // The display command shows table format, not command format
                    // We'll note we found service ports
                }
            }
        }
        
        // Also try to get service-port configs via display command that shows actual commands
        $script = implode("\n", $lines);
        
        // Add note about service ports
        if (!empty($config['service_ports'])) {
            $script .= "\n\n# Service Ports (from display output):";
            foreach ($config['service_ports'] as $sp) {
                $script .= "\n# service-port {$sp['index']} vlan {$sp['vlan']} gpon {$sp['port']} ont {$onuId} gemport {$sp['gemport']}";
            }
        }
        
        return $script;
    }
    
    /**
     * Get comprehensive ONU status including optical, details, WAN, LAN, history, MACs
     * Tries GenieACS first for instant data, falls back to OLT CLI
     */
    public function getONUFullStatus(int $onuDbId): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'];
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuId = $onu['onu_id'];
        
        if ($onuId === null) {
            return ['success' => false, 'error' => 'ONU not authorized on OLT'];
        }
        
        $status = [
            'onu' => $onu,
            'optical' => null,
            'details' => null,
            'wan' => null,
            'lan' => null,
            'history' => null,
            'mac' => null,
            'raw' => [],
            'source' => 'olt'
        ];
        
        // Try GenieACS first (instant) for WAN/LAN/device info
        $genieData = $this->getONUStatusFromGenieACS($onu['sn']);
        if ($genieData['success']) {
            $status['wan'] = $genieData['wan'] ?? null;
            $status['lan'] = $genieData['lan'] ?? null;
            $status['details'] = $genieData['details'] ?? null;
            $status['source'] = 'tr069';
            
            // Still get optical from OLT (quick single command)
            $p = (int)$port;
            $o = (int)$onuId;
            $opticalCmd = "interface gpon {$frame}/{$slot}\r\ndisplay ont optical-info " . $p . " " . $o . "\r\nquit";
            $opticalResult = $this->executeCommand($oltId, $opticalCmd);
            if ($opticalResult['success']) {
                $status['optical'] = $this->parseOpticalStatus($opticalResult['output']);
            }
            
            // Use database values for optical if available
            if (!$status['optical'] && $onu['rx_power']) {
                $status['optical'] = [
                    'rx_power' => $onu['rx_power'],
                    'tx_power' => $onu['tx_power'],
                    'temperature' => null,
                    'olt_rx_power' => null
                ];
            }
            
            return ['success' => true, 'status' => $status];
        }
        
        // Fallback: Combined OLT command - all queries in one session
        // Ensure explicit spacing for port and onu_id
        $p = (int)$port;
        $o = (int)$onuId;
        $combinedCmd = "interface gpon {$frame}/{$slot}\r\n" .
            "display ont optical-info " . $p . " " . $o . "\r\n" .
            "display ont info " . $p . " " . $o . "\r\n" .
            "display ont wan-info " . $p . " " . $o . "\r\n" .
            "display ont port state " . $p . " " . $o . " eth-port all\r\n" .
            "display ont info " . $p . " " . $o . " history\r\n" .
            "quit\r\n" .
            "display mac-address port {$frame}/{$slot}/{$port} ont " . $o;
        
        $result = $this->executeCommand($oltId, $combinedCmd);
        
        if ($result['success']) {
            $output = $result['output'];
            $status['raw']['full'] = $output;
            $status['optical'] = $this->parseOpticalStatus($output);
            $status['details'] = $this->parseOnuDetails($output);
            $status['wan'] = $this->parseWanInfo($output);
            $status['lan'] = $this->parseLanPorts($output);
            $status['history'] = $this->parseOnuHistory($output);
            $status['mac'] = $this->parseMacAddresses($output);
        }
        
        return ['success' => true, 'status' => $status];
    }
    
    /**
     * Get ONU status from GenieACS (instant)
     */
    private function getONUStatusFromGenieACS(string $serial): array {
        try {
            $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'genieacs_url'");
            $genieUrl = $stmt->fetchColumn();
            
            if (!$genieUrl) {
                return ['success' => false, 'error' => 'GenieACS not configured'];
            }
            
            $genieacs = new \App\GenieACS($this->db);
            $result = $genieacs->getDeviceBySerial($serial);
            
            if (!$result['success'] || empty($result['device'])) {
                return ['success' => false, 'error' => 'Device not found in GenieACS'];
            }
            
            $device = $result['device'];
            $data = ['success' => true];
            
            // Extract WAN info
            $data['wan'] = [];
            $wanPaths = [
                'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1',
                'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1'
            ];
            
            foreach ($wanPaths as $idx => $basePath) {
                $ip = $this->extractTR069Value($device, "{$basePath}.ExternalIPAddress");
                if ($ip) {
                    $data['wan'][] = [
                        'index' => $idx + 1,
                        'name' => $idx == 0 ? 'WAN_IP' : 'WAN_PPPoE',
                        'ipv4_address' => $ip,
                        'ipv4_status' => $this->extractTR069Value($device, "{$basePath}.ConnectionStatus") ?? 'Unknown',
                        'ipv4_access_type' => $idx == 0 ? 'DHCP' : 'PPPoE',
                        'mac_address' => $this->extractTR069Value($device, "{$basePath}.MACAddress"),
                        'default_gateway' => $this->extractTR069Value($device, "{$basePath}.DefaultGateway"),
                    ];
                }
            }
            
            // Extract LAN port info
            $data['lan'] = [];
            for ($i = 1; $i <= 4; $i++) {
                $basePath = "InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$i}";
                $status = $this->extractTR069Value($device, "{$basePath}.Status");
                if ($status !== null) {
                    $data['lan'][] = [
                        'port' => $i,
                        'type' => 'ETH',
                        'link_state' => strtolower($status) === 'up' ? 'up' : 'down',
                        'speed' => $this->extractTR069Value($device, "{$basePath}.MaxBitRate") ?? '-'
                    ];
                }
            }
            
            // Extract device details
            $uptime = $this->extractTR069Value($device, 'InternetGatewayDevice.DeviceInfo.UpTime');
            $data['details'] = [
                'run_state' => 'online',
                'control_flag' => 'active',
                'online_duration' => $uptime ? $this->formatUptime((int)$uptime) : null,
                'memory_occupation' => $this->extractTR069Value($device, 'InternetGatewayDevice.DeviceInfo.MemoryStatus.Free'),
                'cpu_occupation' => null,
                'temperature' => null,
                'last_inform' => $device['_lastInform'] ?? null
            ];
            
            return $data;
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function extractTR069Value(array $device, string $path): ?string {
        $parts = explode('.', $path);
        $current = $device;
        
        foreach ($parts as $part) {
            if (isset($current[$part])) {
                $current = $current[$part];
            } else {
                return null;
            }
        }
        
        if (is_array($current) && isset($current['_value'])) {
            return (string)$current['_value'];
        }
        
        return is_scalar($current) ? (string)$current : null;
    }
    
    private function formatUptime(int $seconds): string {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $mins = floor(($seconds % 3600) / 60);
        
        if ($days > 0) {
            return "{$days}d {$hours}h {$mins}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$mins}m";
        }
        return "{$mins}m";
    }
    
    private function parseOpticalStatus(string $output): array {
        $optical = [
            'module_type' => null,
            'rx_power' => null,
            'tx_power' => null,
            'temperature' => null,
            'olt_rx_power' => null,
            'catv_rx_power' => null
        ];
        
        if (preg_match('/Module type\s*:\s*(.+)/i', $output, $m)) {
            $optical['module_type'] = trim($m[1]);
        }
        if (preg_match('/Rx optical power\(dBm\)\s*:\s*([\d\.\-]+)/i', $output, $m)) {
            $optical['rx_power'] = (float)$m[1];
        }
        if (preg_match('/Tx optical power\(dBm\)\s*:\s*([\d\.\-]+)/i', $output, $m)) {
            $optical['tx_power'] = (float)$m[1];
        }
        if (preg_match('/Temperature\(C\)\s*:\s*([\d\.\-]+)/i', $output, $m)) {
            $optical['temperature'] = (float)$m[1];
        }
        if (preg_match('/OLT Rx ONT optical power\(dBm\)\s*:\s*([\d\.\-]+)/i', $output, $m)) {
            $optical['olt_rx_power'] = (float)$m[1];
        }
        if (preg_match('/CATV Rx optical power\(dBm\)\s*:\s*([\d\.\-]+)/i', $output, $m)) {
            $optical['catv_rx_power'] = (float)$m[1];
        }
        
        return $optical;
    }
    
    private function parseOnuDetails(string $output): array {
        $details = [
            'control_flag' => null,
            'run_state' => null,
            'match_state' => null,
            'distance' => null,
            'memory_occupation' => null,
            'cpu_occupation' => null,
            'temperature' => null,
            'sn' => null,
            'management_mode' => null,
            'description' => null,
            'last_down_cause' => null,
            'last_up_time' => null,
            'last_down_time' => null,
            'last_dying_gasp_time' => null,
            'online_duration' => null,
            'line_profile' => null,
            'service_profile' => null,
            'fec_upstream' => null,
            'tr069_acs_profile' => null,
            'ont_ip' => null
        ];
        
        $patterns = [
            'control_flag' => '/Control flag\s*:\s*(.+)/i',
            'run_state' => '/Run state\s*:\s*(.+)/i',
            'match_state' => '/Match state\s*:\s*(.+)/i',
            'distance' => '/(?:ONT\s+)?distance\s*(?:\([^)]*\))?\s*:\s*(\d+)/i',
            'memory_occupation' => '/Memory occupation\s*:\s*(.+)/i',
            'cpu_occupation' => '/CPU occupation\s*:\s*(.+)/i',
            'temperature' => '/Temperature\s*:\s*(.+)/i',
            'sn' => '/SN\s*:\s*(\S+)/i',
            'management_mode' => '/Management mode\s*:\s*(.+)/i',
            'description' => '/Description\s*:\s*(.+)/i',
            'last_down_cause' => '/Last down cause\s*:\s*(.+)/i',
            'last_up_time' => '/Last up time\s*:\s*(.+)/i',
            'last_down_time' => '/Last down time\s*:\s*(.+)/i',
            'last_dying_gasp_time' => '/Last dying gasp time\s*:\s*(.+)/i',
            'online_duration' => '/ONT online duration\s*:\s*(.+)/i',
            'line_profile' => '/Line profile name\s*:\s*(.+)/i',
            'service_profile' => '/Service profile name\s*:\s*(.+)/i',
            'fec_upstream' => '/FEC upstream\s*:\s*(.+)/i',
            'tr069_acs_profile' => '/TR069 ACS profile\s*:\s*(.+)/i'
        ];
        
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $output, $m)) {
                $details[$key] = trim($m[1]);
            }
        }
        
        if (preg_match('/ONT IP\s*\d*\s*address\/mask\s*:\s*([\d\.]+)/i', $output, $m)) {
            $details['ont_ip'] = trim($m[1]);
        }
        
        return $details;
    }
    
    private function parseWanInfo(string $output): array {
        $interfaces = [];
        $current = null;
        
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            
            // New interface starts with (N)
            if (preg_match('/^\((\d+)\)/', $line, $m)) {
                if ($current) {
                    $interfaces[] = $current;
                }
                $current = ['index' => (int)$m[1]];
                continue;
            }
            
            if (!$current) continue;
            
            $patterns = [
                'name' => '/^Name\s*:\s*(.+)/i',
                'service_type' => '/^Service type\s*:\s*(.+)/i',
                'connection_type' => '/^Connection type\s*:\s*(.+)/i',
                'ipv4_status' => '/^IPv4 Connection status\s*:\s*(.+)/i',
                'ipv4_access_type' => '/^IPv4 access type\s*:\s*(.+)/i',
                'ipv4_address' => '/^IPv4 address\s*:\s*(.+)/i',
                'subnet_mask' => '/^Subnet mask\s*:\s*(.+)/i',
                'default_gateway' => '/^Default gateway\s*:\s*(.+)/i',
                'manage_vlan' => '/^Manage VLAN\s*:\s*(.+)/i',
                'manage_priority' => '/^Manage priority\s*:\s*(.+)/i',
                'mac_address' => '/^MAC address\s*:\s*(.+)/i'
            ];
            
            foreach ($patterns as $key => $pattern) {
                if (preg_match($pattern, $line, $m)) {
                    $current[$key] = trim($m[1]);
                }
            }
        }
        
        if ($current) {
            $interfaces[] = $current;
        }
        
        return $interfaces;
    }
    
    private function parseLanPorts(string $output): array {
        $ports = [];
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            $line = trim($line);
            // Match: 1  FE -  - down noloop
            if (preg_match('/^\s*(\d+)\s+(FE|GE)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)/i', $line, $m)) {
                $ports[] = [
                    'port' => (int)$m[1],
                    'type' => $m[2],
                    'speed' => $m[3] === '-' ? null : $m[3],
                    'duplex' => $m[4] === '-' ? null : $m[4],
                    'link_state' => $m[5],
                    'ring_status' => $m[6]
                ];
            }
        }
        
        return $ports;
    }
    
    private function parseOnuHistory(string $output): array {
        $history = [];
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            $line = trim($line);
            // Match: 05  2025-12-29 08:30:29+03:00   ONU is currently online
            // or: 04  2025-12-28 08:26:56+03:00   2025-12-28 22:33:01+03:00     ONT dying-gasp
            if (preg_match('/^(\d+)\s+(\d{4}-\d{2}-\d{2}\s+[\d:+]+)\s+(.+)$/i', $line, $m)) {
                $entry = [
                    'index' => (int)$m[1],
                    'up_time' => trim($m[2]),
                    'offline_time' => null,
                    'down_reason' => null
                ];
                
                $rest = trim($m[3]);
                if (stripos($rest, 'currently online') !== false) {
                    $entry['status'] = 'online';
                } else {
                    // Try to parse offline time and reason
                    if (preg_match('/^(\d{4}-\d{2}-\d{2}\s+[\d:+]+)\s+(.+)$/i', $rest, $m2)) {
                        $entry['offline_time'] = trim($m2[1]);
                        $entry['down_reason'] = trim($m2[2]);
                    } else {
                        $entry['down_reason'] = $rest;
                    }
                }
                
                $history[] = $entry;
            }
        }
        
        return $history;
    }
    
    private function parseMacAddresses(string $output): array {
        $macs = [];
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            $line = trim($line);
            // Match: 400  gpon d4:b1:08:8b:77:45 dynamic  0 /3 /10  14   1   902
            if (preg_match('/^(\d+)\s+(\S+)\s+([0-9a-f:]+)\s+(\S+)\s+(\d+)\s*\/\s*(\d+)\s*\/\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/i', $line, $m)) {
                $macs[] = [
                    'service_port' => (int)$m[1],
                    'type' => $m[2],
                    'mac' => $m[3],
                    'learn_type' => $m[4],
                    'frame' => (int)$m[5],
                    'slot' => (int)$m[6],
                    'port' => (int)$m[7],
                    'vpi' => (int)$m[8],
                    'vci' => (int)$m[9],
                    'vlan' => (int)$m[10]
                ];
            }
        }
        
        return $macs;
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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?::boolean, ?::boolean, ?::boolean, ?::boolean)
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
                !empty($data['iptv_enabled']) ? 'true' : 'false',
                !empty($data['voip_enabled']) ? 'true' : 'false',
                !empty($data['tr069_enabled']) ? 'true' : 'false',
                !empty($data['is_default']) ? 'true' : 'false'
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
                    iptv_enabled = ?::boolean, voip_enabled = ?::boolean, tr069_enabled = ?::boolean, is_default = ?::boolean,
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
                !empty($data['iptv_enabled']) ? 'true' : 'false',
                !empty($data['voip_enabled']) ? 'true' : 'false',
                !empty($data['tr069_enabled']) ? 'true' : 'false',
                !empty($data['is_default']) ? 'true' : 'false',
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
        // Use \r\n for proper Telnet line endings
        $fullCommand = implode("\r\n", $commands);
        
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
    
    // ==================== OMS Notifications ====================
    
    public function sendNewOnuNotification(array $onu, array $olt): bool {
        try {
            require_once __DIR__ . '/WhatsApp.php';
            require_once __DIR__ . '/Settings.php';
            $whatsapp = new \App\WhatsApp($this->db);
            $settings = new \App\Settings();
            
            // New ONU notifications go to provisioning group (separate from branch groups)
            $provisioningGroup = $settings->get('wa_provisioning_group', '');
            if (empty($provisioningGroup)) {
                error_log("OMS Notification: No provisioning group configured (wa_provisioning_group)");
                return false;
            }
            
            $branchName = $olt['branch_name'] ?? 'Unknown Branch';
            $branchCode = $olt['branch_code'] ?? '';
            $onuPort = "{$onu['frame']}/{$onu['slot']}/{$onu['port']}";
            
            $defaultTemplate = "🔔 *NEW ONU DISCOVERED*\n\n🏢 *OLT:* {olt_name}\n📍 *Branch:* {branch_name}\n📊 *Count:* {onu_count} new ONU(s)\n⏰ *Time:* {discovery_time}\n\n📋 *Locations:*\n{onu_locations}\n\n🔢 *Serial Numbers:*\n{onu_serials}\n\n💡 Please authorize these ONUs in the OMS panel.";
            $template = $settings->get('wa_template_oms_new_onu', $defaultTemplate);
            
            $placeholders = [
                '{olt_name}' => $olt['name'],
                '{olt_ip}' => $olt['ip_address'] ?? '',
                '{branch_name}' => $branchName,
                '{branch_code}' => $branchCode,
                '{onu_count}' => '1',
                '{discovery_time}' => date('Y-m-d H:i:s'),
                '{onu_locations}' => "• {$onuPort}",
                '{onu_serials}' => "• {$onu['sn']}"
            ];
            
            $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
            
            $result = $whatsapp->sendToGroup($provisioningGroup, $message);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            error_log("OMS Notification Error (New ONU): " . $e->getMessage());
            return false;
        }
    }
    
    public function sendLosNotification(array $onu, array $olt, string $previousStatus = 'online'): bool {
        if (empty($olt['branch_whatsapp_group'])) {
            return false;
        }
        
        try {
            require_once __DIR__ . '/WhatsApp.php';
            require_once __DIR__ . '/Settings.php';
            $whatsapp = new \App\WhatsApp($this->db);
            $settings = new \App\Settings();
            
            $branchName = $olt['branch_name'] ?? 'Unknown Branch';
            $branchCode = $olt['branch_code'] ?? '';
            $customerName = $onu['customer_name'] ?? 'Unknown Customer';
            $customerPhone = $onu['customer_phone'] ?? '';
            $onuPort = "{$onu['frame']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_id']}";
            
            $defaultTemplate = "⚠️ *ONU LOS ALERT*\n\n🏢 *OLT:* {olt_name}\n📍 *Branch:* {branch_name}\n🔌 *ONU:* {onu_name}\n🔢 *SN:* {onu_sn}\n📡 *Port:* {onu_port}\n⏰ *Time:* {alert_time}\n\n⚡ *Previous Status:* {previous_status}\n❌ *Current Status:* LOS (Loss of Signal)\n\n🔧 Please check fiber connection and customer site.";
            $template = $settings->get('wa_template_oms_los_alert', $defaultTemplate);
            
            $placeholders = [
                '{olt_name}' => $olt['name'],
                '{olt_ip}' => $olt['ip_address'] ?? '',
                '{branch_name}' => $branchName,
                '{branch_code}' => $branchCode,
                '{onu_name}' => $onu['name'] ?? 'N/A',
                '{onu_sn}' => $onu['sn'],
                '{onu_port}' => $onuPort,
                '{alert_time}' => date('Y-m-d H:i:s'),
                '{previous_status}' => $previousStatus,
                '{customer_name}' => $customerName,
                '{customer_phone}' => $customerPhone
            ];
            
            $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
            
            $result = $whatsapp->sendToGroup($olt['branch_whatsapp_group'], $message);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            error_log("OMS Notification Error (LOS): " . $e->getMessage());
            return false;
        }
    }
    
    public function sendOnuAuthorizedNotification(array $onu, array $olt, string $authorizedBy = ''): bool {
        if (empty($olt['branch_whatsapp_group'])) {
            return false;
        }
        
        try {
            require_once __DIR__ . '/WhatsApp.php';
            require_once __DIR__ . '/Settings.php';
            $whatsapp = new \App\WhatsApp($this->db);
            $settings = new \App\Settings();
            
            $branchName = $olt['branch_name'] ?? 'Unknown Branch';
            $branchCode = $olt['branch_code'] ?? '';
            $customerName = $onu['customer_name'] ?? '';
            $customerPhone = $onu['customer_phone'] ?? '';
            $onuPort = "{$onu['frame']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_id']}";
            
            $defaultTemplate = "✅ *ONU AUTHORIZED*\n\n🏢 *OLT:* {olt_name}\n📍 *Branch:* {branch_name}\n🔌 *ONU:* {onu_name}\n🔢 *SN:* {onu_sn}\n📡 *Port:* {onu_port}\n👤 *Customer:* {customer_name}\n⏰ *Time:* {auth_time}\n\n✨ ONU is now online and ready for service.";
            $template = $settings->get('wa_template_oms_onu_authorized', $defaultTemplate);
            
            $placeholders = [
                '{olt_name}' => $olt['name'],
                '{olt_ip}' => $olt['ip_address'] ?? '',
                '{branch_name}' => $branchName,
                '{branch_code}' => $branchCode,
                '{onu_name}' => $onu['name'] ?? 'N/A',
                '{onu_sn}' => $onu['sn'],
                '{onu_port}' => $onuPort,
                '{auth_time}' => date('Y-m-d H:i:s'),
                '{customer_name}' => $customerName,
                '{customer_phone}' => $customerPhone,
                '{service_profile}' => $onu['service_profile'] ?? '',
                '{authorized_by}' => $authorizedBy
            ];
            
            $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
            
            $result = $whatsapp->sendToGroup($olt['branch_whatsapp_group'], $message);
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            error_log("OMS Notification Error (Authorized): " . $e->getMessage());
            return false;
        }
    }
    
    private function getTR069AcsUrl(): ?string {
        try {
            // First check for explicit TR-069 ACS URL setting
            $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'tr069_acs_url'");
            $url = $stmt->fetchColumn();
            if ($url) {
                return $url;
            }
            
            // Fallback: Use WireGuard gateway IP (for remote OLT access via VPN)
            $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'vpn_gateway_ip'");
            $vpnGateway = $stmt->fetchColumn();
            if ($vpnGateway) {
                return "http://{$vpnGateway}:7547";
            }
            
            // Last fallback: Try GenieACS URL
            $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'genieacs_url'");
            $genieUrl = $stmt->fetchColumn();
            if ($genieUrl) {
                $parsed = parse_url($genieUrl);
                $host = $parsed['host'] ?? 'localhost';
                return "http://{$host}:7547";
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get the TR-069 server profile ID from settings
     * This profile-id is configured on the OLT and contains ACS URL, username, password
     */
    private function getTR069ProfileId(): ?int {
        try {
            $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'tr069_profile_id'");
            $profileId = $stmt->fetchColumn();
            return $profileId ? (int)$profileId : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get the TR-069 VLAN configured for an OLT
     * Looks for a VLAN with is_tr069 = true in huawei_vlans table
     */
    private function getTR069VlanForOlt(int $oltId): ?int {
        try {
            $stmt = $this->db->prepare("
                SELECT vlan_id FROM huawei_vlans 
                WHERE olt_id = ? AND is_tr069 = TRUE AND is_active = TRUE
                ORDER BY vlan_id ASC
                LIMIT 1
            ");
            $stmt->execute([$oltId]);
            $vlanId = $stmt->fetchColumn();
            return $vlanId ? (int)$vlanId : null;
        } catch (\Exception $e) {
            error_log("Error fetching TR-069 VLAN for OLT {$oltId}: " . $e->getMessage());
            return null;
        }
    }
    
    public function setOntPortNativeVlan(int $onuDbId, int $ethPort, int $vlanId, int $priority = 0): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuId = $onu['onu_id'];
        
        $cmd = "interface gpon {$frame}/{$slot}\r\n";
        $cmd .= "ont port native-vlan {$port} {$onuId} eth {$ethPort} vlan {$vlanId} priority {$priority}\r\n";
        $cmd .= "quit";
        
        $result = $this->executeCommand($oltId, $cmd);
        
        $success = !empty($result['output']) && strpos($result['output'], 'error') === false;
        
        $this->addLog([
            'olt_id' => $oltId,
            'onu_id' => $onuDbId,
            'action' => 'set_port_native_vlan',
            'status' => $success ? 'success' : 'error',
            'message' => "Set ETH{$ethPort} native VLAN to {$vlanId}",
            'command_sent' => $cmd,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => $success,
            'output' => $result['output'] ?? '',
            'error' => $success ? null : 'Command may have failed'
        ];
    }
    
    public function setOntPortMode(int $onuDbId, int $ethPort, string $mode, array $options = []): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        $mode = strtolower($mode);
        if (!in_array($mode, ['access', 'trunk', 'hybrid', 'transparent'])) {
            return ['success' => false, 'error' => 'Invalid port mode'];
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuId = $onu['onu_id'];
        
        $cmd = "interface gpon {$frame}/{$slot}\r\n";
        
        if ($mode === 'transparent') {
            $cmd .= "ont port route {$port} {$onuId} eth {$ethPort} transparent\r\n";
        } else {
            $cmd .= "ont port route {$port} {$onuId} eth {$ethPort} {$mode}\r\n";
        }
        
        $cmd .= "quit";
        
        $result = $this->executeCommand($oltId, $cmd);
        
        $success = !empty($result['output']) && strpos($result['output'], 'error') === false;
        
        $this->addLog([
            'olt_id' => $oltId,
            'onu_id' => $onuDbId,
            'action' => 'set_port_mode',
            'status' => $success ? 'success' : 'error',
            'message' => "Set ETH{$ethPort} mode to {$mode}",
            'command_sent' => $cmd,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => $success,
            'output' => $result['output'] ?? '',
            'error' => $success ? null : 'Command may have failed'
        ];
    }
    
    public function configureOnuPorts(int $onuDbId, array $portConfigs): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        $onuId = $onu['onu_id'];
        
        $cmd = "interface gpon {$frame}/{$slot}\r\n";
        
        foreach ($portConfigs as $ethPort => $config) {
            $mode = $config['mode'] ?? 'access';
            $vlanId = $config['vlan_id'] ?? null;
            $priority = $config['priority'] ?? 0;
            $allowedVlans = $config['allowed_vlans'] ?? '';
            
            if ($mode === 'transparent') {
                $cmd .= "ont port route {$port} {$onuId} eth {$ethPort} transparent\r\n";
            } else {
                $cmd .= "ont port route {$port} {$onuId} eth {$ethPort} {$mode}\r\n";
            }
            
            if ($vlanId && $mode !== 'trunk') {
                $cmd .= "ont port native-vlan {$port} {$onuId} eth {$ethPort} vlan {$vlanId} priority {$priority}\r\n";
            }
            
            if ($mode === 'trunk' && !empty($allowedVlans)) {
                $cmd .= "ont port vlan {$port} {$onuId} eth {$ethPort} add vlan {$allowedVlans}\r\n";
            }
        }
        
        $cmd .= "quit";
        
        $result = $this->executeCommand($oltId, $cmd);
        
        $success = !empty($result['output']) && strpos($result['output'], 'error') === false;
        
        // Store port configuration in database
        try {
            $stmt = $this->db->prepare("
                UPDATE huawei_onus SET port_config = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
            ");
            $stmt->execute([json_encode($portConfigs), $onuDbId]);
        } catch (\Exception $e) {
            // Column might not exist yet
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'onu_id' => $onuDbId,
            'action' => 'configure_ports',
            'status' => $success ? 'success' : 'error',
            'message' => "Configured " . count($portConfigs) . " ETH ports",
            'command_sent' => $cmd,
            'command_response' => $result['output'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => $success,
            'output' => $result['output'] ?? '',
            'ports_configured' => count($portConfigs),
            'error' => $success ? null : 'Some commands may have failed'
        ];
    }
    
    public function applyPortTemplate(int $onuDbId, string $template): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        // Get ONU type info for number of ports
        $ethPorts = 4; // Default
        if ($onu['onu_type_id']) {
            $stmt = $this->db->prepare("SELECT eth_ports FROM huawei_onu_types WHERE id = ?");
            $stmt->execute([$onu['onu_type_id']]);
            $ethPorts = $stmt->fetchColumn() ?: 4;
        }
        
        $portConfigs = [];
        
        switch ($template) {
            case 'bridge':
                // All ports access mode, same VLAN
                for ($i = 1; $i <= $ethPorts; $i++) {
                    $portConfigs[$i] = ['mode' => 'transparent', 'vlan_id' => null, 'priority' => 0];
                }
                break;
                
            case 'router':
                // Port 1: WAN (access, internet VLAN), Ports 2-4: LAN (transparent)
                $portConfigs[1] = ['mode' => 'access', 'vlan_id' => 100, 'priority' => 0];
                for ($i = 2; $i <= $ethPorts; $i++) {
                    $portConfigs[$i] = ['mode' => 'transparent', 'vlan_id' => null, 'priority' => 0];
                }
                break;
                
            case 'iptv':
                // Port 4: IPTV (access, multicast VLAN), Others: Internet
                for ($i = 1; $i <= $ethPorts - 1; $i++) {
                    $portConfigs[$i] = ['mode' => 'access', 'vlan_id' => 100, 'priority' => 0];
                }
                $portConfigs[$ethPorts] = ['mode' => 'access', 'vlan_id' => 500, 'priority' => 5];
                break;
                
            case 'voip':
                // Port 1: Internet, Port 4: VoIP (high priority)
                $portConfigs[1] = ['mode' => 'access', 'vlan_id' => 100, 'priority' => 0];
                for ($i = 2; $i <= $ethPorts - 1; $i++) {
                    $portConfigs[$i] = ['mode' => 'transparent', 'vlan_id' => null, 'priority' => 0];
                }
                $portConfigs[$ethPorts] = ['mode' => 'access', 'vlan_id' => 300, 'priority' => 6];
                break;
                
            case 'trunk_all':
                // All ports trunk mode
                for ($i = 1; $i <= $ethPorts; $i++) {
                    $portConfigs[$i] = ['mode' => 'trunk', 'vlan_id' => null, 'allowed_vlans' => '100-999', 'priority' => 0];
                }
                break;
                
            default:
                return ['success' => false, 'error' => 'Unknown template: ' . $template];
        }
        
        $result = $this->configureOnuPorts($onuDbId, $portConfigs);
        $result['template'] = $template;
        $result['eth_ports'] = $ethPorts;
        
        return $result;
    }
    
    public function moveONU(int $onuDbId, int $newSlot, int $newPort, ?int $newOnuId = null): array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        $oltId = $onu['olt_id'];
        $frame = $onu['frame'] ?? 0;
        $oldSlot = $onu['slot'];
        $oldPort = $onu['port'];
        $oldOnuId = $onu['onu_id'];
        $sn = $onu['sn'];
        $description = $onu['name'] ?? $onu['description'] ?? $sn;
        
        // Validate: not moving to same location
        if ($oldSlot == $newSlot && $oldPort == $newPort && ($newOnuId === null || $newOnuId == $oldOnuId)) {
            return ['success' => false, 'error' => 'ONU is already at this location'];
        }
        
        // Store original config for rollback
        $originalData = [
            'slot' => $oldSlot,
            'port' => $oldPort,
            'onu_id' => $oldOnuId
        ];
        
        // Get profile info
        $profile = null;
        if ($onu['service_profile_id']) {
            $stmt = $this->db->prepare("SELECT * FROM huawei_service_profiles WHERE id = ?");
            $stmt->execute([$onu['service_profile_id']]);
            $profile = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        $lineProfile = $profile['line_profile'] ?? 10;
        $srvProfile = $profile['srv_profile'] ?? 10;
        
        // Step 1: Add to new location FIRST (less risky)
        $addCmd = "interface gpon {$frame}/{$newSlot}\r\n";
        if ($newOnuId !== null) {
            $addCmd .= "ont add {$newPort} {$newOnuId} sn-auth \"{$sn}\" omci ont-lineprofile-id {$lineProfile} ont-srvprofile-id {$srvProfile} desc \"{$description}\"\r\n";
        } else {
            $addCmd .= "ont add {$newPort} sn-auth \"{$sn}\" omci ont-lineprofile-id {$lineProfile} ont-srvprofile-id {$srvProfile} desc \"{$description}\"\r\n";
        }
        $addCmd .= "quit";
        
        $addResult = $this->executeCommand($oltId, $addCmd);
        $output = "[Add to {$frame}/{$newSlot}/{$newPort}" . ($newOnuId !== null ? ":{$newOnuId}" : '') . "]\n" . ($addResult['output'] ?? '');
        
        // Check if add was successful (look for ONTID or no error)
        $addOutput = strtolower($addResult['output'] ?? '');
        $addSuccess = (strpos($addOutput, 'ontid') !== false || strpos($addOutput, 'success') !== false) && 
                      strpos($addOutput, 'error') === false && strpos($addOutput, 'failed') === false;
        
        // Parse new ONU ID from response
        $assignedOnuId = $newOnuId;
        if (preg_match('/ontid\s*:\s*(\d+)/i', $addResult['output'] ?? '', $m)) {
            $assignedOnuId = (int)$m[1];
        } elseif (preg_match('/ont id\s*:\s*(\d+)/i', $addResult['output'] ?? '', $m)) {
            $assignedOnuId = (int)$m[1];
        }
        
        if (!$addSuccess) {
            $this->addLog([
                'olt_id' => $oltId,
                'onu_id' => $onuDbId,
                'action' => 'move_onu',
                'status' => 'error',
                'message' => "Failed to add ONU {$sn} to new location {$frame}/{$newSlot}/{$newPort}",
                'command_sent' => $addCmd,
                'command_response' => $output,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return [
                'success' => false,
                'error' => 'Failed to add ONU to new location - original location unchanged',
                'output' => $output
            ];
        }
        
        // Step 2: Delete from old location
        $deleteCmd = "interface gpon {$frame}/{$oldSlot}\r\n";
        $deleteCmd .= "ont delete {$oldPort} {$oldOnuId}\r\n";
        $deleteCmd .= "quit";
        
        $deleteResult = $this->executeCommand($oltId, $deleteCmd);
        $output .= "\n\n[Delete from {$frame}/{$oldSlot}/{$oldPort}:{$oldOnuId}]\n" . ($deleteResult['output'] ?? '');
        
        $success = true;
        
        // Update database
        $this->updateONU($onuDbId, [
            'slot' => $newSlot,
            'port' => $newPort,
            'onu_id' => $assignedOnuId ?? $oldOnuId
        ]);
        
        $this->addLog([
            'olt_id' => $oltId,
            'onu_id' => $onuDbId,
            'action' => 'move_onu',
            'status' => 'success',
            'message' => "Moved ONU {$sn} from {$frame}/{$oldSlot}/{$oldPort}:{$oldOnuId} to {$frame}/{$newSlot}/{$newPort}" . ($assignedOnuId ? ":{$assignedOnuId}" : ''),
            'command_sent' => $addCmd . "\n\n" . $deleteCmd,
            'command_response' => $output,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'message' => "ONU moved to {$frame}/{$newSlot}/{$newPort}" . ($assignedOnuId ? " (ID: {$assignedOnuId})" : ''),
            'new_slot' => $newSlot,
            'new_port' => $newPort,
            'new_onu_id' => $assignedOnuId ?? $oldOnuId,
            'output' => $output
        ];
    }
    
    public function bulkMoveONUs(array $migrations): array {
        $results = [];
        $successful = 0;
        $failed = 0;
        
        foreach ($migrations as $migration) {
            $onuDbId = (int)($migration['onu_id'] ?? 0);
            $newSlot = (int)($migration['new_slot'] ?? 0);
            $newPort = (int)($migration['new_port'] ?? 0);
            $newOnuId = !empty($migration['new_onu_id']) ? (int)$migration['new_onu_id'] : null;
            
            if (!$onuDbId || !$newSlot || !$newPort) {
                $results[] = ['onu_id' => $onuDbId, 'success' => false, 'error' => 'Missing required parameters'];
                $failed++;
                continue;
            }
            
            $result = $this->moveONU($onuDbId, $newSlot, $newPort, $newOnuId);
            $results[] = array_merge(['onu_id' => $onuDbId], $result);
            
            if ($result['success']) {
                $successful++;
            } else {
                $failed++;
            }
            
            // Small delay between migrations to prevent overwhelming the OLT
            usleep(500000); // 500ms
        }
        
        return [
            'success' => $failed === 0,
            'total' => count($migrations),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results
        ];
    }
    
    public function getONUsForMigration(int $oltId, ?int $slot = null, ?int $port = null): array {
        $sql = "SELECT o.id, o.sn, o.name, o.frame, o.slot, o.port, o.onu_id, o.status, o.rx_power,
                       c.name as customer_name
                FROM huawei_onus o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE o.olt_id = ? AND o.is_authorized = true";
        $params = [$oltId];
        
        if ($slot !== null) {
            $sql .= " AND o.slot = ?";
            $params[] = $slot;
        }
        if ($port !== null) {
            $sql .= " AND o.port = ?";
            $params[] = $port;
        }
        
        $sql .= " ORDER BY o.slot, o.port, o.onu_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAvailablePorts(int $oltId): array {
        // Get board info for available GPON ports
        $stmt = $this->db->prepare("
            SELECT DISTINCT frame, slot, port, COUNT(*) as onu_count
            FROM huawei_onus 
            WHERE olt_id = ? AND is_authorized = true
            GROUP BY frame, slot, port
            ORDER BY frame, slot, port
        ");
        $stmt->execute([$oltId]);
        $usedPorts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Get OLT board info for available slots
        $stmt = $this->db->prepare("SELECT board_info FROM huawei_olts WHERE id = ?");
        $stmt->execute([$oltId]);
        $olt = $stmt->fetch(\PDO::FETCH_ASSOC);
        $boardInfo = json_decode($olt['board_info'] ?? '{}', true);
        
        $availablePorts = [];
        foreach ($boardInfo as $slotNum => $board) {
            if (isset($board['type']) && (stripos($board['type'], 'GP') !== false || stripos($board['type'], 'GPON') !== false)) {
                // Typically 8 or 16 ports per GPON board
                $portsPerBoard = 8; // Default
                if (preg_match('/(\d+)$/', $board['type'] ?? '', $m)) {
                    $portsPerBoard = min((int)$m[1], 16);
                }
                for ($p = 0; $p < $portsPerBoard; $p++) {
                    $availablePorts[] = [
                        'frame' => 0,
                        'slot' => (int)$slotNum,
                        'port' => $p
                    ];
                }
            }
        }
        
        return ['used_ports' => $usedPorts, 'available_ports' => $availablePorts];
    }
    
    public function getOnuTypeInfo(int $onuDbId): ?array {
        $onu = $this->getONU($onuDbId);
        if (!$onu) {
            return null;
        }
        
        if ($onu['onu_type_id']) {
            $stmt = $this->db->prepare("SELECT * FROM huawei_onu_types WHERE id = ?");
            $stmt->execute([$onu['onu_type_id']]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        // Try to detect from ONU model/description
        $model = $onu['model'] ?? $onu['onu_type'] ?? '';
        if ($model) {
            $stmt = $this->db->prepare("
                SELECT * FROM huawei_onu_types 
                WHERE model ILIKE ? OR model_aliases ILIKE ?
                LIMIT 1
            ");
            $stmt->execute(["%{$model}%", "%{$model}%"]);
            $type = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($type) {
                // Update ONU with detected type
                $this->updateONU($onuDbId, ['onu_type_id' => $type['id']]);
                return $type;
            }
        }
        
        // Return default type info
        return [
            'id' => null,
            'model' => 'Unknown',
            'eth_ports' => 4,
            'pots_ports' => 0,
            'wifi_capable' => false,
            'tr069_capable' => true
        ];
    }
    
    public function bulkConfigureTR069(int $oltId, string $acsUrl, array $options = []): array {
        $onuFilter = $options['onu_ids'] ?? null;
        $tr069Vlan = $options['tr069_vlan'] ?? null;
        $periodicInterval = $options['periodic_interval'] ?? 300;
        $batchSize = $options['batch_size'] ?? 20;
        
        $sql = "SELECT id, frame, slot, port, onu_id, sn, name FROM huawei_onus WHERE olt_id = ? AND is_authorized = true";
        $params = [$oltId];
        
        if ($onuFilter && is_array($onuFilter) && !empty($onuFilter)) {
            $placeholders = implode(',', array_fill(0, count($onuFilter), '?'));
            $sql .= " AND id IN ({$placeholders})";
            $params = array_merge($params, $onuFilter);
        }
        
        $sql .= " ORDER BY slot, port, onu_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $onus = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($onus)) {
            return ['success' => false, 'error' => 'No authorized ONUs found', 'configured' => 0, 'failed' => 0];
        }
        
        $configured = 0;
        $failed = 0;
        $errors = [];
        $currentSlot = null;
        $batchCommands = [];
        
        foreach ($onus as $onu) {
            $frame = $onu['frame'] ?? 0;
            $slot = $onu['slot'];
            $port = $onu['port'];
            $onuId = $onu['onu_id'];
            
            if ($currentSlot !== $slot) {
                if (!empty($batchCommands)) {
                    $batchCommands[] = "quit";
                    $script = implode("\r\n", $batchCommands);
                    $result = $this->executeCommand($oltId, $script);
                    if (!$result['success']) {
                        $failed += count(array_filter($batchCommands, fn($c) => strpos($c, 'acs-url') !== false));
                    } else {
                        $configured += count(array_filter($batchCommands, fn($c) => strpos($c, 'acs-url') !== false));
                    }
                    $batchCommands = [];
                }
                $batchCommands[] = "interface gpon {$frame}/{$slot}";
                $currentSlot = $slot;
            }
            
            if ($tr069Vlan) {
                // Configure WAN with DHCP for TR-069 (no native-vlan on ETH port needed)
                $batchCommands[] = "ont ipconfig {$port} {$onuId} dhcp vlan {$tr069Vlan} priority 2";
            }
            $batchCommands[] = "ont tr069-server-config {$port} {$onuId} acs-url {$acsUrl}";
            $batchCommands[] = "ont tr069-server-config {$port} {$onuId} periodic-inform enable interval {$periodicInterval}";
            
            if (count($batchCommands) >= $batchSize * 4) {
                $batchCommands[] = "quit";
                $script = implode("\r\n", $batchCommands);
                $result = $this->executeCommand($oltId, $script);
                $cmdCount = count(array_filter($batchCommands, fn($c) => strpos($c, 'acs-url') !== false));
                if (!$result['success']) {
                    $failed += $cmdCount;
                    $errors[] = $result['error'] ?? 'Command failed';
                } else {
                    $configured += $cmdCount;
                }
                $batchCommands = [];
                $currentSlot = null;
            }
        }
        
        if (!empty($batchCommands)) {
            $batchCommands[] = "quit";
            $script = implode("\r\n", $batchCommands);
            $result = $this->executeCommand($oltId, $script);
            $cmdCount = count(array_filter($batchCommands, fn($c) => strpos($c, 'acs-url') !== false));
            if (!$result['success']) {
                $failed += $cmdCount;
                $errors[] = $result['error'] ?? 'Command failed';
            } else {
                $configured += $cmdCount;
            }
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'bulk_tr069_config',
            'status' => $failed === 0 ? 'success' : ($configured > 0 ? 'partial' : 'error'),
            'message' => "Bulk TR-069 config: {$configured} configured, {$failed} failed. ACS: {$acsUrl}",
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'configured' => $configured,
            'failed' => $failed,
            'total' => count($onus),
            'errors' => $errors
        ];
    }
    
    // ==================== Signal History & Uptime Tracking ====================
    
    public function recordSignalHistory(int $onuId, ?float $rxPower, ?float $txPower, string $status): void {
        $stmt = $this->db->prepare("
            INSERT INTO onu_signal_history (onu_id, rx_power, tx_power, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$onuId, $rxPower, $txPower, $status]);
    }
    
    public function getSignalHistory(int $onuId, int $hours = 24): array {
        $stmt = $this->db->prepare("
            SELECT rx_power, tx_power, status, recorded_at
            FROM onu_signal_history
            WHERE onu_id = ? AND recorded_at > NOW() - INTERVAL '{$hours} hours'
            ORDER BY recorded_at ASC
        ");
        $stmt->execute([$onuId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function trackStatusChange(int $onuId, string $newStatus): void {
        $stmt = $this->db->prepare("
            SELECT id, status, started_at FROM onu_uptime_log 
            WHERE onu_id = ? AND ended_at IS NULL 
            ORDER BY started_at DESC LIMIT 1
        ");
        $stmt->execute([$onuId]);
        $current = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($current && $current['status'] !== $newStatus) {
            $stmt = $this->db->prepare("
                UPDATE onu_uptime_log 
                SET ended_at = NOW(), 
                    duration_seconds = EXTRACT(EPOCH FROM (NOW() - started_at))::INTEGER
                WHERE id = ?
            ");
            $stmt->execute([$current['id']]);
            
            $stmt = $this->db->prepare("
                INSERT INTO onu_uptime_log (onu_id, status) VALUES (?, ?)
            ");
            $stmt->execute([$onuId, $newStatus]);
        } elseif (!$current) {
            $stmt = $this->db->prepare("
                INSERT INTO onu_uptime_log (onu_id, status) VALUES (?, ?)
            ");
            $stmt->execute([$onuId, $newStatus]);
        }
    }
    
    public function getUptimeStats(int $onuId, int $days = 7): array {
        $stmt = $this->db->prepare("
            SELECT status, SUM(COALESCE(duration_seconds, EXTRACT(EPOCH FROM (NOW() - started_at))::INTEGER)) as total_seconds
            FROM onu_uptime_log
            WHERE onu_id = ? AND started_at > NOW() - INTERVAL '{$days} days'
            GROUP BY status
        ");
        $stmt->execute([$onuId]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $stats = ['online' => 0, 'offline' => 0, 'los' => 0];
        foreach ($results as $row) {
            $stats[$row['status']] = (int)$row['total_seconds'];
        }
        $total = array_sum($stats);
        $stats['uptime_percent'] = $total > 0 ? round(($stats['online'] / $total) * 100, 2) : 0;
        
        return $stats;
    }
    
    public function getPortCapacity(int $oltId): array {
        $stmt = $this->db->prepare("
            SELECT slot, port, COUNT(*) as onu_count,
                   SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online_count,
                   SUM(CASE WHEN status = 'los' THEN 1 ELSE 0 END) as los_count
            FROM huawei_onus
            WHERE olt_id = ? AND is_authorized = TRUE
            GROUP BY slot, port
            ORDER BY slot, port
        ");
        $stmt->execute([$oltId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getSignalAlerts(float $rxThreshold = -28): array {
        $stmt = $this->db->prepare("
            SELECT o.*, ol.name as olt_name, c.name as customer_name, c.phone
            FROM huawei_onus o
            JOIN huawei_olts ol ON o.olt_id = ol.id
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.is_authorized = TRUE 
            AND (o.rx_power < ? OR o.rx_power > -8 OR o.status = 'los')
            ORDER BY o.rx_power ASC NULLS LAST
        ");
        $stmt->execute([$rxThreshold]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function exportONUsToCSV(int $oltId = null): array {
        $sql = "SELECT o.sn, o.name, o.description, ol.name as olt_name, 
                       o.frame, o.slot, o.port, o.onu_id, o.status, o.rx_power, o.tx_power,
                       o.distance, t.model as onu_type, c.name as customer_name, c.phone,
                       z.name as zone_name, o.created_at, o.updated_at
                FROM huawei_onus o
                JOIN huawei_olts ol ON o.olt_id = ol.id
                LEFT JOIN huawei_onu_types t ON o.onu_type_id = t.id
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN huawei_zones z ON o.zone_id = z.id
                WHERE o.is_authorized = TRUE";
        $params = [];
        if ($oltId) {
            $sql .= " AND o.olt_id = ?";
            $params[] = $oltId;
        }
        $sql .= " ORDER BY ol.name, o.slot, o.port, o.onu_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function bulkReboot(array $onuIds): array {
        $success = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($onuIds as $onuId) {
            $onu = $this->getONU($onuId);
            if (!$onu) {
                $failed++;
                $errors[] = "ONU {$onuId} not found";
                continue;
            }
            
            $result = $this->rebootONU($onuId);
            if ($result['success'] ?? false) {
                $success++;
            } else {
                $failed++;
                $errors[] = "ONU {$onu['sn']}: " . ($result['error'] ?? 'Failed');
            }
            usleep(200000);
        }
        
        return ['success' => $success, 'failed' => $failed, 'errors' => $errors];
    }
    
    public function bulkDelete(array $onuIds): array {
        $success = 0;
        $failed = 0;
        
        foreach ($onuIds as $onuId) {
            try {
                $this->deleteONU($onuId);
                $success++;
            } catch (\Exception $e) {
                $failed++;
            }
        }
        
        return ['success' => $success, 'failed' => $failed];
    }
    
    public function matchONUToCustomer(int $onuId, int $customerId): bool {
        $stmt = $this->db->prepare("UPDATE huawei_onus SET customer_id = ? WHERE id = ?");
        return $stmt->execute([$customerId, $onuId]);
    }
    
    public function findCustomersByPhone(string $phone): array {
        $stmt = $this->db->prepare("
            SELECT id, name, phone, email 
            FROM customers 
            WHERE phone LIKE ? OR secondary_phone LIKE ?
            LIMIT 20
        ");
        $search = "%{$phone}%";
        $stmt->execute([$search, $search]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function findUnlinkedONUs(): array {
        $stmt = $this->db->query("
            SELECT o.*, ol.name as olt_name
            FROM huawei_onus o
            JOIN huawei_olts ol ON o.olt_id = ol.id
            WHERE o.is_authorized = TRUE AND o.customer_id IS NULL
            ORDER BY o.created_at DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function createLOSTicket(int $onuId): ?int {
        $onu = $this->getONU($onuId);
        if (!$onu) return null;
        
        $customerId = $onu['customer_id'] ?? null;
        $title = "LOS Alert: ONU " . ($onu['name'] ?: $onu['sn']);
        $description = "Automatic ticket created due to Loss of Signal (LOS) on ONU.\n\n";
        $description .= "Serial Number: {$onu['sn']}\n";
        $description .= "Location: {$onu['frame']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_id']}\n";
        $description .= "Last RX Power: " . ($onu['rx_power'] ? $onu['rx_power'] . ' dBm' : 'N/A') . "\n";
        $description .= "OLT: " . ($onu['olt_name'] ?? 'Unknown');
        
        $stmt = $this->db->prepare("
            INSERT INTO tickets (title, description, customer_id, category, priority, status, created_at)
            VALUES (?, ?, ?, 'Network', 'High', 'Open', NOW())
            RETURNING id
        ");
        $stmt->execute([$title, $description, $customerId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['id'] ?? null;
    }
    
    public function getWANConfigFromGenieACS(string $deviceId): array {
        $stmt = $this->db->query("SELECT * FROM genieacs_config WHERE is_active = TRUE LIMIT 1");
        $config = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$config) {
            return ['success' => false, 'error' => 'GenieACS not configured', 'wans' => []];
        }
        
        $acsUrl = rtrim($config['acs_url'], '/');
        $apiUrl = preg_replace('/:\d+$/', ':7557', parse_url($acsUrl, PHP_URL_HOST));
        $apiUrl = 'http://' . $apiUrl;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl . '/devices/' . urlencode($deviceId),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);
        
        if (!empty($config['username']) && !empty($config['password'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $config['username'] . ':' . $config['password']);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            return ['success' => false, 'error' => 'Failed to fetch device from GenieACS', 'wans' => []];
        }
        
        $device = json_decode($response, true);
        if (!$device) {
            return ['success' => false, 'error' => 'Invalid response from GenieACS', 'wans' => []];
        }
        
        $wans = [];
        $wanPath = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice';
        
        if (isset($device[$wanPath])) {
            foreach ($device[$wanPath] as $wanKey => $wanData) {
                if (!is_array($wanData)) continue;
                
                if (isset($wanData['WANPPPConnection'])) {
                    foreach ($wanData['WANPPPConnection'] as $pppKey => $ppp) {
                        if (!is_array($ppp)) continue;
                        $wans[] = [
                            'name' => $ppp['Name']['_value'] ?? "PPPoE WAN {$wanKey}.{$pppKey}",
                            'type' => 'pppoe',
                            'connected' => ($ppp['ConnectionStatus']['_value'] ?? '') === 'Connected',
                            'ip' => $ppp['ExternalIPAddress']['_value'] ?? null,
                            'vlan' => $ppp['X_HW_VLAN']['_value'] ?? null,
                            'username' => $ppp['Username']['_value'] ?? null
                        ];
                    }
                }
                
                if (isset($wanData['WANIPConnection'])) {
                    foreach ($wanData['WANIPConnection'] as $ipKey => $ipConn) {
                        if (!is_array($ipConn)) continue;
                        $addrType = $ipConn['AddressingType']['_value'] ?? 'DHCP';
                        $wans[] = [
                            'name' => $ipConn['Name']['_value'] ?? "IP WAN {$wanKey}.{$ipKey}",
                            'type' => $addrType === 'Static' ? 'static' : 'dhcp',
                            'connected' => ($ipConn['ConnectionStatus']['_value'] ?? '') === 'Connected',
                            'ip' => $ipConn['ExternalIPAddress']['_value'] ?? null,
                            'vlan' => $ipConn['X_HW_VLAN']['_value'] ?? null
                        ];
                    }
                }
            }
        }
        
        return ['success' => true, 'wans' => $wans];
    }
    
    public function rebootONUViaTR069(string $deviceId): array {
        $stmt = $this->db->query("SELECT * FROM genieacs_config WHERE is_active = TRUE LIMIT 1");
        $config = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$config) {
            return ['success' => false, 'error' => 'GenieACS not configured'];
        }
        
        $acsUrl = rtrim($config['acs_url'], '/');
        $apiUrl = preg_replace('/:\d+$/', ':7557', parse_url($acsUrl, PHP_URL_HOST));
        $apiUrl = 'http://' . $apiUrl;
        
        $task = [
            'name' => 'reboot'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl . '/devices/' . urlencode($deviceId) . '/tasks?connection_request',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($task),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json']
        ]);
        
        if (!empty($config['username']) && !empty($config['password'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $config['username'] . ':' . $config['password']);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'message' => 'Reboot command sent'];
        }
        
        return ['success' => false, 'error' => 'Failed to send reboot command. HTTP: ' . $httpCode];
    }
}
