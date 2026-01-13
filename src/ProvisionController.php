<?php

namespace App;

class ProvisionController
{
    private \PDO $db;
    private GenieACS $acs;
    private Tr069Authorization $auth;
    private Tr069Profile $profile;
    private ServiceWanProvisioner $wan;
    private WifiConfigurator $wifi;

    const STATES = [
        'DISCOVERED' => 0,
        'OWNED' => 1,
        'WAITING_NTP' => 2,
        'READY' => 3,
        'WAN_DEVICE_CREATED' => 4,
        'WAN_CONN_CREATED' => 5,
        'WAN_PPP_CREATED' => 6,
        'WAN_IP_CREATED' => 6,
        'ACTIVE' => 7,
        'ERROR' => -1
    ];

    public function __construct(\PDO $db, GenieACS $acs)
    {
        $this->db = $db;
        $this->acs = $acs;
        $this->auth = new Tr069Authorization($acs);
        $this->profile = new Tr069Profile($acs);
        $this->wan = new ServiceWanProvisioner($acs);
        $this->wifi = new WifiConfigurator($acs);
    }

    public function getOnuState(int $onuId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM huawei_onus WHERE id = ?");
        $stmt->execute([$onuId]);
        $onu = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$onu) {
            return ['state' => null, 'error' => 'ONU not found'];
        }

