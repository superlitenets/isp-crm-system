<?php

namespace App;

class ServiceWanProvisioner
{
    private GenieACS $acs;

    public function __construct(GenieACS $acs)
    {
        $this->acs = $acs;
    }

    public function createWanDevice(string $deviceId): array
    {
        $tasks = [[
            "name" => "addObject",
            "objectName" => "InternetGatewayDevice.WANDevice."
        ]];

        $result = $this->acs->pushTask($deviceId, $tasks);

        return [
            'success' => $result['success'] ?? false,
            'next_state' => 'WAN_DEVICE_CREATED',
            'message' => $result['success'] ? 'WANDevice created' : ($result['error'] ?? 'Failed to create WANDevice')
        ];
    }

    public function createWanConnectionDevice(string $deviceId, string $wanDevicePath): array
    {
        $path = rtrim($wanDevicePath, '.') . '.WANConnectionDevice.';
        
        $tasks = [[
            "name" => "addObject",
            "objectName" => $path
        ]];

        $result = $this->acs->pushTask($deviceId, $tasks);

        return [
            'success' => $result['success'] ?? false,
            'next_state' => 'WAN_CONN_CREATED',
            'message' => $result['success'] ? 'WANConnectionDevice created' : ($result['error'] ?? 'Failed to create WANConnectionDevice')
        ];
    }

    public function createPppConnection(string $deviceId, string $connPath): array
    {
        $path = rtrim($connPath, '.') . '.WANPPPConnection.';
        
        $tasks = [[
            "name" => "addObject",
            "objectName" => $path
        ]];

        $result = $this->acs->pushTask($deviceId, $tasks);

        return [
            'success' => $result['success'] ?? false,
            'next_state' => 'WAN_PPP_CREATED',
            'message' => $result['success'] ? 'WANPPPConnection created' : ($result['error'] ?? 'Failed to create WANPPPConnection')
        ];
    }

    public function createIpConnection(string $deviceId, string $connPath): array
    {
        $path = rtrim($connPath, '.') . '.WANIPConnection.';
        
        $tasks = [[
            "name" => "addObject",
            "objectName" => $path
        ]];

        $result = $this->acs->pushTask($deviceId, $tasks);

        return [
            'success' => $result['success'] ?? false,
            'next_state' => 'WAN_IP_CREATED',
            'message' => $result['success'] ? 'WANIPConnection created' : ($result['error'] ?? 'Failed to create WANIPConnection')
        ];
    }

    public function configurePppoe(string $deviceId, string $pppPath, array $creds): array
    {
        $path = rtrim($pppPath, '.');
        
        $params = [
            ["$path.Username", $creds['username'] ?? $creds['user'], "xsd:string"],
            ["$path.Password", $creds['password'] ?? $creds['pass'], "xsd:string"],
            ["$path.NATEnabled", true, "xsd:boolean"],
            ["$path.Enable", true, "xsd:boolean"]
        ];

        if (isset($creds['vlan'])) {
            $params[] = ["$path.X_HW_VLAN", (int)$creds['vlan'], "xsd:unsignedInt"];
        }

        if (isset($creds['name'])) {
            $params[] = ["$path.Name", $creds['name'], "xsd:string"];
        }

        $tasks = [[
            "name" => "setParameterValues",
            "parameterValues" => $params
        ]];

        $result = $this->acs->pushTask($deviceId, $tasks);

        return [
            'success' => $result['success'] ?? false,
            'next_state' => 'ACTIVE',
            'message' => $result['success'] ? 'PPPoE configured' : ($result['error'] ?? 'Failed to configure PPPoE')
        ];
    }

    public function configureIpoe(string $deviceId, string $ipPath, array $config): array
    {
        $path = rtrim($ipPath, '.');
        
        $params = [
            ["$path.AddressingType", $config['addressing'] ?? 'DHCP', "xsd:string"],
            ["$path.NATEnabled", $config['nat'] ?? true, "xsd:boolean"],
            ["$path.Enable", true, "xsd:boolean"]
        ];

        if (isset($config['vlan'])) {
            $params[] = ["$path.X_HW_VLAN", (int)$config['vlan'], "xsd:unsignedInt"];
        }

        if (isset($config['name'])) {
            $params[] = ["$path.Name", $config['name'], "xsd:string"];
        }

        $tasks = [[
            "name" => "setParameterValues",
            "parameterValues" => $params
        ]];

        $result = $this->acs->pushTask($deviceId, $tasks);

        return [
            'success' => $result['success'] ?? false,
            'next_state' => 'ACTIVE',
            'message' => $result['success'] ? 'IPoE configured' : ($result['error'] ?? 'Failed to configure IPoE')
        ];
    }
}
