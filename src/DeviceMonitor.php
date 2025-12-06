<?php

namespace App;

/**
 * Device Monitoring Class
 * Supports SNMP and Telnet for network device management
 */

class DeviceMonitor {
    private $db;
    
    // Common SNMP OIDs
    const OID_SYSTEM_DESCR = '1.3.6.1.2.1.1.1.0';
    const OID_SYSTEM_UPTIME = '1.3.6.1.2.1.1.3.0';
    const OID_SYSTEM_NAME = '1.3.6.1.2.1.1.5.0';
    const OID_SYSTEM_LOCATION = '1.3.6.1.2.1.1.6.0';
    const OID_IF_TABLE = '1.3.6.1.2.1.2.2.1';
    const OID_IF_DESCR = '1.3.6.1.2.1.2.2.1.2';
    const OID_IF_OPER_STATUS = '1.3.6.1.2.1.2.2.1.8';
    const OID_IF_IN_OCTETS = '1.3.6.1.2.1.2.2.1.10';
    const OID_IF_OUT_OCTETS = '1.3.6.1.2.1.2.2.1.16';
    
    // Huawei OLT specific OIDs
    const OID_HUAWEI_ONU_STATUS = '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.15';
    const OID_HUAWEI_ONU_DISTANCE = '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.20';
    const OID_HUAWEI_ONU_RX_POWER = '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.4';
    const OID_HUAWEI_ONU_TX_POWER = '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.5';
    
