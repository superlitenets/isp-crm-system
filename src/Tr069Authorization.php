<?php

namespace App;

class Tr069Authorization
{
    private GenieACS $acs;
    private string $acsUrl;
    private string $connReqUser;
    private string $connReqPass;

    public function __construct(GenieACS $acs, ?string $acsUrl = null)
    {
        $this->acs = $acs;
        $this->acsUrl = $acsUrl ?? ($_ENV['GENIEACS_URL'] ?? 'http://localhost:7547');
        $this->connReqUser = $_ENV['TR069_CONN_REQ_USER'] ?? 'genieacs';
        $this->connReqPass = $_ENV['TR069_CONN_REQ_PASS'] ?? 'genieacs';
    }

    public function handle(string $deviceId): array
    {
        $tasks = [[
            "name" => "setParameterValues",
            "parameterValues" => [
                ["InternetGatewayDevice.ManagementServer.URL", $this->acsUrl, "xsd:string"],
                ["InternetGatewayDevice.ManagementServer.ConnectionRequestUsername", $this->connReqUser, "xsd:string"],
                ["InternetGatewayDevice.ManagementServer.ConnectionRequestPassword", $this->connReqPass, "xsd:string"],
                ["InternetGatewayDevice.ManagementServer.PeriodicInformEnable", true, "xsd:boolean"],
                ["InternetGatewayDevice.ManagementServer.PeriodicInformInterval", 300, "xsd:unsignedInt"]
            ]
        ]];

        $result = $this->acs->pushTask($deviceId, $tasks);

        return [
            'success' => $result['success'] ?? false,
            'next_state' => 'OWNED',
            'message' => $result['success'] ? 'ACS ownership configured' : ($result['error'] ?? 'Failed to configure ACS')
        ];
    }
}
