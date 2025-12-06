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
    
    // VLAN OIDs (Q-BRIDGE-MIB)
    const OID_VLAN_STATIC_NAME = '1.3.6.1.2.1.17.7.1.4.3.1.1';       // dot1qVlanStaticName
    const OID_VLAN_FDB_ID = '1.3.6.1.2.1.17.7.1.4.2.1.3';            // dot1qVlanFdbId
    const OID_VLAN_CURRENT_EGRESS_PORTS = '1.3.6.1.2.1.17.7.1.4.2.1.4';  // dot1qVlanCurrentEgressPorts
    const OID_VLAN_CURRENT_UNTAGGED_PORTS = '1.3.6.1.2.1.17.7.1.4.2.1.5'; // dot1qVlanCurrentUntaggedPorts
    const OID_VLAN_STATUS = '1.3.6.1.2.1.17.7.1.4.3.1.5';            // dot1qVlanStaticRowStatus
    
    // Interface VLAN counters (via interface table with VLAN sub-interfaces)
    const OID_IF_HC_IN_OCTETS = '1.3.6.1.2.1.31.1.1.1.6';    // 64-bit in octets
    const OID_IF_HC_OUT_OCTETS = '1.3.6.1.2.1.31.1.1.1.10';  // 64-bit out octets
    const OID_IF_NAME = '1.3.6.1.2.1.31.1.1.1.1';            // Interface name (ifName)
    
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
        
        // Interface history for graphs (LibreNMS-style)
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS interface_history (
                id SERIAL PRIMARY KEY,
                interface_id INTEGER REFERENCES device_interfaces(id) ON DELETE CASCADE,
                in_octets BIGINT DEFAULT 0,
                out_octets BIGINT DEFAULT 0,
                in_rate BIGINT DEFAULT 0,
                out_rate BIGINT DEFAULT 0,
                in_errors BIGINT DEFAULT 0,
                out_errors BIGINT DEFAULT 0,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // VLANs table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS device_vlans (
                id SERIAL PRIMARY KEY,
                device_id INTEGER REFERENCES network_devices(id) ON DELETE CASCADE,
                vlan_id INTEGER NOT NULL,
                vlan_name VARCHAR(100),
                vlan_status VARCHAR(20) DEFAULT 'active',
                ports TEXT,
                tagged_ports TEXT,
                untagged_ports TEXT,
                in_octets BIGINT DEFAULT 0,
                out_octets BIGINT DEFAULT 0,
                in_rate BIGINT DEFAULT 0,
                out_rate BIGINT DEFAULT 0,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(device_id, vlan_id)
            )
        ");
        
        // VLAN traffic history
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS vlan_history (
                id SERIAL PRIMARY KEY,
                vlan_record_id INTEGER REFERENCES device_vlans(id) ON DELETE CASCADE,
                in_octets BIGINT DEFAULT 0,
                out_octets BIGINT DEFAULT 0,
                in_rate BIGINT DEFAULT 0,
                out_rate BIGINT DEFAULT 0,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create indexes
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_device_onus_customer ON device_onus(customer_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_device_onus_status ON device_onus(status)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_monitoring_log_device ON device_monitoring_log(device_id, recorded_at)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_interface_history_time ON interface_history(interface_id, recorded_at)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_device_vlans_device ON device_vlans(device_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_vlan_history_time ON vlan_history(vlan_record_id, recorded_at)");
        
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
        // Try PHP extension first
        if (function_exists('snmpget')) {
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
                    $value = @snmpget(
                        $device['ip_address'],
                        $device['snmp_community'],
                        $oid,
                        $timeout,
                        $retries
                    );
                }
                
                if ($value !== false) {
                    return ['success' => true, 'value' => $this->parseSnmpValue($value)];
                }
            } catch (\Exception $e) {
                // Fall through to command-line
            }
        }
        
        // Fallback to command-line snmpget
        return $this->snmpGetCli($device, $oid);
    }
    
    /**
     * SNMP Get using command-line tool
     */
    private function snmpGetCli($device, $oid) {
        $ip = escapeshellarg($device['ip_address']);
        $community = escapeshellarg($device['snmp_community'] ?? 'public');
        $oidEsc = escapeshellarg($oid);
        
        if ($device['snmp_version'] === 'v3') {
            $user = escapeshellarg($device['snmpv3_username'] ?? '');
            $authProto = escapeshellarg($device['snmpv3_auth_protocol'] ?? 'SHA');
            $authPass = escapeshellarg($device['snmpv3_auth_password'] ?? '');
            $privProto = escapeshellarg($device['snmpv3_priv_protocol'] ?? 'AES');
            $privPass = escapeshellarg($device['snmpv3_priv_password'] ?? '');
            
            $cmd = "snmpget -v3 -l authPriv -u {$user} -a {$authProto} -A {$authPass} -x {$privProto} -X {$privPass} -t 2 -r 1 {$ip} {$oidEsc} 2>&1";
        } elseif ($device['snmp_version'] === 'v1') {
            $cmd = "snmpget -v1 -c {$community} -t 2 -r 1 {$ip} {$oidEsc} 2>&1";
        } else {
            $cmd = "snmpget -v2c -c {$community} -t 2 -r 1 {$ip} {$oidEsc} 2>&1";
        }
        
        $output = @shell_exec($cmd);
        
        if ($output === null || strpos($output, 'Timeout') !== false || strpos($output, 'No Response') !== false) {
            return ['success' => false, 'error' => 'SNMP timeout or no response'];
        }
        
        if (strpos($output, 'command not found') !== false) {
            return ['success' => false, 'error' => 'SNMP tools not installed. Run: apt install snmp'];
        }
        
        // Parse output: SNMPv2-MIB::sysDescr.0 = STRING: Linux router
        if (preg_match('/=\s*(.+)$/m', $output, $matches)) {
            return ['success' => true, 'value' => trim($matches[1])];
        }
        
        return ['success' => true, 'value' => trim($output)];
    }
    
    /**
     * SNMP Walk - get multiple values
     */
    public function snmpWalk($device, $oid) {
        // Try PHP extension first
        if (function_exists('snmpwalk')) {
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
                
                if ($values !== false) {
                    $parsed = [];
                    foreach ($values as $key => $value) {
                        $parsed[$key] = $this->parseSnmpValue($value);
                    }
                    return ['success' => true, 'values' => $parsed];
                }
            } catch (\Exception $e) {
                // Fall through to command-line
            }
        }
        
        // Fallback to command-line snmpwalk
        return $this->snmpWalkCli($device, $oid);
    }
    
    /**
     * SNMP Walk using command-line tool
     */
    private function snmpWalkCli($device, $oid) {
        $ip = escapeshellarg($device['ip_address']);
        $community = escapeshellarg($device['snmp_community'] ?? 'public');
        $oidEsc = escapeshellarg($oid);
        
        if ($device['snmp_version'] === 'v3') {
            $user = escapeshellarg($device['snmpv3_username'] ?? '');
            $authProto = escapeshellarg($device['snmpv3_auth_protocol'] ?? 'SHA');
            $authPass = escapeshellarg($device['snmpv3_auth_password'] ?? '');
            $privProto = escapeshellarg($device['snmpv3_priv_protocol'] ?? 'AES');
            $privPass = escapeshellarg($device['snmpv3_priv_password'] ?? '');
            
            $cmd = "snmpwalk -v3 -l authPriv -u {$user} -a {$authProto} -A {$authPass} -x {$privProto} -X {$privPass} -t 3 -r 1 {$ip} {$oidEsc} 2>&1";
        } elseif ($device['snmp_version'] === 'v1') {
            $cmd = "snmpwalk -v1 -c {$community} -t 3 -r 1 {$ip} {$oidEsc} 2>&1";
        } else {
            $cmd = "snmpwalk -v2c -c {$community} -t 3 -r 1 {$ip} {$oidEsc} 2>&1";
        }
        
        $output = @shell_exec($cmd);
        
        if ($output === null || strpos($output, 'Timeout') !== false || strpos($output, 'No Response') !== false) {
            return ['success' => false, 'error' => 'SNMP timeout or no response'];
        }
        
        if (strpos($output, 'command not found') !== false) {
            return ['success' => false, 'error' => 'SNMP tools not installed. Run: apt install snmp'];
        }
        
        // Parse output lines: OID = TYPE: value
        $values = [];
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (preg_match('/^([^\s]+)\s*=\s*(.+)$/m', $line, $matches)) {
                $oidKey = trim($matches[1]);
                $values[$oidKey] = trim($matches[2]);
            }
        }
        
        if (empty($values)) {
            return ['success' => false, 'error' => 'No SNMP data returned'];
        }
        
        return ['success' => true, 'values' => $values];
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
            'telnet' => false,
            'ping_error' => '',
            'snmp_error' => '',
            'telnet_error' => ''
        ];
        
        $ip = $device['ip_address'];
        
        // Test ping using shell_exec
        $pingOutput = @shell_exec("ping -c 1 -W 2 " . escapeshellarg($ip) . " 2>&1");
        if ($pingOutput !== null && (strpos($pingOutput, '1 received') !== false || strpos($pingOutput, '1 packets received') !== false || strpos($pingOutput, 'bytes from') !== false)) {
            $results['ping'] = true;
        } else {
            // Check if ping failed due to permissions
            if ($pingOutput !== null && (strpos($pingOutput, 'Operation not permitted') !== false || strpos($pingOutput, 'cap_net_raw') !== false)) {
                // Ping requires root - use TCP connectivity test instead
                $testPort = $device['ssh_enabled'] ? ($device['ssh_port'] ?? 22) : ($device['telnet_port'] ?? 23);
                $tcpSocket = @fsockopen($ip, $testPort, $errno, $errstr, 2);
                if ($tcpSocket) {
                    $results['ping'] = true;
                    $results['ping_error'] = 'ICMP ping unavailable - TCP connectivity confirmed on port ' . $testPort;
                    fclose($tcpSocket);
                } else {
                    $results['ping_error'] = 'ICMP ping requires root privileges. TCP test also failed.';
                }
            } else {
                $results['ping_error'] = 'Host unreachable or ICMP blocked';
            }
        }
        
        // Test SNMP - try PHP extension first, then command line
        $snmpResult = $this->snmpGet($device, self::OID_SYSTEM_DESCR);
        if ($snmpResult['success']) {
            $results['snmp'] = true;
            $results['snmp_info'] = $snmpResult['value'];
        } else {
            // Try command-line snmpget as fallback
            $community = escapeshellarg($device['snmp_community'] ?? 'public');
            $snmpCmd = "snmpget -v2c -c {$community} -t 2 -r 1 " . escapeshellarg($ip) . " " . escapeshellarg(self::OID_SYSTEM_DESCR) . " 2>&1";
            $snmpOutput = @shell_exec($snmpCmd);
            
            if ($snmpOutput !== null && strpos($snmpOutput, 'Timeout') === false && strpos($snmpOutput, 'No Response') === false && strlen(trim($snmpOutput)) > 0) {
                $results['snmp'] = true;
                $results['snmp_info'] = trim($snmpOutput);
            } else {
                $results['snmp_error'] = $snmpResult['error'] ?? 'SNMP timeout or wrong community string';
            }
        }
        
        // Test Telnet/SSH port connectivity
        $telnetPort = $device['telnet_port'] ?? 23;
        if ($device['ssh_enabled']) {
            $telnetPort = $device['ssh_port'] ?? 22;
        }
        
        // Use fsockopen with shorter timeout
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($ip, $telnetPort, $errno, $errstr, 3);
        
        if ($socket) {
            $results['telnet'] = true;
            fclose($socket);
        } else {
            // Try using shell command
            $ncOutput = @shell_exec("timeout 3 bash -c 'echo > /dev/tcp/" . escapeshellarg($ip) . "/" . $telnetPort . "' 2>&1");
            if ($ncOutput === '') {
                $results['telnet'] = true;
            } else {
                $results['telnet_error'] = "Port $telnetPort not reachable: " . ($errstr ?: 'Connection refused');
            }
        }
        
        // Update status - device is online if ANY test passes
        $isOnline = $results['ping'] || $results['snmp'] || $results['telnet'];
        $status = $isOnline ? 'online' : 'offline';
        $this->updateDeviceStatus($deviceId, $status);
        
        // Add note if ping fails but other tests pass
        if (!$results['ping'] && ($results['snmp'] || $results['telnet'])) {
            $results['ping_error'] = 'ICMP blocked (normal for many devices). Device is reachable via other protocols.';
        }
        
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
     * Record interface history for graphs
     */
    public function recordInterfaceHistory($interfaceId, $inOctets, $outOctets, $inErrors = 0, $outErrors = 0) {
        // Get previous values to calculate rates
        $stmt = $this->db->prepare("
            SELECT in_octets, out_octets, recorded_at 
            FROM interface_history 
            WHERE interface_id = :id 
            ORDER BY recorded_at DESC LIMIT 1
        ");
        $stmt->execute([':id' => $interfaceId]);
        $prev = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $inRate = 0;
        $outRate = 0;
        
        if ($prev) {
            $timeDiff = time() - strtotime($prev['recorded_at']);
            if ($timeDiff > 0) {
                // Handle counter wrap
                $inDiff = $inOctets >= $prev['in_octets'] ? $inOctets - $prev['in_octets'] : $inOctets;
                $outDiff = $outOctets >= $prev['out_octets'] ? $outOctets - $prev['out_octets'] : $outOctets;
                $inRate = ($inDiff * 8) / $timeDiff; // bits per second
                $outRate = ($outDiff * 8) / $timeDiff;
            }
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO interface_history (interface_id, in_octets, out_octets, in_rate, out_rate, in_errors, out_errors)
            VALUES (:interface_id, :in_octets, :out_octets, :in_rate, :out_rate, :in_errors, :out_errors)
        ");
        $stmt->execute([
            ':interface_id' => $interfaceId,
            ':in_octets' => $inOctets,
            ':out_octets' => $outOctets,
            ':in_rate' => (int)$inRate,
            ':out_rate' => (int)$outRate,
            ':in_errors' => $inErrors,
            ':out_errors' => $outErrors
        ]);
    }
    
    /**
     * Get interface history for graphs
     */
    public function getInterfaceHistory($interfaceId, $hours = 24) {
        $stmt = $this->db->prepare("
            SELECT in_rate, out_rate, in_errors, out_errors, recorded_at
            FROM interface_history 
            WHERE interface_id = :id 
            AND recorded_at > NOW() - INTERVAL '{$hours} hours'
            ORDER BY recorded_at ASC
        ");
        $stmt->execute([':id' => $interfaceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get device traffic summary for graphs
     */
    public function getDeviceTrafficSummary($deviceId, $hours = 24) {
        $stmt = $this->db->prepare("
            SELECT 
                di.id, di.if_descr, di.if_index,
                COALESCE(AVG(ih.in_rate), 0) as avg_in_rate,
                COALESCE(AVG(ih.out_rate), 0) as avg_out_rate,
                COALESCE(MAX(ih.in_rate), 0) as max_in_rate,
                COALESCE(MAX(ih.out_rate), 0) as max_out_rate,
                COALESCE(SUM(ih.in_errors), 0) as total_in_errors,
                COALESCE(SUM(ih.out_errors), 0) as total_out_errors
            FROM device_interfaces di
            LEFT JOIN interface_history ih ON di.id = ih.interface_id 
                AND ih.recorded_at > NOW() - INTERVAL '{$hours} hours'
            WHERE di.device_id = :device_id
            GROUP BY di.id, di.if_descr, di.if_index
            ORDER BY di.if_index
        ");
        $stmt->execute([':device_id' => $deviceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Cleanup old history data (keep last 7 days)
     */
    public function cleanupHistory($days = 7) {
        $stmt = $this->db->prepare("
            DELETE FROM interface_history 
            WHERE recorded_at < NOW() - INTERVAL '{$days} days'
        ");
        return $stmt->execute();
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
    
    /**
     * Poll VLANs from device via SNMP
     */
    public function pollVlans($deviceId) {
        $device = $this->getDevice($deviceId);
        if (!$device) {
            return ['success' => false, 'error' => 'Device not found'];
        }
        
        $vlans = [];
        
        // Get VLAN names
        $vlanNames = $this->snmpWalk($device, self::OID_VLAN_STATIC_NAME);
        if ($vlanNames['success'] && !empty($vlanNames['values'])) {
            foreach ($vlanNames['values'] as $oid => $name) {
                // Extract VLAN ID from OID
                $parts = explode('.', $oid);
                $vlanId = end($parts);
                
                $vlans[$vlanId] = [
                    'vlan_id' => intval($vlanId),
                    'vlan_name' => $this->parseSnmpValue($name),
                    'vlan_status' => 'active',
                    'ports' => '',
                    'tagged_ports' => '',
                    'untagged_ports' => ''
                ];
            }
        }
        
        // If no VLANs found via Q-BRIDGE, try to get VLAN interfaces from ifName
        if (empty($vlans)) {
            $ifNames = $this->snmpWalk($device, self::OID_IF_NAME);
            if ($ifNames['success'] && !empty($ifNames['values'])) {
                foreach ($ifNames['values'] as $oid => $name) {
                    $name = $this->parseSnmpValue($name);
                    // Look for VLAN interfaces (e.g., Vlan100, vlan.100, etc)
                    if (preg_match('/[Vv]lan\.?(\d+)/i', $name, $matches)) {
                        $vlanId = intval($matches[1]);
                        $vlans[$vlanId] = [
                            'vlan_id' => $vlanId,
                            'vlan_name' => $name,
                            'vlan_status' => 'active',
                            'ports' => '',
                            'tagged_ports' => '',
                            'untagged_ports' => '',
                            'if_index' => intval(end(explode('.', $oid)))
                        ];
                    }
                }
            }
        }
        
        // Get VLAN egress ports
        $egressPorts = $this->snmpWalk($device, self::OID_VLAN_CURRENT_EGRESS_PORTS);
        if ($egressPorts['success'] && !empty($egressPorts['values'])) {
            foreach ($egressPorts['values'] as $oid => $value) {
                $parts = explode('.', $oid);
                $vlanId = end($parts);
                if (isset($vlans[$vlanId])) {
                    $vlans[$vlanId]['ports'] = $this->parsePortBitmap($value);
                }
            }
        }
        
        // Get VLAN untagged ports
        $untaggedPorts = $this->snmpWalk($device, self::OID_VLAN_CURRENT_UNTAGGED_PORTS);
        if ($untaggedPorts['success'] && !empty($untaggedPorts['values'])) {
            foreach ($untaggedPorts['values'] as $oid => $value) {
                $parts = explode('.', $oid);
                $vlanId = end($parts);
                if (isset($vlans[$vlanId])) {
                    $vlans[$vlanId]['untagged_ports'] = $this->parsePortBitmap($value);
                    // Tagged = Egress - Untagged
                    $vlans[$vlanId]['tagged_ports'] = $this->subtractPorts(
                        $vlans[$vlanId]['ports'],
                        $vlans[$vlanId]['untagged_ports']
                    );
                }
            }
        }
        
        // Get bandwidth for VLANs (via VLAN interfaces)
        foreach ($vlans as $vlanId => &$vlan) {
            if (isset($vlan['if_index'])) {
                $ifIndex = $vlan['if_index'];
                
                // Get current octets
                $inOctets = $this->snmpGet($device, self::OID_IF_HC_IN_OCTETS . '.' . $ifIndex);
                $outOctets = $this->snmpGet($device, self::OID_IF_HC_OUT_OCTETS . '.' . $ifIndex);
                
                if ($inOctets['success']) {
                    $vlan['in_octets'] = intval($this->parseSnmpValue($inOctets['value']));
                }
                if ($outOctets['success']) {
                    $vlan['out_octets'] = intval($this->parseSnmpValue($outOctets['value']));
                }
            }
        }
        
        // Save to database
        foreach ($vlans as $vlanData) {
            $this->saveVlan($deviceId, $vlanData);
        }
        
        return ['success' => true, 'vlans' => array_values($vlans), 'count' => count($vlans)];
    }
    
    /**
     * Save VLAN data and calculate rates
     */
    private function saveVlan($deviceId, $data) {
        // Get existing VLAN for rate calculation
        $stmt = $this->db->prepare("SELECT * FROM device_vlans WHERE device_id = ? AND vlan_id = ?");
        $stmt->execute([$deviceId, $data['vlan_id']]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $inRate = 0;
        $outRate = 0;
        
        if ($existing && isset($data['in_octets'])) {
            $timeDiff = time() - strtotime($existing['last_updated']);
            if ($timeDiff > 0) {
                $inDiff = ($data['in_octets'] ?? 0) - ($existing['in_octets'] ?? 0);
                $outDiff = ($data['out_octets'] ?? 0) - ($existing['out_octets'] ?? 0);
                
                // Handle counter wrap
                if ($inDiff >= 0) $inRate = ($inDiff * 8) / $timeDiff; // bits per second
                if ($outDiff >= 0) $outRate = ($outDiff * 8) / $timeDiff;
            }
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO device_vlans (device_id, vlan_id, vlan_name, vlan_status, ports, tagged_ports, untagged_ports, in_octets, out_octets, in_rate, out_rate, last_updated)
            VALUES (:device_id, :vlan_id, :vlan_name, :vlan_status, :ports, :tagged_ports, :untagged_ports, :in_octets, :out_octets, :in_rate, :out_rate, NOW())
            ON CONFLICT (device_id, vlan_id) DO UPDATE SET
                vlan_name = EXCLUDED.vlan_name,
                vlan_status = EXCLUDED.vlan_status,
                ports = EXCLUDED.ports,
                tagged_ports = EXCLUDED.tagged_ports,
                untagged_ports = EXCLUDED.untagged_ports,
                in_octets = EXCLUDED.in_octets,
                out_octets = EXCLUDED.out_octets,
                in_rate = EXCLUDED.in_rate,
                out_rate = EXCLUDED.out_rate,
                last_updated = NOW()
            RETURNING id
        ");
        
        $stmt->execute([
            ':device_id' => $deviceId,
            ':vlan_id' => $data['vlan_id'],
            ':vlan_name' => $data['vlan_name'] ?? 'VLAN ' . $data['vlan_id'],
            ':vlan_status' => $data['vlan_status'] ?? 'active',
            ':ports' => $data['ports'] ?? '',
            ':tagged_ports' => $data['tagged_ports'] ?? '',
            ':untagged_ports' => $data['untagged_ports'] ?? '',
            ':in_octets' => $data['in_octets'] ?? 0,
            ':out_octets' => $data['out_octets'] ?? 0,
            ':in_rate' => $inRate,
            ':out_rate' => $outRate
        ]);
        
        $vlanRecordId = $stmt->fetchColumn();
        
        // Save to history
        if ($vlanRecordId && ($inRate > 0 || $outRate > 0)) {
            $histStmt = $this->db->prepare("
                INSERT INTO vlan_history (vlan_record_id, in_octets, out_octets, in_rate, out_rate)
                VALUES (?, ?, ?, ?, ?)
            ");
            $histStmt->execute([
                $vlanRecordId,
                $data['in_octets'] ?? 0,
                $data['out_octets'] ?? 0,
                $inRate,
                $outRate
            ]);
        }
    }
    
    /**
     * Get VLANs for a device
     */
    public function getVlans($deviceId) {
        $stmt = $this->db->prepare("
            SELECT * FROM device_vlans 
            WHERE device_id = ? 
            ORDER BY vlan_id
        ");
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get VLAN traffic summary with bandwidth
     */
    public function getVlanTrafficSummary($deviceId, $hours = 24) {
        $stmt = $this->db->prepare("
            SELECT 
                v.*,
                COALESCE(AVG(h.in_rate), 0) as avg_in_rate,
                COALESCE(AVG(h.out_rate), 0) as avg_out_rate,
                COALESCE(MAX(h.in_rate), 0) as max_in_rate,
                COALESCE(MAX(h.out_rate), 0) as max_out_rate
            FROM device_vlans v
            LEFT JOIN vlan_history h ON v.id = h.vlan_record_id 
                AND h.recorded_at >= NOW() - INTERVAL '{$hours} hours'
            WHERE v.device_id = ?
            GROUP BY v.id
            ORDER BY v.vlan_id
        ");
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get VLAN traffic history for graphs
     */
    public function getVlanHistory($vlanRecordId, $hours = 24) {
        $stmt = $this->db->prepare("
            SELECT in_rate, out_rate, in_octets, out_octets, recorded_at
            FROM vlan_history
            WHERE vlan_record_id = ?
            AND recorded_at >= NOW() - INTERVAL '{$hours} hours'
            ORDER BY recorded_at ASC
        ");
        $stmt->execute([$vlanRecordId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Parse port bitmap from SNMP (PortList)
     */
    private function parsePortBitmap($bitmap) {
        $bitmap = $this->parseSnmpValue($bitmap);
        $ports = [];
        
        // Handle hex string format
        if (preg_match('/^[0-9a-fA-F\s]+$/', $bitmap)) {
            $bytes = str_split(str_replace(' ', '', $bitmap), 2);
            $portNum = 1;
            foreach ($bytes as $byte) {
                $val = hexdec($byte);
                for ($bit = 7; $bit >= 0; $bit--) {
                    if ($val & (1 << $bit)) {
                        $ports[] = $portNum;
                    }
                    $portNum++;
                }
            }
        }
        
        return implode(',', $ports);
    }
    
    /**
     * Subtract port lists (tagged = all - untagged)
     */
    private function subtractPorts($allPorts, $untaggedPorts) {
        if (empty($allPorts)) return '';
        
        $all = array_filter(explode(',', $allPorts));
        $untagged = array_filter(explode(',', $untaggedPorts));
        $tagged = array_diff($all, $untagged);
        
        return implode(',', $tagged);
    }
}