    // ZTE OLT specific OIDs
    const OID_ZTE_ONU_STATUS = '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.2';
    const OID_ZTE_ONU_RX_POWER = '1.3.6.1.4.1.3902.1082.500.10.2.3.8.1.2';
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Initialize database tables for device monitoring
     */
    public function initializeTables() {
        // Devices table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS network_devices (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                device_type VARCHAR(50) NOT NULL DEFAULT 'olt',
                vendor VARCHAR(50),
                model VARCHAR(100),
                ip_address VARCHAR(45) NOT NULL,
                snmp_version VARCHAR(10) DEFAULT 'v2c',
                snmp_community VARCHAR(100) DEFAULT 'public',
                snmp_port INTEGER DEFAULT 161,
                snmpv3_username VARCHAR(100),
                snmpv3_auth_protocol VARCHAR(20),
                snmpv3_auth_password VARCHAR(255),
                snmpv3_priv_protocol VARCHAR(20),
                snmpv3_priv_password VARCHAR(255),
                telnet_username VARCHAR(100),
                telnet_password VARCHAR(255),
                telnet_port INTEGER DEFAULT 23,
                ssh_enabled BOOLEAN DEFAULT FALSE,
                ssh_port INTEGER DEFAULT 22,
                location VARCHAR(255),
                status VARCHAR(20) DEFAULT 'unknown',
                last_polled TIMESTAMP,
                poll_interval INTEGER DEFAULT 300,
                enabled BOOLEAN DEFAULT TRUE,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Device interfaces table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS device_interfaces (
                id SERIAL PRIMARY KEY,
                device_id INTEGER REFERENCES network_devices(id) ON DELETE CASCADE,
                if_index INTEGER NOT NULL,
                if_name VARCHAR(100),
                if_descr VARCHAR(255),
                if_type VARCHAR(50),
                if_speed BIGINT,
                if_status VARCHAR(20),
                in_octets BIGINT DEFAULT 0,
                out_octets BIGINT DEFAULT 0,
                in_errors BIGINT DEFAULT 0,
                out_errors BIGINT DEFAULT 0,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(device_id, if_index)
            )
        ");
        
        // ONU/ONT table for fiber networks
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS device_onus (
                id SERIAL PRIMARY KEY,
                device_id INTEGER REFERENCES network_devices(id) ON DELETE CASCADE,
                onu_id VARCHAR(50) NOT NULL,
                serial_number VARCHAR(50),
                mac_address VARCHAR(17),
                pon_port VARCHAR(20),
                slot INTEGER,
                port INTEGER,
                onu_index INTEGER,
                customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
                status VARCHAR(20) DEFAULT 'unknown',
                rx_power DECIMAL(10,2),
                tx_power DECIMAL(10,2),
                distance INTEGER,
                description VARCHAR(255),
                profile VARCHAR(100),
                last_online TIMESTAMP,
                last_offline TIMESTAMP,
                last_polled TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(device_id, onu_id)
            )
        ");
        
        // Monitoring history table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS device_monitoring_log (
                id SERIAL PRIMARY KEY,
                device_id INTEGER REFERENCES network_devices(id) ON DELETE CASCADE,
                metric_type VARCHAR(50) NOT NULL,
                metric_name VARCHAR(100),
                metric_value TEXT,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create indexes
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_device_onus_customer ON device_onus(customer_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_device_onus_status ON device_onus(status)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_monitoring_log_device ON device_monitoring_log(device_id, recorded_at)");
        
        return true;
    }
    
    /**
     * Add a new network device
     */
    public function addDevice($data) {
        $stmt = $this->db->prepare("
            INSERT INTO network_devices (
                name, device_type, vendor, model, ip_address, 
                snmp_version, snmp_community, snmp_port,
                snmpv3_username, snmpv3_auth_protocol, snmpv3_auth_password,
                snmpv3_priv_protocol, snmpv3_priv_password,
                telnet_username, telnet_password, telnet_port,
                ssh_enabled, ssh_port, location, poll_interval, notes
            ) VALUES (
                :name, :device_type, :vendor, :model, :ip_address,
                :snmp_version, :snmp_community, :snmp_port,
                :snmpv3_username, :snmpv3_auth_protocol, :snmpv3_auth_password,
                :snmpv3_priv_protocol, :snmpv3_priv_password,
                :telnet_username, :telnet_password, :telnet_port,
                :ssh_enabled, :ssh_port, :location, :poll_interval, :notes
            )
            RETURNING id
        ");
        
        $stmt->execute([
            ':name' => $data['name'],
            ':device_type' => $data['device_type'] ?? 'olt',
            ':vendor' => $data['vendor'] ?? null,
            ':model' => $data['model'] ?? null,
            ':ip_address' => $data['ip_address'],
            ':snmp_version' => $data['snmp_version'] ?? 'v2c',
            ':snmp_community' => $data['snmp_community'] ?? 'public',
            ':snmp_port' => $data['snmp_port'] ?? 161,
            ':snmpv3_username' => $data['snmpv3_username'] ?? null,
            ':snmpv3_auth_protocol' => $data['snmpv3_auth_protocol'] ?? null,
            ':snmpv3_auth_password' => $data['snmpv3_auth_password'] ?? null,
            ':snmpv3_priv_protocol' => $data['snmpv3_priv_protocol'] ?? null,
            ':snmpv3_priv_password' => $data['snmpv3_priv_password'] ?? null,
            ':telnet_username' => $data['telnet_username'] ?? null,
            ':telnet_password' => $data['telnet_password'] ?? null,
            ':telnet_port' => $data['telnet_port'] ?? 23,
            ':ssh_enabled' => $data['ssh_enabled'] ?? false,
            ':ssh_port' => $data['ssh_port'] ?? 22,
            ':location' => $data['location'] ?? null,
            ':poll_interval' => $data['poll_interval'] ?? 300,
            ':notes' => $data['notes'] ?? null
        ]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC)['id'];
    }
    
    /**
     * Update device
     */
    public function updateDevice($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = [
            'name', 'device_type', 'vendor', 'model', 'ip_address',
            'snmp_version', 'snmp_community', 'snmp_port',
            'snmpv3_username', 'snmpv3_auth_protocol', 'snmpv3_auth_password',
            'snmpv3_priv_protocol', 'snmpv3_priv_password',
            'telnet_username', 'telnet_password', 'telnet_port',
            'ssh_enabled', 'ssh_port', 'location', 'poll_interval', 'enabled', 'notes'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->db->prepare("
            UPDATE network_devices SET " . implode(', ', $fields) . "
            WHERE id = :id
        ");
        
        return $stmt->execute($params);
    }
    
    /**
     * Delete device
     */
    public function deleteDevice($id) {
        $stmt = $this->db->prepare("DELETE FROM network_devices WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * Get all devices
     */
    public function getDevices($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['device_type'])) {
            $where[] = "device_type = :device_type";
            $params[':device_type'] = $filters['device_type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (isset($filters['enabled'])) {
            $where[] = "enabled = :enabled";
            $params[':enabled'] = $filters['enabled'];
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM network_devices 
            WHERE " . implode(' AND ', $where) . "
            ORDER BY name
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get single device
     */
    public function getDevice($id) {
        $stmt = $this->db->prepare("SELECT * FROM network_devices WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * SNMP Get single value
     */
    public function snmpGet($device, $oid) {
        if (!function_exists('snmpget')) {
            return ['success' => false, 'error' => 'SNMP extension not installed'];
        }
        
        try {
            $timeout = 1000000; // 1 second
            $retries = 2;
            
            if ($device['snmp_version'] === 'v3') {
                $value = @snmp3_get(
                    $device['ip_address'],
                    $device['snmpv3_username'],
                    $this->getSnmpSecLevel($device),
                    $device['snmpv3_auth_protocol'] ?? 'SHA',
                    $device['snmpv3_auth_password'] ?? '',
                    $device['snmpv3_priv_protocol'] ?? 'AES',
                    $device['snmpv3_priv_password'] ?? '',
                    $oid,
                    $timeout,
                    $retries
                );
            } else {
                $version = $device['snmp_version'] === 'v1' ? SNMP::VERSION_1 : SNMP::VERSION_2c;
                $value = @snmpget(
                    $device['ip_address'],
                    $device['snmp_community'],
                    $oid,
                    $timeout,
                    $retries
                );
            }
            
            if ($value === false) {
                return ['success' => false, 'error' => 'SNMP get failed'];
            }
            
            return ['success' => true, 'value' => $this->parseSnmpValue($value)];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * SNMP Walk - get multiple values
     */
    public function snmpWalk($device, $oid) {
        if (!function_exists('snmpwalk')) {
            return ['success' => false, 'error' => 'SNMP extension not installed'];
        }
        
        try {
            $timeout = 2000000; // 2 seconds
            $retries = 2;
            
            if ($device['snmp_version'] === 'v3') {
                $values = @snmp3_walk(
                    $device['ip_address'],
                    $device['snmpv3_username'],
                    $this->getSnmpSecLevel($device),
                    $device['snmpv3_auth_protocol'] ?? 'SHA',
                    $device['snmpv3_auth_password'] ?? '',
                    $device['snmpv3_priv_protocol'] ?? 'AES',
                    $device['snmpv3_priv_password'] ?? '',
                    $oid,
                    $timeout,
                    $retries
                );
            } else {
                $values = @snmpwalk(
                    $device['ip_address'],
                    $device['snmp_community'],
                    $oid,
                    $timeout,
                    $retries
                );
            }
            
            if ($values === false) {
                return ['success' => false, 'error' => 'SNMP walk failed'];
            }
            
            $parsed = [];
            foreach ($values as $key => $value) {
                $parsed[$key] = $this->parseSnmpValue($value);
            }
            
            return ['success' => true, 'values' => $parsed];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get device basic info via SNMP
     */
    public function getDeviceInfo($deviceId) {
        $device = $this->getDevice($deviceId);
        if (!$device) {
            return ['success' => false, 'error' => 'Device not found'];
        }
        
        $info = [];
        
        // Get system description
        $result = $this->snmpGet($device, self::OID_SYSTEM_DESCR);
        if ($result['success']) {
            $info['description'] = $result['value'];
        }
        
        // Get system name
        $result = $this->snmpGet($device, self::OID_SYSTEM_NAME);
        if ($result['success']) {
            $info['name'] = $result['value'];
        }
        
        // Get uptime
        $result = $this->snmpGet($device, self::OID_SYSTEM_UPTIME);
        if ($result['success']) {
            $info['uptime'] = $result['value'];
        }
        
        // Get location
        $result = $this->snmpGet($device, self::OID_SYSTEM_LOCATION);
        if ($result['success']) {
            $info['location'] = $result['value'];
        }
        
        // Update device status
        $status = !empty($info) ? 'online' : 'offline';
        $this->updateDeviceStatus($deviceId, $status);
        
        return ['success' => true, 'info' => $info, 'status' => $status];
    }
    
    /**
     * Poll device interfaces via SNMP
     */
    public function pollInterfaces($deviceId) {
        $device = $this->getDevice($deviceId);
        if (!$device) {
            return ['success' => false, 'error' => 'Device not found'];
        }
        
        // Get interface descriptions
        $ifDescr = $this->snmpWalk($device, self::OID_IF_DESCR);
        if (!$ifDescr['success']) {
            return $ifDescr;
        }
        
        // Get interface status
        $ifStatus = $this->snmpWalk($device, self::OID_IF_OPER_STATUS);
        
        // Get interface traffic
        $ifInOctets = $this->snmpWalk($device, self::OID_IF_IN_OCTETS);
        $ifOutOctets = $this->snmpWalk($device, self::OID_IF_OUT_OCTETS);
        
        $interfaces = [];
        foreach ($ifDescr['values'] as $idx => $descr) {
            $ifIndex = $idx + 1;
            $interfaces[] = [
                'if_index' => $ifIndex,
                'if_descr' => $descr,
                'if_status' => $this->parseIfStatus($ifStatus['values'][$idx] ?? 2),
                'in_octets' => $ifInOctets['values'][$idx] ?? 0,
                'out_octets' => $ifOutOctets['values'][$idx] ?? 0
            ];
            
            // Upsert interface data
            $this->upsertInterface($deviceId, $ifIndex, [
                'if_descr' => $descr,
                'if_status' => $this->parseIfStatus($ifStatus['values'][$idx] ?? 2),
                'in_octets' => $ifInOctets['values'][$idx] ?? 0,
                'out_octets' => $ifOutOctets['values'][$idx] ?? 0
            ]);
        }
        
        $this->updateDeviceStatus($deviceId, 'online');
        
        return ['success' => true, 'interfaces' => $interfaces];
    }
    
    /**
     * Poll ONU status for Huawei OLT
     */
    public function pollHuaweiOnus($deviceId) {
        $device = $this->getDevice($deviceId);
        if (!$device) {
            return ['success' => false, 'error' => 'Device not found'];
        }
        
        // Get ONU status
        $onuStatus = $this->snmpWalk($device, self::OID_HUAWEI_ONU_STATUS);
        if (!$onuStatus['success']) {
            return $onuStatus;
        }
        
        // Get ONU RX power
        $onuRxPower = $this->snmpWalk($device, self::OID_HUAWEI_ONU_RX_POWER);
        
        // Get ONU distance
        $onuDistance = $this->snmpWalk($device, self::OID_HUAWEI_ONU_DISTANCE);
        
        $onus = [];
        foreach ($onuStatus['values'] as $idx => $status) {
            $onuId = $idx + 1;
            $onus[] = [
                'onu_id' => $onuId,
                'status' => $this->parseOnuStatus($status),
                'rx_power' => isset($onuRxPower['values'][$idx]) ? $onuRxPower['values'][$idx] / 100 : null,
                'distance' => $onuDistance['values'][$idx] ?? null
            ];
        }
        
        return ['success' => true, 'onus' => $onus];
    }
    
    /**
     * Telnet connection and command execution
     */
    public function telnetCommand($device, $command) {
        $host = $device['ip_address'];
        $port = $device['telnet_port'] ?? 23;
        $username = $device['telnet_username'];
        $password = $device['telnet_password'];
        
        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        if (!$socket) {
            return ['success' => false, 'error' => "Connection failed: $errstr"];
        }
        
        stream_set_timeout($socket, 10);
        
        try {
            // Wait for login prompt
            $this->telnetWaitFor($socket, ['ogin:', 'sername:']);
            fwrite($socket, $username . "\r\n");
            
            // Wait for password prompt
            $this->telnetWaitFor($socket, ['assword:']);
            fwrite($socket, $password . "\r\n");
            
            // Wait for command prompt
            $this->telnetWaitFor($socket, ['>', '#', ']']);
            
            // Send command
            fwrite($socket, $command . "\r\n");
            
            // Get output
            $output = $this->telnetWaitFor($socket, ['>', '#', ']'], true);
            
            fclose($socket);
            
            return ['success' => true, 'output' => $output];
            
        } catch (\Exception $e) {
            fclose($socket);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Execute Huawei OLT command via Telnet
     */
    public function huaweiCommand($deviceId, $command) {
        $device = $this->getDevice($deviceId);
        if (!$device) {
            return ['success' => false, 'error' => 'Device not found'];
        }
        
        // Huawei-specific login sequence
        $host = $device['ip_address'];
        $port = $device['telnet_port'] ?? 23;
        $username = $device['telnet_username'];
        $password = $device['telnet_password'];
        
        $socket = @fsockopen($host, $port, $errno, $errstr, 15);
        if (!$socket) {
            return ['success' => false, 'error' => "Connection failed: $errstr"];
        }
        
        stream_set_timeout($socket, 15);
        
        try {
            // Huawei login
            $this->telnetWaitFor($socket, ['User name:']);
            fwrite($socket, $username . "\r\n");
            
            $this->telnetWaitFor($socket, ['User password:']);
            fwrite($socket, $password . "\r\n");
            
            // Wait for prompt
            $this->telnetWaitFor($socket, ['>']);
            
            // Enter enable mode
            fwrite($socket, "enable\r\n");
            $this->telnetWaitFor($socket, ['#']);
            
            // Send command
            fwrite($socket, $command . "\r\n");
            
            // Get output
            $output = $this->telnetWaitFor($socket, ['#'], true);
            
            // Logout
            fwrite($socket, "quit\r\n");
            
            fclose($socket);
            
            return ['success' => true, 'output' => $output];
            
        } catch (\Exception $e) {
            @fclose($socket);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get Huawei ONU list via Telnet
     */
    public function getHuaweiOnuList($deviceId, $frame = 0, $slot = null) {
        $command = "display ont info summary";
        if ($slot !== null) {
            $command .= " $frame/$slot";
        }
        
        $result = $this->huaweiCommand($deviceId, $command);
        if (!$result['success']) {
            return $result;
        }
        
        // Parse output
        $onus = $this->parseHuaweiOnuList($result['output']);
        
        return ['success' => true, 'onus' => $onus];
    }
    
    /**
     * Get Huawei ONU optical info
     */
    public function getHuaweiOnuOptical($deviceId, $frame, $slot, $port, $onuId) {
        $command = "display ont optical-info $frame/$slot/$port $onuId";
        
        $result = $this->huaweiCommand($deviceId, $command);
        if (!$result['success']) {
            return $result;
        }
        
        // Parse optical info
        $optical = $this->parseHuaweiOptical($result['output']);
        
        return ['success' => true, 'optical' => $optical];
    }
    
    /**
     * Test device connectivity
     */
    public function testConnection($deviceId) {
        $device = $this->getDevice($deviceId);
        if (!$device) {
            return ['success' => false, 'error' => 'Device not found'];
        }
        
        $results = [
            'ping' => false,
            'snmp' => false,
            'telnet' => false
        ];
        
        // Test ping
        $pingResult = exec("ping -c 1 -W 2 " . escapeshellarg($device['ip_address']), $output, $returnCode);
        $results['ping'] = ($returnCode === 0);
        
        // Test SNMP
        $snmpResult = $this->snmpGet($device, self::OID_SYSTEM_DESCR);
        $results['snmp'] = $snmpResult['success'];
        if ($snmpResult['success']) {
            $results['snmp_info'] = $snmpResult['value'];
        }
        
        // Test Telnet if credentials provided
        if (!empty($device['telnet_username']) && !empty($device['telnet_password'])) {
            $socket = @fsockopen($device['ip_address'], $device['telnet_port'] ?? 23, $errno, $errstr, 5);
            if ($socket) {
                $results['telnet'] = true;
                fclose($socket);
            }
        }
        
        // Update status
        $status = $results['ping'] ? 'online' : 'offline';
        $this->updateDeviceStatus($deviceId, $status);
        
        return ['success' => true, 'results' => $results];
    }
    
    /**
     * Get ONU list for a device
     */
    public function getOnus($deviceId, $filters = []) {
        $where = ["device_id = :device_id"];
        $params = [':device_id' => $deviceId];
        
        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['customer_id'])) {
            $where[] = "customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }
        
        $stmt = $this->db->prepare("
            SELECT o.*, c.full_name as customer_name 
            FROM device_onus o
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY o.pon_port, o.onu_index
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Link ONU to customer
     */
    public function linkOnuToCustomer($onuId, $customerId) {
        $stmt = $this->db->prepare("
            UPDATE device_onus SET customer_id = :customer_id, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $onuId, ':customer_id' => $customerId]);
    }
    
    /**
     * Get device statistics
     */
    public function getStatistics() {
        $stats = [];
        
        // Device counts by status
        $stmt = $this->db->query("
            SELECT status, COUNT(*) as count FROM network_devices GROUP BY status
        ");
        $stats['devices_by_status'] = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        
        // Total devices
        $stmt = $this->db->query("SELECT COUNT(*) FROM network_devices");
        $stats['total_devices'] = $stmt->fetchColumn();
        
        // Total ONUs
        $stmt = $this->db->query("SELECT COUNT(*) FROM device_onus");
        $stats['total_onus'] = $stmt->fetchColumn();
        
        // Online ONUs
        $stmt = $this->db->query("SELECT COUNT(*) FROM device_onus WHERE status = 'online'");
        $stats['online_onus'] = $stmt->fetchColumn();
        
        // Offline ONUs
        $stmt = $this->db->query("SELECT COUNT(*) FROM device_onus WHERE status = 'offline'");
        $stats['offline_onus'] = $stmt->fetchColumn();
        
        return $stats;
    }
    
    // Helper methods
    
    private function updateDeviceStatus($deviceId, $status) {
        $stmt = $this->db->prepare("
            UPDATE network_devices SET status = :status, last_polled = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $stmt->execute([':id' => $deviceId, ':status' => $status]);
    }
    
    private function upsertInterface($deviceId, $ifIndex, $data) {
        $stmt = $this->db->prepare("
            INSERT INTO device_interfaces (device_id, if_index, if_descr, if_status, in_octets, out_octets)
            VALUES (:device_id, :if_index, :if_descr, :if_status, :in_octets, :out_octets)
            ON CONFLICT (device_id, if_index) 
            DO UPDATE SET if_descr = :if_descr, if_status = :if_status, 
                          in_octets = :in_octets, out_octets = :out_octets, 
                          last_updated = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':device_id' => $deviceId,
            ':if_index' => $ifIndex,
            ':if_descr' => $data['if_descr'],
            ':if_status' => $data['if_status'],
            ':in_octets' => $data['in_octets'],
            ':out_octets' => $data['out_octets']
        ]);
    }
    
    private function parseSnmpValue($value) {
        // Remove type prefix
        if (preg_match('/^[A-Z]+:\s*(.*)$/i', $value, $matches)) {
            return trim($matches[1], '"');
        }
        return $value;
    }
    
    private function parseIfStatus($status) {
        $statuses = [1 => 'up', 2 => 'down', 3 => 'testing'];
        return $statuses[$status] ?? 'unknown';
    }
    
    private function parseOnuStatus($status) {
        // Huawei ONU status codes
        $statuses = [
            1 => 'online',
            2 => 'offline', 
            3 => 'los',
            4 => 'dyinggasp',
            5 => 'authing',
            6 => 'authfail'
        ];
        return $statuses[$status] ?? 'unknown';
    }
    
    private function getSnmpSecLevel($device) {
        if (!empty($device['snmpv3_priv_password'])) {
            return 'authPriv';
        } elseif (!empty($device['snmpv3_auth_password'])) {
            return 'authNoPriv';
        }
        return 'noAuthNoPriv';
    }
    
    private function telnetWaitFor($socket, $patterns, $returnOutput = false) {
        $buffer = '';
        $timeout = 10;
        $start = time();
        
        while (time() - $start < $timeout) {
            $char = fread($socket, 1);
            if ($char === false || $char === '') {
                usleep(100000);
                continue;
            }
            $buffer .= $char;
            
            foreach ($patterns as $pattern) {
                if (strpos($buffer, $pattern) !== false) {
                    return $returnOutput ? $buffer : true;
                }
            }
        }
        
        throw new \Exception('Telnet timeout waiting for: ' . implode(', ', $patterns));
    }
    
    private function parseHuaweiOnuList($output) {
        $onus = [];
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            // Parse lines like: 0/1/0    1     HWTC-12345678    online
            if (preg_match('/(\d+\/\d+\/\d+)\s+(\d+)\s+(\S+)\s+(online|offline|los)/i', $line, $matches)) {
                $onus[] = [
                    'pon_port' => $matches[1],
                    'onu_id' => $matches[2],
                    'serial' => $matches[3],
                    'status' => strtolower($matches[4])
                ];
            }
        }
        
        return $onus;
    }
    
    private function parseHuaweiOptical($output) {
        $optical = [];
        
        // Parse RX optical power
        if (preg_match('/Rx optical power.*?(-?\d+\.?\d*)\s*dBm/i', $output, $matches)) {
            $optical['rx_power'] = floatval($matches[1]);
        }
        
        // Parse TX optical power
        if (preg_match('/Tx optical power.*?(-?\d+\.?\d*)\s*dBm/i', $output, $matches)) {
            $optical['tx_power'] = floatval($matches[1]);
        }
        
        // Parse OLT RX optical power
        if (preg_match('/OLT Rx optical power.*?(-?\d+\.?\d*)\s*dBm/i', $output, $matches)) {
            $optical['olt_rx_power'] = floatval($matches[1]);
        }
        
        return $optical;
    }
}
