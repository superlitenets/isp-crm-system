<?php
namespace App;

class RadiusClient {
    private const RADIUS_CODE_DISCONNECT_REQUEST = 40;
    private const RADIUS_CODE_DISCONNECT_ACK = 41;
    private const RADIUS_CODE_DISCONNECT_NAK = 42;
    private const RADIUS_CODE_COA_REQUEST = 43;
    private const RADIUS_CODE_COA_ACK = 44;
    private const RADIUS_CODE_COA_NAK = 45;
    
    private const ATTR_USER_NAME = 1;
    private const ATTR_NAS_IP_ADDRESS = 4;
    private const ATTR_FRAMED_IP_ADDRESS = 8;
    private const ATTR_CALLING_STATION_ID = 31;
    private const ATTR_ACCT_SESSION_ID = 44;
    private const ATTR_EVENT_TIMESTAMP = 55;
    private const ATTR_VENDOR_SPECIFIC = 26;
    
    private const VENDOR_MIKROTIK = 14988;
    private const MIKROTIK_RATE_LIMIT = 8;
    
    private string $nasIp;
    private int $nasPort;
    private string $secret;
    private int $timeout;
    
    public function __construct(string $nasIp, string $secret, int $nasPort = 3799, int $timeout = 5) {
        $this->nasIp = $nasIp;
        $this->nasPort = $nasPort;
        $this->secret = $secret;
        $this->timeout = $timeout;
    }
    
    public function disconnect(array $attributes): array {
        return $this->sendPacket(self::RADIUS_CODE_DISCONNECT_REQUEST, $attributes, self::RADIUS_CODE_DISCONNECT_ACK);
    }
    
    public function coa(array $attributes): array {
        return $this->sendPacket(self::RADIUS_CODE_COA_REQUEST, $attributes, self::RADIUS_CODE_COA_ACK);
    }
    
    private function sendPacket(int $code, array $attributes, int $expectedAck): array {
        $socket = @\socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            return ['success' => false, 'error' => 'Failed to create socket: ' . \socket_strerror(\socket_last_error())];
        }
        
        \socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        \socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        
        $identifier = \random_int(0, 255);
        $authenticator = \random_bytes(16);
        
        $attrData = $this->encodeAttributes($attributes);
        
        $length = 20 + \strlen($attrData);
        $packet = \pack('CCn', $code, $identifier, $length) . $authenticator . $attrData;
        
        $newAuth = \md5($packet . $this->secret, true);
        $packet = \substr($packet, 0, 4) . $newAuth . $attrData;
        
        $result = @\socket_sendto($socket, $packet, \strlen($packet), 0, $this->nasIp, $this->nasPort);
        if ($result === false) {
            $error = \socket_strerror(\socket_last_error($socket));
            \socket_close($socket);
            return ['success' => false, 'error' => 'Failed to send packet: ' . $error];
        }
        
        $response = '';
        $from = '';
        $port = 0;
        $result = @\socket_recvfrom($socket, $response, 4096, 0, $from, $port);
        \socket_close($socket);
        
        if ($result === false || $result < 20) {
            $diagnostic = $this->diagnoseConnection();
            return [
                'success' => false, 
                'error' => 'No response from NAS (timeout or connection refused)',
                'diagnostic' => $diagnostic
            ];
        }
        
        $header = \unpack('Ccode/Cid/nlength', \substr($response, 0, 4));
        $responseCode = $header['code'];
        
        $codeNames = [
            self::RADIUS_CODE_DISCONNECT_ACK => 'Disconnect-ACK',
            self::RADIUS_CODE_DISCONNECT_NAK => 'Disconnect-NAK',
            self::RADIUS_CODE_COA_ACK => 'CoA-ACK',
            self::RADIUS_CODE_COA_NAK => 'CoA-NAK',
        ];
        
        $codeName = $codeNames[$responseCode] ?? "Unknown-$responseCode";
        
        if ($responseCode === $expectedAck) {
            return ['success' => true, 'response' => $codeName, 'code' => $responseCode];
        }
        
        return ['success' => false, 'error' => "Received $codeName", 'code' => $responseCode];
    }
    
    private function encodeAttributes(array $attributes): string {
        $data = '';
        
        foreach ($attributes as $name => $value) {
            if ($name === 'Mikrotik-Rate-Limit') {
                $data .= $this->encodeVendorAttribute(self::VENDOR_MIKROTIK, self::MIKROTIK_RATE_LIMIT, (string)$value);
                continue;
            }
            
            $attrType = $this->getAttributeType($name);
            if ($attrType === null) continue;
            
            $encoded = $this->encodeAttributeValue($attrType, $value);
            if ($encoded !== null) {
                $data .= \pack('CC', $attrType, 2 + \strlen($encoded)) . $encoded;
            }
        }
        
        return $data;
    }
    
    private function encodeVendorAttribute(int $vendorId, int $vendorType, string $value): string {
        $vendorData = \pack('CC', $vendorType, 2 + \strlen($value)) . $value;
        $vsaData = \pack('N', $vendorId) . $vendorData;
        return \pack('CC', self::ATTR_VENDOR_SPECIFIC, 2 + \strlen($vsaData)) . $vsaData;
    }
    
    private function getAttributeType(string $name): ?int {
        $map = [
            'User-Name' => self::ATTR_USER_NAME,
            'NAS-IP-Address' => self::ATTR_NAS_IP_ADDRESS,
            'Framed-IP-Address' => self::ATTR_FRAMED_IP_ADDRESS,
            'Calling-Station-Id' => self::ATTR_CALLING_STATION_ID,
            'Acct-Session-Id' => self::ATTR_ACCT_SESSION_ID,
            'Event-Timestamp' => self::ATTR_EVENT_TIMESTAMP,
        ];
        
        return $map[$name] ?? null;
    }
    
    private function encodeAttributeValue(int $type, $value): ?string {
        switch ($type) {
            case self::ATTR_NAS_IP_ADDRESS:
            case self::ATTR_FRAMED_IP_ADDRESS:
                $ip = \ip2long($value);
                return $ip !== false ? \pack('N', $ip) : null;
                
            case self::ATTR_EVENT_TIMESTAMP:
                return \pack('N', \is_int($value) ? $value : \time());
                
            default:
                return (string)$value;
        }
    }
    
    private function diagnoseConnection(): array {
        $diagnostic = [
            'nas_ip' => $this->nasIp,
            'coa_port' => $this->nasPort,
            'ping_reachable' => false,
            'likely_cause' => ''
        ];
        
        \exec("ping -c 1 -W 2 " . \escapeshellarg($this->nasIp) . " 2>&1", $output, $exitCode);
        $diagnostic['ping_reachable'] = ($exitCode === 0);
        
        if ($diagnostic['ping_reachable']) {
            $diagnostic['likely_cause'] = 'NAS is reachable but CoA port not responding. Check MikroTik config: /radius incoming set accept=yes port=3799';
            $diagnostic['mikrotik_fix'] = '/radius incoming set accept=yes port=3799';
        } else {
            $diagnostic['likely_cause'] = 'NAS is not reachable. Check VPN tunnel, firewall, or NAS IP address.';
        }
        
        return $diagnostic;
    }
}
