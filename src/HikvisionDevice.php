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
            $json = json_encode([
                'UserInfoSearchCond' => [
                    'searchID' => '0',
                    'searchResultPosition' => $searchPosition,
                    'maxResults' => $maxResults
                ]
            ]);
            
            $response = $this->sendRequest('/ISAPI/AccessControl/UserInfo/Search?format=json', 'POST', $json, 'application/json');
            
            error_log("Hikvision getUsers response code: " . $response['code']);
            error_log("Hikvision getUsers response body: " . substr($response['body'] ?? '', 0, 500));
            
            if ($response['code'] !== 200) {
                $this->setError('Failed to get users: ' . ($response['error'] ?? 'HTTP ' . $response['code']));
                break;
            }
            
            $data = json_decode($response['body'], true);
            
            if (!$data) {
                error_log("Hikvision getUsers: Failed to parse JSON response");
                break;
            }
            
            if (!isset($data['UserInfoSearch']['UserInfo'])) {
                error_log("Hikvision getUsers: No UserInfo in response. Keys: " . implode(', ', array_keys($data)));
                $totalMatches = $data['UserInfoSearch']['totalMatches'] ?? 0;
                if ($totalMatches == 0) {
                    break;
                }
            }
            
            $userList = $data['UserInfoSearch']['UserInfo'] ?? [];
            if (!is_array($userList)) {
                $userList = [$userList];
            }
            
            foreach ($userList as $userInfo) {
                $users[] = [
                    'device_user_id' => $userInfo['employeeNo'] ?? '',
                    'name' => $userInfo['name'] ?? '',
                    'card_no' => $userInfo['numOfCard'] ?? 0,
                    'has_fingerprint' => ($userInfo['numOfFP'] ?? 0) > 0,
                    'has_face' => ($userInfo['numOfFace'] ?? 0) > 0,
                    'role' => 0
                ];
            }
            
            $totalMatches = $data['UserInfoSearch']['totalMatches'] ?? 0;
            $numOfMatches = $data['UserInfoSearch']['numOfMatches'] ?? count($userList);
            
            $searchPosition += $numOfMatches;
            $hasMore = $searchPosition < $totalMatches && $numOfMatches > 0;
        }
        
        return $users;
    }
    
    public function addUser(string $employeeNo, string $name, ?string $cardNo = null): array {
        $userInfo = [
            'UserInfo' => [
                'employeeNo' => $employeeNo,
                'name' => $name,
                'userType' => 'normal',
                'Valid' => [
                    'enable' => true,
                    'beginTime' => date('Y-m-d\T00:00:00'),
                    'endTime' => date('Y-m-d\T23:59:59', strtotime('+10 years'))
                ],
                'doorRight' => '1',
                'RightPlan' => [
                    ['doorNo' => 1, 'planTemplateNo' => '1']
                ]
            ]
        ];
        
        if ($cardNo) {
            $userInfo['UserInfo']['numOfCard'] = 1;
            $userInfo['UserInfo']['CardList'] = [
                ['cardNo' => $cardNo, 'cardType' => 'normalCard']
            ];
        }
        
        $response = $this->sendRequest(
            '/ISAPI/AccessControl/UserInfo/Record?format=json',
            'POST',
            json_encode($userInfo),
            'application/json'
        );
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['statusCode']) && $data['statusCode'] == 1) {
                return ['success' => true, 'message' => 'User added successfully'];
            }
            return ['success' => true, 'message' => 'User added', 'response' => $data];
        }
        
        $error = 'Failed to add user';
        if ($response['body']) {
            $data = json_decode($response['body'], true);
            $error = $data['statusString'] ?? $data['subStatusCode'] ?? $response['error'] ?? $error;
        }
        
        return ['success' => false, 'error' => $error, 'code' => $response['code']];
    }
    
    public function updateUser(string $employeeNo, string $name, ?string $cardNo = null): array {
        $userInfo = [
            'UserInfo' => [
                'employeeNo' => $employeeNo,
                'name' => $name
            ]
        ];
        
        if ($cardNo) {
            $userInfo['UserInfo']['numOfCard'] = 1;
            $userInfo['UserInfo']['CardList'] = [
                ['cardNo' => $cardNo, 'cardType' => 'normalCard']
            ];
        }
        
        $response = $this->sendRequest(
            '/ISAPI/AccessControl/UserInfo/Modify?format=json',
            'PUT',
            json_encode($userInfo),
            'application/json'
        );
        
        if ($response['code'] === 200) {
            return ['success' => true, 'message' => 'User updated successfully'];
        }
        
        $error = 'Failed to update user';
        if ($response['body']) {
            $data = json_decode($response['body'], true);
            $error = $data['statusString'] ?? $data['subStatusCode'] ?? $error;
        }
        
        return ['success' => false, 'error' => $error, 'code' => $response['code']];
    }
    
    public function deleteUser(string $employeeNo): array {
        $deleteData = [
            'UserInfoDelCond' => [
                'EmployeeNoList' => [
                    ['employeeNo' => $employeeNo]
                ]
            ]
        ];
        
        $response = $this->sendRequest(
            '/ISAPI/AccessControl/UserInfo/Delete?format=json',
            'PUT',
            json_encode($deleteData),
            'application/json'
        );
        
        if ($response['code'] === 200) {
            return ['success' => true, 'message' => 'User deleted successfully'];
        }
        
        $error = 'Failed to delete user';
        if ($response['body']) {
            $data = json_decode($response['body'], true);
            $error = $data['statusString'] ?? $data['subStatusCode'] ?? $error;
        }
        
        return ['success' => false, 'error' => $error, 'code' => $response['code']];
    }
    
    public function getUserCount(): int {
        $response = $this->sendRequest('/ISAPI/AccessControl/UserInfo/Count?format=json');
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            return $data['UserInfoCount']['userNumber'] ?? 0;
        }
        
        return 0;
    }
    
    public function userExists(string $employeeNo): bool {
        $json = json_encode([
            'UserInfoSearchCond' => [
                'searchID' => '0',
                'searchResultPosition' => 0,
                'maxResults' => 1,
                'EmployeeNoList' => [
                    ['employeeNo' => $employeeNo]
                ]
            ]
        ]);
        
        $response = $this->sendRequest('/ISAPI/AccessControl/UserInfo/Search?format=json', 'POST', $json, 'application/json');
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            $totalMatches = $data['UserInfoSearch']['totalMatches'] ?? 0;
            return $totalMatches > 0;
        }
        
        return false;
    }
    
    public function getUser(string $employeeNo): ?array {
        $json = json_encode([
            'UserInfoSearchCond' => [
                'searchID' => '0',
                'searchResultPosition' => 0,
                'maxResults' => 1,
                'EmployeeNoList' => [
                    ['employeeNo' => $employeeNo]
                ]
            ]
        ]);
        
        $response = $this->sendRequest('/ISAPI/AccessControl/UserInfo/Search?format=json', 'POST', $json, 'application/json');
        
        error_log("Hikvision getUser($employeeNo) response code: " . $response['code']);
        error_log("Hikvision getUser($employeeNo) response body: " . substr($response['body'] ?? '', 0, 500));
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            $userList = $data['UserInfoSearch']['UserInfo'] ?? [];
            if (!is_array($userList)) {
                $userList = [$userList];
            }
            if (!empty($userList)) {
                $userInfo = $userList[0];
                return [
                    'device_user_id' => $userInfo['employeeNo'] ?? '',
                    'name' => $userInfo['name'] ?? '',
                    'card_no' => $userInfo['numOfCard'] ?? 0,
                    'has_fingerprint' => ($userInfo['numOfFP'] ?? 0) > 0,
                    'has_face' => ($userInfo['numOfFace'] ?? 0) > 0,
                    'raw' => $userInfo
                ];
            }
        }
        
        return null;
    }
    
    public function getCapabilities(): array {
        $response = $this->sendRequest('/ISAPI/AccessControl/UserInfo/capabilities?format=json');
        
        if ($response['code'] === 200) {
            return json_decode($response['body'], true) ?? [];
        }
        
        return [];
    }
    
    public function startFaceEnrollment(string $employeeNo): array {
        $captureData = [
            'FaceCapture' => [
                'employeeNo' => $employeeNo,
                'faceLibType' => 'blackFD'
            ]
        ];
        
        $response = $this->sendRequest(
            '/ISAPI/AccessControl/FaceCapture/Start?format=json',
            'PUT',
            json_encode($captureData),
            'application/json'
        );
        
        if ($response['code'] === 200) {
            return ['success' => true, 'message' => 'Face enrollment started on device. Employee should look at the camera.'];
        }
        
        $error = 'Failed to start face enrollment';
        if ($response['body']) {
            $data = json_decode($response['body'], true);
            $error = $data['statusString'] ?? $data['subStatusCode'] ?? $error;
        }
        
        return ['success' => false, 'error' => $error, 'code' => $response['code']];
    }
    
    public function startFingerprintEnrollment(string $employeeNo, int $fingerNo = 1): array {
        $fpData = [
            'FingerPrintCfg' => [
                'employeeNo' => $employeeNo,
                'fingerPrintID' => $fingerNo,
                'fingerType' => 'normalFP'
            ]
        ];
        
        $response = $this->sendRequest(
            '/ISAPI/AccessControl/FingerPrint/Capture?format=json',
            'PUT',
            json_encode($fpData),
            'application/json'
        );
        
        if ($response['code'] === 200) {
            return ['success' => true, 'message' => 'Fingerprint enrollment started on device. Employee should place finger on scanner.'];
        }
        
        $error = 'Failed to start fingerprint enrollment';
        if ($response['body']) {
            $data = json_decode($response['body'], true);
            $error = $data['statusString'] ?? $data['subStatusCode'] ?? $error;
        }
        
        return ['success' => false, 'error' => $error, 'code' => $response['code']];
    }
    
    public function addUserWithEnrollment(string $employeeNo, string $name, ?string $cardNo = null, bool $startEnrollment = true): array {
        $existingUser = $this->getUser($employeeNo);
        $userExisted = $existingUser !== null;
        
        if ($userExisted) {
            $addResult = $this->updateUser($employeeNo, $name, $cardNo);
            if (!$addResult['success']) {
                $addResult = ['success' => true, 'message' => 'User already exists on device'];
            }
        } else {
            $addResult = $this->addUser($employeeNo, $name, $cardNo);
            
            if (!$addResult['success']) {
                $errorLower = strtolower($addResult['error'] ?? '');
                if (strpos($errorLower, 'reedit') !== false || strpos($errorLower, 'exist') !== false || strpos($errorLower, 'duplicate') !== false) {
                    $addResult = $this->updateUser($employeeNo, $name, $cardNo);
                    $userExisted = true;
                }
                
                if (!$addResult['success']) {
                    return $addResult;
                }
            }
        }
        
        if ($startEnrollment) {
            $enrollResult = $this->startFingerprintEnrollment($employeeNo);
            $hasFingerprint = $existingUser ? $existingUser['has_fingerprint'] : false;
            
            return [
                'success' => true,
                'message' => ($userExisted ? 'User already exists. ' : 'User registered. ') . 
                             ($enrollResult['success'] ? $enrollResult['message'] : 
                              ($hasFingerprint ? 'Fingerprint already enrolled.' : 'Fingerprint enrollment not available.')),
                'enrollment_started' => $enrollResult['success'],
                'user_existed' => $userExisted,
                'has_fingerprint' => $hasFingerprint
            ];
        }
        
        return [
            'success' => true,
            'message' => $userExisted ? 'User already exists on device' : 'User added successfully',
            'user_existed' => $userExisted
        ];
    }
    
    private function sendRequest(string $endpoint, string $method = 'GET', ?string $data = null, string $contentType = 'application/xml'): array {
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
                'Content-Type: ' . $contentType,
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
