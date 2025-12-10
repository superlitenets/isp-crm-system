<?php
$sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$sock) { 
    echo 'Failed to create socket'; 
    exit(1); 
}
socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);
$cmd = pack('CCCCCCCC', 0x50, 0x50, 0x82, 0x7d, 0xe8, 0x03, 0x00, 0x00);
if (@socket_sendto($sock, $cmd, strlen($cmd), 0, '192.168.1.250', 4370)) {
    $buf = '';
    $from = ''; 
    $port = 0;
    if (@socket_recvfrom($sock, $buf, 1024, 0, $from, $port)) {
        echo 'SUCCESS: Got response from device (' . strlen($buf) . ' bytes)' . PHP_EOL;
    } else {
        echo 'FAIL: No response from device (timeout)' . PHP_EOL;
    }
} else {
    echo 'FAIL: Could not send packet' . PHP_EOL;
}
socket_close($sock);
