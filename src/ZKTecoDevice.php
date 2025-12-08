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
    private const CMD_GET_SERIAL = 11;
    private const CMD_OPTIONS_RRQ = 13;
    private const CMD_ACK_OK = 2000;
    private const CMD_ACK_ERROR = 2001;
    private const CMD_ACK_DATA = 2002;
    private const CMD_ACK_UNAUTH = 2005;
    private const CMD_USERTEMP_RRQ = 9;
    private const CMD_ATTLOG_RRQ = 13;
    private const CMD_FREE_DATA = 1502;
    private const CMD_DATA_WRRQ = 1503;
    private const CMD_DATA_RDY = 1504;
    
    private const USHRT_MAX = 65535;
    
    public function __construct(int $deviceId, string $ip, int $port = 4370, ?string $username = null, ?string $password = null) {
        parent::__construct($deviceId, $ip, $port ?: 4370, $username, $password);
    }
    
    public function connect(): bool {
        if (!function_exists('socket_create')) {
            $this->setError('PHP sockets extension is not enabled');
            return false;
        }
        
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
            $result['serial_number'] = $this->getSerialNumber();
            $result['version'] = $this->getVersion();
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
            $command = $this->createHeader(self::CMD_DATA_WRRQ, chr(1) . chr(13), $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                throw new \Exception('Failed to request attendance data');
            }
            
            $response = $this->receiveData();
            if ($response === false) {
                throw new \Exception('No response for attendance request');
            }
            
            $commandId = unpack('v', substr($response, 0, 2))[1];
            
            if ($commandId == self::CMD_DATA_RDY) {
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
            $command = $this->createHeader(self::CMD_USERTEMP_RRQ, chr(0x05), $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                throw new \Exception('Failed to request user data');
            }
            
            $response = $this->receiveData();
            if ($response === false) {
                $command = $this->createHeader(self::CMD_DATA_WRRQ, chr(1) . chr(9), $this->sessionId, $this->replyId);
                @\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
                $response = $this->receiveData();
            }
            
            if ($response === false) {
                throw new \Exception('No response for user request');
            }
            
            $commandId = unpack('v', substr($response, 0, 2))[1];
            
            if ($commandId == self::CMD_DATA_RDY) {
                $dataSize = unpack('V', substr($response, 8, 4))[1];
                $data = $this->receiveRawData($dataSize);
                
                if ($data && strlen($data) > 0) {
                    $users = $this->parseUserDataK40($data);
                    if (empty($users)) {
                        $users = $this->parseUserData($data);
                    }
                    if (empty($users)) {
                        $users = $this->parseUserDataNewFormat($data);
                    }
                }
            } elseif ($commandId == self::CMD_ACK_OK || $commandId == self::CMD_ACK_DATA) {
                $data = substr($response, 8);
                if (strlen($data) > 0) {
                    $users = $this->parseUserDataK40($data);
                    if (empty($users)) {
                        $users = $this->parseUserData($data);
                    }
                    if (empty($users)) {
                        $users = $this->parseUserDataNewFormat($data);
                    }
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
                'response_command' => null,
                'data_size' => 0,
                'raw_data_length' => 0,
                'raw_data_hex' => '',
                'error' => null
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
            $command = $this->createHeader(self::CMD_USERTEMP_RRQ, chr(0x05), $this->sessionId, $this->replyId);
            
            if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
                $result['debug']['error'] = 'Failed to send command';
                return $result;
            }
            
            $result['debug']['command_sent'] = true;
            
            $response = $this->receiveData();
            if ($response === false) {
                $command = $this->createHeader(self::CMD_DATA_WRRQ, chr(1) . chr(9), $this->sessionId, $this->replyId);
                @\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
                $response = $this->receiveData();
            }
            
            if ($response === false) {
                $result['debug']['error'] = 'No response from device';
                return $result;
            }
            
            $result['debug']['response_received'] = true;
            $commandId = unpack('v', substr($response, 0, 2))[1];
            $result['debug']['response_command'] = $commandId;
            
            $data = null;
            
            if ($commandId == self::CMD_DATA_RDY) {
                $dataSize = unpack('V', substr($response, 8, 4))[1];
                $result['debug']['data_size'] = $dataSize;
                $data = $this->receiveRawData($dataSize);
            } elseif ($commandId == self::CMD_ACK_OK || $commandId == self::CMD_ACK_DATA) {
                $data = substr($response, 8);
            }
            
            if ($data && strlen($data) > 0) {
                $result['debug']['raw_data_length'] = strlen($data);
                $result['debug']['raw_data_hex'] = substr(bin2hex($data), 0, 200);
                
                $result['users'] = $this->parseUserDataK40($data);
                if (empty($result['users'])) {
                    $result['users'] = $this->parseUserData($data);
                }
                if (empty($result['users'])) {
                    $result['users'] = $this->parseUserDataNewFormat($data);
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
        return $this->executeCommandWithData(self::CMD_OPTIONS_RRQ, "~DeviceName\x00") ?: 'Unknown';
    }
    
    private function getSerialNumber(): string {
        return $this->executeCommand(self::CMD_GET_SERIAL) ?: 'Unknown';
    }
    
    private function getVersion(): string {
        return $this->executeCommand(self::CMD_GET_VERSION) ?: 'Unknown';
    }
    
    private function executeCommandWithData(int $commandId, string $data): ?string {
        $command = $this->createHeader($commandId, $data, $this->sessionId, $this->replyId);
        
        if (!@\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
            return null;
        }
        
        $response = $this->receiveData();
        if ($response === false) {
            return null;
        }
        
        $respCommandId = unpack('v', substr($response, 0, 2))[1];
        
        if ($respCommandId == self::CMD_ACK_OK || $respCommandId == self::CMD_ACK_DATA) {
            $result = trim(substr($response, 8));
            if (strpos($result, '=') !== false) {
                $parts = explode('=', $result, 2);
                return trim($parts[1] ?? $result);
            }
            return $result;
        }
        
        return null;
    }
    
    private function enableDevice(): bool {
        $command = $this->createHeader(self::CMD_ENABLEDEVICE, '', $this->sessionId, $this->replyId);
        @\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
        $this->receiveData();
        return true;
    }
    
    private function disableDevice(): bool {
        $command = $this->createHeader(self::CMD_DISABLEDEVICE, '', $this->sessionId, $this->replyId);
        @\socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
        $this->receiveData();
        return true;
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
            return trim(substr($response, 8));
        }
        
        return null;
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
        
        $checksum = ($checksum >> 16) + ($checksum & 0xFFFF);
        $checksum = ~$checksum & 0xFFFF;
        
        return $checksum;
    }
    
    private function receiveData(): false|string {
        $data = '';
        $from = '';
        $port = 0;
        
        $result = @\socket_recvfrom($this->socket, $data, 1024, 0, $from, $port);
        
        if ($result === false || $result === 0) {
            return false;
        }
        
        $this->replyId = ($this->replyId + 1) % self::USHRT_MAX;
        
        return $data;
    }
    
    private function receiveRawData(int $size): ?string {
        $data = '';
        $remaining = $size;
        
        while ($remaining > 0) {
            $chunk = '';
            $from = '';
            $port = 0;
            
            $result = @\socket_recvfrom($this->socket, $chunk, min($remaining + 16, 65536), 0, $from, $port);
            
            if ($result === false || $result === 0) {
                break;
            }
            
            $data .= substr($chunk, 8);
            $remaining -= ($result - 8);
        }
        
        return $data ?: null;
    }
    
    private function parseAttendanceData(string $data, ?string $since, ?string $until): array {
        $attendance = [];
        
        $sinceTime = $since ? strtotime($since) : null;
        $untilTime = $until ? strtotime($until) : null;
        
        $recordSizes = [40, 16, 14, 8];
        
        foreach ($recordSizes as $recordSize) {
            if (strlen($data) >= $recordSize && strlen($data) % $recordSize === 0) {
                $records = str_split($data, $recordSize);
                $tempAttendance = [];
                $validRecords = 0;
                
                foreach ($records as $record) {
                    if (strlen($record) < $recordSize) continue;
                    
                    $uid = 0;
                    $userId = '';
                    $state = 0;
                    $timestamp = 0;
                    $type = 0;
                    
                    if ($recordSize == 40) {
                        $uid = unpack('v', substr($record, 0, 2))[1];
                        $userId = trim(substr($record, 2, 9));
                        $state = ord($record[26]);
                        $timestamp = unpack('V', substr($record, 27, 4))[1];
                        $type = ord($record[31]);
                    } elseif ($recordSize == 16) {
                        $uid = unpack('v', substr($record, 0, 2))[1];
                        $userId = (string)$uid;
                        $timestamp = unpack('V', substr($record, 4, 4))[1];
                        $state = ord($record[8]);
                        $type = ord($record[10]);
                    } elseif ($recordSize == 14) {
                        $uid = unpack('v', substr($record, 0, 2))[1];
                        $userId = (string)$uid;
                        $timestamp = unpack('V', substr($record, 4, 4))[1];
                        $state = ord($record[8]);
                    } elseif ($recordSize == 8) {
                        $uid = unpack('v', substr($record, 0, 2))[1];
                        $userId = (string)$uid;
                        $timestamp = unpack('V', substr($record, 2, 4))[1];
                        $state = ord($record[6]);
                    }
                    
                    if ($timestamp < 100000 || $timestamp > 2000000000) {
                        continue;
                    }
                    
                    $datetime = $this->decodeTime($timestamp);
                    $time = strtotime($datetime);
                    
                    if ($time === false || $time < strtotime('2000-01-01') || $time > strtotime('2100-01-01')) {
                        continue;
                    }
                    
                    $validRecords++;
                    
                    if ($sinceTime && $time < $sinceTime) continue;
                    if ($untilTime && $time > $untilTime) continue;
                    
                    $direction = 'unknown';
                    if ($state == 0 || $state == 4) $direction = 'in';
                    if ($state == 1 || $state == 5) $direction = 'out';
                    
                    $verificationTypes = [
                        0 => 'password',
                        1 => 'fingerprint',
                        2 => 'card',
                        15 => 'face'
                    ];
                    
                    $tempAttendance[] = [
                        'device_user_id' => $userId ?: (string)$uid,
                        'log_time' => $datetime,
                        'direction' => $direction,
                        'verification_type' => $verificationTypes[$type] ?? 'unknown',
                        'state' => $state,
                        'raw_uid' => $uid
                    ];
                }
                
                if ($validRecords > 0 && !empty($tempAttendance)) {
                    return $tempAttendance;
                }
            }
        }
        
        return $attendance;
    }
    
    private function parseUserDataK40(string $data): array {
        $users = [];
        
        $recordSizes = [28, 29, 72, 73];
        
        foreach ($recordSizes as $recordSize) {
            if (strlen($data) < $recordSize) continue;
            if (strlen($data) % $recordSize !== 0) continue;
            
            $records = str_split($data, $recordSize);
            $tempUsers = [];
            
            foreach ($records as $record) {
                if (strlen($record) < $recordSize) continue;
                
                $uid = unpack('v', substr($record, 0, 2))[1];
                if ($uid === 0 || $uid > 65000) continue;
                
                $userId = '';
                $name = '';
                $role = 0;
                $cardNo = 0;
                
                if ($recordSize == 28) {
                    $role = ord($record[2]);
                    $password = trim(substr($record, 3, 8));
                    $name = $this->cleanString(substr($record, 11, 8));
                    $userId = trim(substr($record, 19, 9)) ?: (string)$uid;
                } elseif ($recordSize == 29) {
                    $role = ord($record[2]);
                    $password = trim(substr($record, 3, 8));
                    $name = $this->cleanString(substr($record, 11, 8));
                    $userId = trim(substr($record, 19, 9)) ?: (string)$uid;
                } elseif ($recordSize == 72 || $recordSize == 73) {
                    $role = ord($record[2]);
                    $name = $this->cleanString(substr($record, 11, 24));
                    $cardNo = unpack('V', substr($record, 35, 4))[1] ?? 0;
                    $userId = trim(substr($record, 48, 9)) ?: (string)$uid;
                }
                
                if (!$userId && !$name) {
                    $userId = (string)$uid;
                }
                
                $tempUsers[] = [
                    'uid' => $uid,
                    'device_user_id' => $userId,
                    'name' => $name ?: 'User ' . $uid,
                    'role' => $role,
                    'card_no' => $cardNo
                ];
            }
            
            if (!empty($tempUsers)) {
                return $tempUsers;
            }
        }
        
        return $users;
    }
    
    private function cleanString(string $str): string {
        $str = trim($str);
        $str = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $str);
        return $str;
    }
    
    private function parseUserData(string $data): array {
        $users = [];
        $recordSize = 72;
        
        if (strlen($data) < $recordSize) {
            return $users;
        }
        
        $records = str_split($data, $recordSize);
        
        foreach ($records as $record) {
            if (strlen($record) < $recordSize) continue;
            
            $uid = unpack('v', substr($record, 0, 2))[1];
            $role = ord($record[2]);
            $password = trim(substr($record, 3, 8));
            $name = $this->cleanString(substr($record, 11, 24));
            $cardNo = unpack('V', substr($record, 35, 4))[1];
            $userId = trim(substr($record, 48, 9));
            
            if ($uid > 0 && ($userId || $name)) {
                $users[] = [
                    'uid' => $uid,
                    'device_user_id' => $userId ?: (string)$uid,
                    'name' => $name,
                    'role' => $role,
                    'card_no' => $cardNo
                ];
            }
        }
        
        return $users;
    }
    
    private function parseUserDataNewFormat(string $data): array {
        $users = [];
        
        $recordSizes = [28, 32, 46, 52, 56, 64, 80];
        
        foreach ($recordSizes as $recordSize) {
            if (strlen($data) >= $recordSize && strlen($data) % $recordSize === 0) {
                $records = str_split($data, $recordSize);
                $tempUsers = [];
                
                foreach ($records as $record) {
                    if (strlen($record) < $recordSize) continue;
                    
                    $uid = unpack('v', substr($record, 0, 2))[1];
                    
                    $userId = '';
                    $name = '';
                    
                    if ($recordSize == 28) {
                        $userId = trim(substr($record, 2, 9));
                        $name = trim(substr($record, 11, 16));
                    } elseif ($recordSize == 32) {
                        $userId = trim(substr($record, 2, 9));
                        $name = trim(substr($record, 11, 20));
                    } elseif ($recordSize == 46) {
                        $userId = trim(substr($record, 2, 9));
                        $name = trim(substr($record, 11, 24));
                    } elseif ($recordSize == 52) {
                        $userId = trim(substr($record, 2, 9));
                        $name = trim(substr($record, 11, 28));
                    } elseif ($recordSize == 56) {
                        $userId = trim(substr($record, 2, 9));
                        $name = trim(substr($record, 11, 32));
                    } elseif ($recordSize == 64) {
                        $userId = trim(substr($record, 2, 9));
                        $name = trim(substr($record, 11, 40));
                    } elseif ($recordSize == 80) {
                        $userId = trim(substr($record, 2, 9));
                        $name = trim(substr($record, 11, 48));
                    }
                    
                    $name = preg_replace('/[^\x20-\x7E]/', '', $name);
                    $userId = preg_replace('/[^\x20-\x7E]/', '', $userId);
                    
                    if ($uid > 0 && ($userId || $name)) {
                        $tempUsers[] = [
                            'uid' => $uid,
                            'device_user_id' => $userId ?: (string)$uid,
                            'name' => $name ?: 'User ' . $uid,
                            'role' => 0,
                            'card_no' => 0
                        ];
                    }
                }
                
                if (!empty($tempUsers)) {
                    return $tempUsers;
                }
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
}
