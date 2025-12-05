<?php
namespace App;

class SmartOLT {
    private $db;
    private $apiUrl;
    private $apiKey;
    private $timeout = 30;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
        $this->loadSettings();
    }
    
    private function loadSettings(): void {
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        
        $stmt->execute(['smartolt_api_url']);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->apiUrl = $result ? rtrim($result['setting_value'], '/') : '';
        
        $stmt->execute(['smartolt_api_key']);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->apiKey = $result ? $result['setting_value'] : '';
    }
    
    public function isConfigured(): bool {
        return !empty($this->apiUrl) && !empty($this->apiKey);
    }
    
    public function getSettings(): array {
        return [
            'api_url' => $this->apiUrl,
            'api_key' => $this->apiKey ? '********' . substr($this->apiKey, -4) : ''
        ];
    }
    
    public static function saveSettings(\PDO $db, array $data): bool {
        $settings = [
            'smartolt_api_url' => $data['api_url'] ?? '',
            'smartolt_api_key' => $data['api_key'] ?? ''
        ];
        
        foreach ($settings as $key => $value) {
            if ($key === 'smartolt_api_key' && (empty($value) || strpos($value, '********') === 0)) {
                continue;
            }
            
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value
            ");
            $stmt->execute([$key, $value]);
        }
        
        return true;
    }
    
    private function makeRequest(string $endpoint, string $method = 'GET', array $data = []): array {
        if (!$this->isConfigured()) {
            return ['status' => false, 'error' => 'SmartOLT is not configured'];
        }
        
        $baseUrl = rtrim($this->apiUrl, '/');
        if (!preg_match('/\/api\/?$/', $baseUrl)) {
            $baseUrl .= '/api';
        }
        $url = $baseUrl . '/' . ltrim($endpoint, '/');
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'X-Token: ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['status' => false, 'error' => 'Connection error: ' . $error];
        }
        
        if ($httpCode !== 200) {
            return ['status' => false, 'error' => 'HTTP error: ' . $httpCode];
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['status' => false, 'error' => 'Invalid JSON response'];
        }
        
        return $decoded;
    }
    
    public function testConnection(): array {
        $result = $this->getOLTs();
        if ($result['status']) {
            return [
                'success' => true,
                'message' => 'Connected successfully. Found ' . count($result['response'] ?? []) . ' OLT(s).'
            ];
        }
        return [
            'success' => false,
            'message' => $result['error'] ?? 'Connection failed'
        ];
    }
    
    public function getOLTs(): array {
        return $this->makeRequest('system/get_olts');
    }
    
    public function getOLTsUptimeAndTemperature(): array {
        return $this->makeRequest('olt/get_olts_uptime_and_env_temperature');
    }
    
    public function getOLTCardsDetails(int $oltId): array {
        return $this->makeRequest("system/get_olt_cards_details/{$oltId}");
    }
    
    public function getOLTPonPortsDetails(int $oltId): array {
        return $this->makeRequest("system/get_olt_pon_ports_details/{$oltId}");
    }
    
    public function getOLTUplinkPortsDetails(int $oltId): array {
        return $this->makeRequest("system/get_olt_uplink_ports_details/{$oltId}");
    }
    
    public function getAllUnconfiguredONUs(): array {
        return $this->makeRequest('onu/get_unconfigured_onus');
    }
    
    public function getUnconfiguredONUsByOLT(int $oltId): array {
        return $this->makeRequest("onu/get_unconfigured_onus/{$oltId}");
    }
    
    public function getAllONUsStatuses(): array {
        return $this->makeRequest('onu/get_all_onus_statuses');
    }
    
    public function getAllONUsSignals(): array {
        return $this->makeRequest('onu/get_all_onus_signals');
    }
    
    public function getAllONUsDetails(): array {
        return $this->makeRequest('onu/get_all_onus_details');
    }
    
    public function getONUStatus(string $externalId): array {
        return $this->makeRequest("onu/get_onu_status/{$externalId}");
    }
    
    public function getONUSignal(string $externalId): array {
        return $this->makeRequest("onu/get_onu_signal/{$externalId}");
    }
    
    public function getONUDetails(string $externalId): array {
        return $this->makeRequest("onu/get_onu_details/{$externalId}");
    }
    
    public function getONUFullStatusInfo(string $externalId): array {
        return $this->makeRequest("onu/get_onu_full_status_info/{$externalId}");
    }
    
    public function getONURunningConfig(string $externalId): array {
        return $this->makeRequest("onu/get_onu_running_config/{$externalId}");
    }
    
    public function rebootONU(string $externalId): array {
        return $this->makeRequest("onu/reboot_onu/{$externalId}", 'POST');
    }
    
    public function resyncONUConfig(string $externalId): array {
        return $this->makeRequest("onu/resync_onu_config/{$externalId}", 'POST');
    }
    
    public function enableONU(string $externalId): array {
        return $this->makeRequest("onu/enable_onu/{$externalId}", 'POST');
    }
    
    public function disableONU(string $externalId): array {
        return $this->makeRequest("onu/disable_onu/{$externalId}", 'POST');
    }
    
    public function getDashboardStats(): array {
        $stats = [
            'olts' => [],
            'total_olts' => 0,
            'configured_onus' => 0,
            'unconfigured_onus' => 0,
            'online_onus' => 0,
            'offline_onus' => 0,
            'los_onus' => 0,
            'power_fail_onus' => 0,
            'critical_power_onus' => 0,
            'low_power_onus' => 0
        ];
        
        $oltsResult = $this->getOLTs();
        if ($oltsResult['status'] && isset($oltsResult['response'])) {
            $stats['olts'] = $oltsResult['response'];
            $stats['total_olts'] = count($oltsResult['response']);
        }
        
        $uptimeResult = $this->getOLTsUptimeAndTemperature();
        if ($uptimeResult['status'] && isset($uptimeResult['response'])) {
            foreach ($uptimeResult['response'] as $oltUptime) {
                foreach ($stats['olts'] as &$olt) {
                    if ($olt['id'] == $oltUptime['olt_id']) {
                        $olt['uptime'] = $oltUptime['uptime'] ?? 'N/A';
                        $olt['env_temp'] = $oltUptime['env_temp'] ?? 'N/A';
                        break;
                    }
                }
            }
            unset($olt);
        }
        
        $unconfiguredResult = $this->getAllUnconfiguredONUs();
        if ($unconfiguredResult['status'] && isset($unconfiguredResult['response'])) {
            $stats['unconfigured_onus'] = count($unconfiguredResult['response']);
            $stats['unconfigured_list'] = $unconfiguredResult['response'];
        }
        
        $statusesResult = $this->getAllONUsStatuses();
        if ($statusesResult['status'] && isset($statusesResult['response'])) {
            foreach ($statusesResult['response'] as $onu) {
                $stats['configured_onus']++;
                $status = strtolower($onu['status'] ?? '');
                
                if (strpos($status, 'online') !== false) {
                    $stats['online_onus']++;
                } elseif (strpos($status, 'los') !== false) {
                    $stats['los_onus']++;
                    $stats['offline_onus']++;
                } elseif (strpos($status, 'power') !== false || strpos($status, 'dyinggasp') !== false) {
                    $stats['power_fail_onus']++;
                    $stats['offline_onus']++;
                } else {
                    $stats['offline_onus']++;
                }
            }
        }
        
        $signalsResult = $this->getAllONUsSignals();
        if ($signalsResult['status'] && isset($signalsResult['response'])) {
            $stats['signals_list'] = $signalsResult['response'];
            foreach ($signalsResult['response'] as $signal) {
                $rxPower = $this->parseSignalPower($signal['onu_rx_power'] ?? null);
                if ($rxPower !== null) {
                    if ($rxPower <= -28) {
                        $stats['critical_power_onus']++;
                    } elseif ($rxPower <= -25) {
                        $stats['low_power_onus']++;
                    }
                }
            }
        }
        
        return $stats;
    }
    
    private function parseSignalPower(?string $power): ?float {
        if (empty($power) || $power === 'N/A' || $power === '-') {
            return null;
        }
        preg_match('/([-\d.]+)/', $power, $matches);
        return isset($matches[1]) ? (float)$matches[1] : null;
    }
    
    public function getONUsByStatus(string $status): array {
        $result = [];
        $statusesResult = $this->getAllONUsStatuses();
        $detailsResult = $this->getAllONUsDetails();
        
        $detailsMap = [];
        if ($detailsResult['status'] && isset($detailsResult['response'])) {
            foreach ($detailsResult['response'] as $detail) {
                $id = $detail['onu_external_id'] ?? $detail['id'] ?? null;
                if ($id) {
                    $detailsMap[$id] = $detail;
                }
            }
        }
        
        if ($statusesResult['status'] && isset($statusesResult['response'])) {
            foreach ($statusesResult['response'] as $onu) {
                $onuStatus = strtolower($onu['status'] ?? '');
                $match = false;
                
                switch (strtolower($status)) {
                    case 'online':
                        $match = strpos($onuStatus, 'online') !== false;
                        break;
                    case 'offline':
                        $match = strpos($onuStatus, 'online') === false;
                        break;
                    case 'los':
                        $match = strpos($onuStatus, 'los') !== false;
                        break;
                    case 'power_fail':
                        $match = strpos($onuStatus, 'power') !== false || strpos($onuStatus, 'dyinggasp') !== false;
                        break;
                }
                
                if ($match) {
                    $id = $onu['onu_external_id'] ?? $onu['id'] ?? null;
                    if ($id && isset($detailsMap[$id])) {
                        $result[] = array_merge($onu, $detailsMap[$id]);
                    } else {
                        $result[] = $onu;
                    }
                }
            }
        }
        
        return $result;
    }
    
    public function getCriticalPowerONUs(float $threshold = -28): array {
        $result = [];
        $signalsResult = $this->getAllONUsSignals();
        $detailsResult = $this->getAllONUsDetails();
        
        $detailsMap = [];
        if ($detailsResult['status'] && isset($detailsResult['response'])) {
            foreach ($detailsResult['response'] as $detail) {
                $id = $detail['onu_external_id'] ?? $detail['id'] ?? null;
                if ($id) {
                    $detailsMap[$id] = $detail;
                }
            }
        }
        
        if ($signalsResult['status'] && isset($signalsResult['response'])) {
            foreach ($signalsResult['response'] as $signal) {
                $rxPower = $this->parseSignalPower($signal['onu_rx_power'] ?? null);
                if ($rxPower !== null && $rxPower <= $threshold) {
                    $id = $signal['onu_external_id'] ?? $signal['id'] ?? null;
                    $onuData = $signal;
                    $onuData['rx_power_value'] = $rxPower;
                    
                    if ($id && isset($detailsMap[$id])) {
                        $onuData = array_merge($onuData, $detailsMap[$id]);
                    }
                    
                    $result[] = $onuData;
                }
            }
        }
        
        usort($result, function($a, $b) {
            return ($a['rx_power_value'] ?? 0) <=> ($b['rx_power_value'] ?? 0);
        });
        
        return $result;
    }
    
    public function getOLTDetails(int $oltId): array {
        $olts = $this->getOLTs();
        $oltInfo = null;
        
        if ($olts['status'] && isset($olts['response'])) {
            foreach ($olts['response'] as $olt) {
                if ($olt['id'] == $oltId) {
                    $oltInfo = $olt;
                    break;
                }
            }
        }
        
        if (!$oltInfo) {
            return ['status' => false, 'error' => 'OLT not found'];
        }
        
        $uptimeResult = $this->getOLTsUptimeAndTemperature();
        if ($uptimeResult['status'] && isset($uptimeResult['response'])) {
            foreach ($uptimeResult['response'] as $uptime) {
                if ($uptime['olt_id'] == $oltId) {
                    $oltInfo['uptime'] = $uptime['uptime'] ?? 'N/A';
                    $oltInfo['env_temp'] = $uptime['env_temp'] ?? 'N/A';
                    break;
                }
            }
        }
        
        $cardsResult = $this->getOLTCardsDetails($oltId);
        $oltInfo['cards'] = ($cardsResult['status'] && isset($cardsResult['response'])) ? $cardsResult['response'] : [];
        
        $ponResult = $this->getOLTPonPortsDetails($oltId);
        $oltInfo['pon_ports'] = ($ponResult['status'] && isset($ponResult['response'])) ? $ponResult['response'] : [];
        
        $uplinkResult = $this->getOLTUplinkPortsDetails($oltId);
        $oltInfo['uplink_ports'] = ($uplinkResult['status'] && isset($uplinkResult['response'])) ? $uplinkResult['response'] : [];
        
        return ['status' => true, 'response' => $oltInfo];
    }
}
