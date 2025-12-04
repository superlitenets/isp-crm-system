<?php

namespace App;

class SMSGateway {
    private ?string $apiUrl = null;
    private ?string $apiKey = null;
    private ?string $senderId = null;
    private ?string $partnerId = null;
    private string $method = 'POST';
    private bool $enabled = false;
    private string $provider = 'custom';
    private array $customHeaders = [];
    private string $messageParam = 'message';
    private string $phoneParam = 'phone';
    private string $senderParam = 'sender';

    public function __construct() {
        $settings = new Settings();
        $advantaConfig = $settings->getAdvantaConfig();
        
        if ($advantaConfig['configured']) {
            $this->setupAdvanta(
                $advantaConfig['url'],
                $advantaConfig['api_key'],
                $advantaConfig['partner_id'],
                $advantaConfig['shortcode']
            );
        } else {
            $this->apiUrl = getenv('SMS_API_URL') ?: null;
            $this->apiKey = getenv('SMS_API_KEY') ?: null;
            $this->senderId = getenv('SMS_SENDER_ID') ?: 'ISP-CRM';
            $this->method = getenv('SMS_API_METHOD') ?: 'POST';
            $this->messageParam = getenv('SMS_MESSAGE_PARAM') ?: 'message';
            $this->phoneParam = getenv('SMS_PHONE_PARAM') ?: 'phone';
            $this->senderParam = getenv('SMS_SENDER_PARAM') ?: 'sender';

            if ($this->apiUrl && $this->apiKey) {
                $this->enabled = true;
                $this->provider = 'custom';
            }
        }

        if (!$this->enabled) {
            $smsSettings = $settings->getSMSSettings();
            $twilioSid = getenv('TWILIO_ACCOUNT_SID') ?: $smsSettings['twilio_account_sid'];
            $twilioToken = getenv('TWILIO_AUTH_TOKEN') ?: $smsSettings['twilio_auth_token'];
            $twilioPhone = getenv('TWILIO_PHONE_NUMBER') ?: $smsSettings['twilio_phone_number'];
            
            if ($twilioSid && $twilioToken && $twilioPhone) {
                $this->setupTwilio($twilioSid, $twilioToken, $twilioPhone);
            }
        }
    }

    private function setupAdvanta(string $url, string $apiKey, string $partnerId, string $shortcode): void {
        $this->apiUrl = $url;
        $this->apiKey = $apiKey;
        $this->partnerId = $partnerId;
        $this->senderId = $shortcode;
        $this->method = 'POST';
        $this->phoneParam = 'mobile';
        $this->messageParam = 'message';
        $this->senderParam = 'shortcode';
        $this->provider = 'advanta';
        $this->enabled = true;
    }

    private function setupTwilio(string $sid, string $token, string $phone): void {
        $this->apiUrl = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
        $this->apiKey = base64_encode("$sid:$token");
        $this->senderId = $phone;
        $this->method = 'POST';
        $this->phoneParam = 'To';
        $this->messageParam = 'Body';
        $this->senderParam = 'From';
        $this->customHeaders = [
            'Authorization: Basic ' . $this->apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ];
        $this->enabled = true;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function getGatewayInfo(): array {
        if (!$this->enabled) {
            return ['status' => 'Not Configured', 'type' => 'None', 'provider' => 'none'];
        }
        
        if ($this->provider === 'advanta') {
            return ['status' => 'Enabled', 'type' => 'Advanta SMS', 'provider' => 'advanta'];
        }
        
        if (strpos($this->apiUrl, 'twilio.com') !== false) {
            return ['status' => 'Enabled', 'type' => 'Twilio', 'provider' => 'twilio'];
        }
        
        return ['status' => 'Enabled', 'type' => 'Custom Gateway', 'provider' => 'custom'];
    }

    public function getProvider(): string {
        return $this->provider;
    }

    private function normalizePhoneNumber(string $phone): string {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (str_starts_with($phone, '+254')) {
            return substr($phone, 1);
        }
        
        if (str_starts_with($phone, '254')) {
            return $phone;
        }
        
        if (str_starts_with($phone, '07') || str_starts_with($phone, '01')) {
            return '254' . substr($phone, 1);
        }
        
        if (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
            return '254' . $phone;
        }
        
        if (str_starts_with($phone, '+')) {
            return substr($phone, 1);
        }
        
        return $phone;
    }

    public function send(string $to, string $message): array {
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'SMS gateway not configured',
                'simulated' => true
            ];
        }

