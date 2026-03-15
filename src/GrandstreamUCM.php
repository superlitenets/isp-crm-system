<?php

class GrandstreamUCM {
    private $db;
    private $host;
    private $port;
    private $username;
    private $password;
    private $cookie = null;
    private $connected = false;

    public function __construct($db) {
        $this->db = $db;
        $this->host = $this->getSetting('ucm_host') ?: '';
        $this->port = $this->getSetting('ucm_port') ?: '8443';
        $this->username = $this->getSetting('ucm_username') ?: 'admin';
        $this->password = $this->getSetting('ucm_password') ?: '';
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

    private function getBaseUrl() {
        return "https://{$this->host}:{$this->port}";
    }

    public function isConfigured() {
        return !empty($this->host) && !empty($this->password);
    }

    public function login() {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'UCM not configured. Set ucm_host, ucm_username, ucm_password in Call Centre settings.'];
        }

        $challengeResult = $this->apiRequest('POST', '/api', [
            'request' => [
                'action' => 'challenge',
                'user' => $this->username
            ]
        ], false);

        if (!$challengeResult['success']) {
            $challengeResult = $this->apiRequest('GET', '/api', [
                'action' => 'challenge',
                'user' => $this->username
            ], false);
        }

        if (!$challengeResult['success']) {
            return ['success' => false, 'error' => 'Failed to get challenge: ' . ($challengeResult['error'] ?? 'Unknown error')];
        }

        $challenge = $challengeResult['data']['response']['challenge'] ?? null;
        if (!$challenge) {
            return ['success' => false, 'error' => 'No challenge received from UCM. Verify API is enabled and user exists.'];
        }

        $tokenFormats = [
            md5($challenge . $this->password),
            md5($this->username . ':' . $this->password . ':' . $challenge),
            md5($this->password . $challenge),
        ];

        foreach ($tokenFormats as $token) {
            $loginResult = $this->apiRequest('POST', '/api', [
                'request' => [
                    'action' => 'login',
                    'user' => $this->username,
                    'token' => $token
                ]
            ], false);

            if ($loginResult['success']) {
                $response = $loginResult['data']['response'] ?? [];
                if (($response['status'] ?? -1) == 0) {
                    $this->cookie = $response['cookie'] ?? null;
                    $this->connected = true;
                    return ['success' => true, 'cookie' => $this->cookie];
                }
            }
        }

