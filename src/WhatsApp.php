<?php

namespace App;

class WhatsApp {
    private bool $enabled = false;
    private string $defaultCountryCode = '';
    private string $provider = 'web';
    private ?string $apiUrl = null;
    private ?string $apiKey = null;
    private ?string $phoneNumberId = null;
    private ?string $businessId = null;
    
    public function __construct() {
        $settings = new Settings();
        $this->enabled = $settings->get('whatsapp_enabled', '1') === '1';
        $this->defaultCountryCode = $settings->get('whatsapp_country_code', '254');
        $this->provider = $settings->get('whatsapp_provider', 'web');
        
        if ($this->provider === 'meta') {
            $this->apiKey = $settings->get('whatsapp_meta_token', '') ?: getenv('WHATSAPP_META_TOKEN') ?: null;
            $this->phoneNumberId = $settings->get('whatsapp_phone_number_id', '') ?: getenv('WHATSAPP_PHONE_NUMBER_ID') ?: null;
            $this->businessId = $settings->get('whatsapp_business_id', '') ?: getenv('WHATSAPP_BUSINESS_ID') ?: null;
            $this->apiUrl = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";
        } elseif ($this->provider === 'waha') {
            $this->apiUrl = $settings->get('whatsapp_waha_url', '') ?: getenv('WHATSAPP_WAHA_URL') ?: null;
            $this->apiKey = $settings->get('whatsapp_waha_api_key', '') ?: getenv('WHATSAPP_WAHA_API_KEY') ?: null;
        } elseif ($this->provider === 'ultramsg') {
            $instanceId = $settings->get('whatsapp_ultramsg_instance', '') ?: getenv('WHATSAPP_ULTRAMSG_INSTANCE') ?: '';
            $this->apiKey = $settings->get('whatsapp_ultramsg_token', '') ?: getenv('WHATSAPP_ULTRAMSG_TOKEN') ?: null;
            $this->apiUrl = "https://api.ultramsg.com/{$instanceId}/messages/chat";
        } elseif ($this->provider === 'custom') {
            $this->apiUrl = $settings->get('whatsapp_custom_url', '') ?: getenv('WHATSAPP_CUSTOM_URL') ?: null;
            $this->apiKey = $settings->get('whatsapp_custom_api_key', '') ?: getenv('WHATSAPP_CUSTOM_API_KEY') ?: null;
        }
    }
    
    public function isEnabled(): bool {
        return $this->enabled;
    }
    
    public function isApiConfigured(): bool {
        if ($this->provider === 'web') {
            return false;
        }
        return $this->apiUrl && $this->apiKey;
    }
    
    public function getProvider(): string {
        return $this->provider;
    }
    
    public function getGatewayInfo(): array {
        if (!$this->enabled) {
            return ['status' => 'Disabled', 'type' => 'None', 'provider' => 'none'];
        }
        
        $types = [
            'web' => 'WhatsApp Web Links',
            'meta' => 'Meta WhatsApp Business API',
            'waha' => 'WAHA (WhatsApp HTTP API)',
            'ultramsg' => 'UltraMsg API',
            'custom' => 'Custom API Gateway'
        ];
        
        $configured = $this->provider === 'web' || $this->isApiConfigured();
        
        return [
            'status' => $configured ? 'Enabled' : 'Not Configured',
            'type' => $types[$this->provider] ?? 'Unknown',
            'provider' => $this->provider
        ];
    }
    
    public function formatPhone(string $phone): string {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (str_starts_with($phone, '0')) {
            $phone = $this->defaultCountryCode . substr($phone, 1);
        }
        
        if (strlen($phone) < 10) {
            $phone = $this->defaultCountryCode . $phone;
        }
        
        return $phone;
    }
    
    public function generateLink(string $phone, string $message = ''): string {
        $formattedPhone = $this->formatPhone($phone);
        $encodedMessage = urlencode($message);
        return "https://wa.me/{$formattedPhone}?text={$encodedMessage}";
    }
    
    public function generateWebLink(string $phone, string $message = ''): string {
        $formattedPhone = $this->formatPhone($phone);
        $encodedMessage = urlencode($message);
        return "https://web.whatsapp.com/send?phone={$formattedPhone}&text={$encodedMessage}";
    }
    
