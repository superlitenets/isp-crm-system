<?php

namespace App;

class BioTimeCloud extends BiometricDevice {
    private ?string $token = null;
    private ?string $tokenExpiry = null;
    private string $baseUrl = '';
    private int $timeout = 30;
    private ?int $lastTransactionId = null;
    
    public function __construct(int $deviceId, string $ip, int $port = 8090, ?string $username = null, ?string $password = null) {
        parent::__construct($deviceId, $ip, $port ?: 8090, $username, $password);
        $this->baseUrl = "http://{$ip}:{$port}";
    }
    
    public function setBaseUrl(string $url): void {
        $this->baseUrl = rtrim($url, '/');
    }
    
    public function connect(): bool {
        return $this->authenticate();
    }
    
    public function disconnect(): void {
        $this->token = null;
        $this->tokenExpiry = null;
    }
    
    private function authenticate(): bool {
        if ($this->token && $this->tokenExpiry && strtotime($this->tokenExpiry) > time()) {
            return true;
        }
        
        $url = $this->baseUrl . '/jwt-api-token-auth/';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'username' => $this->username,
                'password' => $this->password
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->setError("Connection failed: $error");
            return false;
        }
        
        if ($httpCode !== 200) {
            $this->setError("Authentication failed: HTTP $httpCode");
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['token'])) {
            $this->setError("Authentication failed: No token received");
            return false;
        }
        
        $this->token = $data['token'];
        $this->tokenExpiry = date('Y-m-d H:i:s', strtotime('+4 hours'));
        
        return true;
    }
    
    private function apiRequest(string $endpoint, string $method = 'GET', ?array $data = null): ?array {
        if (!$this->authenticate()) {
            return null;
        }
        
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: JWT ' . $this->token
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ];
        
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if ($data) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        } elseif ($method === 'PATCH') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
            if ($data) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        } elseif ($method === 'DELETE') {
            $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->setError("API request failed: $error");
            return null;
        }
        
        if ($httpCode >= 400) {
            $this->setError("API error: HTTP $httpCode");
            return null;
        }
        
        return json_decode($response, true) ?? [];
    }
    
    public function testConnection(): array {
        $result = [
            'success' => false,
            'device_name' => 'BioTime Cloud',
            'serial_number' => '',
            'version' => '',
            'message' => '',
            'devices_count' => 0,
            'employees_count' => 0
        ];
        
        if (!$this->authenticate()) {
            $result['message'] = $this->lastError['message'] ?? 'Authentication failed';
            return $result;
        }
        
        $devices = $this->apiRequest('/iclock/api/terminals/');
        if ($devices !== null) {
            $result['devices_count'] = $devices['count'] ?? count($devices['data'] ?? []);
        }
        
        $employees = $this->apiRequest('/personnel/api/employees/?page_size=1');
        if ($employees !== null) {
            $result['employees_count'] = $employees['count'] ?? 0;
        }
        
        $result['success'] = true;
        $result['message'] = "Connected successfully. Found {$result['devices_count']} device(s) and {$result['employees_count']} employee(s).";
        $result['version'] = 'BioTime 8.5 API';
        
        return $result;
    }
    
    public function getAttendance(?string $since = null, ?string $until = null): array {
        $logs = [];
        $page = 1;
        $pageSize = 100;
        
        do {
            $params = "?page=$page&page_size=$pageSize";
            
            if ($since) {
                $params .= "&punch_time__gte=" . urlencode($since);
            }
            if ($until) {
                $params .= "&punch_time__lte=" . urlencode($until);
            }
            
            $response = $this->apiRequest("/iclock/api/transactions/$params");
            
            if ($response === null) {
                break;
            }
            
            $transactions = $response['data'] ?? $response['results'] ?? [];
            
            foreach ($transactions as $tx) {
                $logs[] = [
                    'device_user_id' => $tx['emp_code'] ?? $tx['emp'] ?? '',
                    'log_time' => $tx['punch_time'] ?? '',
                    'direction' => $this->mapPunchState($tx['punch_state'] ?? 0),
                    'verification_type' => $this->mapVerifyType($tx['verify_type'] ?? 0),
                    'raw_data' => json_encode($tx),
                    'biotime_id' => $tx['id'] ?? null,
                    'terminal_sn' => $tx['terminal_sn'] ?? '',
                    'terminal_alias' => $tx['terminal_alias'] ?? ''
                ];
            }
            
            $hasMore = isset($response['next']) && $response['next'] !== null;
            $page++;
            
        } while ($hasMore && $page <= 100);
        
        return $logs;
    }
    
    public function getNewAttendance(?int $lastId = null): array {
        $logs = [];
        $page = 1;
        $pageSize = 100;
        
        do {
            $params = "?page=$page&page_size=$pageSize&ordering=id";
            
            if ($lastId) {
                $params .= "&id__gt=$lastId";
            }
            
            $response = $this->apiRequest("/iclock/api/transactions/$params");
            
            if ($response === null) {
                break;
            }
            
            $transactions = $response['data'] ?? $response['results'] ?? [];
            
            foreach ($transactions as $tx) {
                $logs[] = [
                    'device_user_id' => $tx['emp_code'] ?? $tx['emp'] ?? '',
                    'log_time' => $tx['punch_time'] ?? '',
                    'direction' => $this->mapPunchState($tx['punch_state'] ?? 0),
                    'verification_type' => $this->mapVerifyType($tx['verify_type'] ?? 0),
                    'raw_data' => json_encode($tx),
                    'biotime_id' => $tx['id'] ?? null,
                    'terminal_sn' => $tx['terminal_sn'] ?? '',
                    'terminal_alias' => $tx['terminal_alias'] ?? ''
                ];
                
                if (isset($tx['id']) && $tx['id'] > ($this->lastTransactionId ?? 0)) {
                    $this->lastTransactionId = $tx['id'];
                }
            }
            
            $hasMore = isset($response['next']) && $response['next'] !== null;
            $page++;
            
        } while ($hasMore && $page <= 100);
        
        return $logs;
    }
    
    public function getLastTransactionId(): ?int {
        return $this->lastTransactionId;
    }
    
    private function mapPunchState(int $state): string {
        return match($state) {
            0, 4 => 'in',
            1, 5 => 'out',
            2 => 'break_out',
            3 => 'break_in',
            default => 'unknown'
        };
    }
    
    private function mapVerifyType(int $type): string {
        return match($type) {
            0 => 'password',
            1 => 'fingerprint',
            2 => 'card',
            3 => 'password',
            4 => 'fingerprint',
            15 => 'face',
            default => 'other'
        };
    }
    
    public function getUsers(): array {
        $users = [];
        $page = 1;
        $pageSize = 100;
        
        do {
            $params = "?page=$page&page_size=$pageSize";
            $response = $this->apiRequest("/personnel/api/employees/$params");
            
            if ($response === null) {
                break;
            }
            
            $employees = $response['data'] ?? $response['results'] ?? [];
            
            foreach ($employees as $emp) {
                $users[] = [
                    'user_id' => $emp['emp_code'] ?? $emp['id'] ?? '',
                    'name' => trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')),
                    'card_number' => $emp['card_no'] ?? '',
                    'department' => $emp['department'] ?? null,
                    'privilege' => 0,
                    'biotime_id' => $emp['id'] ?? null,
                    'raw_data' => json_encode($emp)
                ];
            }
            
            $hasMore = isset($response['next']) && $response['next'] !== null;
            $page++;
            
        } while ($hasMore && $page <= 50);
        
        return $users;
    }
    
    public function getDevices(): array {
        $devices = [];
        $page = 1;
        $pageSize = 50;
        
        do {
            $params = "?page=$page&page_size=$pageSize";
            $response = $this->apiRequest("/iclock/api/terminals/$params");
            
            if ($response === null) {
                break;
            }
            
            $terminals = $response['data'] ?? $response['results'] ?? [];
            
            foreach ($terminals as $term) {
                $devices[] = [
                    'id' => $term['id'] ?? 0,
                    'sn' => $term['sn'] ?? '',
                    'alias' => $term['terminal_name'] ?? $term['alias'] ?? '',
                    'ip_address' => $term['ip_address'] ?? '',
                    'state' => $term['state'] ?? 0,
                    'last_activity' => $term['last_activity'] ?? '',
                    'push_ver' => $term['push_ver'] ?? '',
                    'raw_data' => json_encode($term)
                ];
            }
            
            $hasMore = isset($response['next']) && $response['next'] !== null;
            $page++;
            
        } while ($hasMore && $page <= 10);
        
        return $devices;
    }
    
    public function createEmployee(array $data): ?int {
        $response = $this->apiRequest('/personnel/api/employees/', 'POST', [
            'emp_code' => $data['emp_code'] ?? '',
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'department' => $data['department'] ?? 1,
            'hire_date' => $data['hire_date'] ?? date('Y-m-d')
        ]);
        
        if ($response && isset($response['id'])) {
            return (int) $response['id'];
        }
        
        return null;
    }
    
    public function updateEmployee(int $id, array $data): bool {
        $response = $this->apiRequest("/personnel/api/employees/$id/", 'PATCH', $data);
        return $response !== null;
    }
    
    public function deleteEmployee(int $id): bool {
        $response = $this->apiRequest("/personnel/api/employees/$id/", 'DELETE');
        return $response !== null || (isset($this->lastError['code']) && $this->lastError['code'] === 204);
    }
    
    public function getDepartments(): array {
        $departments = [];
        $response = $this->apiRequest('/personnel/api/departments/');
        
        if ($response === null) {
            return [];
        }
        
        $depts = $response['data'] ?? $response['results'] ?? [];
        
        foreach ($depts as $dept) {
            $departments[] = [
                'id' => $dept['id'] ?? 0,
                'code' => $dept['dept_code'] ?? '',
                'name' => $dept['dept_name'] ?? '',
                'parent_id' => $dept['parent_dept'] ?? null
            ];
        }
        
        return $departments;
    }
}
