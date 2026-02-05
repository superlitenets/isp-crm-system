<?php

class CallCenter {
    private $db;
    private $ami_host;
    private $ami_port;
    private $ami_user;
    private $ami_pass;
    private $socket;
    private $connected = false;

    public function __construct($db) {
        $this->db = $db;
        // Load settings from database first, fallback to env vars
        $this->ami_host = $this->getSetting('pbx_host') ?: (getenv('FREEPBX_HOST') ?: 'localhost');
        $this->ami_port = $this->getSetting('ami_port') ?: (getenv('FREEPBX_AMI_PORT') ?: 5038);
        $this->ami_user = $this->getSetting('ami_user') ?: (getenv('FREEPBX_AMI_USER') ?: 'crmadmin');
        $this->ami_pass = $this->getSetting('ami_pass') ?: (getenv('FREEPBX_AMI_PASS') ?: '');
    }
    
    private function getSetting($key) {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM call_center_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function connectAMI() {
        $this->socket = @fsockopen($this->ami_host, $this->ami_port, $errno, $errstr, 5);
        if (!$this->socket) {
            return ['success' => false, 'error' => "Failed to connect: $errstr ($errno)"];
        }

        fgets($this->socket);

        $response = $this->sendCommand([
            'Action' => 'Login',
            'Username' => $this->ami_user,
            'Secret' => $this->ami_pass
        ]);

        if (strpos($response, 'Success') !== false) {
            $this->connected = true;
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Authentication failed'];
    }

    public function disconnectAMI() {
        if ($this->socket) {
            $this->sendCommand(['Action' => 'Logoff']);
            fclose($this->socket);
            $this->connected = false;
        }
    }

    private function sendCommand($params) {
        if (!$this->socket) return '';

        $command = '';
        foreach ($params as $key => $value) {
            $command .= "$key: $value\r\n";
        }
        $command .= "\r\n";

        fwrite($this->socket, $command);

        $response = '';
        while ($line = fgets($this->socket)) {
            $response .= $line;
            if (trim($line) === '') break;
        }

        return $response;
    }

    public function originateCall($extension, $destination, $customerId = null, $ticketId = null, $callerid = null) {
        if (!$this->connected) {
            $result = $this->connectAMI();
            if (!$result['success']) return $result;
        }

        // Try PJSIP first (modern FreePBX), fall back to SIP
        $channelType = getenv('FREEPBX_CHANNEL_TYPE') ?: 'PJSIP';
        
        $params = [
            'Action' => 'Originate',
            'Channel' => "$channelType/$extension",
            'Exten' => $destination,
            'Context' => 'from-internal',
            'Priority' => 1,
            'Async' => 'true',
            'Timeout' => 30000,
            'ActionID' => uniqid('originate_')
        ];

        if ($callerid) {
            $params['CallerID'] = $callerid;
        }

        $response = $this->sendCommand($params);
        
        // Read additional responses to get the actual result
        $fullResponse = $response;
        $timeout = time() + 3;
        while (time() < $timeout && $this->socket) {
            stream_set_timeout($this->socket, 1);
            $line = @fgets($this->socket, 1024);
            if ($line === false) break;
            $fullResponse .= $line;
            if (strpos($fullResponse, 'Response:') !== false) break;
        }
        
        $this->disconnectAMI();

        // Check for success in the full response
        if (strpos($fullResponse, 'Response: Success') !== false || 
            strpos($fullResponse, 'Originate successfully queued') !== false) {
            $this->logCall($extension, $destination, 'outbound', [
                'customer_id' => $customerId,
                'ticket_id' => $ticketId,
                'disposition' => 'CALLING'
            ]);
            return ['success' => true, 'message' => 'Call initiated'];
        }

        // Extract error message if available
        if (preg_match('/Message:\s*(.+)/i', $fullResponse, $matches)) {
            return ['success' => false, 'error' => trim($matches[1]), 'debug' => $fullResponse];
        }

        return ['success' => false, 'error' => 'Failed to originate call', 'debug' => $fullResponse];
    }

    public function getQueueStatus($queue = null) {
        if (!$this->connected) {
            $result = $this->connectAMI();
            if (!$result['success']) return $result;
        }

        $params = ['Action' => 'QueueStatus'];
        if ($queue) {
            $params['Queue'] = $queue;
        }

        $response = $this->sendCommand($params);
        $this->disconnectAMI();

        return ['success' => true, 'data' => $this->parseQueueStatus($response)];
    }

    private function parseQueueStatus($response) {
        $queues = [];
        $lines = explode("\n", $response);
        $currentQueue = null;

        foreach ($lines as $line) {
            if (preg_match('/^Queue:\s*(.+)$/i', $line, $m)) {
                $currentQueue = trim($m[1]);
                $queues[$currentQueue] = ['members' => [], 'callers' => 0];
            }
            if ($currentQueue && preg_match('/^Calls:\s*(\d+)$/i', $line, $m)) {
                $queues[$currentQueue]['callers'] = (int)$m[1];
            }
        }

        return $queues;
    }

    public function setAgentPaused($extension, $paused, $queue = null, $reason = '') {
        if (!$this->connected) {
            $result = $this->connectAMI();
            if (!$result['success']) return $result;
        }

        $params = [
            'Action' => 'QueuePause',
            'Interface' => "SIP/$extension",
            'Paused' => $paused ? 'true' : 'false'
        ];

        if ($queue) $params['Queue'] = $queue;
        if ($reason) $params['Reason'] = $reason;

        $response = $this->sendCommand($params);
        $this->disconnectAMI();

        $this->logAgentStatus($extension, $paused ? 'paused' : 'available', $reason);

        return strpos($response, 'Success') !== false 
            ? ['success' => true] 
            : ['success' => false, 'error' => 'Failed to update agent status'];
    }

    public function hangupCall($channel) {
        if (!$this->connected) {
            $result = $this->connectAMI();
            if (!$result['success']) return $result;
        }

        $response = $this->sendCommand([
            'Action' => 'Hangup',
            'Channel' => $channel
        ]);

        $this->disconnectAMI();

        return strpos($response, 'Success') !== false
            ? ['success' => true]
            : ['success' => false, 'error' => 'Failed to hangup call'];
    }

    public function transferCall($channel, $destination, $context = 'from-internal') {
        if (!$this->connected) {
            $result = $this->connectAMI();
            if (!$result['success']) return $result;
        }

        $response = $this->sendCommand([
            'Action' => 'Redirect',
            'Channel' => $channel,
            'Exten' => $destination,
            'Context' => $context,
            'Priority' => 1
        ]);

        $this->disconnectAMI();

        return strpos($response, 'Success') !== false
            ? ['success' => true]
            : ['success' => false, 'error' => 'Failed to transfer call'];
    }

    public function getActiveChannels() {
        if (!$this->connected) {
            $result = $this->connectAMI();
            if (!$result['success']) return $result;
        }

        $response = $this->sendCommand(['Action' => 'CoreShowChannels']);
        $this->disconnectAMI();

        return ['success' => true, 'data' => $response];
    }

    // Database Operations using PDO

    public function getExtensions($activeOnly = true) {
        $sql = "SELECT e.*, u.name as user_name, u.email as user_email
                FROM call_center_extensions e
                LEFT JOIN users u ON e.user_id = u.id";
        if ($activeOnly) {
            $sql .= " WHERE e.is_active = true";
        }
        $sql .= " ORDER BY e.extension";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getExtension($id) {
        $stmt = $this->db->prepare("SELECT * FROM call_center_extensions WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getExtensionByUserId($userId) {
        $stmt = $this->db->prepare("SELECT * FROM call_center_extensions WHERE user_id = ? AND is_active = true");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getExtensionByNumber($extension) {
        $stmt = $this->db->prepare("SELECT * FROM call_center_extensions WHERE extension = ? AND is_active = true");
        $stmt->execute([$extension]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function saveExtension($data) {
        if (isset($data['id']) && $data['id']) {
            $sql = "UPDATE call_center_extensions SET 
                    user_id = ?, extension = ?, name = ?, secret = ?, 
                    caller_id = ?, device_type = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['user_id'] ?: null,
                $data['extension'],
                $data['name'],
                $data['secret'] ?? null,
                $data['caller_id'] ?? null,
                $data['device_type'] ?? 'softphone',
                $data['is_active'] ?? true,
                $data['id']
            ]);
            return $data['id'];
        } else {
            $sql = "INSERT INTO call_center_extensions 
                    (user_id, extension, name, secret, caller_id, device_type, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['user_id'] ?: null,
                $data['extension'],
                $data['name'],
                $data['secret'] ?? null,
                $data['caller_id'] ?? null,
                $data['device_type'] ?? 'softphone',
                $data['is_active'] ?? true
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['id'];
        }
    }

    public function deleteExtension($id) {
        $stmt = $this->db->prepare("UPDATE call_center_extensions SET is_active = false WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getQueues($activeOnly = true) {
        $sql = "SELECT q.*, 
                (SELECT COUNT(*) FROM call_center_queue_members qm WHERE qm.queue_id = q.id AND qm.is_active = true) as member_count
                FROM call_center_queues q";
        if ($activeOnly) {
            $sql .= " WHERE q.is_active = true";
        }
        $sql .= " ORDER BY q.name";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getQueue($id) {
        $stmt = $this->db->prepare("SELECT * FROM call_center_queues WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function saveQueue($data) {
        if (isset($data['id']) && $data['id']) {
            $sql = "UPDATE call_center_queues SET 
                    name = ?, extension = ?, strategy = ?, timeout = ?,
                    wrapup_time = ?, max_wait_time = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['extension'],
                $data['strategy'] ?? 'ringall',
                $data['timeout'] ?? 30,
                $data['wrapup_time'] ?? 5,
                $data['max_wait_time'] ?? 300,
                $data['is_active'] ?? true,
                $data['id']
            ]);
            return $data['id'];
        } else {
            $sql = "INSERT INTO call_center_queues 
                    (name, extension, strategy, timeout, wrapup_time, max_wait_time, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['extension'],
                $data['strategy'] ?? 'ringall',
                $data['timeout'] ?? 30,
                $data['wrapup_time'] ?? 5,
                $data['max_wait_time'] ?? 300,
                $data['is_active'] ?? true
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['id'];
        }
    }

    public function getQueueMembers($queueId) {
        $sql = "SELECT qm.*, e.extension, e.name as extension_name, u.name as user_name
                FROM call_center_queue_members qm
                JOIN call_center_extensions e ON qm.extension_id = e.id
                LEFT JOIN users u ON e.user_id = u.id
                WHERE qm.queue_id = ? AND qm.is_active = true
                ORDER BY qm.penalty, e.extension";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$queueId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addQueueMember($queueId, $extensionId, $penalty = 0) {
        $sql = "INSERT INTO call_center_queue_members (queue_id, extension_id, penalty)
                VALUES (?, ?, ?)
                ON CONFLICT (queue_id, extension_id) DO UPDATE SET is_active = true, penalty = EXCLUDED.penalty";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$queueId, $extensionId, $penalty]);
    }

    public function removeQueueMember($queueId, $extensionId) {
        $stmt = $this->db->prepare("UPDATE call_center_queue_members SET is_active = false WHERE queue_id = ? AND extension_id = ?");
        return $stmt->execute([$queueId, $extensionId]);
    }

    public function getCalls($filters = [], $limit = 100, $offset = 0) {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = "c.call_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "c.call_date <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['extension_id'])) {
            $where[] = "c.extension_id = ?";
            $params[] = $filters['extension_id'];
        }
        if (!empty($filters['customer_id'])) {
            $where[] = "c.customer_id = ?";
            $params[] = $filters['customer_id'];
        }
        if (!empty($filters['direction'])) {
            $where[] = "c.direction = ?";
            $params[] = $filters['direction'];
        }
        if (!empty($filters['disposition'])) {
            $where[] = "c.disposition = ?";
            $params[] = $filters['disposition'];
        }

        $sql = "SELECT c.*, 
                e.extension, e.name as extension_name,
                cust.name as customer_name, cust.phone as customer_phone,
                q.name as queue_name
                FROM call_center_calls c
                LEFT JOIN call_center_extensions e ON c.extension_id = e.id
                LEFT JOIN customers cust ON c.customer_id = cust.id
                LEFT JOIN call_center_queues q ON c.queue_id = q.id
                WHERE " . implode(" AND ", $where) . "
                ORDER BY c.call_date DESC
                LIMIT $limit OFFSET $offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCallStats($period = 'today') {
        $dateCondition = match($period) {
            'today' => "call_date >= CURRENT_DATE",
            'week' => "call_date >= CURRENT_DATE - INTERVAL '7 days'",
            'month' => "call_date >= CURRENT_DATE - INTERVAL '30 days'",
            default => "1=1"
        };

        $sql = "SELECT 
                COUNT(*) as total_calls,
                COUNT(*) FILTER (WHERE direction = 'inbound') as inbound_calls,
                COUNT(*) FILTER (WHERE direction = 'outbound') as outbound_calls,
                COUNT(*) FILTER (WHERE disposition = 'ANSWERED') as answered_calls,
                COUNT(*) FILTER (WHERE disposition = 'NO ANSWER') as missed_calls,
                COUNT(*) FILTER (WHERE disposition = 'BUSY') as busy_calls,
                COALESCE(AVG(duration) FILTER (WHERE disposition = 'ANSWERED'), 0) as avg_duration,
                COALESCE(SUM(duration), 0) as total_duration
                FROM call_center_calls
                WHERE $dateCondition";

        $stmt = $this->db->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function logCall($src, $dst, $direction, $data = []) {
        $sql = "INSERT INTO call_center_calls 
                (uniqueid, call_date, src, dst, direction, disposition, duration, billsec, 
                 extension_id, customer_id, ticket_id, notes)
                VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['uniqueid'] ?? uniqid('call_'),
            $src,
            $dst,
            $direction,
            $data['disposition'] ?? 'UNKNOWN',
            $data['duration'] ?? 0,
            $data['billsec'] ?? 0,
            $data['extension_id'] ?? null,
            $data['customer_id'] ?? null,
            $data['ticket_id'] ?? null,
            $data['notes'] ?? null
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['id'];
    }

    public function logAgentStatus($extensionId, $status, $reason = null) {
        $stmt = $this->db->prepare("UPDATE call_center_agent_status 
                SET ended_at = NOW(), duration = EXTRACT(EPOCH FROM (NOW() - started_at))::INTEGER
                WHERE extension_id = ? AND ended_at IS NULL");
        $stmt->execute([$extensionId]);

        $stmt = $this->db->prepare("INSERT INTO call_center_agent_status (extension_id, status, status_reason) VALUES (?, ?, ?)");
        return $stmt->execute([$extensionId, $status, $reason]);
    }

    public function getAgentStatusHistory($extensionId, $limit = 50) {
        $stmt = $this->db->prepare("SELECT * FROM call_center_agent_status WHERE extension_id = ? ORDER BY started_at DESC LIMIT ?");
        $stmt->execute([$extensionId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getTrunks($activeOnly = true) {
        $sql = "SELECT * FROM call_center_trunks";
        if ($activeOnly) {
            $sql .= " WHERE is_active = true";
        }
        $sql .= " ORDER BY name";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getTrunk($id) {
        $stmt = $this->db->prepare("SELECT * FROM call_center_trunks WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function saveTrunk($data) {
        if (isset($data['id']) && $data['id']) {
            $sql = "UPDATE call_center_trunks SET 
                    name = ?, trunk_type = ?, host = ?, port = ?, username = ?,
                    secret = ?, codecs = ?, max_channels = ?, registration = ?,
                    is_active = ?, updated_at = NOW()
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['trunk_type'] ?? 'peer',
                $data['host'],
                $data['port'] ?? 5060,
                $data['username'] ?? null,
                $data['secret'] ?? null,
                $data['codecs'] ?? 'ulaw,alaw,g729',
                $data['max_channels'] ?? 30,
                $data['registration'] ?? false,
                $data['is_active'] ?? true,
                $data['id']
            ]);
            return $data['id'];
        } else {
            $sql = "INSERT INTO call_center_trunks 
                    (name, trunk_type, host, port, username, secret, codecs, max_channels, registration, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['trunk_type'] ?? 'peer',
                $data['host'],
                $data['port'] ?? 5060,
                $data['username'] ?? null,
                $data['secret'] ?? null,
                $data['codecs'] ?? 'ulaw,alaw,g729',
                $data['max_channels'] ?? 30,
                $data['registration'] ?? false,
                $data['is_active'] ?? true
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['id'];
        }
    }

    public function deleteTrunk($id) {
        $stmt = $this->db->prepare("UPDATE call_center_trunks SET is_active = false WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function findCustomerByPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $stmt = $this->db->prepare("SELECT id, name, phone, email, address 
                FROM customers 
                WHERE REPLACE(REPLACE(phone, ' ', ''), '-', '') LIKE ?
                LIMIT 1");
        $stmt->execute(["%$phone%"]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function linkCallToTicket($callId, $ticketId) {
        $stmt = $this->db->prepare("UPDATE call_center_calls SET ticket_id = ? WHERE id = ?");
        return $stmt->execute([$ticketId, $callId]);
    }

    public function getDashboardStats() {
        $stats = $this->getCallStats('today');
        
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM call_center_extensions WHERE is_active = true");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['active_extensions'] = $row['count'];

        $stmt = $this->db->query("SELECT COUNT(*) as count FROM call_center_queues WHERE is_active = true");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['active_queues'] = $row['count'];

        $stmt = $this->db->query("SELECT COUNT(*) as count FROM call_center_trunks WHERE is_active = true");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['active_trunks'] = $row['count'];

        return $stats;
    }
}
