<?php

namespace App;

class ZKTecoDevice extends BiometricDevice {
    private $socket = null;
    private int $sessionId = 0;
    private int $replyId = 0;
    
    private const CMD_CONNECT = 1000;
    private const CMD_EXIT = 1001;
    private const CMD_ENABLEDEVICE = 1002;
    private const CMD_DISABLEDEVICE = 1003;
    private const CMD_GET_VERSION = 1100;
    private const CMD_GET_SERIALNUMBER = 1101;
    private const CMD_GET_DEVICENAME = 1102;
    private const CMD_ACK_OK = 2000;
    private const CMD_ACK_ERROR = 2001;
    private const CMD_ACK_DATA = 2002;
    private const CMD_ACK_UNAUTH = 2005;
    private const CMD_USERTEMP_RRQ = 9;
    private const CMD_ATTLOG_RRQ = 13;
    private const CMD_FREE_DATA = 1502;
    private const CMD_DATA_WRRQ = 1503;
    private const CMD_DATA_RDY = 1504;
    private const CMD_PREPARE_DATA = 1500;
    private const CMD_SET_USER = 8;
    private const CMD_DELETE_USER = 18;
    private const CMD_DELETE_USERTEMP = 19;
    private const CMD_STARTENROLL = 61;
    private const CMD_CANCELENROLL = 62;
    private const CMD_ENROLL_FINGERPRINT = 63;
    private const CMD_WRITE_FINGERPRINT = 1503;
    private const CMD_TMP_WRITE = 87;
    private const CMD_USER_TMP_ALL = 88;
    
    private const USHRT_MAX = 65535;
    
    public function __construct(int $deviceId, string $ip, int $port = 4370, ?string $username = null, ?string $password = null) {
        parent::__construct($deviceId, $ip, $port ?: 4370, $username, $password);
    }
    
    public function connect(): bool {
        $this->socket = @\socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$this->socket) {
            $this->setError('Failed to create socket: ' . \socket_strerror(\socket_last_error()));
            return false;
        }
        
