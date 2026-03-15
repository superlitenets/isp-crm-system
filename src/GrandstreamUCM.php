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

    private $apiPath = '/api';

    public function login() {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'UCM not configured. Set ucm_host, ucm_username, ucm_password in Call Centre settings.'];
        }

        $apiPaths = ['/api', '/cgi'];
        $challengeAttempts = [];
        $challenge = null;
        $workingPath = null;

        foreach ($apiPaths as $path) {
            $challengeResult = $this->apiRequest('POST', $path, [
                'request' => [
                    'action' => 'challenge',
                    'user' => $this->username
                ]
            ], false);

            if (!$challengeResult['success']) {
                $challengeResult = $this->apiRequest('POST', $path, [
                    'action' => 'challenge',
                    'user' => $this->username
                ], false);
            }

            if (!$challengeResult['success']) {
                $challengeResult = $this->apiRequest('GET', $path, [
                    'action' => 'challenge',
                    'user' => $this->username
                ], false);
            }

            if ($challengeResult['success']) {
                $data = $challengeResult['data'] ?? [];
                $challenge = $data['response']['challenge'] 
                    ?? $data['body']['challenge'] 
                    ?? $data['challenge'] 
                    ?? null;
                if ($challenge) {
                    $workingPath = $path;
                    break;
                }
            }

            $challengeAttempts[] = $path . ': ' . ($challengeResult['error'] ?? 'no challenge in response');
        }

        if (!$challenge) {
            $debugInfo = implode('; ', $challengeAttempts);
            return ['success' => false, 'error' => "Failed to get challenge from UCM. Tried endpoints: $debugInfo. Verify: 1) UCM IP/port are correct, 2) API is enabled in UCM web UI under Value-added Features → API Configuration, 3) API user exists."];
        }

        $this->apiPath = $workingPath;

        $tokenFormats = [
            md5($challenge . $this->password),
            md5($this->username . ':' . $this->password . ':' . $challenge),
            md5($this->password . $challenge),
        ];

        foreach ($tokenFormats as $token) {
            $loginPayloads = [
                ['request' => ['action' => 'login', 'user' => $this->username, 'token' => $token]],
                ['action' => 'login', 'user' => $this->username, 'token' => $token],
            ];

            foreach ($loginPayloads as $payload) {
                $loginResult = $this->apiRequest('POST', $this->apiPath, $payload, false);

                if ($loginResult['success']) {
                    $data = $loginResult['data'] ?? [];
                    $response = $data['response'] ?? $data['body'] ?? $data;
                    $status = $response['status'] ?? -1;
                    if ($status == 0) {
                        $this->cookie = $response['cookie'] ?? null;
                        $this->connected = true;
                        return ['success' => true, 'cookie' => $this->cookie];
                    }
                }
            }
        }

        return ['success' => false, 'error' => 'Authentication failed. Challenge received OK but login rejected. Check: 1) API user password is correct, 2) The API user (not the web admin) credentials are being used.'];
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

        if (empty($response)) {
            return ['success' => false, 'error' => "Empty response from UCM (HTTP $httpCode). Check if the port is correct — try 443 if 8443 doesn't work."];
        }

        $data = json_decode($response, true);
        if ($data === null) {
            if (stripos($response, '<html') !== false || stripos($response, '<!DOCTYPE') !== false) {
                return ['success' => false, 'error' => "UCM returned an HTML page instead of JSON (HTTP $httpCode). The API endpoint may be wrong — ensure API is enabled under Value-added Features → API Configuration, and try port 8443 or 443."];
            }
            if (stripos($response, '<?xml') !== false) {
                $xml = @simplexml_load_string($response);
                if ($xml) {
                    $data = json_decode(json_encode($xml), true);
                    return ['success' => true, 'data' => $data, 'httpCode' => $httpCode];
                }
            }
            return ['success' => false, 'error' => "Invalid response from UCM (HTTP $httpCode, not JSON). First 200 chars: " . substr($response, 0, 200)];
        }

        return ['success' => true, 'data' => $data, 'httpCode' => $httpCode];
    }

    private function authenticatedRequest($action, $params = []) {
        $this->ensureConnected();

        $request = array_merge(['action' => $action, 'cookie' => $this->cookie], $params);
        $result = $this->apiRequest('POST', $this->apiPath, ['request' => $request], false);

        if (!$result['success']) {
            $result = $this->apiRequest('POST', $this->apiPath, $request, false);
        }

        if ($result['success']) {
            $response = $result['data']['response'] ?? $result['data']['body'] ?? $result['data'];
            $status = $response['status'] ?? -1;
            if ($status == -6) {
                $this->connected = false;
                $this->cookie = null;
                $this->ensureConnected();
                $request['cookie'] = $this->cookie;
                $result = $this->apiRequest('POST', $this->apiPath, ['request' => $request], false);
                if (!$result['success']) {
                    $result = $this->apiRequest('POST', $this->apiPath, $request, false);
                }
            }
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
            $sys = $this->extractResponse($statusResult);
            return [
                'success' => true,
                'message' => 'Connected to UCM successfully (via ' . $this->apiPath . ')',
                'system' => [
                    'model' => $sys['system_model'] ?? $sys['model_name'] ?? 'UCM',
                    'firmware' => $sys['firmware_version'] ?? $sys['prog_version'] ?? 'Unknown',
                    'uptime' => $sys['system_uptime'] ?? $sys['up_time'] ?? 'Unknown'
                ]
            ];
        }

        return ['success' => true, 'message' => 'Connected and authenticated to UCM successfully (via ' . $this->apiPath . ')', 'system' => ['model' => 'UCM', 'firmware' => 'Unknown', 'uptime' => 'Unknown']];
    }

    private function extractResponse($result) {
        $data = $result['data'] ?? [];
        return $data['response'] ?? $data['body'] ?? $data;
    }

    public function getExtensions() {
        $result = $this->authenticatedRequest('listAccount');
        if (!$result['success']) return $result;

        $resp = $this->extractResponse($result);
        $accounts = $resp['account'] ?? $resp['extension'] ?? [];
        if (!is_array($accounts)) $accounts = [];
        if (isset($accounts['extension'])) $accounts = [$accounts];

        $extensions = [];
        foreach ($accounts as $acc) {
            $extensions[] = [
                'extension' => $acc['extension'] ?? $acc['account_name'] ?? '',
                'name' => $acc['fullname'] ?? $acc['callerid'] ?? $acc['account_name'] ?? '',
                'status' => $acc['status'] ?? 'unknown',
                'type' => $acc['account_type'] ?? 'SIP',
                'ip' => $acc['addr'] ?? $acc['ip'] ?? ''
            ];
        }

        return ['success' => true, 'extensions' => $extensions];
    }

    public function getExtensionStatus() {
        $result = $this->authenticatedRequest('listAccountStatus');
        if (!$result['success']) return $result;

        $resp = $this->extractResponse($result);
        $statuses = $resp['account'] ?? [];
        return ['success' => true, 'statuses' => $statuses];
    }

    public function getTrunks() {
        $result = $this->authenticatedRequest('listVoIPTrunk');
        if (!$result['success']) return $result;

        $resp = $this->extractResponse($result);
        $trunks = $resp['trunk'] ?? [];
        return ['success' => true, 'trunks' => $trunks];
    }

    public function getTrunkStatus() {
        $result = $this->authenticatedRequest('listTrunkStatus');
        if (!$result['success']) return $result;

        return ['success' => true, 'statuses' => $this->extractResponse($result)];
    }

    public function getActiveCalls() {
        $result = $this->authenticatedRequest('listActiveCalls');
        if (!$result['success']) return $result;

        $resp = $this->extractResponse($result);
        $calls = $resp['active_calls'] ?? $resp['activecalls'] ?? [];
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

        $resp = $this->extractResponse($result);
        $records = $resp['cdr_root'] ?? $resp['cdr'] ?? [];

        return [
            'success' => true,
            'records' => $records,
            'total' => $resp['total_item'] ?? count($records),
            'page' => $page
        ];
    }

    public function originateCall($extension, $destination, $callerName = '') {
        $params = [
            'caller' => $extension,
            'callee' => $destination,
        ];
        if ($callerName) {
            $params['callername'] = $callerName;
        }

        $result = $this->authenticatedRequest('callOriginate', $params);
        if (!$result['success']) {
            $result2 = $this->authenticatedRequest('Originate', [
                'channel' => "PJSIP/$extension",
                'exten' => $destination,
                'context' => 'from-internal',
                'priority' => '1',
                'async' => 'true',
                'timeout' => '30000'
            ]);
            if ($result2['success']) {
                $resp2 = $this->extractResponse($result2);
                if (($resp2['status'] ?? -1) == 0) {
                    return ['success' => true, 'message' => 'Call initiated via UCM'];
                }
            }
            return ['success' => false, 'error' => 'Failed to originate call: ' . ($result['error'] ?? 'UCM rejected the request')];
        }

        $resp = $this->extractResponse($result);
        if (($resp['status'] ?? -1) == 0) {
            return ['success' => true, 'message' => 'Call initiated via UCM'];
        }

        return ['success' => false, 'error' => 'UCM returned error status: ' . ($resp['status'] ?? 'unknown')];
    }

    public function hangupCall($channel) {
        $result = $this->authenticatedRequest('callHangup', [
            'channel' => $channel
        ]);
        if (!$result['success']) return ['success' => false, 'error' => 'Failed to hangup call'];
        $resp = $this->extractResponse($result);
        return ($resp['status'] ?? -1) == 0
            ? ['success' => true]
            : ['success' => false, 'error' => 'Failed to hangup call'];
    }

    public function transferCall($channel, $destination) {
        $result = $this->authenticatedRequest('callTransfer', [
            'channel' => $channel,
            'exten' => $destination
        ]);
        if (!$result['success']) return ['success' => false, 'error' => 'Failed to transfer call'];
        $resp = $this->extractResponse($result);
        return ($resp['status'] ?? -1) == 0
            ? ['success' => true]
            : ['success' => false, 'error' => 'Failed to transfer call'];
    }

    public function getRecordings($startDate = '', $endDate = '') {
        $params = [];
        if ($startDate) $params['startDate'] = $startDate;
        if ($endDate) $params['endDate'] = $endDate;

        $result = $this->authenticatedRequest('recapi', $params);
        if (!$result['success']) return $result;

        $resp = $this->extractResponse($result);
        return [
            'success' => true,
            'recordings' => $resp['rec_root'] ?? $resp['recordings'] ?? [],
            'total' => $resp['total_item'] ?? 0
        ];
    }

    public function downloadRecording($filename) {
        $this->ensureConnected();

        $url = $this->getBaseUrl() . "{$this->apiPath}?action=recapi&cookie={$this->cookie}&filedir=&filename={$filename}";

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

        $sys = $this->extractResponse($result);
        return [
            'success' => true,
            'status' => [
                'model' => $sys['system_model'] ?? $sys['model_name'] ?? 'Unknown',
                'firmware' => $sys['firmware_version'] ?? $sys['prog_version'] ?? 'Unknown',
                'uptime' => $sys['system_uptime'] ?? $sys['up_time'] ?? 'Unknown',
                'cpu_usage' => $sys['cpu_usage'] ?? 'N/A',
                'memory_usage' => $sys['memory_usage'] ?? $sys['mem_usage'] ?? 'N/A',
                'disk_usage' => $sys['disk_usage'] ?? 'N/A',
                'active_calls' => $sys['active_calls'] ?? 0,
                'total_extensions' => $sys['total_accounts'] ?? 0
            ]
        ];
    }

    public function getQueues() {
        $result = $this->authenticatedRequest('listQueue');
        if (!$result['success']) return $result;

        $resp = $this->extractResponse($result);
        $queues = $resp['queue'] ?? [];
        return ['success' => true, 'queues' => $queues];
    }

    public function getRingGroups() {
        $result = $this->authenticatedRequest('listRingGroup');
        if (!$result['success']) return $result;

        $resp = $this->extractResponse($result);
        $groups = $resp['ring_group'] ?? [];
        return ['success' => true, 'ring_groups' => $groups];
    }

    public function getIVR() {
        $result = $this->authenticatedRequest('listIVR');
        if (!$result['success']) return $result;

        $resp = $this->extractResponse($result);
        $ivrs = $resp['ivr'] ?? [];
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
