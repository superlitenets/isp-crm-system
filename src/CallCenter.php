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
        $this->ami_host = getenv('FREEPBX_HOST') ?: 'localhost';
        $this->ami_port = getenv('FREEPBX_AMI_PORT') ?: 5038;
        $this->ami_user = getenv('FREEPBX_AMI_USER') ?: 'crmadmin';
        $this->ami_pass = getenv('FREEPBX_AMI_PASS') ?: 'crmami2025';
    }

    // AMI Connection
    public function connectAMI() {
        $this->socket = @fsockopen($this->ami_host, $this->ami_port, $errno, $errstr, 5);
        if (!$this->socket) {
            return ['success' => false, 'error' => "Failed to connect: $errstr ($errno)"];
        }

        // Read welcome message
        fgets($this->socket);

        // Login
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

    // Originate a call (click-to-call)
    public function originateCall($extension, $destination, $customerId = null, $ticketId = null, $callerid = null) {
        if (!$this->connected) {
            $result = $this->connectAMI();
            if (!$result['success']) return $result;
        }

        $params = [
            'Action' => 'Originate',
            'Channel' => "SIP/$extension",
            'Exten' => $destination,
            'Context' => 'from-internal',
            'Priority' => 1,
            'Async' => 'true',
            'Timeout' => 30000
        ];

        if ($callerid) {
            $params['CallerID'] = $callerid;
        }

        $response = $this->sendCommand($params);
        $this->disconnectAMI();

        if (strpos($response, 'Success') !== false) {
            $this->logCall($extension, $destination, 'outbound', [
                'customer_id' => $customerId,
                'ticket_id' => $ticketId,
                'disposition' => 'CALLING'
            ]);
            return ['success' => true, 'message' => 'Call initiated'];
        }

        return ['success' => false, 'error' => 'Failed to originate call'];
    }

    // Get queue status
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

    // Pause/unpause agent
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

    // Hangup a call
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

    // Transfer call
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

    // Get active channels
    public function getActiveChannels() {
        if (!$this->connected) {
            $result = $this->connectAMI();
            if (!$result['success']) return $result;
        }

        $response = $this->sendCommand(['Action' => 'CoreShowChannels']);
        $this->disconnectAMI();

        return ['success' => true, 'data' => $response];
    }

    // Database Operations
    
    // Extensions
    public function getExtensions($activeOnly = true) {
        $sql = "SELECT e.*, u.name as user_name, u.email as user_email
                FROM call_center_extensions e
                LEFT JOIN users u ON e.user_id = u.id";
        if ($activeOnly) {
            $sql .= " WHERE e.is_active = true";
        }
        $sql .= " ORDER BY e.extension";
        
        $result = pg_query($this->db, $sql);
        return pg_fetch_all($result) ?: [];
    }

    public function getExtension($id) {
        $sql = "SELECT * FROM call_center_extensions WHERE id = $1";
        $result = pg_query_params($this->db, $sql, [$id]);
        return pg_fetch_assoc($result);
    }

    public function getExtensionByUserId($userId) {
        $sql = "SELECT * FROM call_center_extensions WHERE user_id = $1 AND is_active = true";
        $result = pg_query_params($this->db, $sql, [$userId]);
        return pg_fetch_assoc($result);
    }

    public function saveExtension($data) {
        if (isset($data['id']) && $data['id']) {
            $sql = "UPDATE call_center_extensions SET 
                    user_id = $1, extension = $2, name = $3, secret = $4, 
                    caller_id = $5, device_type = $6, is_active = $7, updated_at = NOW()
                    WHERE id = $8";
            $params = [
                $data['user_id'] ?: null,
                $data['extension'],
                $data['name'],
                $data['secret'] ?? null,
                $data['caller_id'] ?? null,
                $data['device_type'] ?? 'softphone',
                $data['is_active'] ?? true,
                $data['id']
            ];
            pg_query_params($this->db, $sql, $params);
            return $data['id'];
        } else {
            $sql = "INSERT INTO call_center_extensions 
                    (user_id, extension, name, secret, caller_id, device_type, is_active)
                    VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id";
            $params = [
                $data['user_id'] ?: null,
                $data['extension'],
                $data['name'],
                $data['secret'] ?? null,
                $data['caller_id'] ?? null,
                $data['device_type'] ?? 'softphone',
                $data['is_active'] ?? true
            ];
            $result = pg_query_params($this->db, $sql, $params);
            $row = pg_fetch_assoc($result);
            return $row['id'];
        }
    }

    public function deleteExtension($id) {
        $sql = "UPDATE call_center_extensions SET is_active = false WHERE id = $1";
        return pg_query_params($this->db, $sql, [$id]);
    }

    // Queues
    public function getQueues($activeOnly = true) {
        $sql = "SELECT q.*, 
                (SELECT COUNT(*) FROM call_center_queue_members qm WHERE qm.queue_id = q.id AND qm.is_active = true) as member_count
                FROM call_center_queues q";
        if ($activeOnly) {
            $sql .= " WHERE q.is_active = true";
        }
        $sql .= " ORDER BY q.name";
        
        $result = pg_query($this->db, $sql);
        return pg_fetch_all($result) ?: [];
    }

    public function getQueue($id) {
        $sql = "SELECT * FROM call_center_queues WHERE id = $1";
        $result = pg_query_params($this->db, $sql, [$id]);
        return pg_fetch_assoc($result);
    }

    public function saveQueue($data) {
        if (isset($data['id']) && $data['id']) {
            $sql = "UPDATE call_center_queues SET 
                    name = $1, extension = $2, strategy = $3, timeout = $4,
                    wrapup_time = $5, max_wait_time = $6, is_active = $7, updated_at = NOW()
                    WHERE id = $8";
            $params = [
                $data['name'],
                $data['extension'],
                $data['strategy'] ?? 'ringall',
                $data['timeout'] ?? 30,
                $data['wrapup_time'] ?? 5,
                $data['max_wait_time'] ?? 300,
                $data['is_active'] ?? true,
                $data['id']
            ];
            pg_query_params($this->db, $sql, $params);
            return $data['id'];
        } else {
            $sql = "INSERT INTO call_center_queues 
                    (name, extension, strategy, timeout, wrapup_time, max_wait_time, is_active)
                    VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id";
            $params = [
                $data['name'],
                $data['extension'],
                $data['strategy'] ?? 'ringall',
                $data['timeout'] ?? 30,
                $data['wrapup_time'] ?? 5,
                $data['max_wait_time'] ?? 300,
                $data['is_active'] ?? true
            ];
            $result = pg_query_params($this->db, $sql, $params);
            $row = pg_fetch_assoc($result);
            return $row['id'];
        }
    }

    // Queue Members
    public function getQueueMembers($queueId) {
        $sql = "SELECT qm.*, e.extension, e.name as extension_name, u.name as user_name
                FROM call_center_queue_members qm
                JOIN call_center_extensions e ON qm.extension_id = e.id
                LEFT JOIN users u ON e.user_id = u.id
                WHERE qm.queue_id = $1 AND qm.is_active = true
                ORDER BY qm.penalty, e.extension";
        $result = pg_query_params($this->db, $sql, [$queueId]);
        return pg_fetch_all($result) ?: [];
    }

    public function addQueueMember($queueId, $extensionId, $penalty = 0) {
        $sql = "INSERT INTO call_center_queue_members (queue_id, extension_id, penalty)
                VALUES ($1, $2, $3)
                ON CONFLICT (queue_id, extension_id) DO UPDATE SET is_active = true, penalty = $3";
        return pg_query_params($this->db, $sql, [$queueId, $extensionId, $penalty]);
    }

    public function removeQueueMember($queueId, $extensionId) {
        $sql = "UPDATE call_center_queue_members SET is_active = false 
                WHERE queue_id = $1 AND extension_id = $2";
        return pg_query_params($this->db, $sql, [$queueId, $extensionId]);
    }

    // Call Logs
    public function getCalls($filters = [], $limit = 100, $offset = 0) {
        $where = ["1=1"];
        $params = [];
        $paramNum = 1;

        if (!empty($filters['date_from'])) {
            $where[] = "c.call_date >= $$paramNum";
            $params[] = $filters['date_from'];
            $paramNum++;
        }
        if (!empty($filters['date_to'])) {
            $where[] = "c.call_date <= $$paramNum";
            $params[] = $filters['date_to'];
            $paramNum++;
        }
        if (!empty($filters['extension_id'])) {
            $where[] = "c.extension_id = $$paramNum";
            $params[] = $filters['extension_id'];
            $paramNum++;
        }
        if (!empty($filters['customer_id'])) {
            $where[] = "c.customer_id = $$paramNum";
            $params[] = $filters['customer_id'];
            $paramNum++;
        }
        if (!empty($filters['direction'])) {
            $where[] = "c.direction = $$paramNum";
            $params[] = $filters['direction'];
            $paramNum++;
        }
        if (!empty($filters['disposition'])) {
            $where[] = "c.disposition = $$paramNum";
            $params[] = $filters['disposition'];
            $paramNum++;
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

        $result = pg_query_params($this->db, $sql, $params);
        return pg_fetch_all($result) ?: [];
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

        $result = pg_query($this->db, $sql);
        return pg_fetch_assoc($result);
    }

    public function logCall($src, $dst, $direction, $data = []) {
        $sql = "INSERT INTO call_center_calls 
                (uniqueid, call_date, src, dst, direction, disposition, duration, billsec, 
                 extension_id, customer_id, ticket_id, notes)
                VALUES ($1, NOW(), $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
                RETURNING id";
        
        $params = [
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
        ];

        $result = pg_query_params($this->db, $sql, $params);
        $row = pg_fetch_assoc($result);
        return $row['id'];
    }

    // Agent status
    public function logAgentStatus($extensionId, $status, $reason = null) {
        // Close previous status
        $sql = "UPDATE call_center_agent_status 
                SET ended_at = NOW(), duration = EXTRACT(EPOCH FROM (NOW() - started_at))::INTEGER
                WHERE extension_id = $1 AND ended_at IS NULL";
        pg_query_params($this->db, $sql, [$extensionId]);

        // Log new status
        $sql = "INSERT INTO call_center_agent_status (extension_id, status, status_reason)
                VALUES ($1, $2, $3)";
        return pg_query_params($this->db, $sql, [$extensionId, $status, $reason]);
    }

    public function getAgentStatusHistory($extensionId, $limit = 50) {
        $sql = "SELECT * FROM call_center_agent_status 
                WHERE extension_id = $1 
                ORDER BY started_at DESC LIMIT $2";
        $result = pg_query_params($this->db, $sql, [$extensionId, $limit]);
        return pg_fetch_all($result) ?: [];
    }

    // SIP Trunks
    public function getTrunks($activeOnly = true) {
        $sql = "SELECT * FROM call_center_trunks";
        if ($activeOnly) {
            $sql .= " WHERE is_active = true";
        }
        $sql .= " ORDER BY name";
        
        $result = pg_query($this->db, $sql);
        return pg_fetch_all($result) ?: [];
    }

    public function getTrunk($id) {
        $sql = "SELECT * FROM call_center_trunks WHERE id = $1";
        $result = pg_query_params($this->db, $sql, [$id]);
        return pg_fetch_assoc($result);
    }

    public function saveTrunk($data) {
        if (isset($data['id']) && $data['id']) {
            $sql = "UPDATE call_center_trunks SET 
                    name = $1, trunk_type = $2, host = $3, port = $4, username = $5,
                    secret = $6, codecs = $7, max_channels = $8, registration = $9,
                    is_active = $10, updated_at = NOW()
                    WHERE id = $11";
            $params = [
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
            ];
            pg_query_params($this->db, $sql, $params);
            return $data['id'];
        } else {
            $sql = "INSERT INTO call_center_trunks 
                    (name, trunk_type, host, port, username, secret, codecs, max_channels, registration, is_active)
                    VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10) RETURNING id";
            $params = [
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
            ];
            $result = pg_query_params($this->db, $sql, $params);
            $row = pg_fetch_assoc($result);
            return $row['id'];
        }
    }

    public function deleteTrunk($id) {
        $sql = "UPDATE call_center_trunks SET is_active = false WHERE id = $1";
        return pg_query_params($this->db, $sql, [$id]);
    }

    // Customer phone lookup
    public function findCustomerByPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $sql = "SELECT id, name, phone, email, address 
                FROM customers 
                WHERE REPLACE(REPLACE(phone, ' ', ''), '-', '') LIKE $1
                LIMIT 1";
        $result = pg_query_params($this->db, $sql, ["%$phone%"]);
        return pg_fetch_assoc($result);
    }

    // Link call to ticket
    public function linkCallToTicket($callId, $ticketId) {
        $sql = "UPDATE call_center_calls SET ticket_id = $1 WHERE id = $2";
        return pg_query_params($this->db, $sql, [$ticketId, $callId]);
    }

    // Dashboard stats
    public function getDashboardStats() {
        $stats = $this->getCallStats('today');
        
        // Active agents
        $sql = "SELECT COUNT(*) as count FROM call_center_extensions WHERE is_active = true";
        $result = pg_query($this->db, $sql);
        $row = pg_fetch_assoc($result);
        $stats['active_extensions'] = $row['count'];

        // Active queues
        $sql = "SELECT COUNT(*) as count FROM call_center_queues WHERE is_active = true";
        $result = pg_query($this->db, $sql);
        $row = pg_fetch_assoc($result);
        $stats['active_queues'] = $row['count'];

        // Trunks
        $sql = "SELECT COUNT(*) as count FROM call_center_trunks WHERE is_active = true";
        $result = pg_query($this->db, $sql);
        $row = pg_fetch_assoc($result);
        $stats['active_trunks'] = $row['count'];

        return $stats;
    }
}
