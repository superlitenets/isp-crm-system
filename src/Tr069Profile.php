<?php

namespace App;

class Tr069Profile
{
    private GenieACS $acs;

    public function __construct(GenieACS $acs)
    {
        $this->acs = $acs;
    }

    public function handle(string $deviceId, ?array $device = null): array
    {
        if (!$device) {
            $device = $this->acs->getDevice($deviceId);
        }

        if (!$device) {
            return [
                'success' => false,
                'next_state' => null,
                'message' => 'Device not found in GenieACS'
            ];
        }

        $time = $this->extractParam($device, 'InternetGatewayDevice.DeviceInfo.CurrentTime');
        $year = $time ? intval(substr($time, 0, 4)) : 0;

        if ($year < 2020) {
            $tasks = [[
                "name" => "setParameterValues",
                "parameterValues" => [
                    ["InternetGatewayDevice.Time.NTPServer1", "pool.ntp.org", "xsd:string"],
                    ["InternetGatewayDevice.Time.NTPServer2", "time.google.com", "xsd:string"],
                    ["InternetGatewayDevice.Time.Enable", true, "xsd:boolean"]
                ]
            ]];

            $result = $this->acs->pushTask($deviceId, $tasks);

            return [
                'success' => false,
                'next_state' => 'WAITING_NTP',
                'message' => "Device time invalid (year: $year). NTP pushed. Wait for next Inform.",
                'retry_after' => 60
            ];
        }

        return [
            'success' => true,
            'next_state' => 'READY',
            'message' => 'Device time valid. Ready for WAN provisioning.'
        ];
    }

    private function extractParam(array $device, string $path): ?string
    {
        if (isset($device[$path]['_value'])) {
            return $device[$path]['_value'];
        }
        if (isset($device[$path])) {
            return is_array($device[$path]) ? ($device[$path][0] ?? null) : $device[$path];
        }
        return null;
    }
}