        return [
            'state' => $onu['provision_state'] ?? 'DISCOVERED',
            'onu' => $onu
        ];
    }

    public function updateState(int $onuId, string $state, ?array $extraData = null): bool
    {
        $updates = ['provision_state' => $state];
        
        if ($extraData) {
            $updates = array_merge($updates, $extraData);
        }

        $setClauses = [];
        $params = [];
        foreach ($updates as $key => $value) {
            $setClauses[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $onuId;

        $sql = "UPDATE huawei_onus SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function provision(int $onuId, array $config = []): array
    {
        $stateInfo = $this->getOnuState($onuId);
        
        if (!$stateInfo['onu']) {
            return ['success' => false, 'error' => 'ONU not found'];
        }

        $onu = $stateInfo['onu'];
        $state = $stateInfo['state'];
        $deviceId = $onu['genieacs_id'] ?? $onu['sn'];

        $result = match ($state) {
            'DISCOVERED' => $this->handleDiscovered($deviceId, $onuId),
            'OWNED' => $this->handleOwned($deviceId, $onuId),
            'WAITING_NTP' => $this->handleOwned($deviceId, $onuId),
            'READY' => $this->handleReady($deviceId, $onuId, $config),
            'WAN_DEVICE_CREATED' => $this->handleWanDeviceCreated($deviceId, $onuId, $config),
            'WAN_CONN_CREATED' => $this->handleWanConnCreated($deviceId, $onuId, $config),
            'WAN_PPP_CREATED', 'WAN_IP_CREATED' => $this->handleWanPppCreated($deviceId, $onuId, $config),
            'ACTIVE' => $this->handleActive($deviceId, $onuId, $config),
            default => ['success' => false, 'error' => "Unknown state: $state"]
        };

        if ($result['success'] && !empty($result['next_state'])) {
            $extraData = [];
            if (isset($result['wan_device_path'])) {
                $extraData['wan_device_path'] = $result['wan_device_path'];
            }
            if (isset($result['wan_conn_path'])) {
                $extraData['wan_conn_path'] = $result['wan_conn_path'];
            }
            if (isset($result['ppp_path'])) {
                $extraData['ppp_path'] = $result['ppp_path'];
            }
            
            $this->updateState($onuId, $result['next_state'], $extraData ?: null);
        }

        $result['current_state'] = $state;
        $result['onu_id'] = $onuId;

        return $result;
    }

    private function handleDiscovered(string $deviceId, int $onuId): array
    {
        return $this->auth->handle($deviceId);
    }

    private function handleOwned(string $deviceId, int $onuId): array
    {
        return $this->profile->handle($deviceId);
    }

    private function handleReady(string $deviceId, int $onuId, array $config): array
    {
        $result = $this->wan->createWanDevice($deviceId);
        
        if ($result['success']) {
            $result['wan_device_path'] = 'InternetGatewayDevice.WANDevice.2';
        }
        
        return $result;
    }

    private function handleWanDeviceCreated(string $deviceId, int $onuId, array $config): array
    {
        $onu = $this->getOnuState($onuId)['onu'];
        $wanDevicePath = $onu['wan_device_path'] ?? 'InternetGatewayDevice.WANDevice.2';
        
        $result = $this->wan->createWanConnectionDevice($deviceId, $wanDevicePath);
        
        if ($result['success']) {
            $result['wan_conn_path'] = $wanDevicePath . '.WANConnectionDevice.1';
        }
        
        return $result;
    }

    private function handleWanConnCreated(string $deviceId, int $onuId, array $config): array
    {
        $onu = $this->getOnuState($onuId)['onu'];
        $connPath = $onu['wan_conn_path'] ?? 'InternetGatewayDevice.WANDevice.2.WANConnectionDevice.1';
        
        $wanType = $config['wan_type'] ?? 'pppoe';
        
        if ($wanType === 'pppoe') {
            $result = $this->wan->createPppConnection($deviceId, $connPath);
            if ($result['success']) {
                $result['ppp_path'] = $connPath . '.WANPPPConnection.1';
            }
        } else {
            $result = $this->wan->createIpConnection($deviceId, $connPath);
            if ($result['success']) {
                $result['ppp_path'] = $connPath . '.WANIPConnection.1';
            }
        }
        
        return $result;
    }

    private function handleWanPppCreated(string $deviceId, int $onuId, array $config): array
    {
        $onu = $this->getOnuState($onuId)['onu'];
        $pppPath = $onu['ppp_path'] ?? 'InternetGatewayDevice.WANDevice.2.WANConnectionDevice.1.WANPPPConnection.1';
        
        $wanType = $config['wan_type'] ?? 'pppoe';
        
        if ($wanType === 'pppoe') {
            return $this->wan->configurePppoe($deviceId, $pppPath, [
                'username' => $config['pppoe_username'] ?? '',
                'password' => $config['pppoe_password'] ?? '',
                'vlan' => $config['vlan'] ?? null,
                'name' => $config['wan_name'] ?? 'Internet'
            ]);
        } else {
            return $this->wan->configureIpoe($deviceId, $pppPath, [
                'addressing' => $config['addressing'] ?? 'DHCP',
                'vlan' => $config['vlan'] ?? null,
                'name' => $config['wan_name'] ?? 'Internet'
            ]);
        }
    }

    private function handleActive(string $deviceId, int $onuId, array $config): array
    {
        if (empty($config['wifi_ssid']) || empty($config['wifi_password'])) {
            return [
                'success' => true,
                'message' => 'ONU is ACTIVE. No WiFi config requested.',
                'next_state' => 'ACTIVE'
            ];
        }

        return $this->wifi->configure($deviceId, $config['wifi_ssid'], $config['wifi_password']);
    }

    public function configureWifiOnly(int $onuId, string $ssid, string $password, int $radioIndex = 1): array
    {
        $stateInfo = $this->getOnuState($onuId);
        
        if (!$stateInfo['onu']) {
            return ['success' => false, 'error' => 'ONU not found'];
        }

        $state = $stateInfo['state'];
        
        if (!in_array($state, ['ACTIVE', 'READY', 'WAN_PPP_CREATED', 'WAN_IP_CREATED'])) {
            return [
                'success' => false,
                'blocked' => true,
                'error' => "WiFi config not allowed in state: $state. Complete WAN provisioning first."
            ];
        }

        $deviceId = $stateInfo['onu']['genieacs_id'] ?? $stateInfo['onu']['sn'];
        return $this->wifi->configure($deviceId, $ssid, $password, $radioIndex);
    }

    public function getStateDescription(string $state): string
    {
        return match ($state) {
            'DISCOVERED' => 'ONU discovered, awaiting ACS ownership',
            'OWNED' => 'ACS owns device, checking time/NTP',
            'WAITING_NTP' => 'Waiting for NTP sync (device time invalid)',
            'READY' => 'Device ready for WAN provisioning',
            'WAN_DEVICE_CREATED' => 'WANDevice created, creating WANConnectionDevice',
            'WAN_CONN_CREATED' => 'WANConnectionDevice created, creating PPP/IP connection',
            'WAN_PPP_CREATED' => 'PPPoE connection created, configuring credentials',
            'WAN_IP_CREATED' => 'IPoE connection created, configuring settings',
            'ACTIVE' => 'ONU fully provisioned and active',
            'ERROR' => 'Provisioning error occurred',
            default => 'Unknown state'
        };
    }
}
