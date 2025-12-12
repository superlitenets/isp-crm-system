<?php

namespace App;

class OneISP {
    private \PDO $db;
    private string $baseUrl = 'https://ns3.api.one-isp.net/api/isp';
    private ?string $token = null;
    
    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
        $this->loadToken();
    }
    
    private function loadToken(): void {
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'oneisp_api_token'");
        $stmt->execute();
        $result = $stmt->fetch();
        $this->token = $result ? $result['setting_value'] : null;
    }
    
    public function isConfigured(): bool {
        return !empty($this->token);
    }
    
    public function getToken(): ?string {
        return $this->token;
    }
    
    private function request(string $endpoint, array $params = []): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'One-ISP API token not configured'];
        }
        
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->token,
            "Accept: application/json"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => 'cURL error: ' . $error];
        }
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'API returned HTTP ' . $httpCode, 'response' => $response];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Invalid JSON response'];
        }
        
        return ['success' => true, 'data' => $data];
    }
    
    public function getCustomers(int $page = 1, int $perPage = 100): array {
        return $this->request('/customers', ['page' => $page, 'per_page' => $perPage]);
    }
    
    public function searchCustomers(string $search): array {
        return $this->request('/customers', ['search' => $search]);
    }
    
    public function getCustomer(int $id): array {
        return $this->request('/customers/' . $id);
    }
    
    public function testConnection(): array {
        $result = $this->getCustomers(1, 1);
        if ($result['success']) {
            return ['success' => true, 'message' => 'Connection successful'];
        }
        return $result;
    }
    
    public function mapCustomerToLocal(array $billingCustomer): array {
        $firstName = trim($billingCustomer['first_name'] ?? '');
        $lastName = trim($billingCustomer['last_name'] ?? '');
        $fullName = $billingCustomer['name'] ?? null;
        
        if (empty($fullName)) {
            $fullName = trim($firstName . ' ' . $lastName);
        }
        if (empty($fullName)) {
            $fullName = 'Billing Customer #' . ($billingCustomer['id'] ?? 'Unknown');
        }
        
        $phone = $billingCustomer['phone'] ?? $billingCustomer['mobile'] ?? null;
        $address = $billingCustomer['address'] ?? $billingCustomer['physical_address'] ?? 'N/A';
        $servicePlan = $billingCustomer['package'] ?? $billingCustomer['tariff'] ?? 'Standard';
        
        return [
            'billing_id' => $billingCustomer['id'] ?? null,
            'username' => $billingCustomer['username'] ?? null,
            'name' => $fullName,
            'email' => $billingCustomer['email'] ?? null,
            'phone' => $phone,
            'address' => !empty($address) ? $address : 'N/A',
            'service_plan' => !empty($servicePlan) ? $servicePlan : 'Standard',
            'connection_status' => $this->mapStatus($billingCustomer['status'] ?? 'active'),
        ];
    }
    
    private function mapStatus(string $status): string {
        $statusMap = [
            'active' => 'active',
            'enabled' => 'active',
            'disabled' => 'inactive',
            'suspended' => 'suspended',
            'expired' => 'inactive',
        ];
        return $statusMap[strtolower($status)] ?? 'active';
    }
    
    public function importCustomer(array $billingCustomer): ?int {
        $mapped = $this->mapCustomerToLocal($billingCustomer);
        
        $existingStmt = $this->db->prepare("SELECT id FROM customers WHERE billing_id = ? OR (phone IS NOT NULL AND phone = ?)");
        $existingStmt->execute([$mapped['billing_id'], $mapped['phone']]);
        $existing = $existingStmt->fetch();
        
        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE customers SET 
                    username = COALESCE(?, username),
                    name = COALESCE(?, name),
                    email = COALESCE(?, email),
                    address = COALESCE(?, address),
                    service_plan = COALESCE(?, service_plan),
                    billing_id = COALESCE(?, billing_id),
                    connection_status = COALESCE(?, connection_status),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $mapped['username'],
                $mapped['name'],
                $mapped['email'],
                $mapped['address'],
                $mapped['service_plan'],
                $mapped['billing_id'],
                $mapped['connection_status'],
                $existing['id']
            ]);
            return (int)$existing['id'];
        }
        
        $customer = new Customer();
        return $customer->create([
            'account_number' => $customer->generateAccountNumber(),
            'name' => $mapped['name'],
            'email' => $mapped['email'],
            'phone' => $mapped['phone'],
            'address' => $mapped['address'] ?? 'N/A',
            'service_plan' => $mapped['service_plan'] ?? 'Standard',
            'connection_status' => $mapped['connection_status'],
            'username' => $mapped['username'],
            'billing_id' => $mapped['billing_id'],
        ]);
    }
}