        return ['success' => false, 'error' => 'Authentication failed. Check UCM API username and password. Make sure an API user has been created under Integrations → API Configuration.'];
    }

    public function logout() {
        if ($this->cookie) {
            $this->apiRequest('POST', '/api', [
                'request' => [
                    'action' => 'logout',
                    'cookie' => $this->cookie
                ]
            ], false);
            $this->cookie = null;
            $this->connected = false;
        }
    }

    private function ensureConnected() {
        if (!$this->connected || !$this->cookie) {
            $result = $this->login();
            if (!$result['success']) {
                throw new Exception($result['error']);
            }
        }
    }

    private function apiRequest($method, $path, $params = [], $auth = true) {
        $url = $this->getBaseUrl() . $path;

        if ($auth && $this->cookie) {
            if ($method === 'GET') {
                $params['cookie'] = $this->cookie;
            } else {
                if (isset($params['request'])) {
                    $params['request']['cookie'] = $this->cookie;
                } else {
                    $params['cookie'] = $this->cookie;
                }
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => "Connection error: $error"];
        }

        $data = json_decode($response, true);
        if ($data === null) {
            return ['success' => false, 'error' => 'Invalid response from UCM', 'raw' => substr($response, 0, 500)];
        }

        return ['success' => true, 'data' => $data, 'httpCode' => $httpCode];
    }

    private function authenticatedRequest($action, $params = []) {
        $this->ensureConnected();

        $request = array_merge(['action' => $action, 'cookie' => $this->cookie], $params);
        $result = $this->apiRequest('POST', '/api', ['request' => $request], false);

        if (!$result['success']) {
            return $result;
        }

        $status = $result['data']['response']['status'] ?? -1;
        if ($status == -6) {
            $this->connected = false;
            $this->cookie = null;
            $this->ensureConnected();
            $request['cookie'] = $this->cookie;
            $result = $this->apiRequest('POST', '/api', ['request' => $request], false);
        }

        return $result;
    }

    public function testConnection() {
        $loginResult = $this->login();
        if (!$loginResult['success']) {
            return $loginResult;
        }

        $statusResult = $this->authenticatedRequest('getSystemStatus');
        $this->logout();

        if ($statusResult['success']) {
            $sys = $statusResult['data']['response'] ?? [];
            return [
                'success' => true,
                'message' => 'Connected to UCM successfully',
                'system' => [
                    'model' => $sys['system_model'] ?? 'UCM',
                    'firmware' => $sys['firmware_version'] ?? 'Unknown',
                    'uptime' => $sys['system_uptime'] ?? 'Unknown'
                ]
            ];
        }

        return ['success' => false, 'error' => 'Connected but failed to get system status'];
    }

    public function getExtensions() {
        $result = $this->authenticatedRequest('listAccount');
        if (!$result['success']) return $result;

        $accounts = $result['data']['response']['account'] ?? [];
        $extensions = [];
        foreach ($accounts as $acc) {
            $extensions[] = [
                'extension' => $acc['extension'] ?? $acc['account_name'] ?? '',
                'name' => $acc['fullname'] ?? $acc['account_name'] ?? '',
                'status' => $acc['status'] ?? 'unknown',
                'type' => $acc['account_type'] ?? 'SIP',
                'ip' => $acc['addr'] ?? ''
            ];
        }

        return ['success' => true, 'extensions' => $extensions];
    }

    public function getExtensionStatus() {
        $result = $this->authenticatedRequest('listAccountStatus');
        if (!$result['success']) return $result;

        $statuses = $result['data']['response']['account'] ?? [];
        return ['success' => true, 'statuses' => $statuses];
    }

    public function getTrunks() {
        $result = $this->authenticatedRequest('listVoIPTrunk');
        if (!$result['success']) return $result;

        $trunks = $result['data']['response']['trunk'] ?? [];
        return ['success' => true, 'trunks' => $trunks];
    }

    public function getTrunkStatus() {
        $result = $this->authenticatedRequest('listTrunkStatus');
        if (!$result['success']) return $result;

        return ['success' => true, 'statuses' => $result['data']['response'] ?? []];
    }

    public function getActiveCalls() {
        $result = $this->authenticatedRequest('listActiveCalls');
        if (!$result['success']) return $result;

        $calls = $result['data']['response']['active_calls'] ?? [];
        return ['success' => true, 'calls' => $calls, 'count' => count($calls)];
    }

    public function getCDR($startDate, $endDate, $caller = '', $callee = '', $page = 1, $limit = 50) {
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'page' => $page,
            'item_num' => $limit
        ];

        if ($caller) $params['caller'] = $caller;
        if ($callee) $params['callee'] = $callee;

        $result = $this->authenticatedRequest('cdrapi', $params);
        if (!$result['success']) return $result;

        $response = $result['data']['response'] ?? [];
        $records = $response['cdr_root'] ?? $response['cdr'] ?? [];

        return [
            'success' => true,
            'records' => $records,
            'total' => $response['total_item'] ?? count($records),
            'page' => $page
        ];
    }

    public function getRecordings($startDate = '', $endDate = '') {
        $params = [];
        if ($startDate) $params['startDate'] = $startDate;
        if ($endDate) $params['endDate'] = $endDate;

        $result = $this->authenticatedRequest('recapi', $params);
        if (!$result['success']) return $result;

        $response = $result['data']['response'] ?? [];
        return [
            'success' => true,
            'recordings' => $response['rec_root'] ?? $response['recordings'] ?? [],
            'total' => $response['total_item'] ?? 0
        ];
    }

    public function downloadRecording($filename) {
        $this->ensureConnected();

        $url = $this->getBaseUrl() . "/api?action=recapi&cookie={$this->cookie}&filedir=&filename={$filename}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $data = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        return ['success' => true, 'data' => $data, 'content_type' => $contentType, 'filename' => $filename];
    }

    public function getSystemStatus() {
        $result = $this->authenticatedRequest('getSystemStatus');
        if (!$result['success']) return $result;

        $sys = $result['data']['response'] ?? [];
        return [
            'success' => true,
            'status' => [
                'model' => $sys['system_model'] ?? 'Unknown',
                'firmware' => $sys['firmware_version'] ?? 'Unknown',
                'uptime' => $sys['system_uptime'] ?? 'Unknown',
                'cpu_usage' => $sys['cpu_usage'] ?? 'N/A',
                'memory_usage' => $sys['memory_usage'] ?? 'N/A',
                'disk_usage' => $sys['disk_usage'] ?? 'N/A',
                'active_calls' => $sys['active_calls'] ?? 0,
                'total_extensions' => $sys['total_accounts'] ?? 0
            ]
        ];
    }

    public function getQueues() {
        $result = $this->authenticatedRequest('listQueue');
        if (!$result['success']) return $result;

        $queues = $result['data']['response']['queue'] ?? [];
        return ['success' => true, 'queues' => $queues];
    }

    public function getRingGroups() {
        $result = $this->authenticatedRequest('listRingGroup');
        if (!$result['success']) return $result;

        $groups = $result['data']['response']['ring_group'] ?? [];
        return ['success' => true, 'ring_groups' => $groups];
    }

    public function getIVR() {
        $result = $this->authenticatedRequest('listIVR');
        if (!$result['success']) return $result;

        $ivrs = $result['data']['response']['ivr'] ?? [];
        return ['success' => true, 'ivrs' => $ivrs];
    }

    public function syncExtensionsToDB() {
        $result = $this->getExtensions();
        if (!$result['success']) return $result;

        $synced = 0;
        foreach ($result['extensions'] as $ext) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO call_center_extensions (extension, name, type, context, is_active, created_at)
                    VALUES (?, ?, ?, 'default', 't', NOW())
                    ON CONFLICT (extension) DO UPDATE SET
                        name = EXCLUDED.name,
                        type = EXCLUDED.type,
                        updated_at = NOW()
                ");
                $stmt->execute([$ext['extension'], $ext['name'], strtolower($ext['type'] ?: 'sip')]);
                $synced++;
            } catch (PDOException $e) {
                continue;
            }
        }

        return ['success' => true, 'synced' => $synced, 'total' => count($result['extensions'])];
    }

    public function syncCDRToDB($startDate, $endDate) {
        $result = $this->getCDR($startDate, $endDate, '', '', 1, 500);
        if (!$result['success']) return $result;

        $synced = 0;
        foreach ($result['records'] as $record) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO call_center_call_logs (
                        extension, phone_number, direction, duration,
                        status, disposition, recording_url,
                        started_at, ended_at, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ON CONFLICT DO NOTHING
                ");

                $caller = $record['src'] ?? $record['caller'] ?? '';
                $callee = $record['dst'] ?? $record['callee'] ?? '';
                $duration = $record['billsec'] ?? $record['duration'] ?? 0;
                $disposition = $record['disposition'] ?? 'UNKNOWN';
                $startTime = $record['start'] ?? $record['calldate'] ?? date('Y-m-d H:i:s');
                $endTime = $record['end'] ?? null;
                $recording = $record['recordingfile'] ?? null;
                $direction = (strlen($caller) <= 5) ? 'outbound' : 'inbound';

                $stmt->execute([
                    $direction === 'outbound' ? $caller : $callee,
                    $direction === 'outbound' ? $callee : $caller,
                    $direction,
                    (int)$duration,
                    $disposition === 'ANSWERED' ? 'completed' : 'missed',
                    $disposition,
                    $recording,
                    $startTime,
                    $endTime
                ]);
                $synced++;
            } catch (PDOException $e) {
                continue;
            }
        }

        return ['success' => true, 'synced' => $synced, 'total' => count($result['records'])];
    }

    public function getDashboardStats() {
        $stats = [];

        $sysResult = $this->getSystemStatus();
        if ($sysResult['success']) {
            $stats['system'] = $sysResult['status'];
        }

        $callsResult = $this->getActiveCalls();
        if ($callsResult['success']) {
            $stats['active_calls'] = $callsResult['count'];
            $stats['calls'] = $callsResult['calls'];
        }

        $extResult = $this->getExtensionStatus();
        if ($extResult['success']) {
            $statuses = $extResult['statuses'];
            $stats['extensions_total'] = count($statuses);
            $stats['extensions_online'] = count(array_filter($statuses, function($s) {
                return ($s['status'] ?? '') === 'Registered' || ($s['status'] ?? '') === 'OK';
            }));
        }

        $trunkResult = $this->getTrunkStatus();
        if ($trunkResult['success']) {
            $stats['trunks'] = $trunkResult['statuses'];
        }

        return ['success' => true, 'stats' => $stats];
    }

    public function __destruct() {
        $this->logout();
    }
}