    public function send(string $phone, string $message): array {
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'WhatsApp is disabled',
                'method' => 'none'
            ];
        }
        
        if ($this->provider === 'web' || !$this->isApiConfigured()) {
            return [
                'success' => true,
                'method' => 'web',
                'whatsapp_link' => $this->generateLink($phone, $message),
                'web_link' => $this->generateWebLink($phone, $message),
                'message' => $message,
                'phone' => $this->formatPhone($phone),
                'note' => 'Click the link to send via WhatsApp Web'
            ];
        }
        
        $formattedPhone = $this->formatPhone($phone);
        
        try {
            $ch = curl_init();
            $headers = [];
            $postData = null;
            
            if ($this->provider === 'meta') {
                $headers = [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json'
                ];
                $postData = json_encode([
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $formattedPhone,
                    'type' => 'text',
                    'text' => ['body' => $message]
                ]);
            } elseif ($this->provider === 'waha') {
                $headers = [
                    'Content-Type: application/json'
                ];
                if ($this->apiKey) {
                    $headers[] = 'X-Api-Key: ' . $this->apiKey;
                }
                $postData = json_encode([
                    'chatId' => $formattedPhone . '@c.us',
                    'text' => $message
                ]);
                $this->apiUrl = rtrim($this->apiUrl, '/') . '/api/sendText';
            } elseif ($this->provider === 'ultramsg') {
                $headers = [
                    'Content-Type: application/x-www-form-urlencoded'
                ];
                $postData = http_build_query([
                    'token' => $this->apiKey,
                    'to' => '+' . $formattedPhone,
                    'body' => $message
                ]);
            } elseif ($this->provider === 'custom') {
                $headers = [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey
                ];
                $postData = json_encode([
                    'phone' => $formattedPhone,
                    'message' => $message
                ]);
            }
            
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
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
                    'error' => $error,
                    'method' => 'api',
                    'provider' => $this->provider,
                    'whatsapp_link' => $this->generateLink($phone, $message)
                ];
            }
            
            $responseData = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'method' => 'api',
                    'provider' => $this->provider,
                    'response' => $responseData,
                    'http_code' => $httpCode,
                    'phone' => $formattedPhone,
                    'message' => $message
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $responseData['error']['message'] ?? $responseData['message'] ?? "HTTP $httpCode",
                    'method' => 'api',
                    'provider' => $this->provider,
                    'http_code' => $httpCode,
                    'whatsapp_link' => $this->generateLink($phone, $message)
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'method' => 'api',
                'provider' => $this->provider,
                'whatsapp_link' => $this->generateLink($phone, $message)
            ];
        }
    }
    
    public function notifyCustomer(string $phone, string $ticketNumber, string $status, string $message = ''): array {
        $text = "ISP Support - Ticket #{$ticketNumber}\nStatus: {$status}";
        if ($message) {
            $text .= "\n{$message}";
        }
        
        return $this->send($phone, $text);
    }
    
    public function notifyTechnician(string $phone, string $ticketNumber, string $customerName, string $subject): array {
        $text = "New Ticket Assigned - #{$ticketNumber}\nCustomer: {$customerName}\nSubject: {$subject}";
        
        return $this->send($phone, $text);
    }
    
    public function sendMessage(string $phone, string $message): array {
        return $this->send($phone, $message);
    }
    
    public function sendBulk(array $recipients, string $message): array {
        $results = [];
        foreach ($recipients as $phone) {
            $results[$phone] = $this->send($phone, $message);
            if ($this->provider !== 'web') {
                usleep(100000);
            }
        }
        return $results;
    }
    
    public function logMessage(?int $ticketId, ?int $orderId, ?int $complaintId, string $phone, string $recipientType, ?string $message, string $status, string $messageType = 'custom'): void {
        $db = \Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO whatsapp_logs (ticket_id, order_id, complaint_id, recipient_phone, recipient_type, message, status, message_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ticketId, $orderId, $complaintId, $phone, $recipientType, $message, $status, $messageType]);
    }
    
    public function getRecentLogs(int $limit = 10): array {
        $db = \Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM whatsapp_logs ORDER BY sent_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    public function testConnection(): array {
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'WhatsApp is disabled'
            ];
        }
        
        if ($this->provider === 'web') {
            return [
                'success' => true,
                'gateway' => $this->getGatewayInfo(),
                'note' => 'Web mode - no API test required'
            ];
        }
        
        if (!$this->isApiConfigured()) {
            return [
                'success' => false,
                'error' => 'API credentials not configured',
                'provider' => $this->provider
            ];
        }
        
        return [
            'success' => true,
            'gateway' => $this->getGatewayInfo(),
            'api_url' => $this->apiUrl ? substr($this->apiUrl, 0, 50) . '...' : null
        ];
    }
}
