<?php
namespace LicenseServer;

class Mpesa {
    private $config;
    private $accessToken;
    
    public function __construct(array $config) {
        $this->config = $config;
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
        
        $payload = [
            'BusinessShortCode' => $this->config['shortcode'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => ceil($amount),
            'PartyA' => $phone,
            'PartyB' => $this->config['shortcode'],
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->config['callback_url'],
            'AccountReference' => $reference,
            'TransactionDesc' => $description
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
            CURLOPT_TIMEOUT => 30
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
        
        return [
            'success' => true,
            'checkout_request_id' => $checkoutRequestId,
            'amount' => $parsed['Amount'] ?? 0,
            'mpesa_receipt' => $parsed['MpesaReceiptNumber'] ?? null,
            'phone' => $parsed['PhoneNumber'] ?? null,
            'transaction_date' => $parsed['TransactionDate'] ?? null
        ];
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
