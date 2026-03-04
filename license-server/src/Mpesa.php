<?php
namespace LicenseServer;

class Mpesa {
    private $config;
    private $db;
    private $accessToken;
    
    public function __construct(array $config, \PDO $db) {
        $this->config = $config;
        $this->db = $db;
    }
    
    public function getAccessToken(): ?string {
        if ($this->accessToken) {
            return $this->accessToken;
        }
        
        $url = $this->getBaseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->config['consumer_key'] . ':' . $this->config['consumer_secret'])
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("M-Pesa API error: $error");
        }
        
        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new \Exception("Failed to get M-Pesa access token");
        }
        
        $this->accessToken = $data['access_token'];
        return $this->accessToken;
    }
    
    public function stkPush(string $phone, float $amount, string $reference, string $description = 'License Payment'): array {
        $token = $this->getAccessToken();
        $timestamp = date('YmdHis');
        $password = base64_encode($this->config['shortcode'] . $this->config['passkey'] . $timestamp);
        
        $phone = $this->formatPhone($phone);
        $accountType = $this->config['account_type'] ?? 'paybill';
        $transactionType = ($accountType === 'till') ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline';
        
        $payload = [
            'BusinessShortCode' => $this->config['shortcode'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $transactionType,
            'Amount' => (int)ceil($amount),
            'PartyA' => $phone,
            'PartyB' => $this->config['shortcode'],
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->config['callback_url'],
            'AccountReference' => substr($reference, 0, 12),
            'TransactionDesc' => substr($description, 0, 13)
        ];
        
        $url = $this->getBaseUrl() . '/mpesa/stkpush/v1/processrequest';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("M-Pesa API error: $error");
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['errorCode'])) {
            throw new \Exception($data['errorMessage'] ?? 'M-Pesa request failed');
        }
        
        return $data;
    }
    
    public function processCallback(array $data): array {
        $resultCode = $data['Body']['stkCallback']['ResultCode'] ?? -1;
        $resultDesc = $data['Body']['stkCallback']['ResultDesc'] ?? 'Unknown error';
        $checkoutRequestId = $data['Body']['stkCallback']['CheckoutRequestID'] ?? null;
        
        if ($resultCode !== 0) {
            if ($checkoutRequestId) {
                $this->updatePaymentStatus($checkoutRequestId, 'failed', null, $resultDesc);
            }
            return [
                'success' => false,
                'checkout_request_id' => $checkoutRequestId,
                'result_code' => $resultCode,
                'result_desc' => $resultDesc
            ];
        }
        
        $metadata = $data['Body']['stkCallback']['CallbackMetadata']['Item'] ?? [];
        $parsed = [];
        foreach ($metadata as $item) {
            $parsed[$item['Name']] = $item['Value'] ?? null;
        }
        
        $mpesaReceipt = $parsed['MpesaReceiptNumber'] ?? null;
        
        if ($checkoutRequestId) {
            $this->updatePaymentStatus($checkoutRequestId, 'completed', $mpesaReceipt);
            $payment = $this->getPaymentByCheckoutId($checkoutRequestId);
            if ($payment) {
                $meta = json_decode($payment['metadata'] ?? '{}', true);
                $billingCycle = $meta['billing_cycle'] ?? 'monthly';
                $this->extendLicense($payment['license_id'], $billingCycle);
            }
        }
        
        return [
            'success' => true,
            'checkout_request_id' => $checkoutRequestId,
            'amount' => $parsed['Amount'] ?? 0,
            'mpesa_receipt' => $mpesaReceipt,
            'phone' => $parsed['PhoneNumber'] ?? null,
            'transaction_date' => $parsed['TransactionDate'] ?? null
        ];
    }

    public function createPaymentRecord(int $licenseId, float $amount, string $phone, string $checkoutRequestId, string $billingCycle): int {
        $subStmt = $this->db->prepare("SELECT id FROM license_subscriptions WHERE license_id = ? ORDER BY created_at DESC LIMIT 1");
        $subStmt->execute([$licenseId]);
        $subscriptionId = $subStmt->fetchColumn() ?: null;

        $stmt = $this->db->prepare("
            INSERT INTO license_payments (license_id, subscription_id, amount, currency, payment_method, transaction_id, phone_number, status, metadata)
            VALUES (?, ?, ?, 'KES', 'mpesa', ?, ?, 'pending', ?)
            RETURNING id
        ");
        $meta = json_encode(['billing_cycle' => $billingCycle, 'checkout_request_id' => $checkoutRequestId]);
        $stmt->execute([$licenseId, $subscriptionId, $amount, $checkoutRequestId, $phone, $meta]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)($row['id'] ?? 0);
    }

    public function getPaymentStatus(string $checkoutRequestId): ?array {
        $stmt = $this->db->prepare("SELECT id, status, mpesa_receipt, amount, paid_at FROM license_payments WHERE transaction_id = ?");
        $stmt->execute([$checkoutRequestId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function updatePaymentStatus(string $checkoutRequestId, string $status, ?string $mpesaReceipt, ?string $error = null): void {
        $sql = "UPDATE license_payments SET status = ?, mpesa_receipt = COALESCE(?, mpesa_receipt),
                paid_at = CASE WHEN ? = 'completed' THEN NOW() ELSE paid_at END,
                metadata = COALESCE(metadata, '{}')::jsonb || ?::jsonb
                WHERE transaction_id = ?";
        $stmt = $this->db->prepare($sql);
        $meta = json_encode($error ? ['error' => $error] : ['completed_at' => date('Y-m-d H:i:s')]);
        $stmt->execute([$status, $mpesaReceipt, $status, $meta, $checkoutRequestId]);
    }

    private function getPaymentByCheckoutId(string $checkoutRequestId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM license_payments WHERE transaction_id = ?");
        $stmt->execute([$checkoutRequestId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function extendLicense(int $licenseId, string $billingCycle): void {
        $months = $billingCycle === 'yearly' ? 12 : 1;

        $stmt = $this->db->prepare("SELECT expires_at FROM licenses WHERE id = ?");
        $stmt->execute([$licenseId]);
        $license = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$license) return;

        $baseDate = ($license['expires_at'] && strtotime($license['expires_at']) > time())
            ? $license['expires_at']
            : date('Y-m-d H:i:s');
        $newExpiry = date('Y-m-d H:i:s', strtotime($baseDate . " +{$months} months"));

        $stmt = $this->db->prepare("UPDATE licenses SET expires_at = ?, is_active = TRUE, is_suspended = FALSE, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newExpiry, $licenseId]);

        $this->db->prepare("
            UPDATE license_subscriptions SET status = 'active', next_billing_date = ?, updated_at = NOW()
            WHERE license_id = ? AND status IN ('pending', 'active')
        ")->execute([date('Y-m-d', strtotime($newExpiry)), $licenseId]);
    }
    
    private function formatPhone(string $phone): string {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 1) === '+') {
            $phone = substr($phone, 1);
        } elseif (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }
        return $phone;
    }
    
    private function getBaseUrl(): string {
        return ($this->config['env'] ?? 'sandbox') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }
}
