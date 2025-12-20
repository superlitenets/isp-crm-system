<?php
namespace App;

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
        
        if (!function_exists('snmpget')) {
            return ['success' => false, 'error' => 'PHP SNMP extension not installed'];
        }
        
        $community = $olt['snmp_community'] ?? $olt['snmp_read_community'] ?? 'public';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        
        $result = @snmpget($host, $community, $oid, $timeout, $retries);
        
        if ($result === false) {
            return ['success' => false, 'error' => 'SNMP read failed for OID: ' . $oid];
        }
        
        return ['success' => true, 'value' => $result, 'oid' => $oid];
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
        
        if (!function_exists('snmpwalk')) {
            return ['success' => false, 'error' => 'PHP SNMP extension not installed'];
        }
        
        $community = $olt['snmp_community'] ?? $olt['snmp_read_community'] ?? 'public';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        
        $result = @snmprealwalk($host, $community, $oid, $timeout, $retries);
        
        if ($result === false) {
            return ['success' => false, 'error' => 'SNMP walk failed for OID: ' . $oid];
        }
        
        return ['success' => true, 'data' => $result, 'count' => count($result)];
    }
    
    public function getOLTSystemInfoViaSNMP(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        if (!function_exists('snmpget')) {
            return ['success' => false, 'error' => 'PHP SNMP extension not installed'];
        }
        
        $community = $olt['snmp_community'] ?? $olt['snmp_read_community'] ?? 'public';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        $timeout = 2000000;
        $retries = 2;
        
        $systemOIDs = [
            'sysDescr' => '1.3.6.1.2.1.1.1.0',
            'sysObjectID' => '1.3.6.1.2.1.1.2.0',
            'sysUpTime' => '1.3.6.1.2.1.1.3.0',
            'sysContact' => '1.3.6.1.2.1.1.4.0',
            'sysName' => '1.3.6.1.2.1.1.5.0',
            'sysLocation' => '1.3.6.1.2.1.1.6.0',
        ];
        
        $info = [];
        foreach ($systemOIDs as $name => $oid) {
            $result = @snmpget($host, $community, $oid, $timeout, $retries);
            $info[$name] = $result !== false ? $this->cleanSnmpValue($result) : null;
        }
        
        $this->db->prepare("UPDATE huawei_olts SET last_sync_at = CURRENT_TIMESTAMP, last_status = 'online' WHERE id = ?")->execute([$oltId]);
        
        return ['success' => true, 'info' => $info];
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
        $statuses = @snmprealwalk($host, $community, $huaweiONTStatusBase, 10000000, 2);
        $descriptions = @snmprealwalk($host, $community, $huaweiONTDescBase, 10000000, 2);
        
        if ($serials === false) {
            return ['success' => false, 'error' => 'Failed to get ONU list via SNMP'];
        }
        
        $onus = [];
        foreach ($serials as $oid => $serial) {
            // Index format: frame.slot.port.onu_id (e.g., 0.1.3.12)
            $indexPart = substr($oid, strlen($huaweiONTSerialBase) + 1);
            $parts = explode('.', $indexPart);
            
            // MA5680T uses 4-part index: frame.slot.port.onu_id
            if (count($parts) >= 4) {
                $frame = (int)$parts[0];
                $slot = (int)$parts[1];
                $port = (int)$parts[2];
                $onuId = (int)$parts[3];
                
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
        }
        
        return ['success' => true, 'onus' => $onus, 'count' => count($onus)];
    }
    
    public function getONUOpticalInfoViaSNMP(int $oltId, int $frame, int $slot, int $port, int $onuId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        if (!function_exists('snmpget')) {
            return ['success' => false, 'error' => 'PHP SNMP extension not installed'];
        }
        
        // Use snmp_community field from database
        $community = $olt['snmp_community'] ?? 'public';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        
        // Configure SNMP for plain values
        if (function_exists('snmp_set_quick_print')) {
            snmp_set_quick_print(true);
        }
        if (function_exists('snmp_set_valueretrieval')) {
            snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
        }
        
        // Huawei ONU OIDs (hwGponOnuInfo table .43.1.x)
        // Index format: frame.slot.pon.onu (e.g., 0.1.3.12)
        // .43.1.3 = RX Power, .43.1.4 = TX Power (divide by 10 for 0.1 dBm units)
        $indexSuffix = "{$frame}.{$slot}.{$port}.{$onuId}";
        
        $rxPowerOid = "1.3.6.1.4.1.2011.6.128.1.1.2.43.1.3.{$indexSuffix}";
        $txPowerOid = "1.3.6.1.4.1.2011.6.128.1.1.2.43.1.4.{$indexSuffix}";
        
        $rxPower = @snmpget($host, $community, $rxPowerOid, 2000000, 2);
        $txPower = @snmpget($host, $community, $txPowerOid, 2000000, 2);
        
        // Parse values (divide by 10 for 0.1 dBm units)
        $rxValue = $this->parseOpticalPowerDiv10($rxPower);
        $txValue = $this->parseOpticalPowerDiv10($txPower);
        
        return [
            'success' => true,
            'optical' => [
                'rx_power' => $rxValue,
                'tx_power' => $txValue,
            ],
            'debug' => [
                'index' => $indexSuffix,
                'rx_oid' => $rxPowerOid,
                'tx_oid' => $txPowerOid,
                'rx_raw' => $rxPower,
                'tx_raw' => $txPower,
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
            
            $data = [
                'olt_id' => $oltId,
                'sn' => $onu['sn'],
                'frame' => $onu['frame'],
                'slot' => $onu['slot'],
                'port' => $onu['port'],
                'onu_id' => $onu['onu_id'],
                'status' => $onu['status'],
                'description' => $onu['description'],
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
        // Get ONU list from OLT via SNMP (has correct frame/slot/port/onu_id)
        $snmpResult = $this->getONUListViaSNMP($oltId);
        if (!$snmpResult['success']) {
            return $snmpResult;
        }
        
        if (empty($snmpResult['onus'])) {
            return ['success' => false, 'error' => 'No ONUs found via SNMP. Check OLT SNMP configuration.'];
        }
        
        // Build a map by serial number for quick lookup
        $snmpOnuMap = [];
        foreach ($snmpResult['onus'] as $onu) {
            $sn = strtoupper(trim($onu['sn'] ?? ''));
            if (!empty($sn)) {
                $snmpOnuMap[$sn] = $onu;
            }
        }
        
        // Get all existing ONUs in huawei_onus table for this OLT
        $existingOnus = $this->getONUs(['olt_id' => $oltId]);
        
        $updated = 0;
        $notFound = 0;
        $errors = [];
        
        foreach ($existingOnus as $existing) {
            $sn = strtoupper(trim($existing['sn'] ?? ''));
            if (empty($sn)) continue;
            
            if (isset($snmpOnuMap[$sn])) {
                $snmpData = $snmpOnuMap[$sn];
                
                // Update location data from SNMP
                try {
                    $stmt = $this->db->prepare("
                        UPDATE huawei_onus 
                        SET frame = ?, slot = ?, port = ?, onu_id = ?, status = ?, updated_at = CURRENT_TIMESTAMP
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
            'errors' => $errors
        ];
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
                            $this->updateONUOpticalInDB($onu['id'], $data['rx_power'] ?? null, $data['tx_power'] ?? null);
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
        
        // Use snmp_community field from database
        $community = $olt['snmp_community'] ?? 'public';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        
        // Huawei ONU OIDs (hwGponOnuInfo table .43.1.x)
        // Index format: frame.slot.pon.onu (e.g., 0.1.3.12)
        $oids = [
            'rx' => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.3',  // RX Power (divide by 10)
            'tx' => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.4',  // TX Power (divide by 10)
        ];
        
        $results = [];
        
        // Configure SNMP for plain values
        if (function_exists('snmp_set_quick_print')) {
            snmp_set_quick_print(true);
        }
        if (function_exists('snmp_set_valueretrieval')) {
            snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
        }
        
        // Walk RX power table
        $rxResults = @snmpwalk($host, $community, $oids['rx']);
        if ($rxResults !== false) {
            foreach ($rxResults as $index => $value) {
                $key = $this->parseHuaweiOnuIndex($index, $oids['rx']);
                if ($key) {
                    $power = $this->parseOpticalPowerDiv10($value);
                    if ($power !== null) {
                        if (!isset($results[$key])) {
                            $results[$key] = [];
                        }
                        $results[$key]['rx_power'] = $power;
                    }
                }
            }
        }
        
        // Walk TX power table
        $txResults = @snmpwalk($host, $community, $oids['tx']);
        if ($txResults !== false) {
            foreach ($txResults as $index => $value) {
                $key = $this->parseHuaweiOnuIndex($index, $oids['tx']);
                if ($key) {
                    $power = $this->parseOpticalPowerDiv10($value);
                    if ($power !== null) {
                        if (!isset($results[$key])) {
                            $results[$key] = [];
                        }
                        $results[$key]['tx_power'] = $power;
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
    
    public function refreshONUOptical(int $onuId): array {
        $onu = $this->getONU($onuId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
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
        
        // SNMP is the only method for optical power
        if (!function_exists('snmpget')) {
            return ['success' => false, 'error' => 'PHP SNMP extension not installed. Install with: apt install php-snmp'];
        }
        
        $optical = $this->getONUOpticalInfoViaSNMP(
            $onu['olt_id'],
            $frame,
            $slot,
            $port,
            $onuIdNum
        );
        
        if (!$optical['success']) {
            return ['success' => false, 'error' => $optical['error'] ?? 'SNMP query failed'];
        }
        
        if ($optical['optical']['rx_power'] === null && $optical['optical']['tx_power'] === null) {
            $debug = $optical['debug'] ?? [];
            $debugStr = json_encode($debug);
            return ['success' => false, 'error' => "No power data. Debug: {$debugStr}"];
        }
        
        $this->updateONUOpticalInDB($onuId, $optical['optical']['rx_power'], $optical['optical']['tx_power']);
        
        return [
            'success' => true,
            'rx_power' => $optical['optical']['rx_power'],
            'tx_power' => $optical['optical']['tx_power']
        ];
    }
    
    private function updateONUOpticalInDB(int $onuId, ?float $rxPower, ?float $txPower): void {
        $stmt = $this->db->prepare("
            UPDATE huawei_onus 
            SET rx_power = ?, tx_power = ?, optical_updated_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$rxPower, $txPower, $onuId]);
    }
    
    public function getONUOpticalInfoViaCLI(int $oltId, int $frame, int $slot, int $port, int $onuId): array {
        // Use CLI command to get optical power: display ont optical-info <frame>/<slot>/<port> <onu_id>
        $command = "display ont optical-info {$frame}/{$slot}/{$port} {$onuId}";
        $result = $this->executeCommand($oltId, $command);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['message'] ?? 'CLI command failed'];
        }
        
        $output = $result['output'] ?? '';
        $rxPower = null;
        $txPower = null;
        
        // Parse CLI output for optical power values
        // Huawei format: "Rx optical power(dBm)    : -18.50"
        //                "Tx optical power(dBm)    : 2.35"
        if (preg_match('/Rx\s+optical\s+power\s*\(?dBm\)?\s*:\s*([-\d.]+)/i', $output, $m)) {
            $rxPower = (float)$m[1];
        }
        if (preg_match('/Tx\s+optical\s+power\s*\(?dBm\)?\s*:\s*([-\d.]+)/i', $output, $m)) {
            $txPower = (float)$m[1];
        }
        
        // Alternative format: "OLT Rx ONT optical power(dBm) : -18.50"
        if ($rxPower === null && preg_match('/OLT\s+Rx.*power\s*\(?dBm\)?\s*:\s*([-\d.]+)/i', $output, $m)) {
            $rxPower = (float)$m[1];
        }
        
        return [
            'success' => true,
            'optical' => [
                'rx_power' => $rxPower,
                'tx_power' => $txPower,
            ],
            'raw_output' => $output
        ];
    }
    
    public function discoverUnconfiguredONUs(int $oltId): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'error' => 'OLT not found'];
        }
        
        if (!function_exists('snmpwalk')) {
            return ['success' => false, 'error' => 'PHP SNMP extension not installed'];
        }
        
        $community = $olt['snmp_community'] ?? $olt['snmp_read_community'] ?? 'public';
        $host = $olt['ip_address'] . ':' . ($olt['snmp_port'] ?? 161);
        
        $huaweiAutofindSerialBase = '1.3.6.1.4.1.2011.6.128.1.1.2.45.1.3';
        $huaweiAutofindTypeBase = '1.3.6.1.4.1.2011.6.128.1.1.2.45.1.5';
        $huaweiAutofindPortBase = '1.3.6.1.4.1.2011.6.128.1.1.2.45.1.1';
        
        $serials = @snmprealwalk($host, $community, $huaweiAutofindSerialBase, 10000000, 2);
        $types = @snmprealwalk($host, $community, $huaweiAutofindTypeBase, 10000000, 2);
        
        if ($serials === false) {
            return ['success' => false, 'error' => 'Failed to discover unconfigured ONUs via SNMP'];
        }
        
        $unconfigured = [];
        $added = 0;
        
        foreach ($serials as $oid => $serial) {
            $indexPart = substr($oid, strlen($huaweiAutofindSerialBase) + 1);
            $parts = explode('.', $indexPart);
            
            if (count($parts) >= 2) {
                $portIndex = (int)$parts[0];
                $autofindId = (int)$parts[1];
                
                $frame = 0;
                $slot = floor($portIndex / 100000000);
                $port = ($portIndex % 100000000) / 1000000;
                
                $typeOid = $huaweiAutofindTypeBase . '.' . $indexPart;
                $onuType = isset($types[$typeOid]) ? $this->cleanSnmpValue($types[$typeOid]) : '';
                
                $sn = $this->cleanSnmpValue($serial);
                
                $existing = $this->getONUBySN($sn);
                if (!$existing) {
                    $this->addONU([
                        'olt_id' => $oltId,
                        'sn' => $sn,
                        'frame' => (int)$frame,
                        'slot' => (int)$slot,
                        'port' => (int)$port,
                        'onu_type' => $onuType,
                        'status' => 'unconfigured',
                        'is_authorized' => false,
                    ]);
                    $added++;
                }
                
                $unconfigured[] = [
                    'sn' => $sn,
                    'frame' => (int)$frame,
                    'slot' => (int)$slot,
                    'port' => (int)$port,
                    'onu_type' => $onuType,
                    'autofind_id' => $autofindId
                ];
            }
        }
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'discover_unconfigured',
            'status' => 'success',
            'message' => "Found " . count($unconfigured) . " unconfigured ONUs, added {$added} new",
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'success' => true,
            'onus' => $unconfigured,
            'count' => count($unconfigured),
            'added' => $added
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
    
    private function parseOpticalPower($value): ?float {
        // Huawei returns power in 0.1 dBm units, divide by 10
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
    
    public function addServiceProfile(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO huawei_service_profiles (name, description, profile_type, vlan_id, vlan_mode,
                                                  speed_profile_up, speed_profile_down, qos_profile, gem_port,
                                                  tcont_profile, line_profile, srv_profile, native_vlan,
                                                  additional_config, is_default, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $this->castBoolean($data['is_active'] ?? true)
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateServiceProfile(int $id, array $data): bool {
        $fields = ['name', 'description', 'profile_type', 'vlan_id', 'vlan_mode', 'speed_profile_up',
                   'speed_profile_down', 'qos_profile', 'gem_port', 'tcont_profile', 'line_profile',
                   'srv_profile', 'native_vlan', 'additional_config'];
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
        
        stream_set_timeout($socket, $timeout);
        stream_set_blocking($socket, false);
        
        $response = '';
        $startTime = time();
        
        while ((time() - $startTime) < 5) {
            $chunk = @fread($socket, 4096);
            if ($chunk) {
                $response .= $chunk;
            }
            if (stripos($response, 'name') !== false || stripos($response, 'login') !== false) {
                break;
            }
            usleep(100000);
        }
        
        if (stripos($response, 'name') !== false || stripos($response, 'login') !== false) {
            fwrite($socket, $username . "\r\n");
            usleep(1000000);
            
            $startTime = time();
            while ((time() - $startTime) < 5) {
                $chunk = @fread($socket, 4096);
                if ($chunk) {
                    $response .= $chunk;
                }
                if (stripos($response, 'assword') !== false) {
                    break;
                }
                usleep(100000);
            }
        }
        
        if (stripos($response, 'assword') !== false) {
            fwrite($socket, $password . "\r\n");
            usleep(2000000);
            
            $startTime = time();
            while ((time() - $startTime) < 5) {
                $chunk = @fread($socket, 4096);
                if ($chunk) {
                    $response .= $chunk;
                }
                if (stripos($response, '>') !== false || stripos($response, '#') !== false) {
                    break;
                }
                usleep(100000);
            }
        }
        
        fwrite($socket, "enable\r\n");
        usleep(500000);
        @fread($socket, 4096);
        
        fwrite($socket, "config\r\n");
        usleep(500000);
        @fread($socket, 4096);
        
        // Disable pagination with multiple methods for compatibility
        fwrite($socket, "screen-length 0 temporary\r\n");
        usleep(300000);
        @fread($socket, 4096);
        
        fwrite($socket, "scroll 512\r\n");
        usleep(300000);
        @fread($socket, 4096);
        
        fwrite($socket, $command . "\r\n");
        usleep(2000000);
        
        $output = '';
        $startTime = time();
        stream_set_blocking($socket, false);
        
        while ((time() - $startTime) < 20) {
            $chunk = @fread($socket, 8192);
            if ($chunk) {
                $output .= $chunk;
                if (strlen($output) > 65536) break;
                
                // Handle "---- More ----" pagination prompts
                if (preg_match('/----\s*More\s*----/i', $chunk)) {
                    fwrite($socket, " "); // Send space to continue
                    usleep(500000);
                    continue;
                }
                
                // Handle Huawei command prompts like "{ <cr>|... }:" - send Enter
                if (preg_match('/\}\s*:\s*$/', $output)) {
                    fwrite($socket, "\r\n"); // Send Enter to accept default/show all
                    usleep(1000000);
                    continue;
                }
                
                if (preg_match('/[>#]\s*$/', $output)) {
                    usleep(500000);
                    $extra = @fread($socket, 4096);
                    if (empty($extra)) break;
                    $output .= $extra;
                }
            }
            usleep(100000);
        }
        
        fwrite($socket, "quit\r\n");
        usleep(200000);
        fwrite($socket, "quit\r\n");
        usleep(200000);
        
        fclose($socket);
        
        $output = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $output);
        $output = preg_replace('/---- More.*?----/', '', $output);
        
        return [
            'success' => true,
            'message' => 'Command executed',
            'output' => $output
        ];
    }
    
    private function executeSSHCommand(string $ip, int $port, string $username, string $password, string $command): array {
        if (!function_exists('ssh2_connect')) {
            return ['success' => false, 'message' => 'SSH extension not available'];
        }
        
        $connection = @ssh2_connect($ip, $port);
        if (!$connection) {
            return ['success' => false, 'message' => 'SSH connection failed'];
        }
        
        if (!@ssh2_auth_password($connection, $username, $password)) {
            return ['success' => false, 'message' => 'SSH authentication failed'];
        }
        
        $stream = ssh2_exec($connection, $command);
        if (!$stream) {
            return ['success' => false, 'message' => 'Command execution failed'];
        }
        
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);
        
        return [
            'success' => true,
            'message' => 'Command executed',
            'output' => $output
        ];
    }
    
    public function authorizeONU(int $onuId, int $profileId, string $authMethod = 'sn', string $loid = '', string $loidPassword = ''): array {
        $onu = $this->getONU($onuId);
        $profile = $this->getServiceProfile($profileId);
        
        if (!$onu || !$profile) {
            return ['success' => false, 'message' => 'ONU or Profile not found'];
        }
        
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'];
        $port = $onu['port'];
        
        // Build command based on authentication method (Huawei MA5600/MA5800 syntax)
        switch ($authMethod) {
            case 'loid':
                if (empty($loid)) {
                    return ['success' => false, 'message' => 'LOID value is required for LOID authentication'];
                }
                // Huawei syntax: ont add 0/1/0 loid-auth loid <loid> [password <pwd>]
                $authPart = "loid-auth loid {$loid}";
                if (!empty($loidPassword)) {
                    $authPart .= " password {$loidPassword}";
                }
                break;
            case 'mac':
                // MAC auth requires a valid MAC address stored in the ONU record
                // Huawei syntax: ont add 0/1/0 mac-auth mac xxxx-xxxx-xxxx
                $mac = $onu['mac_address'] ?? '';
                if (empty($mac)) {
                    return ['success' => false, 'message' => 'MAC address not available for this ONU. Please update the ONU record with a valid MAC address first.'];
                }
                // Validate MAC format and convert to Huawei format (xxxx-xxxx-xxxx)
                $cleanMac = preg_replace('/[^a-fA-F0-9]/', '', $mac);
                if (strlen($cleanMac) !== 12) {
                    return ['success' => false, 'message' => 'Invalid MAC address format. Expected 12 hex characters.'];
                }
                $huaweiMac = strtolower(substr($cleanMac, 0, 4) . '-' . substr($cleanMac, 4, 4) . '-' . substr($cleanMac, 8, 4));
                $authPart = "mac-auth mac {$huaweiMac}";
                break;
            case 'sn':
            default:
                // Huawei syntax: ont add 0/1/0 sn-auth <serial-number>
                $authPart = "sn-auth {$onu['sn']}";
                break;
        }
        
        $command = "ont add {$frame}/{$slot}/{$port} {$authPart} omci ont-lineprofile-id {$profile['line_profile']} ont-srvprofile-id {$profile['srv_profile']}";
        
        $result = $this->executeCommand($onu['olt_id'], $command);
        
        if ($result['success']) {
            $this->updateONU($onuId, [
                'is_authorized' => true,
                'service_profile_id' => $profileId,
                'line_profile' => $profile['line_profile'],
                'srv_profile' => $profile['srv_profile']
            ]);
            
            $this->addLog([
                'olt_id' => $onu['olt_id'],
                'onu_id' => $onuId,
                'action' => 'authorize',
                'status' => 'success',
                'message' => "ONU {$onu['sn']} authorized with profile {$profile['name']} using {$authMethod} auth",
                'command_sent' => $command,
                'command_response' => $result['output'] ?? '',
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
        }
        
        return $result;
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
    
    public function createVLAN(int $oltId, int $vlanId, string $description = '', string $type = 'smart'): array {
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
        
        // Also sync the new VLAN to local cache
        if ($result['success']) {
            $stmt = $this->db->prepare("
                INSERT INTO huawei_vlans (olt_id, vlan_id, vlan_type, description, updated_at)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (olt_id, vlan_id) DO UPDATE SET
                    vlan_type = EXCLUDED.vlan_type,
                    description = EXCLUDED.description,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$oltId, $vlanId, $type, $description]);
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
        
        // Parse port name (e.g., "0/19/0" -> frame=0, slot=19, port=0)
        $parts = explode('/', $portName);
        if (count($parts) !== 3) {
            return ['success' => false, 'message' => 'Invalid port format'];
        }
        
        $frame = $parts[0];
        $slot = $parts[1];
        $port = $parts[2];
        
        // Huawei command to add VLAN to uplink port
        $command = "port vlan {$vlanId} {$frame}/{$slot}";
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
    
    public function getONUDetailedInfo(int $oltId, int $frame, int $slot, int $port, int $onuId): array {
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
