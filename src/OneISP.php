<?php

namespace App;

class OneISP {
    private \PDO $db;
    private string $baseUrl = 'https://one-isp.net';
    private string $apiUrl = 'https://ns3.api.one-isp.net/api/isp';
    private ?string $token = null;
    private ?string $sessionCookie = null;
    private ?string $prefix = null;
    private ?string $username = null;
    private ?string $password = null;
    private string $authMode = 'token';
    
    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
        $this->loadCredentials();
    }
    
    private function loadCredentials(): void {
        $this->token = getenv('ONEISP_API_TOKEN') ?: null;
        $this->prefix = getenv('ONEISP_PREFIX') ?: null;
        $this->username = getenv('ONEISP_USERNAME') ?: null;
        $this->password = getenv('ONEISP_PASSWORD') ?: null;
        
        if (empty($this->token)) {
            $stmt = $this->db->prepare("SELECT setting_value FROM company_settings WHERE setting_key = 'oneisp_api_token'");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->token = !empty($result['setting_value']) ? trim($result['setting_value']) : null;
        }
        
        if (empty($this->prefix)) {
            $stmt = $this->db->prepare("SELECT setting_value FROM company_settings WHERE setting_key = 'oneisp_prefix'");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->prefix = !empty($result['setting_value']) ? trim($result['setting_value']) : null;
        }
        
        if (empty($this->username)) {
            $stmt = $this->db->prepare("SELECT setting_value FROM company_settings WHERE setting_key = 'oneisp_username'");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->username = !empty($result['setting_value']) ? trim($result['setting_value']) : null;
        }
        
        if (empty($this->password)) {
            $stmt = $this->db->prepare("SELECT setting_value FROM company_settings WHERE setting_key = 'oneisp_password'");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->password = !empty($result['setting_value']) ? trim($result['setting_value']) : null;
        }
        
        if (!empty($this->token)) {
            $this->authMode = 'token';
        } elseif (!empty($this->username) && !empty($this->password)) {
            $this->authMode = 'login';
        }
    }
    
    public function isConfigured(): bool {
        return !empty($this->token) || (!empty($this->username) && !empty($this->password));
    }
    
    public function getAuthMode(): string {
        return $this->authMode;
    }
    
    public function getToken(): ?string {
        return $this->token;
    }
    
    public function getUsername(): ?string {
        return $this->username;
    }
    
    private function login(): bool {
        if (empty($this->username) || empty($this->password)) {
            return false;
        }
        
        $cookieFile = sys_get_temp_dir() . '/oneisp_cookies_' . md5($this->username) . '.txt';
        
        $loginUrl = $this->baseUrl . '/login';
        $ch = curl_init($loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $html = curl_exec($ch);
        curl_close($ch);
        
        $csrfToken = '';
        if (preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $matches)) {
            $csrfToken = $matches[1];
        } elseif (preg_match('/name="_token"[^>]+value="([^"]+)"/', $html, $matches)) {
            $csrfToken = $matches[1];
        }
        
        $postData = [
            'email' => $this->username,
            'password' => $this->password,
            '_token' => $csrfToken
        ];
        
        if (!empty($this->prefix)) {
            $postData['prefix'] = $this->prefix;
        }
        
        $ch = curl_init($loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: text/html,application/xhtml+xml',
            'Referer: ' . $loginUrl
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        if (strpos($finalUrl, 'dashboard') !== false || strpos($finalUrl, 'home') !== false || $httpCode === 302) {
            $this->sessionCookie = $cookieFile;
            return true;
        }
        
        if (strpos($response, 'dashboard') !== false || strpos($response, 'logout') !== false) {
            $this->sessionCookie = $cookieFile;
            return true;
        }
        
        return false;
    }
    
    private function request(string $endpoint, array $params = []): array {
        if ($this->authMode === 'token') {
            return $this->requestWithToken($endpoint, $params);
        } else {
            return $this->requestWithSession($endpoint, $params);
        }
    }
    
    private function requestWithToken(string $endpoint, array $params = []): array {
        if (empty($this->token)) {
            return ['success' => false, 'error' => 'One-ISP API token not configured'];
        }
        
        $url = $this->apiUrl . $endpoint;
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
    
    private function requestWithSession(string $endpoint, array $params = []): array {
        if (empty($this->sessionCookie) && !$this->login()) {
            return ['success' => false, 'error' => 'Failed to login to One-ISP. Check username/password.'];
        }
        
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->sessionCookie);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json, text/html',
            'X-Requested-With: XMLHttpRequest'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => 'cURL error: ' . $error];
        }
        
        if ($httpCode === 401 || $httpCode === 403) {
            $this->sessionCookie = null;
            if ($this->login()) {
                return $this->requestWithSession($endpoint, $params);
            }
            return ['success' => false, 'error' => 'Session expired and re-login failed'];
        }
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'Request returned HTTP ' . $httpCode];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return ['success' => true, 'data' => $data];
        }
        
        return ['success' => true, 'data' => $response, 'is_html' => true];
    }
    
    public function getCustomers(int $page = 1, int $perPage = 100): array {
        if ($this->authMode === 'token') {
            return $this->request('/customers', ['page' => $page, 'per_page' => $perPage]);
        }
        
        $result = $this->requestWithSession('/api/customers', ['page' => $page, 'per_page' => $perPage]);
        
        if (!$result['success'] || !empty($result['is_html'])) {
            $htmlResult = $this->requestWithSession('/customers');
            if ($htmlResult['success'] && !empty($htmlResult['is_html'])) {
                return $this->parseCustomersFromHtml($htmlResult['data']);
            }
        }
        
        return $result;
    }
    
    private function parseCustomersFromHtml(string $html): array {
        $customers = [];
        
        if (preg_match_all('/<tr[^>]*data-customer-id="(\d+)"[^>]*>(.*?)<\/tr>/s', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $row = $match[2];
                $customer = ['ID' => $match[1]];
                
                if (preg_match('/<td[^>]*class="[^"]*name[^"]*"[^>]*>(.*?)<\/td>/s', $row, $nameMatch)) {
                    $name = strip_tags($nameMatch[1]);
                    $parts = explode(' ', trim($name), 2);
                    $customer['FirstName'] = $parts[0] ?? '';
                    $customer['LastName'] = $parts[1] ?? '';
                }
                
                if (preg_match('/[\d]{10,12}/', $row, $phoneMatch)) {
                    $customer['PhoneNumber'] = $phoneMatch[0];
                }
                
                if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $row, $emailMatch)) {
                    $customer['Email'] = $emailMatch[0];
                }
                
                $customers[] = $customer;
            }
        }
        
        if (empty($customers) && preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $html, $rowMatches)) {
            foreach ($rowMatches[1] as $row) {
                if (strpos($row, '<th') !== false) continue;
                
                preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cells);
                if (count($cells[1]) >= 3) {
                    $customer = [];
                    foreach ($cells[1] as $i => $cell) {
                        $value = trim(strip_tags($cell));
                        if ($i === 0 && is_numeric($value)) {
                            $customer['ID'] = $value;
                        } elseif (preg_match('/^[A-Za-z\s]+$/', $value) && strlen($value) > 2 && empty($customer['FirstName'])) {
                            $parts = explode(' ', $value, 2);
                            $customer['FirstName'] = $parts[0] ?? '';
                            $customer['LastName'] = $parts[1] ?? '';
                        } elseif (preg_match('/[\d]{10,12}/', $value)) {
                            $customer['PhoneNumber'] = $value;
                        } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $customer['Email'] = $value;
                        }
                    }
                    if (!empty($customer['FirstName'])) {
                        $customers[] = $customer;
                    }
                }
            }
        }
        
        return ['success' => true, 'data' => $customers, 'source' => 'html_parse'];
    }
    
    public function searchCustomers(string $search): array {
        if ($this->authMode === 'token') {
            return $this->request('/customers', ['search' => $search]);
        }
        return $this->requestWithSession('/api/customers', ['search' => $search]);
    }
    
    public function getCustomer(int $id): array {
        if ($this->authMode === 'token') {
            return $this->request('/customers/' . $id);
        }
        return $this->requestWithSession('/api/customers/' . $id);
    }
    
    public function testConnection(): array {
        if ($this->authMode === 'login') {
            if ($this->login()) {
                return ['success' => true, 'message' => 'Login successful', 'mode' => 'login'];
            }
            return ['success' => false, 'error' => 'Login failed. Check username and password.'];
        }
        
        $result = $this->getCustomers(1, 1);
        if ($result['success']) {
            return ['success' => true, 'message' => 'API connection successful', 'mode' => 'token'];
        }
        return $result;
    }
    
    public function mapCustomerToLocal(array $billingCustomer): ?array {
        $firstName = trim($billingCustomer['FirstName'] ?? $billingCustomer['first_name'] ?? '');
        $lastName = trim($billingCustomer['LastName'] ?? $billingCustomer['last_name'] ?? '');
        
        $fullName = trim($firstName . ' ' . $lastName);
        if (empty($fullName) && !empty($billingCustomer['name'])) {
            $fullName = trim($billingCustomer['name']);
        }
        if (empty($fullName)) {
            return null;
        }
        
        $username = $billingCustomer['UserName'] ?? $billingCustomer['username'] ?? null;
        $phone = $billingCustomer['PhoneNumber'] ?? $billingCustomer['phone'] ?? null;
        
        $location = $billingCustomer['Location'] ?? $billingCustomer['location'] ?? '';
        $apartment = $billingCustomer['Apartment'] ?? $billingCustomer['apartment'] ?? '';
        $address = !empty($location) ? $location : (!empty($apartment) ? $apartment : 'N/A');
        
        $servicePlan = $billingCustomer['PackageName'] ?? $billingCustomer['package_name'] ?? $billingCustomer['package'] ?? 'Standard';
        
        $status = 'active';
        if (!empty($billingCustomer['DisabledAt']) || !empty($billingCustomer['disabled_at'])) {
            $status = 'inactive';
        } elseif (!empty($billingCustomer['PausedAt']) || !empty($billingCustomer['paused_at'])) {
            $status = 'suspended';
        }
        
        return [
            'billing_id' => $billingCustomer['ID'] ?? $billingCustomer['id'] ?? null,
            'username' => $username,
            'name' => $fullName,
            'email' => $billingCustomer['Email'] ?? $billingCustomer['email'] ?? null,
            'phone' => $phone,
            'address' => $address,
            'service_plan' => !empty($servicePlan) ? $servicePlan : 'Standard',
            'connection_status' => $this->mapStatus($status),
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
        if (!$mapped) {
            return null;
        }
        
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
    
    public function saveCredentials(array $credentials): bool {
        try {
            if (!empty($credentials['api_token'])) {
                $this->saveSetting('oneisp_api_token', $credentials['api_token']);
            }
            if (!empty($credentials['username'])) {
                $this->saveSetting('oneisp_username', $credentials['username']);
            }
            if (!empty($credentials['password'])) {
                $this->saveSetting('oneisp_password', $credentials['password']);
            }
            
            $this->loadCredentials();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function saveSetting(string $key, string $value): void {
        $stmt = $this->db->prepare("
            INSERT INTO company_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value
        ");
        $stmt->execute([$key, $value]);
    }
}