        $to = $this->normalizePhoneNumber($to);

        try {
            $ch = curl_init();
            $url = $this->apiUrl;
            $headers = [];

            if ($this->provider === 'advanta') {
                $data = [
                    'apikey' => $this->apiKey,
                    'partnerID' => $this->partnerId,
                    'shortcode' => $this->senderId,
                    'mobile' => $to,
                    'message' => $message
                ];
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_POST, true);
            } elseif (strpos($this->apiUrl, 'twilio.com') !== false) {
                $data = [
                    $this->phoneParam => $to,
                    $this->messageParam => $message,
                    $this->senderParam => $this->senderId
                ];
                $headers = $this->customHeaders;
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_POST, true);
            } else {
                $data = [
                    $this->phoneParam => $to,
                    $this->messageParam => $message,
                    $this->senderParam => $this->senderId
                ];

                $contentType = getenv('SMS_CONTENT_TYPE') ?: 'json';
                
                if ($this->apiKey) {
                    $authHeader = getenv('SMS_AUTH_HEADER') ?: 'Bearer';
                    if ($authHeader === 'Bearer') {
                        $headers[] = 'Authorization: Bearer ' . $this->apiKey;
                    } elseif ($authHeader === 'Basic') {
                        $headers[] = 'Authorization: Basic ' . $this->apiKey;
                    } elseif ($authHeader === 'X-API-Key') {
                        $headers[] = 'X-API-Key: ' . $this->apiKey;
                    } else {
                        $headers[] = $authHeader . ': ' . $this->apiKey;
                    }
                }

                $method = strtoupper($this->method);
                
                if ($method === 'GET') {
                    $separator = (strpos($url, '?') !== false) ? '&' : '?';
                    $url .= $separator . http_build_query($data);
                } else {
                    if ($contentType === 'form') {
                        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                    } else {
                        $headers[] = 'Content-Type: application/json';
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    }
                    curl_setopt($ch, CURLOPT_POST, true);
                }
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return [
                    'success' => false,
                    'error' => $error
                ];
            }

            $responseData = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                $success = true;
                if ($this->provider === 'advanta' && isset($responseData['responses'])) {
                    foreach ($responseData['responses'] as $resp) {
                        if (isset($resp['response-code']) && $resp['response-code'] != 200) {
                            $success = false;
                        }
                    }
                }
                return [
                    'success' => $success,
                    'response' => $responseData,
                    'http_code' => $httpCode
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $responseData['message'] ?? $responseData['error'] ?? "HTTP $httpCode",
                    'http_code' => $httpCode
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function notifyCustomer(string $phone, string $ticketNumber, string $status, string $message = ''): array {
        $text = "ISP Support - Ticket #$ticketNumber\nStatus: $status";
        if ($message) {
            $text .= "\n$message";
        }
        return $this->send($phone, $text);
    }

    public function notifyTechnician(string $phone, string $ticketNumber, string $customerName, string $subject): array {
        $text = "New Ticket Assigned - #$ticketNumber\nCustomer: $customerName\nSubject: $subject";
        return $this->send($phone, $text);
    }

    public function notifyEmployee(string $phone, string $subject, string $message): array {
        $text = "ISP HR Notice\n$subject\n$message";
        return $this->send($phone, $text);
    }

    public function sendBulk(array $recipients, string $message): array {
        $results = [];
        foreach ($recipients as $phone) {
            $results[$phone] = $this->send($phone, $message);
        }
        return $results;
    }

    public function logSMS(int $ticketId, string $phone, string $recipientType, string $message, string $status): void {
        $db = \Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO sms_logs (ticket_id, recipient_phone, recipient_type, message, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ticketId, $phone, $recipientType, $message, $status]);
    }

    public function testConnection(): array {
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'SMS gateway not configured'
            ];
        }

        return [
            'success' => true,
            'gateway' => $this->getGatewayInfo(),
            'api_url' => $this->apiUrl ? substr($this->apiUrl, 0, 50) . '...' : null
        ];
    }
}
