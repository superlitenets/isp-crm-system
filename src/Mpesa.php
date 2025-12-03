<?php

namespace App;

use PDO;

class Mpesa {
    private PDO $db;
    private string $consumerKey;
    private string $consumerSecret;
    private string $shortcode;
    private string $passkey;
    private string $callbackUrl;
    private string $validationUrl;
    private string $confirmationUrl;
    private bool $isSandbox;
    private string $baseUrl;
    
    public function __construct() {
        $this->db = \Database::getConnection();
        $this->loadConfig();
    }
    
    private function loadConfig(): void {
        $this->consumerKey = $_ENV['MPESA_CONSUMER_KEY'] ?? $this->getConfigValue('mpesa_consumer_key') ?? '';
        $this->consumerSecret = $_ENV['MPESA_CONSUMER_SECRET'] ?? $this->getConfigValue('mpesa_consumer_secret') ?? '';
        $this->shortcode = $_ENV['MPESA_SHORTCODE'] ?? $this->getConfigValue('mpesa_shortcode') ?? '174379';
        $this->passkey = $_ENV['MPESA_PASSKEY'] ?? $this->getConfigValue('mpesa_passkey') ?? 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
        $this->isSandbox = ($_ENV['MPESA_ENVIRONMENT'] ?? $this->getConfigValue('mpesa_environment') ?? 'sandbox') === 'sandbox';
        
        $this->baseUrl = $this->isSandbox 
            ? 'https://sandbox.safaricom.co.ke' 
            : 'https://api.safaricom.co.ke';
        
        $domain = $_ENV['REPL_SLUG'] ?? '';
        $owner = $_ENV['REPL_OWNER'] ?? '';
        $baseCallbackUrl = "https://{$domain}.{$owner}.repl.co";
        
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'https';
            $baseCallbackUrl = "{$protocol}://{$_SERVER['HTTP_HOST']}";
        }
        
