<?php

namespace App;

class WifiConfigurator
{
    private GenieACS $acs;

    public function __construct(GenieACS $acs)
    {
        $this->acs = $acs;
    }

    public function configure(string $deviceId, string $ssid, string $password, int $radioIndex = 1): array
    {
        if (strlen($password) < 8 || strlen($password) > 63) {
            return [
                'success' => false,
                'message' => 'WiFi password must be 8-63 characters'
            ];
        }

        $basePath = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.$radioIndex";

        $tasks = [[
            "name" => "setParameterValues",
            "parameterValues" => [
                ["$basePath.SSID", $ssid, "xsd:string"],
                ["$basePath.PreSharedKey.1.PreSharedKey", $password, "xsd:string"],
                ["$basePath.BeaconType", "WPAand11i", "xsd:string"],
                ["$basePath.WPAEncryptionModes", "AESEncryption", "xsd:string"],
                ["$basePath.IEEE11iEncryptionModes", "AESEncryption", "xsd:string"],
                ["$basePath.WPAAuthenticationMode", "PSKAuthentication", "xsd:string"],
                ["$basePath.IEEE11iAuthenticationMode", "PSKAuthentication", "xsd:string"],
                ["$basePath.Enable", true, "xsd:boolean"]
            ]
        ]];

        $result = $this->acs->pushTask($deviceId, $tasks);

        return [
            'success' => $result['success'] ?? false,
            'message' => $result['success'] 
                ? "WiFi configured: SSID=$ssid" 
                : ($result['error'] ?? 'Failed to configure WiFi')
        ];
    }

    public function configureDualBand(string $deviceId, array $config24, array $config5 = []): array
    {
        $results = [];

        if (!empty($config24['ssid']) && !empty($config24['password'])) {
            $results['2.4GHz'] = $this->configure($deviceId, $config24['ssid'], $config24['password'], 1);
        }

        if (!empty($config5['ssid']) && !empty($config5['password'])) {
            $results['5GHz'] = $this->configure($deviceId, $config5['ssid'], $config5['password'], 5);
        }

        $success = true;
        foreach ($results as $band => $result) {
            if (!($result['success'] ?? false)) {
                $success = false;
            }
        }

        return [
            'success' => $success,
            'results' => $results,
            'message' => $success ? 'WiFi configured for all bands' : 'Some bands failed'
        ];
    }

    public function disable(string $deviceId, int $radioIndex = 1): array
    {
        $basePath = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.$radioIndex";

        $tasks = [[
            "name" => "setParameterValues",
            "parameterValues" => [
                ["$basePath.Enable", false, "xsd:boolean"]
            ]
        ]];

        $result = $this->acs->pushTask($deviceId, $tasks);

        return [
            'success' => $result['success'] ?? false,
            'message' => $result['success'] ? 'WiFi disabled' : ($result['error'] ?? 'Failed to disable WiFi')
        ];
    }
}
