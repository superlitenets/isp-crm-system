<?php

namespace App;

class HikvisionDevice extends BiometricDevice {
    
    public function __construct(int $deviceId, string $ip, int $port = 80, ?string $username = null, ?string $password = null) {
        parent::__construct($deviceId, $ip, $port ?: 80, $username ?: 'admin', $password);
    }
    
    public function connect(): bool {
        $result = $this->testConnection();
        return $result['success'];
    }
    
    public function disconnect(): void {
    }
    
    public function testConnection(): array {
        $result = [
            'success' => false,
            'device_name' => '',
            'serial_number' => '',
            'version' => '',
            'message' => ''
        ];
        
        $response = $this->sendRequest('/ISAPI/System/deviceInfo');
        
        if ($response['code'] === 200 && !empty($response['body'])) {
            $xml = @simplexml_load_string($response['body']);
            
            if ($xml) {
                $result['success'] = true;
                $result['device_name'] = (string)($xml->deviceName ?? 'Hikvision Device');
                $result['serial_number'] = (string)($xml->serialNumber ?? 'Unknown');
                $result['version'] = (string)($xml->firmwareVersion ?? 'Unknown');
                $result['message'] = 'Connected successfully';
            } else {
                $result['message'] = 'Failed to parse device info';
            }
        } else {
            $result['message'] = $response['error'] ?? 'Connection failed (HTTP ' . $response['code'] . ')';
        }
        
        return $result;
    }
    
    public function getAttendance(?string $since = null, ?string $until = null): array {
        $attendance = [];
        
        $startTime = $since ? date('Y-m-d\TH:i:s\Z', strtotime($since)) : date('Y-m-d\T00:00:00\Z');
        $endTime = $until ? date('Y-m-d\TH:i:s\Z', strtotime($until)) : date('Y-m-d\T23:59:59\Z');
        
        $searchPosition = 0;
        $maxResults = 30;
        $hasMore = true;
        
        while ($hasMore) {
            $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<AcsEventCond>
    <searchID>0</searchID>
    <searchResultPosition>{$searchPosition}</searchResultPosition>
    <maxResults>{$maxResults}</maxResults>
    <major>5</major>
    <minor>75</minor>
    <startTime>{$startTime}</startTime>
    <endTime>{$endTime}</endTime>
</AcsEventCond>
XML;
            
            $response = $this->sendRequest('/ISAPI/AccessControl/AcsEvent?format=json', 'POST', $xml);
            
            if ($response['code'] !== 200) {
                $this->setError('Failed to get attendance: ' . ($response['error'] ?? 'HTTP ' . $response['code']));
                break;
            }
            
            $data = json_decode($response['body'], true);
            
            if (!$data || !isset($data['AcsEvent']['InfoList'])) {
                break;
            }
            
            foreach ($data['AcsEvent']['InfoList'] as $event) {
                $employeeNo = $event['employeeNoString'] ?? '';
                $eventTime = $event['time'] ?? '';
                $attendanceStatus = $event['attendanceStatus'] ?? '';
                
                if (empty($employeeNo) || empty($eventTime)) continue;
                
                $direction = 'unknown';
                if (stripos($attendanceStatus, 'checkIn') !== false || stripos($attendanceStatus, 'in') !== false) {
                    $direction = 'in';
                } elseif (stripos($attendanceStatus, 'checkOut') !== false || stripos($attendanceStatus, 'out') !== false) {
                    $direction = 'out';
                }
                
                $verifyType = 'unknown';
                if (isset($event['currentVerifyMode'])) {
                    $verifyModes = [
                        'fingerPrint' => 'fingerprint',
                        'card' => 'card',
                        'face' => 'face',
                        'password' => 'password'
                    ];
                    $verifyType = $verifyModes[$event['currentVerifyMode']] ?? 'unknown';
                }
                
                $attendance[] = [
                    'device_user_id' => $employeeNo,
                    'log_time' => date('Y-m-d H:i:s', strtotime($eventTime)),
                    'direction' => $direction,
                    'verification_type' => $verifyType,
                    'raw_data' => $event
                ];
            }
            
            $totalMatches = $data['AcsEvent']['totalMatches'] ?? 0;
            $numOfMatches = $data['AcsEvent']['numOfMatches'] ?? 0;
            
            $searchPosition += $numOfMatches;
            $hasMore = $searchPosition < $totalMatches && $numOfMatches > 0;
        }
        
        return $attendance;
    }
    
    public function getUsers(): array {
        $users = [];
        $searchPosition = 0;
        $maxResults = 30;
        $hasMore = true;
        
        while ($hasMore) {
            $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<UserInfoSearchCond>
    <searchID>0</searchID>
    <searchResultPosition>{$searchPosition}</searchResultPosition>
    <maxResults>{$maxResults}</maxResults>
</UserInfoSearchCond>
XML;
            
            $response = $this->sendRequest('/ISAPI/AccessControl/UserInfo/Search?format=json', 'POST', $xml);
            
            if ($response['code'] !== 200) {
                $this->setError('Failed to get users: ' . ($response['error'] ?? 'HTTP ' . $response['code']));
                break;
            }
            
            $data = json_decode($response['body'], true);
            
            if (!$data || !isset($data['UserInfoSearch']['UserInfo'])) {
                break;
            }
            
            foreach ($data['UserInfoSearch']['UserInfo'] as $userInfo) {
                $users[] = [
                    'device_user_id' => $userInfo['employeeNo'] ?? '',
                    'name' => $userInfo['name'] ?? '',
                    'card_no' => $userInfo['numOfCard'] ?? 0,
                    'role' => 0
                ];
            }
            
            $totalMatches = $data['UserInfoSearch']['totalMatches'] ?? 0;
            $numOfMatches = $data['UserInfoSearch']['numOfMatches'] ?? 0;
            
            $searchPosition += $numOfMatches;
            $hasMore = $searchPosition < $totalMatches && $numOfMatches > 0;
        }
        
        return $users;
    }
    
    private function sendRequest(string $endpoint, string $method = 'GET', ?string $data = null): array {
        $url = "http://{$this->ip}:{$this->port}{$endpoint}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/xml',
                'Content-Length: ' . strlen($data)
            ]);
        }
        
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'code' => $code,
            'body' => $body,
            'error' => $error ?: null
        ];
    }
}
