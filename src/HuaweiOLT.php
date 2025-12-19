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
        return getenv('SESSION_SECRET') ?: 'huawei-olt-default-key-2024';
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
                                     snmp_community, snmp_version, snmp_port, vendor, model, location, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['ip_address'],
            $data['port'] ?? 23,
            $data['connection_type'] ?? 'telnet',
            $data['username'] ?? '',
            !empty($data['password']) ? $this->encrypt($data['password']) : '',
            $data['snmp_community'] ?? 'public',
            $data['snmp_version'] ?? 'v2c',
            $data['snmp_port'] ?? 161,
            $data['vendor'] ?? 'Huawei',
            $data['model'] ?? '',
            $data['location'] ?? '',
            !empty($data['is_active'])
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateOLT(int $id, array $data): bool {
        $fields = ['name', 'ip_address', 'port', 'connection_type', 'username', 
                   'snmp_community', 'snmp_version', 'snmp_port', 'vendor', 'model', 'location', 'is_active'];
        $updates = [];
        $params = [];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
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
            return $this->testSNMPConnection($olt['ip_address'], $olt['snmp_community'], $olt['snmp_port']);
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
            $params[] = $filters['is_authorized'];
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
                                     is_authorized, auth_type, password)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (olt_id, sn) DO UPDATE SET
                name = EXCLUDED.name, description = EXCLUDED.description, frame = EXCLUDED.frame,
                slot = EXCLUDED.slot, port = EXCLUDED.port, onu_id = EXCLUDED.onu_id,
                onu_type = EXCLUDED.onu_type, status = EXCLUDED.status, updated_at = CURRENT_TIMESTAMP
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
            $data['is_authorized'] ?? false,
            $data['auth_type'] ?? 'sn',
            $data['password'] ?? ''
        ]);
        return (int)$stmt->fetchColumn();
    }
    
    public function updateONU(int $id, array $data): bool {
        $fields = ['customer_id', 'name', 'description', 'frame', 'slot', 'port', 'onu_id', 'onu_type',
                   'mac_address', 'status', 'rx_power', 'tx_power', 'distance', 'service_profile_id',
                   'line_profile', 'srv_profile', 'is_authorized', 'firmware_version', 'ip_address',
                   'config_state', 'run_state', 'auth_type', 'password', 'last_down_cause'];
        $updates = [];
        $params = [];
        
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
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
            $data['is_default'] ?? false,
            $data['is_active'] ?? true
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateServiceProfile(int $id, array $data): bool {
        $fields = ['name', 'description', 'profile_type', 'vlan_id', 'vlan_mode', 'speed_profile_up',
                   'speed_profile_down', 'qos_profile', 'gem_port', 'tcont_profile', 'line_profile',
                   'srv_profile', 'native_vlan', 'additional_config', 'is_default', 'is_active'];
        $updates = [];
        $params = [];
        
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
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
    
    // ==================== ONU Operations ====================
    
    public function executeCommand(int $oltId, string $command): array {
        $olt = $this->getOLT($oltId);
        if (!$olt) {
            return ['success' => false, 'message' => 'OLT not found'];
        }
        
        $password = !empty($olt['password_encrypted']) ? $this->decrypt($olt['password_encrypted']) : '';
        
        $this->addLog([
            'olt_id' => $oltId,
            'action' => 'command',
            'status' => 'pending',
            'command_sent' => $command,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        if ($olt['connection_type'] === 'telnet') {
            return $this->executeTelnetCommand($olt['ip_address'], $olt['port'], $olt['username'], $password, $command);
        } elseif ($olt['connection_type'] === 'ssh') {
            return $this->executeSSHCommand($olt['ip_address'], $olt['port'], $olt['username'], $password, $command);
        }
        
        return ['success' => false, 'message' => 'Unsupported connection type for commands'];
    }
    
    private function executeTelnetCommand(string $ip, int $port, string $username, string $password, string $command): array {
        $timeout = 10;
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        
        if (!$socket) {
            return ['success' => false, 'message' => "Connection failed: {$errstr}"];
        }
        
        stream_set_timeout($socket, $timeout);
        
        $response = '';
        $response .= fread($socket, 4096);
        
        if (strpos($response, 'sername') !== false || strpos($response, 'ogin') !== false) {
            fwrite($socket, $username . "\r\n");
            usleep(500000);
            $response .= fread($socket, 4096);
        }
        
        if (strpos($response, 'assword') !== false) {
            fwrite($socket, $password . "\r\n");
            usleep(500000);
            $response .= fread($socket, 4096);
        }
        
        fwrite($socket, $command . "\r\n");
        usleep(1000000);
        
        $output = '';
        while (!feof($socket)) {
            $chunk = fread($socket, 4096);
            if (empty($chunk)) break;
            $output .= $chunk;
            if (strlen($output) > 65536) break;
        }
        
        fclose($socket);
        
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
    
    public function authorizeONU(int $onuId, int $profileId): array {
        $onu = $this->getONU($onuId);
        $profile = $this->getServiceProfile($profileId);
        
        if (!$onu || !$profile) {
            return ['success' => false, 'message' => 'ONU or Profile not found'];
        }
        
        $command = "ont add {$onu['frame']}/{$onu['slot']}/{$onu['port']} sn-auth {$onu['sn']} omci ont-lineprofile-id {$profile['line_profile']} ont-srvprofile-id {$profile['srv_profile']}";
        
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
                'message' => "ONU {$onu['sn']} authorized with profile {$profile['name']}",
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
}