        $this->callbackUrl = $this->getConfigValue('mpesa_callback_url') ?: "{$baseCallbackUrl}/?page=mpesa_callback&type=stkpush";
        $this->validationUrl = $this->getConfigValue('mpesa_validation_url') ?: "{$baseCallbackUrl}/?page=mpesa_callback&type=validation";
        $this->confirmationUrl = $this->getConfigValue('mpesa_confirmation_url') ?: "{$baseCallbackUrl}/?page=mpesa_callback&type=confirmation";
    }
    
    private function getConfigValue(string $key): ?string {
        try {
            $stmt = $this->db->prepare("SELECT config_value FROM mpesa_config WHERE config_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['config_value'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function saveConfig(string $key, string $value, bool $isEncrypted = false): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO mpesa_config (config_key, config_value, is_encrypted, updated_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (config_key) DO UPDATE SET
                    config_value = EXCLUDED.config_value,
                    is_encrypted = EXCLUDED.is_encrypted,
                    updated_at = CURRENT_TIMESTAMP
            ");
            return $stmt->execute([$key, $value, $isEncrypted]);
        } catch (\Exception $e) {
            error_log("Error saving M-Pesa config: " . $e->getMessage());
            return false;
        }
    }
    
    public function isConfigured(): bool {
        return !empty($this->consumerKey) && !empty($this->consumerSecret) && !empty($this->shortcode);
    }
    
    public function getAccessToken(): ?string {
        if (!$this->isConfigured()) {
            return null;
        }
        
        $url = "{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials";
        
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => ['Content-Type:application/json; charset=utf8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_USERPWD => "{$this->consumerKey}:{$this->consumerSecret}",
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            error_log("M-Pesa token error: " . $error);
            return null;
        }
        
        $response = json_decode($result, true);
        
        if ($httpCode === 200 && isset($response['access_token'])) {
            return $response['access_token'];
        }
        
        error_log("M-Pesa token error: " . json_encode($response));
        return null;
    }
    
    public function stkPush(string $phone, float $amount, string $accountRef, string $description = 'Payment', ?int $customerId = null): array {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return ['success' => false, 'message' => 'Failed to get access token. Check M-Pesa credentials.'];
        }
        
        $phone = $this->formatPhoneNumber($phone);
        if (!$phone) {
            return ['success' => false, 'message' => 'Invalid phone number format'];
        }
        
        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
        
        $data = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int)$amount,
            'PartyA' => $phone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => substr($accountRef, 0, 12),
            'TransactionDesc' => substr($description, 0, 13)
        ];
        
        $url = "{$this->baseUrl}/mpesa/stkpush/v1/processrequest";
        
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => [
                'Content-Type:application/json',
                'Authorization:Bearer ' . $accessToken
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60
        ]);
        
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            return ['success' => false, 'message' => 'Connection error: ' . $error];
        }
        
        $response = json_decode($result, true);
        
        if ($httpCode === 200 && isset($response['ResponseCode']) && $response['ResponseCode'] === '0') {
            $this->saveTransaction([
                'transaction_type' => 'stkpush',
                'merchant_request_id' => $response['MerchantRequestID'] ?? null,
                'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
                'phone_number' => $phone,
                'amount' => $amount,
                'account_reference' => $accountRef,
                'transaction_desc' => $description,
                'customer_id' => $customerId,
                'status' => 'pending'
            ]);
            
            return [
                'success' => true,
                'message' => $response['CustomerMessage'] ?? 'STK Push sent successfully',
                'data' => $response
            ];
        }
        
        return [
            'success' => false,
            'message' => $response['errorMessage'] ?? $response['ResponseDescription'] ?? 'STK Push failed',
            'data' => $response
        ];
    }
    
    public function stkQuery(string $checkoutRequestId): array {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return ['success' => false, 'message' => 'Failed to get access token'];
        }
        
        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
        
        $data = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId
        ];
        
        $url = "{$this->baseUrl}/mpesa/stkpushquery/v1/query";
        
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => [
                'Content-Type:application/json',
                'Authorization:Bearer ' . $accessToken
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $result = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($result, true) ?? ['success' => false, 'message' => 'Invalid response'];
    }
    
    public function registerC2BUrls(): array {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return ['success' => false, 'message' => 'Failed to get access token'];
        }
        
        $data = [
            'ShortCode' => $this->shortcode,
            'ResponseType' => 'Completed',
            'ConfirmationURL' => $this->confirmationUrl,
            'ValidationURL' => $this->validationUrl
        ];
        
        $url = "{$this->baseUrl}/mpesa/c2b/v1/registerurl";
        
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => [
                'Content-Type:application/json',
                'Authorization:Bearer ' . $accessToken
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $response = json_decode($result, true);
        
        if ($httpCode === 200 && isset($response['ResponseDescription'])) {
            $this->saveConfig('c2b_urls_registered', date('Y-m-d H:i:s'));
            return ['success' => true, 'message' => $response['ResponseDescription'], 'data' => $response];
        }
        
        return ['success' => false, 'message' => $response['errorMessage'] ?? 'C2B URL registration failed', 'data' => $response];
    }
    
    public function handleStkCallback(array $data): bool {
        try {
            $callback = $data['Body']['stkCallback'] ?? null;
            
            if (!$callback) {
                error_log("Invalid STK callback data");
                return false;
            }
            
            $merchantRequestId = $callback['MerchantRequestID'] ?? null;
            $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
            $resultCode = $callback['ResultCode'] ?? null;
            $resultDesc = $callback['ResultDesc'] ?? null;
            
            $amount = null;
            $receiptNumber = null;
            $transactionDate = null;
            $phoneNumber = null;
            
            if ($resultCode === 0 && isset($callback['CallbackMetadata']['Item'])) {
                foreach ($callback['CallbackMetadata']['Item'] as $item) {
                    switch ($item['Name']) {
                        case 'Amount':
                            $amount = $item['Value'];
                            break;
                        case 'MpesaReceiptNumber':
                            $receiptNumber = $item['Value'];
                            break;
                        case 'TransactionDate':
                            $transactionDate = date('Y-m-d H:i:s', strtotime($item['Value']));
                            break;
                        case 'PhoneNumber':
                            $phoneNumber = $item['Value'];
                            break;
                    }
                }
            }
            
            $status = $resultCode === 0 ? 'completed' : 'failed';
            
            $stmt = $this->db->prepare("
                UPDATE mpesa_transactions SET
                    result_code = ?,
                    result_desc = ?,
                    mpesa_receipt_number = ?,
                    transaction_date = ?,
                    amount = COALESCE(?, amount),
                    phone_number = COALESCE(?, phone_number),
                    status = ?,
                    raw_callback = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE checkout_request_id = ?
            ");
            
            return $stmt->execute([
                $resultCode,
                $resultDesc,
                $receiptNumber,
                $transactionDate,
                $amount,
                $phoneNumber,
                $status,
                json_encode($data),
                $checkoutRequestId
            ]);
        } catch (\Exception $e) {
            error_log("STK callback error: " . $e->getMessage());
            return false;
        }
    }
    
    public function handleC2BValidation(array $data): array {
        return [
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted'
        ];
    }
    
    public function handleC2BConfirmation(array $data): bool {
        try {
            $transTime = $data['TransTime'] ?? null;
            if ($transTime) {
                $transTime = date('Y-m-d H:i:s', strtotime($transTime));
            }
            
            $customerId = $this->findCustomerByPhone($data['MSISDN'] ?? '') 
                       ?? $this->findCustomerByAccountRef($data['BillRefNumber'] ?? '');
            
            $stmt = $this->db->prepare("
                INSERT INTO mpesa_c2b_transactions (
                    transaction_type, trans_id, trans_time, trans_amount,
                    business_short_code, bill_ref_number, invoice_number,
                    org_account_balance, third_party_trans_id, msisdn,
                    first_name, middle_name, last_name, customer_id, raw_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (trans_id) DO NOTHING
            ");
            
            return $stmt->execute([
                $data['TransactionType'] ?? null,
                $data['TransID'] ?? null,
                $transTime,
                $data['TransAmount'] ?? 0,
                $data['BusinessShortCode'] ?? null,
                $data['BillRefNumber'] ?? null,
                $data['InvoiceNumber'] ?? null,
                $data['OrgAccountBalance'] ?? null,
                $data['ThirdPartyTransID'] ?? null,
                $data['MSISDN'] ?? null,
                $data['FirstName'] ?? null,
                $data['MiddleName'] ?? null,
                $data['LastName'] ?? null,
                $customerId,
                json_encode($data)
            ]);
        } catch (\Exception $e) {
            error_log("C2B confirmation error: " . $e->getMessage());
            return false;
        }
    }
    
    private function saveTransaction(array $data): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO mpesa_transactions (
                    transaction_type, merchant_request_id, checkout_request_id,
                    phone_number, amount, account_reference, transaction_desc,
                    customer_id, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $data['transaction_type'],
                $data['merchant_request_id'],
                $data['checkout_request_id'],
                $data['phone_number'],
                $data['amount'],
                $data['account_reference'],
                $data['transaction_desc'],
                $data['customer_id'],
                $data['status']
            ]);
        } catch (\Exception $e) {
            error_log("Save transaction error: " . $e->getMessage());
            return false;
        }
    }
    
    private function findCustomerByPhone(string $phone): ?int {
        if (empty($phone)) return null;
        
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $phone = preg_replace('/^254/', '0', $phone);
        
        $stmt = $this->db->prepare("
            SELECT id FROM customers 
            WHERE REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') LIKE ?
            OR REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') LIKE ?
            LIMIT 1
        ");
        $stmt->execute(["%{$phone}", "%254" . substr($phone, 1)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['id'] : null;
    }
    
    private function findCustomerByAccountRef(string $accountRef): ?int {
        if (empty($accountRef)) return null;
        
        $stmt = $this->db->prepare("
            SELECT id FROM customers 
            WHERE account_number = ? OR account_number LIKE ?
            LIMIT 1
        ");
        $stmt->execute([$accountRef, "%{$accountRef}%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['id'] : null;
    }
    
    public function formatPhoneNumber(string $phone): ?string {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 9 && in_array($phone[0], ['7', '1'])) {
            return '254' . $phone;
        }
        
        if (strlen($phone) === 10 && $phone[0] === '0') {
            return '254' . substr($phone, 1);
        }
        
        if (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
            return $phone;
        }
        
        if (strlen($phone) === 13 && substr($phone, 0, 4) === '+254') {
            return substr($phone, 1);
        }
        
        return null;
    }
    
    public function getTransactions(array $filters = []): array {
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['type'])) {
            $where[] = "t.transaction_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "t.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "t.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(t.phone_number LIKE ? OR t.mpesa_receipt_number LIKE ? OR t.account_reference LIKE ? OR c.name LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }
        
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;
        
        $sql = "
            SELECT t.*, c.name as customer_name, c.account_number as customer_account
            FROM mpesa_transactions t
            LEFT JOIN customers c ON t.customer_id = c.id
            {$whereClause}
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getC2BTransactions(array $filters = []): array {
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "t.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "t.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(t.msisdn LIKE ? OR t.trans_id LIKE ? OR t.bill_ref_number LIKE ? OR c.name LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }
        
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;
        
        $sql = "
            SELECT t.*, c.name as customer_name, c.account_number as customer_account
            FROM mpesa_c2b_transactions t
            LEFT JOIN customers c ON t.customer_id = c.id
            {$whereClause}
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPaymentStats(string $period = 'today'): array {
        $dateCondition = match($period) {
            'today' => "DATE(created_at) = CURRENT_DATE",
            'week' => "created_at >= CURRENT_DATE - INTERVAL '7 days'",
            'month' => "created_at >= CURRENT_DATE - INTERVAL '30 days'",
            'year' => "created_at >= CURRENT_DATE - INTERVAL '365 days'",
            default => "1=1"
        };
        
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) FILTER (WHERE status = 'completed') as completed_count,
                COUNT(*) FILTER (WHERE status = 'pending') as pending_count,
                COUNT(*) FILTER (WHERE status = 'failed') as failed_count,
                COALESCE(SUM(amount) FILTER (WHERE status = 'completed'), 0) as total_amount,
                COUNT(*) as total_transactions
            FROM mpesa_transactions
            WHERE {$dateCondition}
        ");
        $stkStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as c2b_count,
                COALESCE(SUM(trans_amount), 0) as c2b_amount
            FROM mpesa_c2b_transactions
            WHERE {$dateCondition}
        ");
        $c2bStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'stk' => $stkStats,
            'c2b' => $c2bStats,
            'period' => $period
        ];
    }
    
    public function getConfig(): array {
        try {
            $stmt = $this->db->query("SELECT config_key, config_value FROM mpesa_config");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $config = [];
            foreach ($rows as $row) {
                $config[$row['config_key']] = $row['config_value'];
            }
            
            return $config;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function getCallbackUrl(): string {
        return $this->callbackUrl;
    }
    
    public function getValidationUrl(): string {
        return $this->validationUrl;
    }
    
    public function getConfirmationUrl(): string {
        return $this->confirmationUrl;
    }
}