        \socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 10, 'usec' => 0]);
        \socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 10, 'usec' => 0]);
        
        $command = $this->createHeader(self::CMD_CONNECT, '', 0, 0);
        
        if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
            $this->setError('Failed to send connect command: ' . \socket_strerror(\socket_last_error()));
            return false;
        }
        
        $response = $this->receiveData();
        if ($response === false) {
            $this->setError('No response from device');
            return false;
        }
        
        $commandId = unpack('v', substr($response, 0, 2))[1];
        
        if ($commandId == self::CMD_ACK_OK || $commandId == self::CMD_ACK_UNAUTH) {
            $this->sessionId = unpack('v', substr($response, 4, 2))[1];
            $this->replyId = unpack('v', substr($response, 6, 2))[1];
            return true;
        }
        
        $this->setError('Connection rejected by device');
        return false;
    }
    
    public function disconnect(): void {
        if ($this->socket && $this->sessionId) {
            $command = $this->createHeader(self::CMD_EXIT, '', $this->sessionId, $this->replyId);
            @\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
        }
        
        if ($this->socket) {
            \socket_close($this->socket);
            $this->socket = null;
        }
        
        $this->sessionId = 0;
        $this->replyId = 0;
    }
    
    public function testConnection(): array {
        $result = [
            'success' => false,
            'device_name' => '',
            'serial_number' => '',
            'version' => '',
            'message' => ''
        ];
        
        if (!$this->connect()) {
            $result['message'] = $this->lastError['message'] ?? 'Connection failed';
            return $result;
        }
        
        try {
            $result['device_name'] = $this->getDeviceName();
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
            $result['serial_number'] = $this->getSerialNumber();
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
            $result['version'] = $this->getVersion();
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
            $result['success'] = true;
            $result['message'] = 'Connected successfully';
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }
        
        $this->disconnect();
        return $result;
    }
    
    public function getAttendance(?string $since = null, ?string $until = null): array {
        $attendance = [];
        
        if (!$this->socket || !$this->sessionId) {
            if (!$this->connect()) {
                return $attendance;
            }
        }
        
        $this->disableDevice();
        
        try {
            $command = $this->createHeader(self::CMD_DATA_WRRQ, chr(1) . chr(self::CMD_ATTLOG_RRQ), $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                throw new \Exception('Failed to request attendance data');
            }
            
            $response = $this->receiveData();
            if ($response === false) {
                throw new \Exception('No response for attendance request');
            }
            
            $commandId = unpack('v', substr($response, 0, 2))[1];
            
            if ($commandId == self::CMD_PREPARE_DATA) {
                $dataSize = unpack('V', substr($response, 8, 4))[1];
                $data = $this->receiveRawData($dataSize);
                
                if ($data) {
                    $attendance = $this->parseAttendanceData($data, $since, $until);
                }
            }
            
            $freeCommand = $this->createHeader(self::CMD_FREE_DATA, '', $this->sessionId, $this->replyId);
            @\socket_sendto($this->socket, $freeCommand, strlen($freeCommand), 0, $this->ip, $this->port);
            
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
        }
        
        $this->enableDevice();
        return $attendance;
    }
    
    public function getUsers(): array {
        $users = [];
        
        if (!$this->socket || !$this->sessionId) {
            if (!$this->connect()) {
                return $users;
            }
        }
        
        $this->disableDevice();
        
        try {
            $command = $this->createHeader(self::CMD_DATA_WRRQ, chr(1) . chr(self::CMD_USERTEMP_RRQ), $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                throw new \Exception('Failed to request user data');
            }
            
            $response = $this->receiveData();
            if ($response === false) {
                throw new \Exception('No response for user request');
            }
            
            $commandId = unpack('v', substr($response, 0, 2))[1];
            
            if ($commandId == self::CMD_PREPARE_DATA) {
                $dataSize = unpack('V', substr($response, 8, 4))[1];
                $data = $this->receiveRawData($dataSize);
                
                if ($data) {
                    $users = $this->parseUserData($data);
                }
            }
            
            $freeCommand = $this->createHeader(self::CMD_FREE_DATA, '', $this->sessionId, $this->replyId);
            @\socket_sendto($this->socket, $freeCommand, strlen($freeCommand), 0, $this->ip, $this->port);
            
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
        }
        
        $this->enableDevice();
        return $users;
    }
    
    public function getUsersWithDebug(): array {
        $result = [
            'users' => [],
            'debug' => [
                'connected' => false,
                'command_sent' => false,
                'response_received' => false,
                'data_size' => 0
            ]
        ];
        
        if (!$this->socket || !$this->sessionId) {
            if (!$this->connect()) {
                $result['debug']['error'] = 'Connection failed';
                return $result;
            }
        }
        
        $result['debug']['connected'] = true;
        $this->disableDevice();
        
        try {
            $command = $this->createHeader(self::CMD_DATA_WRRQ, chr(1) . chr(self::CMD_USERTEMP_RRQ), $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                throw new \Exception('Failed to send command');
            }
            
            $result['debug']['command_sent'] = true;
            
            $response = $this->receiveData();
            if ($response === false) {
                throw new \Exception('No response received');
            }
            
            $result['debug']['response_received'] = true;
            $commandId = unpack('v', substr($response, 0, 2))[1];
            $result['debug']['response_command'] = $commandId;
            
            if ($commandId == self::CMD_PREPARE_DATA) {
                $dataSize = unpack('V', substr($response, 8, 4))[1];
                $result['debug']['data_size'] = $dataSize;
                
                $data = $this->receiveRawData($dataSize);
                
                if ($data) {
                    $result['debug']['raw_data_length'] = strlen($data);
                    $result['debug']['raw_data_hex'] = substr(bin2hex($data), 0, 500);
                    $result['debug']['possible_record_sizes'] = [
                        '72_records' => intdiv(strlen($data), 72),
                        '28_records' => intdiv(strlen($data), 28),
                        '29_records' => intdiv(strlen($data), 29)
                    ];
                    $result['users'] = $this->parseUserData($data);
                    $result['debug']['parsed_users_count'] = count($result['users']);
                }
            } elseif ($commandId == self::CMD_ACK_OK || $commandId == self::CMD_ACK_DATA) {
                $data = substr($response, 8);
                if ($data && strlen($data) > 0) {
                    $result['debug']['raw_data_length'] = strlen($data);
                    $result['debug']['raw_data_hex'] = substr(bin2hex($data), 0, 200);
                    $result['users'] = $this->parseUserData($data);
                }
            }
            
            $freeCommand = $this->createHeader(self::CMD_FREE_DATA, '', $this->sessionId, $this->replyId);
            @\socket_sendto($this->socket, $freeCommand, strlen($freeCommand), 0, $this->ip, $this->port);
            
        } catch (\Exception $e) {
            $result['debug']['error'] = $e->getMessage();
        }
        
        $this->enableDevice();
        return $result;
    }
    
    private function getDeviceName(): string {
        return $this->executeCommand(self::CMD_GET_DEVICENAME) ?: 'Unknown';
    }
    
    private function getSerialNumber(): string {
        return $this->executeCommand(self::CMD_GET_SERIALNUMBER) ?: 'Unknown';
    }
    
    private function getVersion(): string {
        return $this->executeCommand(self::CMD_GET_VERSION) ?: 'Unknown';
    }
    
    private function executeCommand(int $commandId): ?string {
        $command = $this->createHeader($commandId, '', $this->sessionId, $this->replyId);
        
        if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
            return null;
        }
        
        $response = $this->receiveData();
        if ($response === false) {
            return null;
        }
        
        $respCommandId = unpack('v', substr($response, 0, 2))[1];
        
        if ($respCommandId == self::CMD_ACK_OK || $respCommandId == self::CMD_ACK_DATA) {
            return trim(substr($response, 8), "\x00\x20");
        }
        
        return null;
    }
    
    private function enableDevice(): bool {
        $command = $this->createHeader(self::CMD_ENABLEDEVICE, '', $this->sessionId, $this->replyId);
        @\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
        $this->receiveData();
        $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
        return true;
    }
    
    private function disableDevice(): bool {
        $command = $this->createHeader(self::CMD_DISABLEDEVICE, '', $this->sessionId, $this->replyId);
        @\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
        $this->receiveData();
        $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
        return true;
    }
    
    private function createHeader(int $commandId, string $data, int $sessionId, int $replyId): string {
        $buf = pack('v', $commandId);
        $buf .= pack('v', 0);
        $buf .= pack('v', $sessionId);
        $buf .= pack('v', $replyId);
        $buf .= $data;
        
        $buf[2] = chr(strlen($buf) & 0xFF);
        $buf[3] = chr((strlen($buf) >> 8) & 0xFF);
        
        $checksum = $this->calculateChecksum($buf);
        $buf .= pack('v', $checksum);
        
        return $buf;
    }
    
    private function calculateChecksum(string $data): int {
        $checksum = 0;
        $len = strlen($data);
        
        for ($i = 0; $i < $len; $i += 2) {
            if ($i + 1 < $len) {
                $checksum += unpack('v', substr($data, $i, 2))[1];
            } else {
                $checksum += ord($data[$i]);
            }
        }
        
        $checksum = ($checksum & 0xFFFF) + ($checksum >> 16);
        $checksum = ~$checksum & 0xFFFF;
        
        return $checksum;
    }
    
    private function receiveData(): string|false {
        $buffer = '';
        $from = '';
        $port = 0;
        
        $result = @\socket_recvfrom($this->socket, $buffer, 65536, 0, $from, $port);
        
        if ($result === false || $result < 8) {
            return false;
        }
        
        return $buffer;
    }
    
    private function receiveRawData(int $size): string|false {
        $data = '';
        $received = 0;
        $attempts = 0;
        $maxAttempts = 50;
        
        while ($received < $size && $attempts < $maxAttempts) {
            $buffer = '';
            $from = '';
            $port = 0;
            
            $result = @\socket_recvfrom($this->socket, $buffer, 65536, 0, $from, $port);
            
            if ($result === false) {
                $attempts++;
                continue;
            }
            
            if ($result >= 8) {
                $commandId = unpack('v', substr($buffer, 0, 2))[1];
                
                if ($commandId == self::CMD_DATA_RDY) {
                    $chunk = substr($buffer, 8);
                    $data .= $chunk;
                    $received += strlen($chunk);
                } elseif ($commandId == self::CMD_ACK_OK) {
                    break;
                }
            }
            
            $attempts++;
        }
        
        return $data ?: false;
    }
    
    private function parseAttendanceData(string $data, ?string $since = null, ?string $until = null): array {
        $attendance = [];
        $recordSize = 40;
        $records = str_split($data, $recordSize);
        
        foreach ($records as $record) {
            if (strlen($record) < $recordSize) continue;
            
            $uid = unpack('v', substr($record, 0, 2))[1];
            $userId = trim(substr($record, 2, 9));
            $state = ord($record[26]);
            $timestamp = unpack('V', substr($record, 27, 4))[1];
            $type = ord($record[31]);
            
            $datetime = $this->decodeTime($timestamp);
            
            if ($since && $datetime < $since) continue;
            if ($until && $datetime > $until) continue;
            
            $direction = ($state == 0 || $state == 4) ? 'in' : 'out';
            
            $verificationTypes = [
                0 => 'password',
                1 => 'fingerprint',
                2 => 'card',
                15 => 'face'
            ];
            
            $attendance[] = [
                'device_user_id' => $userId ?: (string)$uid,
                'log_time' => $datetime,
                'direction' => $direction,
                'verification_type' => $verificationTypes[$type] ?? 'unknown',
                'state' => $state,
                'raw_uid' => $uid
            ];
        }
        
        return $attendance;
    }
    
    private function parseUserData(string $data): array {
        $users = [];
        $dataLen = strlen($data);
        
        if ($dataLen === 0) {
            return $users;
        }
        
        $recordSizes = [72, 28, 29];
        
        foreach ($recordSizes as $recordSize) {
            if ($dataLen % $recordSize === 0 || $dataLen >= $recordSize) {
                $parsedUsers = $this->tryParseUsers($data, $recordSize);
                if (!empty($parsedUsers)) {
                    return $parsedUsers;
                }
            }
        }
        
        return $this->tryParseUsers($data, 72);
    }
    
    private function tryParseUsers(string $data, int $recordSize): array {
        $users = [];
        $records = str_split($data, $recordSize);
        
        foreach ($records as $record) {
            if (strlen($record) < $recordSize) continue;
            
            $user = null;
            
            if ($recordSize === 72) {
                $uid = unpack('v', substr($record, 0, 2))[1];
                $role = ord($record[2]);
                $password = trim(substr($record, 3, 8), "\x00");
                $name = trim(substr($record, 11, 24), "\x00");
                $cardNo = unpack('V', substr($record, 35, 4))[1];
                $userId = trim(substr($record, 48, 9), "\x00");
                
                if (!empty($name) || !empty($userId)) {
                    $user = [
                        'uid' => $uid,
                        'device_user_id' => $userId ?: (string)$uid,
                        'name' => $name ?: 'User ' . $uid,
                        'role' => $role,
                        'card_no' => $cardNo
                    ];
                }
            } elseif ($recordSize === 28 || $recordSize === 29) {
                $uid = unpack('v', substr($record, 0, 2))[1];
                $role = ord($record[2]);
                $password = trim(substr($record, 3, 8), "\x00");
                $name = trim(substr($record, 11, $recordSize === 28 ? 17 : 18), "\x00");
                
                if (!empty($name) || $uid > 0) {
                    $user = [
                        'uid' => $uid,
                        'device_user_id' => (string)$uid,
                        'name' => $name ?: 'User ' . $uid,
                        'role' => $role,
                        'card_no' => 0
                    ];
                }
            }
            
            if ($user) {
                $users[] = $user;
            }
        }
        
        return $users;
    }
    
    private function decodeTime(int $timestamp): string {
        $second = $timestamp % 60;
        $timestamp = intdiv($timestamp, 60);
        
        $minute = $timestamp % 60;
        $timestamp = intdiv($timestamp, 60);
        
        $hour = $timestamp % 24;
        $timestamp = intdiv($timestamp, 24);
        
        $day = ($timestamp % 31) + 1;
        $timestamp = intdiv($timestamp, 31);
        
        $month = ($timestamp % 12) + 1;
        $timestamp = intdiv($timestamp, 12);
        
        $year = $timestamp + 2000;
        
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }
    
    public function setUser(int $uid, string $userId, string $name, string $password = '', int $role = 0, string $cardNo = ''): bool {
        if (!$this->socket || !$this->sessionId) {
            if (!$this->connect()) {
                return false;
            }
        }
        
        $this->disableDevice();
        
        try {
            $userData = pack('v', $uid);
            $userData .= chr($role);
            $userData .= str_pad(substr($password, 0, 8), 8, "\x00");
            $userData .= str_pad(substr($name, 0, 24), 24, "\x00");
            $userData .= pack('V', intval($cardNo) ?: 0);
            $userData .= str_repeat("\x00", 9);
            $userData .= str_pad(substr($userId, 0, 9), 9, "\x00");
            $userData .= str_repeat("\x00", 15);
            
            $command = $this->createHeader(self::CMD_SET_USER, $userData, $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                throw new \Exception('Failed to send set user command');
            }
            
            $response = $this->receiveData();
            if ($response === false) {
                throw new \Exception('No response for set user');
            }
            
            $commandId = unpack('v', substr($response, 0, 2))[1];
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
            $this->enableDevice();
            
            return $commandId == self::CMD_ACK_OK;
            
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            $this->enableDevice();
            return false;
        }
    }
    
    public function deleteUser(int $uid): bool {
        if (!$this->socket || !$this->sessionId) {
            if (!$this->connect()) {
                return false;
            }
        }
        
        $this->disableDevice();
        
        try {
            $command = $this->createHeader(self::CMD_DELETE_USER, pack('v', $uid), $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                throw new \Exception('Failed to send delete user command');
            }
            
            $response = $this->receiveData();
            $commandId = unpack('v', substr($response, 0, 2))[1];
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
            $this->enableDevice();
            
            return $commandId == self::CMD_ACK_OK;
            
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            $this->enableDevice();
            return false;
        }
    }
    
    public function startEnrollment(int $uid, int $fingerId): array {
        $result = [
            'success' => false,
            'message' => '',
            'enrolling' => false
        ];
        
        if (!$this->socket || !$this->sessionId) {
            if (!$this->connect()) {
                $result['message'] = 'Connection failed';
                return $result;
            }
        }
        
        $this->disableDevice();
        
        try {
            $enrollData = pack('v', $uid);
            $enrollData .= chr($fingerId);
            
            $command = $this->createHeader(self::CMD_STARTENROLL, $enrollData, $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                throw new \Exception('Failed to send enrollment command');
            }
            
            $response = $this->receiveData();
            if ($response === false) {
                throw new \Exception('No response from device');
            }
            
            $commandId = unpack('v', substr($response, 0, 2))[1];
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
            if ($commandId == self::CMD_ACK_OK) {
                $result['success'] = true;
                $result['enrolling'] = true;
                $result['message'] = 'Enrollment started. Please place your finger on the device scanner.';
            } else {
                $result['message'] = 'Device rejected enrollment request. Make sure user exists on device.';
            }
            
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }
        
        return $result;
    }
    
    public function cancelEnrollment(): bool {
        if (!$this->socket || !$this->sessionId) {
            return false;
        }
        
        try {
            $command = $this->createHeader(self::CMD_CANCELENROLL, '', $this->sessionId, $this->replyId);
            @\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
            $this->receiveData();
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            $this->enableDevice();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function checkEnrollmentStatus(int $uid, int $fingerId): array {
        $result = [
            'success' => false,
            'enrolled' => false,
            'message' => ''
        ];
        
        try {
            $fingerprints = $this->getFingerprints($uid);
            
            foreach ($fingerprints as $fp) {
                if ($fp['finger_id'] == $fingerId) {
                    $result['success'] = true;
                    $result['enrolled'] = true;
                    $result['message'] = 'Fingerprint enrolled successfully';
                    $result['template'] = $fp['template'] ?? null;
                    return $result;
                }
            }
            
            $result['success'] = true;
            $result['enrolled'] = false;
            $result['message'] = 'Fingerprint not yet enrolled. Please scan finger on device.';
            
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }
        
        return $result;
    }
    
    public function getFingerprints(int $uid): array {
        $fingerprints = [];
        
        if (!$this->socket || !$this->sessionId) {
            if (!$this->connect()) {
                return $fingerprints;
            }
        }
        
        $this->disableDevice();
        
        try {
            $command = $this->createHeader(self::CMD_DATA_WRRQ, chr(1) . chr(self::CMD_USERTEMP_RRQ), $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                throw new \Exception('Failed to request fingerprint data');
            }
            
            $response = $this->receiveData();
            if ($response === false) {
                throw new \Exception('No response');
            }
            
            $commandId = unpack('v', substr($response, 0, 2))[1];
            
            if ($commandId == self::CMD_PREPARE_DATA) {
                $dataSize = unpack('V', substr($response, 8, 4))[1];
                $data = $this->receiveRawData($dataSize);
                
                if ($data) {
                    $fingerprints = $this->parseFingerprintData($data, $uid);
                }
            }
            
            $freeCommand = $this->createHeader(self::CMD_FREE_DATA, '', $this->sessionId, $this->replyId);
            @\socket_sendto($this->socket, $freeCommand, strlen($freeCommand), 0, $this->ip, $this->port);
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
        }
        
        $this->enableDevice();
        return $fingerprints;
    }
    
    public function setFingerprint(int $uid, int $fingerId, string $template): bool {
        if (!$this->socket || !$this->sessionId) {
            if (!$this->connect()) {
                return false;
            }
        }
        
        $this->disableDevice();
        
        try {
            $templateSize = strlen($template);
            
            $data = pack('v', $templateSize);
            $data .= pack('v', $uid);
            $data .= chr($fingerId);
            $data .= chr(1);
            $data .= $template;
            
            $command = $this->createHeader(self::CMD_TMP_WRITE, $data, $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                throw new \Exception('Failed to send fingerprint data');
            }
            
            $response = $this->receiveData();
            if ($response === false) {
                throw new \Exception('No response');
            }
            
            $commandId = unpack('v', substr($response, 0, 2))[1];
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
            $this->enableDevice();
            
            return $commandId == self::CMD_ACK_OK;
            
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            $this->enableDevice();
            return false;
        }
    }
    
    public function deleteFingerprint(int $uid, int $fingerId): bool {
        if (!$this->socket || !$this->sessionId) {
            if (!$this->connect()) {
                return false;
            }
        }
        
        $this->disableDevice();
        
        try {
            $data = pack('v', $uid);
            $data .= chr($fingerId);
            
            $command = $this->createHeader(self::CMD_DELETE_USERTEMP, $data, $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                throw new \Exception('Failed to send delete fingerprint command');
            }
            
            $response = $this->receiveData();
            $commandId = unpack('v', substr($response, 0, 2))[1];
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
            $this->enableDevice();
            
            return $commandId == self::CMD_ACK_OK;
            
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            $this->enableDevice();
            return false;
        }
    }
    
    public function getAllFingerprints(): array {
        $fingerprints = [];
        
        if (!$this->socket || !$this->sessionId) {
            if (!$this->connect()) {
                return $fingerprints;
            }
        }
        
        $this->disableDevice();
        
        try {
            $command = $this->createHeader(self::CMD_DATA_WRRQ, chr(1) . chr(self::CMD_USER_TMP_ALL), $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                throw new \Exception('Failed to request all fingerprints');
            }
            
            $response = $this->receiveData();
            if ($response === false) {
                throw new \Exception('No response');
            }
            
            $commandId = unpack('v', substr($response, 0, 2))[1];
            
            if ($commandId == self::CMD_PREPARE_DATA) {
                $dataSize = unpack('V', substr($response, 8, 4))[1];
                $data = $this->receiveRawData($dataSize);
                
                if ($data) {
                    $fingerprints = $this->parseFingerprintData($data);
                }
            }
            
            $freeCommand = $this->createHeader(self::CMD_FREE_DATA, '', $this->sessionId, $this->replyId);
            @\socket_sendto($this->socket, $freeCommand, strlen($freeCommand), 0, $this->ip, $this->port);
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
        }
        
        $this->enableDevice();
        return $fingerprints;
    }
    
    private function parseFingerprintData(string $data, ?int $filterUid = null): array {
        $fingerprints = [];
        $offset = 0;
        $dataLen = strlen($data);
        
        while ($offset < $dataLen) {
            if ($offset + 6 > $dataLen) break;
            
            $size = unpack('v', substr($data, $offset, 2))[1];
            $uid = unpack('v', substr($data, $offset + 2, 2))[1];
            $fingerId = ord($data[$offset + 4]);
            $valid = ord($data[$offset + 5]);
            
            if ($size <= 0 || $offset + 6 + $size > $dataLen) break;
            
            $template = substr($data, $offset + 6, $size);
            
            if ($filterUid === null || $uid == $filterUid) {
                $fingerprints[] = [
                    'uid' => $uid,
                    'finger_id' => $fingerId,
                    'valid' => $valid == 1,
                    'template' => base64_encode($template),
                    'template_size' => $size
                ];
            }
            
            $offset += 6 + $size;
        }
        
        return $fingerprints;
    }
    
    public function getDeviceInfo(): array {
        $info = [
            'device_name' => '',
            'serial_number' => '',
            'platform' => '',
            'firmware_version' => '',
            'users_count' => 0,
            'fingerprints_count' => 0,
            'logs_count' => 0
        ];
        
        if (!$this->connect()) {
            return $info;
        }
        
        try {
            $info['device_name'] = $this->getDeviceName();
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
            $info['serial_number'] = $this->getSerialNumber();
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
            $info['firmware_version'] = $this->getVersion();
            $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
            
            $users = $this->getUsers();
            $info['users_count'] = count($users);
            
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
        }
        
        $this->disconnect();
        return $info;
    }
    
    public function syncUsersFromDatabase(\PDO $db): array {
        $result = [
            'success' => false,
            'synced' => 0,
            'errors' => []
        ];
        
        try {
            $stmt = $db->prepare("
                SELECT dum.device_user_id, e.first_name, e.last_name, e.id as employee_id
                FROM device_user_mapping dum
                JOIN employees e ON dum.employee_id = e.id
                WHERE dum.device_id = ?
            ");
            $stmt->execute([$this->deviceId]);
            $mappings = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (!$this->connect()) {
                $result['errors'][] = 'Connection failed';
                return $result;
            }
            
            foreach ($mappings as $mapping) {
                try {
                    $uid = (int)$mapping['device_user_id'];
                    $name = trim(($mapping['first_name'] ?? '') . ' ' . ($mapping['last_name'] ?? ''));
                    if (empty($name)) {
                        $name = 'Employee ' . $mapping['employee_id'];
                    }
                    $userId = str_pad($mapping['employee_id'], 9, '0', STR_PAD_LEFT);
                    
                    if ($this->setUser($uid, $userId, $name, '', 0)) {
                        $result['synced']++;
                    } else {
                        $result['errors'][] = "Failed to sync user {$uid}";
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Error syncing user {$mapping['device_user_id']}: " . $e->getMessage();
                }
            }
            
            $this->disconnect();
            $result['success'] = true;
            
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
}
