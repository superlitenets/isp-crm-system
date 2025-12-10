<?php
echo "Testing ZKTeco K40 connection...\n";

$sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$sock) { 
    echo 'FAIL: Could not create socket - ' . socket_strerror(socket_last_error()) . "\n"; 
    exit(1); 
}
echo "Socket created OK\n";

socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 10, 'usec' => 0]);
socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 10, 'usec' => 0]);

$ip = '192.168.1.250';
$port = 4370;

// ZKTeco connect command - proper 16-byte header
// Start(2) + Reserved(2) + CMD_CONNECT(2) + Checksum(2) + SessionID(2) + ReplyNo(2) + PayloadSize(4)
$cmd = pack('v', 0x5050);    // Start signature
$cmd .= pack('v', 0x0000);   // Reserved
$cmd .= pack('v', 0x03E8);   // CMD_CONNECT (1000)
$cmd .= pack('v', 0x0000);   // Checksum placeholder
$cmd .= pack('v', 0x0000);   // Session ID (0 for connect)
$cmd .= pack('v', 0x0000);   // Reply number
$cmd .= pack('V', 0x00000000); // Payload size (0)

// Calculate checksum
$checksum = 0;
for ($i = 0; $i < strlen($cmd); $i += 2) {
    if ($i == 6) continue; // Skip checksum field
    $val = unpack('v', substr($cmd, $i, 2))[1];
    $checksum += $val;
}
$checksum = ($checksum >> 16) + ($checksum & 0xFFFF);
$checksum = (~$checksum) & 0xFFFF;

// Insert checksum
$cmd = substr($cmd, 0, 6) . pack('v', $checksum) . substr($cmd, 8);

echo "Packet: " . bin2hex($cmd) . " (" . strlen($cmd) . " bytes)\n";
echo "Sending to $ip:$port...\n";

$sent = @socket_sendto($sock, $cmd, strlen($cmd), 0, $ip, $port);
if ($sent === false) {
    echo 'FAIL: socket_sendto failed - ' . socket_strerror(socket_last_error($sock)) . "\n";
    socket_close($sock);
    exit(1);
}
echo "Sent $sent bytes\n";

echo "Waiting for response (10 sec timeout)...\n";
$buf = '';
$from = ''; 
$port_from = 0;
$recv = @socket_recvfrom($sock, $buf, 1024, 0, $from, $port_from);
if ($recv === false) {
    $err = socket_last_error($sock);
    echo 'FAIL: No response - ' . socket_strerror($err) . " (code: $err)\n";
} else {
    echo "SUCCESS: Got $recv bytes from $from:$port_from\n";
    echo "Response: " . bin2hex($buf) . "\n";
}

socket_close($sock);
echo "Done\n";
