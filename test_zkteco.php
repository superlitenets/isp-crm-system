<?php
echo "Testing ZKTeco connection...\n";

$sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$sock) { 
    echo 'FAIL: Could not create socket - ' . socket_strerror(socket_last_error()) . "\n"; 
    exit(1); 
}
echo "Socket created OK\n";

socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);
socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 5, 'usec' => 0]);

$ip = '192.168.1.250';
$port = 4370;

// ZKTeco connect command
$cmd = pack('CCCCCCCC', 0x50, 0x50, 0x82, 0x7d, 0xe8, 0x03, 0x00, 0x00);

echo "Sending to $ip:$port (" . strlen($cmd) . " bytes)...\n";

$sent = @socket_sendto($sock, $cmd, strlen($cmd), 0, $ip, $port);
if ($sent === false) {
    echo 'FAIL: socket_sendto failed - ' . socket_strerror(socket_last_error($sock)) . "\n";
    socket_close($sock);
    exit(1);
}
echo "Sent $sent bytes\n";

echo "Waiting for response...\n";
$buf = '';
$from = ''; 
$port_from = 0;
$recv = @socket_recvfrom($sock, $buf, 1024, 0, $from, $port_from);
if ($recv === false) {
    $err = socket_last_error($sock);
    echo 'FAIL: socket_recvfrom failed - ' . socket_strerror($err) . " (code: $err)\n";
} else {
    echo "SUCCESS: Got $recv bytes from $from:$port_from\n";
}

socket_close($sock);
echo "Done\n";
