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
        $this->isSandbox = ($_ENV['MPESA_ENV'] ?? $_ENV['MPESA_ENVIRONMENT'] ?? $this->getConfigValue('mpesa_environment') ?? 'sandbox') === 'sandbox';
        
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
            $encryptedVal = $isEncrypted ? 't' : 'f';
            $stmt = $this->db->prepare("
                INSERT INTO mpesa_config (config_key, config_value, is_encrypted, updated_at)
                VALUES (:key, :value, :encrypted, CURRENT_TIMESTAMP)
                ON CONFLICT (config_key) DO UPDATE SET
                    config_value = EXCLUDED.config_value,
                    is_encrypted = EXCLUDED.is_encrypted,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->bindValue(':key', $key);
            $stmt->bindValue(':value', $value);
            $stmt->bindValue(':encrypted', $isEncrypted, \PDO::PARAM_BOOL);
            $result = $stmt->execute();
            error_log("M-Pesa config saved: $key = " . ($result ? 'success' : 'failed'));
            return $result;
        } catch (\Exception $e) {
            error_log("Error saving M-Pesa config '$key': " . $e->getMessage());
            return false;
        }
    }
    
    public function isConfigured(): bool {
        return !empty($this->consumerKey) && !empty($this->consumerSecret) && !empty($this->shortcode);
    }
    
    private ?string $lastError = null;
    
    public function getLastError(): ?string {
        return $this->lastError;
    }
    
    private function makeRequest(string $url, array $options, int $maxRetries = 3): array {
        $lastError = null;
        $lastHttpCode = 0;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $curl = curl_init($url);
            
            $defaultOptions = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_DNS_CACHE_TIMEOUT => 120,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_ENCODING => '',
            ];
            
            curl_setopt_array($curl, array_replace($defaultOptions, $options));
            
            $result = curl_exec($curl);
            $lastHttpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            $curlErrno = curl_errno($curl);
            curl_close($curl);
            
            if (!$curlError && $result !== false) {
                return [
                    'success' => true,
                    'result' => $result,
                    'httpCode' => $lastHttpCode
                ];
            }
            
            $errorMessages = [
                CURLE_COULDNT_RESOLVE_HOST => 'DNS resolution failed. Check internet connection.',
                CURLE_COULDNT_CONNECT => 'Could not connect to M-Pesa servers. Check firewall settings.',
                CURLE_OPERATION_TIMEDOUT => 'Connection timed out. M-Pesa servers may be slow.',
                CURLE_SSL_CONNECT_ERROR => 'SSL/TLS connection error. Check SSL certificates.',
                CURLE_GOT_NOTHING => 'Empty response from server. Try again.',
                CURLE_SEND_ERROR => 'Failed to send data. Network issue.',
                CURLE_RECV_ERROR => 'Failed to receive data. Network issue.',
            ];
            
            $lastError = $errorMessages[$curlErrno] ?? "Network error: {$curlError} (code: {$curlErrno})";
            error_log("M-Pesa request attempt {$attempt}/{$maxRetries} failed: {$lastError}");
            
            if ($attempt < $maxRetries) {
                usleep(500000 * $attempt);
            }
        }
        
        return [
            'success' => false,
            'error' => $lastError,
            'httpCode' => $lastHttpCode
        ];
    }
    
    public function getAccessToken(): ?string {
        if (!$this->isConfigured()) {
            $this->lastError = 'M-Pesa is not configured. Please set up credentials in Settings.';
            return null;
        }
        
        $url = "{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials";
        
        $response = $this->makeRequest($url, [
            CURLOPT_HTTPHEADER => ['Content-Type:application/json; charset=utf8'],
            CURLOPT_HEADER => false,
            CURLOPT_USERPWD => "{$this->consumerKey}:{$this->consumerSecret}",
            CURLOPT_TIMEOUT => 30
        ], 3);
        
        if (!$response['success']) {
            $this->lastError = $response['error'];
            error_log("M-Pesa token error: " . $response['error']);
            return null;
        }
        
        $data = json_decode($response['result'], true);
        
        if ($response['httpCode'] === 200 && isset($data['access_token'])) {
            $this->lastError = null;
            return $data['access_token'];
        }
        
        $this->lastError = $data['error_description'] ?? $data['errorMessage'] ?? 'Invalid credentials or server error';
        error_log("M-Pesa token error: " . json_encode($data));
        return null;
    }
    
    public function stkPush(string $phone, float $amount, string $accountRef, string $description = 'Payment', ?int $customerId = null): array {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            $errorMsg = $this->lastError ?? 'Failed to get access token. Check M-Pesa credentials.';
            return ['success' => false, 'message' => $errorMsg];
        }
        
        $phone = $this->formatPhoneNumber($phone);
        if (!$phone) {
            return ['success' => false, 'message' => 'Invalid phone number format. Use 254XXXXXXXXX or 07XXXXXXXX'];
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
        
        $apiResponse = $this->makeRequest($url, [
            CURLOPT_HTTPHEADER => [
                'Content-Type:application/json',
                'Authorization:Bearer ' . $accessToken
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 60
        ], 2);
        
        if (!$apiResponse['success']) {
            $this->lastError = $apiResponse['error'];
            return ['success' => false, 'message' => $apiResponse['error']];
        }
        
        $response = json_decode($apiResponse['result'], true);
        $httpCode = $apiResponse['httpCode'];
        
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
    
    public function stkPushForInvoice(int $invoiceId, string $phone, ?float $amount = null): array {
        $accounting = new Accounting($this->db);
        $invoice = $accounting->getInvoice($invoiceId);
        
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found'];
        }
        
        if ($invoice['status'] === 'paid') {
            return ['success' => false, 'message' => 'Invoice is already paid'];
        }
        
        $payAmount = $amount ?? $invoice['balance_due'];
        if ($payAmount <= 0) {
            return ['success' => false, 'message' => 'Invalid payment amount'];
        }
        
        $accountRef = $invoice['invoice_number'];
        $description = 'Invoice Payment';
        
        $result = $this->stkPush($phone, $payAmount, $accountRef, $description, $invoice['customer_id']);
        
        if ($result['success'] && isset($result['data']['CheckoutRequestID'])) {
            $stmt = $this->db->prepare("
                UPDATE mpesa_transactions SET invoice_id = ? WHERE checkout_request_id = ?
            ");
            $stmt->execute([$invoiceId, $result['data']['CheckoutRequestID']]);
        }
        
        return $result;
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
            
            $updated = $stmt->execute([
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
            
            if ($updated && $resultCode === 0 && $amount > 0) {
                $this->applyPaymentToInvoiceFromCallback($checkoutRequestId, $amount, $receiptNumber);
                $this->processRadiusPayment($checkoutRequestId, $amount, $receiptNumber, $phoneNumber);
            }
            
            return $updated;
        } catch (\Exception $e) {
            error_log("STK callback error: " . $e->getMessage());
            return false;
        }
    }
    
    private function processRadiusPayment(string $checkoutRequestId, float $amount, ?string $receiptNumber, ?string $phoneNumber): void {
        try {
            $stmt = $this->db->prepare("SELECT account_reference FROM mpesa_transactions WHERE checkout_request_id = ?");
            $stmt->execute([$checkoutRequestId]);
            $trans = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$trans) return;
            
            $accountRef = $trans['account_reference'];
            $subscription = null;
            
            // Check if account_reference is in format "radius_X" or "HS-X" where X is subscription ID
            $subscriptionId = null;
            if (preg_match('/^radius_(\d+)$/i', $accountRef, $matches)) {
                $subscriptionId = (int)$matches[1];
            } elseif (preg_match('/^HS-(\d+)$/i', $accountRef, $matches)) {
                $subscriptionId = (int)$matches[1];
            }
            
            if ($subscriptionId) {
                $stmt = $this->db->prepare("
                    SELECT s.*, c.name as customer_name, c.phone, p.name as package_name, p.price, p.validity_days
                    FROM radius_subscriptions s
                    LEFT JOIN customers c ON s.customer_id = c.id
                    LEFT JOIN radius_packages p ON s.package_id = p.id
                    WHERE s.id = ?
                ");
                $stmt->execute([$subscriptionId]);
                $subscription = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($subscription) {
                    error_log("RADIUS: Found subscription by ID={$subscriptionId} (username: {$subscription['username']})");
                    
                    // For pending_payment hotspot subscriptions, activate them
                    if ($subscription['status'] === 'pending_payment') {
                        $sessionHours = null;
                        $phStmt = $this->db->prepare("SELECT session_duration_hours FROM radius_packages WHERE id = ?");
                        $phStmt->execute([$subscription['package_id'] ?? 0]);
                        $pkgRow = $phStmt->fetch(\PDO::FETCH_ASSOC);
                        if ($pkgRow && !empty($pkgRow['session_duration_hours'])) {
                            $sessionHours = (float)$pkgRow['session_duration_hours'];
                        }

                        if ($sessionHours && $sessionHours > 0) {
                            $durationSeconds = (int)($sessionHours * 3600);
                            $expiryDate = date('Y-m-d H:i:s', time() + $durationSeconds);
                        } else {
                            $validityDays = $subscription['validity_days'] ?? 30;
                            $expiryDate = date('Y-m-d H:i:s', strtotime("+{$validityDays} days"));
                        }
                        $activateStmt = $this->db->prepare("
                            UPDATE radius_subscriptions 
                            SET status = 'active', expiry_date = ?, updated_at = CURRENT_TIMESTAMP 
                            WHERE id = ?
                        ");
                        $activateStmt->execute([$expiryDate, $subscriptionId]);
                        error_log("RADIUS: Activated hotspot subscription ID={$subscriptionId}, expires: {$expiryDate}");
                        
                        // Add the first device to radius_subscription_devices table for multi-device support
                        if (!empty($subscription['mac_address'])) {
                            $deviceCheckStmt = $this->db->prepare("
                                SELECT id FROM radius_subscription_devices 
                                WHERE subscription_id = ? AND mac_address = ?
                            ");
                            $deviceCheckStmt->execute([$subscriptionId, $subscription['mac_address']]);
                            if (!$deviceCheckStmt->fetch()) {
                                $deviceInsertStmt = $this->db->prepare("
                                    INSERT INTO radius_subscription_devices 
                                    (subscription_id, mac_address, device_name, is_active, created_at)
                                    VALUES (?, ?, 'Primary Device', true, CURRENT_TIMESTAMP)
                                ");
                                $deviceInsertStmt->execute([$subscriptionId, $subscription['mac_address']]);
                                error_log("RADIUS: Added primary device {$subscription['mac_address']} to subscription ID={$subscriptionId}");
                            }
                        }
                    }
                }
            }
            
            // Fallback: Find subscription by username or phone if not found by ID
            if (!$subscription) {
                $stmt = $this->db->prepare("
                    SELECT s.*, c.name as customer_name, c.phone, p.name as package_name, p.price
                    FROM radius_subscriptions s
                    LEFT JOIN customers c ON s.customer_id = c.id
                    LEFT JOIN radius_packages p ON s.package_id = p.id
                    WHERE s.username = ? OR c.phone LIKE ?
                    ORDER BY s.id DESC LIMIT 1
                ");
                $phoneSearch = '%' . substr(preg_replace('/[^0-9]/', '', $phoneNumber ?? ''), -9);
                $stmt->execute([$accountRef, $phoneSearch]);
                $subscription = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            
            if (!$subscription) {
                error_log("RADIUS: No subscription found for account_ref={$accountRef} or phone={$phoneNumber}");
                return;
            }
            
            require_once __DIR__ . '/RadiusBilling.php';
            $radiusBilling = new RadiusBilling($this->db);
            
            // Use the proper processPayment method which handles wallet credits
            $result = $radiusBilling->processPayment($receiptNumber ?? $checkoutRequestId, $phoneNumber ?? '', $amount, $accountRef, $subscription['id']);
            
            if ($result['success'] && empty($result['duplicate'])) {
                if (!empty($result['wallet_topup'])) {
                    // Partial payment - credited to wallet
                    error_log("RADIUS: KES {$amount} credited to wallet for {$subscription['username']}. Balance: KES {$result['new_balance']}. Ref: {$receiptNumber}");
                    
                    // Send SMS about wallet top-up
                    if (!empty($subscription['phone'])) {
                        try {
                            require_once __DIR__ . '/SMSGateway.php';
                            $sms = new SMSGateway();
                            $message = "Payment of KES " . number_format($amount) . " received and credited to your wallet. " .
                                       "Balance: KES " . number_format($result['new_balance'], 2) . ". " .
                                       "Need KES " . number_format($result['needed_for_renewal'], 2) . " more to renew. " .
                                       "Ref: {$receiptNumber}";
                            $sms->send($subscription['phone'], $message);
                        } catch (\Exception $e) {
                            error_log("SMS wallet notification error: " . $e->getMessage());
                        }
                    }
                } else {
                    // Full renewal
                    error_log("RADIUS subscription renewed for {$subscription['username']} via M-Pesa {$receiptNumber}");
                    
                    // Disconnect expired users so they can reconnect with new session
                    $wasExpired = $subscription['status'] === 'expired' || $subscription['status'] === 'inactive' ||
                                  ($subscription['expiry_date'] && strtotime($subscription['expiry_date']) < time());
                    
                    if ($wasExpired && empty($result['disconnected'])) {
                        $disconnectResult = $radiusBilling->disconnectSubscription($subscription['id']);
                        if ($disconnectResult['success']) {
                            error_log("Disconnected expired {$subscription['username']} after STK payment for session refresh");
                        }
                    }
                    
                    // Send SMS confirmation
                    if (!empty($subscription['phone'])) {
                        try {
                            require_once __DIR__ . '/SMSGateway.php';
                            $sms = new SMSGateway();
                            $expiryDate = $result['expiry_date'] ?? date('Y-m-d', strtotime('+30 days'));
                            $walletMsg = !empty($result['wallet_remaining']) ? " Wallet balance: KES " . number_format($result['wallet_remaining'], 2) . "." : "";
                            $hasTime = strpos($expiryDate, ':') !== false;
                            $formattedExpiry = $hasTime ? date('M j, Y g:i A', strtotime($expiryDate)) : date('M j, Y', strtotime($expiryDate));
                            $message = "Payment received! Your {$subscription['package_name']} subscription has been renewed until " . 
                                       $formattedExpiry . ".{$walletMsg} " .
                                       "Ref: {$receiptNumber}. Thank you!";
                            $sms->send($subscription['phone'], $message);
                        } catch (\Exception $e) {
                            error_log("SMS confirmation error: " . $e->getMessage());
                        }
                    }
                }
            } elseif ($result['success'] && !empty($result['duplicate'])) {
                error_log("RADIUS: Skipping duplicate SMS for {$subscription['username']} - payment already processed. Ref: {$receiptNumber}");
            } else {
                error_log("RADIUS payment processing failed: " . ($result['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            error_log("RADIUS payment processing error: " . $e->getMessage());
        }
    }
    
    private function applyPaymentToInvoiceFromCallback(string $checkoutRequestId, float $amount, ?string $receiptNumber): void {
        try {
            $stmt = $this->db->prepare("SELECT invoice_id, customer_id FROM mpesa_transactions WHERE checkout_request_id = ?");
            $stmt->execute([$checkoutRequestId]);
            $trans = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$trans || !$trans['invoice_id']) {
                return;
            }
            
            $accounting = new Accounting($this->db);
            $accounting->recordCustomerPayment([
                'customer_id' => $trans['customer_id'],
                'invoice_id' => $trans['invoice_id'],
                'payment_date' => date('Y-m-d'),
                'amount' => $amount,
                'payment_method' => 'mpesa',
                'mpesa_receipt' => $receiptNumber,
                'notes' => 'Auto-recorded from M-Pesa STK Push',
                'status' => 'completed'
            ]);
            
            error_log("Payment of KES {$amount} applied to invoice ID {$trans['invoice_id']} via M-Pesa");
        } catch (\Exception $e) {
            error_log("Error applying M-Pesa payment to invoice: " . $e->getMessage());
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
            
            $result = $stmt->execute([
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
            
            if ($result) {
                $this->processRadiusC2BPayment(
                    $data['BillRefNumber'] ?? '',
                    (float)($data['TransAmount'] ?? 0),
                    $data['TransID'] ?? '',
                    $data['MSISDN'] ?? ''
                );
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log("C2B confirmation error: " . $e->getMessage());
            return false;
        }
    }
    
    private function processRadiusC2BPayment(string $accountRef, float $amount, string $transId, string $phone): void {
        try {
            require_once __DIR__ . '/RadiusBilling.php';
            $radiusBilling = new RadiusBilling($this->db);
            
            $subscription = null;
            $subscriptionId = null;
            
            // Priority 1: Check if accountRef is in "radius_X" or "HS-X" format (subscription ID)
            if (preg_match('/^(?:radius_|HS-)(\d+)$/i', $accountRef, $matches)) {
                $subscriptionId = (int)$matches[1];
                $stmt = $this->db->prepare("
                    SELECT s.*, c.name as customer_name, c.phone, p.name as package_name, p.price
                    FROM radius_subscriptions s
                    LEFT JOIN customers c ON s.customer_id = c.id
                    LEFT JOIN radius_packages p ON s.package_id = p.id
                    WHERE s.id = ?
                ");
                $stmt->execute([$subscriptionId]);
                $subscription = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($subscription) {
                    error_log("C2B: Found subscription by ID={$subscriptionId} (username: {$subscription['username']})");
                }
            }
            
            // Priority 2: Check if accountRef is a username (like SFL001)
            if (!$subscription && preg_match('/^[A-Za-z]/', $accountRef)) {
                $stmt = $this->db->prepare("
                    SELECT s.*, c.name as customer_name, c.phone, p.name as package_name, p.price
                    FROM radius_subscriptions s
                    LEFT JOIN customers c ON s.customer_id = c.id
                    LEFT JOIN radius_packages p ON s.package_id = p.id
                    WHERE s.username = ?
                ");
                $stmt->execute([$accountRef]);
                $subscription = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($subscription) {
                    error_log("C2B: Found subscription by username={$accountRef}");
                }
            }
            
            // Priority 3: Match by phone number (accountRef as phone or sender's phone)
            if (!$subscription) {
                $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);
                $normalizedAccountRef = preg_replace('/[^0-9]/', '', $accountRef);
                $phoneSearch = '%' . substr($normalizedPhone, -9);
                $accountRefSearch = '%' . substr($normalizedAccountRef, -9);
                
                $stmt = $this->db->prepare("
                    SELECT s.*, c.name as customer_name, c.phone, p.name as package_name, p.price
                    FROM radius_subscriptions s
                    LEFT JOIN customers c ON s.customer_id = c.id
                    LEFT JOIN radius_packages p ON s.package_id = p.id
                    WHERE REPLACE(REPLACE(c.phone, '+', ''), ' ', '') LIKE ?
                       OR REPLACE(REPLACE(c.phone, '+', ''), ' ', '') LIKE ?
                    ORDER BY s.id DESC LIMIT 1
                ");
                $stmt->execute([$accountRefSearch, $phoneSearch]);
                $subscription = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($subscription) {
                    error_log("C2B: Found subscription by phone (accountRef or sender). Username: {$subscription['username']}");
                }
            }
            
            if (!$subscription) {
                error_log("C2B: No subscription found for accountRef={$accountRef}, phone={$phone}");
                return;
            }
            
            // Use the unified processPayment method which handles wallet credits properly
            $result = $radiusBilling->processPayment($transId, $phone, $amount, $accountRef, $subscription['id']);
            
            if ($result['success']) {
                if (!empty($result['renewed'])) {
                    error_log("C2B: Subscription {$subscription['username']} renewed. Ref: {$transId}");
                } elseif (!empty($result['wallet_topup'])) {
                    error_log("C2B: KES {$amount} credited to wallet for {$subscription['username']}. Balance: KES {$result['new_balance']}. Ref: {$transId}");
                }
            } else {
                error_log("C2B: Payment processing failed for {$subscription['username']}: " . ($result['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            error_log("RADIUS C2B payment processing error: " . $e->getMessage());
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
    
    // ==================== B2C (Business to Customer) ====================
    
    private function getSecurityCredential(): string {
        $initiatorPassword = $this->getConfigValue('mpesa_b2c_initiator_password') ?? '';
        
        $certPath = $this->isSandbox 
            ? __DIR__ . '/../config/mpesa_sandbox_cert.cer'
            : __DIR__ . '/../config/mpesa_production_cert.cer';
        
        if (!file_exists($certPath)) {
            $certContent = $this->isSandbox
                ? file_get_contents('https://developer.safaricom.co.ke/api-documentation/api-portal-docs/media/cert/SandboxCertificate.cer')
                : '';
            if ($certContent) {
                @file_put_contents($certPath, $certContent);
            }
        }
        
        if (file_exists($certPath)) {
            $cert = file_get_contents($certPath);
            $publicKey = openssl_pkey_get_public($cert);
            if ($publicKey) {
                openssl_public_encrypt($initiatorPassword, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);
                return base64_encode($encrypted);
            }
        }
        
        return $this->getConfigValue('mpesa_b2c_security_credential') ?? '';
    }
    
    public function b2cPayment(
        string $phone, 
        float $amount, 
        string $commandId = 'BusinessPayment',
        string $remarks = 'Payment',
        string $occasion = '',
        string $purpose = 'manual',
        ?int $linkedId = null,
        ?string $linkedType = null,
        ?int $userId = null
    ): array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'message' => 'Failed to get access token'];
        }
        
        $phone = $this->formatPhoneNumber($phone);
        if (!$phone) {
            return ['success' => false, 'message' => 'Invalid phone number'];
        }
        
        $b2cShortcode = $this->getConfigValue('mpesa_b2c_shortcode') ?: $this->shortcode;
        $initiatorName = $this->getConfigValue('mpesa_b2c_initiator_name') ?? 'testapi';
        $securityCredential = $this->getSecurityCredential();
        
        if (empty($securityCredential)) {
            return ['success' => false, 'message' => 'B2C security credential not configured'];
        }
        
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        $b2cCallbackUrl = $this->getConfigValue('mpesa_b2c_callback_url') ?: "https://{$domain}/api/mpesa-b2c-callback.php";
        $b2cTimeoutUrl = $this->getConfigValue('mpesa_b2c_timeout_url') ?: "https://{$domain}/api/mpesa-b2c-timeout.php";
        
        $requestId = 'B2C' . time() . rand(1000, 9999);
        
        $data = [
            'InitiatorName' => $initiatorName,
            'SecurityCredential' => $securityCredential,
            'CommandID' => $commandId,
            'Amount' => (int)$amount,
            'PartyA' => $b2cShortcode,
            'PartyB' => $phone,
            'Remarks' => substr($remarks, 0, 100),
            'QueueTimeOutURL' => $b2cTimeoutUrl,
            'ResultURL' => $b2cCallbackUrl,
            'Occasion' => substr($occasion, 0, 100)
        ];
        
        $stmt = $this->db->prepare("
            INSERT INTO mpesa_b2c_transactions 
            (request_id, shortcode, initiator_name, phone, amount, command_id, purpose, remarks, occasion, linked_type, linked_id, initiated_by, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$requestId, $b2cShortcode, $initiatorName, $phone, $amount, $commandId, $purpose, $remarks, $occasion, $linkedType, $linkedId, $userId]);
        $transactionId = $this->db->lastInsertId();
        
        $url = "{$this->baseUrl}/mpesa/b2c/v1/paymentrequest";
        
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
        curl_close($curl);
        
        $response = json_decode($result, true);
        
        if ($httpCode === 200 && isset($response['ConversationID'])) {
            $stmt = $this->db->prepare("
                UPDATE mpesa_b2c_transactions 
                SET conversation_id = ?, originator_conversation_id = ?, status = 'queued', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$response['ConversationID'], $response['OriginatorConversationID'] ?? '', $transactionId]);
            
            return [
                'success' => true,
                'message' => 'B2C request submitted',
                'transaction_id' => $transactionId,
                'conversation_id' => $response['ConversationID']
            ];
        }
        
        $errorMsg = $response['errorMessage'] ?? $response['ResultDesc'] ?? 'B2C request failed';
        $stmt = $this->db->prepare("UPDATE mpesa_b2c_transactions SET status = 'failed', result_desc = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$errorMsg, $transactionId]);
        
        return ['success' => false, 'message' => $errorMsg, 'response' => $response];
    }
    
    public function handleB2CCallback(array $data): bool {
        try {
            $result = $data['Result'] ?? [];
            $conversationId = $result['ConversationID'] ?? '';
            $originatorConversationId = $result['OriginatorConversationID'] ?? '';
            $resultCode = $result['ResultCode'] ?? '';
            $resultDesc = $result['ResultDesc'] ?? '';
            
            $stmt = $this->db->prepare("SELECT id FROM mpesa_b2c_transactions WHERE conversation_id = ? OR originator_conversation_id = ?");
            $stmt->execute([$conversationId, $originatorConversationId]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                error_log("B2C callback: Transaction not found for conversation_id: $conversationId");
                return false;
            }
            
            $status = $resultCode == '0' ? 'success' : 'failed';
            $transactionReceipt = '';
            $receiverName = '';
            $utilityBalance = null;
            $workingBalance = null;
            
            if (isset($result['ResultParameters']['ResultParameter'])) {
                foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                    $key = $param['Key'] ?? '';
                    $value = $param['Value'] ?? '';
                    switch ($key) {
                        case 'TransactionReceipt': $transactionReceipt = $value; break;
                        case 'ReceiverPartyPublicName': $receiverName = $value; break;
                        case 'B2CUtilityAccountAvailableFunds': $utilityBalance = $value; break;
                        case 'B2CWorkingAccountAvailableFunds': $workingBalance = $value; break;
                    }
                }
            }
            
            $stmt = $this->db->prepare("
                UPDATE mpesa_b2c_transactions SET
                    status = ?,
                    result_code = ?,
                    result_desc = ?,
                    transaction_receipt = ?,
                    receiver_party_public_name = ?,
                    b2c_utility_account_balance = ?,
                    b2c_working_account_balance = ?,
                    callback_payload = ?,
                    updated_at = CURRENT_TIMESTAMP,
                    completed_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $status, $resultCode, $resultDesc, $transactionReceipt, $receiverName,
                $utilityBalance, $workingBalance, json_encode($data), $transaction['id']
            ]);
            
            if ($status === 'success') {
                $stmt = $this->db->prepare("SELECT linked_type, linked_id FROM mpesa_b2c_transactions WHERE id = ?");
                $stmt->execute([$transaction['id']]);
                $tx = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($tx['linked_type'] === 'salary_advance' && $tx['linked_id']) {
                    $stmt = $this->db->prepare("UPDATE salary_advances SET disbursement_status = 'disbursed', disbursed_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$tx['linked_id']]);
                }
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("B2C callback error: " . $e->getMessage());
            return false;
        }
    }
    
    // ==================== B2B (Business to Business) ====================
    
    public function b2bPayment(
        string $receiverShortcode,
        float $amount,
        string $accountRef = '',
        string $commandId = 'BusinessPayBill',
        string $remarks = 'Payment',
        string $receiverType = '4',
        ?string $linkedType = null,
        ?int $linkedId = null,
        ?int $userId = null
    ): array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'message' => 'Failed to get access token'];
        }
        
        $senderShortcode = $this->getConfigValue('mpesa_b2b_shortcode') ?: $this->shortcode;
        $initiatorName = $this->getConfigValue('mpesa_b2b_initiator_name') ?: $this->getConfigValue('mpesa_b2c_initiator_name') ?? 'testapi';
        $securityCredential = $this->getSecurityCredential();
        
        if (empty($securityCredential)) {
            return ['success' => false, 'message' => 'B2B security credential not configured'];
        }
        
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        $b2bCallbackUrl = $this->getConfigValue('mpesa_b2b_callback_url') ?: "https://{$domain}/api/mpesa-b2b-callback.php";
        $b2bTimeoutUrl = $this->getConfigValue('mpesa_b2b_timeout_url') ?: "https://{$domain}/api/mpesa-b2b-timeout.php";
        
        $requestId = 'B2B' . time() . rand(1000, 9999);
        
        $data = [
            'Initiator' => $initiatorName,
            'SecurityCredential' => $securityCredential,
            'CommandID' => $commandId,
            'SenderIdentifierType' => '4',
            'RecieverIdentifierType' => $receiverType,
            'Amount' => (int)$amount,
            'PartyA' => $senderShortcode,
            'PartyB' => $receiverShortcode,
            'AccountReference' => substr($accountRef, 0, 20),
            'Remarks' => substr($remarks, 0, 100),
            'QueueTimeOutURL' => $b2bTimeoutUrl,
            'ResultURL' => $b2bCallbackUrl
        ];
        
        $stmt = $this->db->prepare("
            INSERT INTO mpesa_b2b_transactions 
            (request_id, sender_shortcode, receiver_shortcode, receiver_type, amount, command_id, account_reference, remarks, linked_type, linked_id, initiated_by, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$requestId, $senderShortcode, $receiverShortcode, $receiverType, $amount, $commandId, $accountRef, $remarks, $linkedType, $linkedId, $userId]);
        $transactionId = $this->db->lastInsertId();
        
        $url = "{$this->baseUrl}/mpesa/b2b/v1/paymentrequest";
        
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
        curl_close($curl);
        
        $response = json_decode($result, true);
        
        if ($httpCode === 200 && isset($response['ConversationID'])) {
            $stmt = $this->db->prepare("
                UPDATE mpesa_b2b_transactions 
                SET conversation_id = ?, originator_conversation_id = ?, status = 'queued', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$response['ConversationID'], $response['OriginatorConversationID'] ?? '', $transactionId]);
            
            return [
                'success' => true,
                'message' => 'B2B request submitted',
                'transaction_id' => $transactionId,
                'conversation_id' => $response['ConversationID']
            ];
        }
        
        $errorMsg = $response['errorMessage'] ?? $response['ResultDesc'] ?? 'B2B request failed';
        $stmt = $this->db->prepare("UPDATE mpesa_b2b_transactions SET status = 'failed', result_desc = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$errorMsg, $transactionId]);
        
        return ['success' => false, 'message' => $errorMsg, 'response' => $response];
    }
    
    public function handleB2BCallback(array $data): bool {
        try {
            $result = $data['Result'] ?? [];
            $conversationId = $result['ConversationID'] ?? '';
            $resultCode = $result['ResultCode'] ?? '';
            $resultDesc = $result['ResultDesc'] ?? '';
            
            $stmt = $this->db->prepare("SELECT id FROM mpesa_b2b_transactions WHERE conversation_id = ?");
            $stmt->execute([$conversationId]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                return false;
            }
            
            $status = $resultCode == '0' ? 'success' : 'failed';
            $transactionId = '';
            $debitName = '';
            $creditName = '';
            
            if (isset($result['ResultParameters']['ResultParameter'])) {
                foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                    $key = $param['Key'] ?? '';
                    $value = $param['Value'] ?? '';
                    switch ($key) {
                        case 'TransactionID': $transactionId = $value; break;
                        case 'DebitPartyName': $debitName = $value; break;
                        case 'CreditPartyName': $creditName = $value; break;
                    }
                }
            }
            
            $stmt = $this->db->prepare("
                UPDATE mpesa_b2b_transactions SET
                    status = ?, result_code = ?, result_desc = ?, transaction_id = ?,
                    debit_party_name = ?, credit_party_name = ?, callback_payload = ?,
                    updated_at = CURRENT_TIMESTAMP, completed_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$status, $resultCode, $resultDesc, $transactionId, $debitName, $creditName, json_encode($data), $transaction['id']]);
            
            return true;
        } catch (\Exception $e) {
            error_log("B2B callback error: " . $e->getMessage());
            return false;
        }
    }
    
    // ==================== Dashboard Statistics ====================
    
    public function getDashboardStats(): array {
        $defaultStats = ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0, 'total_amount' => 0];
        $stats = ['stk' => $defaultStats, 'c2b' => $defaultStats, 'b2c' => $defaultStats, 'b2b' => $defaultStats];
        
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status = 'success') as success,
                    COUNT(*) FILTER (WHERE status = 'failed') as failed,
                    COUNT(*) FILTER (WHERE status IN ('pending', 'queued', 'processing')) as pending,
                    COALESCE(SUM(amount) FILTER (WHERE status = 'success'), 0) as total_amount
                FROM mpesa_transactions WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
            ");
            $stats['stk'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: $defaultStats;
        } catch (\Exception $e) {}
        
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status = 'completed' OR status = 'success') as success,
                    COALESCE(SUM(trans_amount) FILTER (WHERE status = 'completed' OR status = 'success'), 0) as total_amount
                FROM mpesa_c2b_transactions WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
            ");
            $stats['c2b'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: $defaultStats;
        } catch (\Exception $e) {}
        
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status = 'success') as success,
                    COUNT(*) FILTER (WHERE status = 'failed') as failed,
                    COUNT(*) FILTER (WHERE status IN ('pending', 'queued', 'processing')) as pending,
                    COALESCE(SUM(amount) FILTER (WHERE status = 'success'), 0) as total_amount
                FROM mpesa_b2c_transactions WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
            ");
            $stats['b2c'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: $defaultStats;
        } catch (\Exception $e) {}
        
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status = 'success') as success,
                    COUNT(*) FILTER (WHERE status = 'failed') as failed,
                    COALESCE(SUM(amount) FILTER (WHERE status = 'success'), 0) as total_amount
                FROM mpesa_b2b_transactions WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
            ");
            $stats['b2b'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: $defaultStats;
        } catch (\Exception $e) {}
        
        return $stats;
    }
    
    public function getB2CTransactions(array $filters = [], int $limit = 50, int $offset = 0): array {
        try {
            $sql = "SELECT t.*, u.name as initiated_by_name FROM mpesa_b2c_transactions t LEFT JOIN users u ON t.initiated_by = u.id WHERE 1=1";
            $params = [];
            
            if (!empty($filters['status'])) {
                $sql .= " AND t.status = ?";
                $params[] = $filters['status'];
            }
            if (!empty($filters['purpose'])) {
                $sql .= " AND t.purpose = ?";
                $params[] = $filters['purpose'];
            }
            if (!empty($filters['phone'])) {
                $sql .= " AND t.phone LIKE ?";
                $params[] = '%' . $filters['phone'] . '%';
            }
            if (!empty($filters['date_from'])) {
                $sql .= " AND t.created_at >= ?";
                $params[] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $sql .= " AND t.created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            $sql .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function getB2BTransactions(array $filters = [], int $limit = 50, int $offset = 0): array {
        try {
            $sql = "SELECT t.*, u.name as initiated_by_name FROM mpesa_b2b_transactions t LEFT JOIN users u ON t.initiated_by = u.id WHERE 1=1";
            $params = [];
            
            if (!empty($filters['status'])) {
                $sql .= " AND t.status = ?";
                $params[] = $filters['status'];
            }
            if (!empty($filters['date_from'])) {
                $sql .= " AND t.created_at >= ?";
                $params[] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $sql .= " AND t.created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            $sql .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function isB2CConfigured(): bool {
        $initiatorName = $this->getConfigValue('mpesa_b2c_initiator_name');
        $initiatorPassword = $this->getConfigValue('mpesa_b2c_initiator_password');
        return !empty($initiatorName) && !empty($initiatorPassword) && $this->isConfigured();
    }
    
    public function isB2BConfigured(): bool {
        return $this->isB2CConfigured();
    }
}
