<?php

namespace App;

class WhatsApp {
    private bool $enabled = true; // Always enabled
    private string $defaultCountryCode = '';
    private string $provider = 'web';
    private ?string $apiUrl = null;
    private ?string $apiKey = null;
    private ?string $phoneNumberId = null;
    private ?string $businessId = null;
    private string $sessionServiceUrl = 'http://localhost:3001';
    private ?string $sessionApiSecret = null;
    
    public function __construct() {
        $settings = new Settings();
        // WhatsApp is always enabled - ignore database setting
        $this->enabled = true;
        $countryCode = $settings->get('whatsapp_country_code', '254');
        $this->defaultCountryCode = !empty($countryCode) ? $countryCode : '254';
        $this->provider = $settings->get('whatsapp_provider', 'web');
        $this->sessionServiceUrl = $settings->get('whatsapp_session_url', '') 
            ?: getenv('WHATSAPP_SESSION_URL') 
            ?: 'http://localhost:3001';
        $this->sessionApiSecret = $settings->get('whatsapp_session_secret', '') ?: $this->loadSessionSecretFromFile();
        
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
        } elseif ($this->provider === 'session') {
            $this->apiUrl = $this->sessionServiceUrl;
        }
    }
    
    private function loadSessionSecretFromFile(): ?string {
        $paths = [
            __DIR__ . '/../whatsapp-service/.api_secret_dir/secret',
            __DIR__ . '/../whatsapp-service/.api_secret',
        ];
        foreach ($paths as $secretFile) {
            if (file_exists($secretFile)) {
                return trim(file_get_contents($secretFile));
            }
        }
        return null;
    }
    
    private function getSessionHeaders(): array {
        $headers = ['Content-Type: application/json'];
        if ($this->sessionApiSecret) {
            $headers[] = 'Authorization: Bearer ' . $this->sessionApiSecret;
        }
        return $headers;
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
            'session' => 'WhatsApp Web Session',
            'meta' => 'Meta WhatsApp Business API',
            'waha' => 'WAHA (WhatsApp HTTP API)',
            'ultramsg' => 'UltraMsg API',
            'custom' => 'Custom API Gateway'
        ];
        
        $configured = $this->provider === 'web' || $this->provider === 'session' || $this->isApiConfigured();
        
        return [
            'status' => $configured ? 'Enabled' : 'Not Configured',
            'type' => $types[$this->provider] ?? 'Unknown',
            'provider' => $this->provider
        ];
    }
    
    public function fetchGroups(): array {
        try {
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $this->sessionServiceUrl . '/groups');
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            if ($error) {
                return ['success' => false, 'error' => $error, 'groups' => []];
            }
            
            if ($httpCode !== 200) {
                return ['success' => false, 'error' => "HTTP $httpCode", 'groups' => []];
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['groups'])) {
                return ['success' => false, 'error' => 'Invalid response', 'groups' => []];
            }
            
            return [
                'success' => true,
                'groups' => $data['groups']
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'groups' => []];
        }
    }
    
    public function formatPhone(string $phone): string {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }
        
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (empty($phone)) {
            return '';
        }
        
        if (str_starts_with($phone, '0')) {
            $phone = $this->defaultCountryCode . substr($phone, 1);
        }
        
        if (strlen($phone) === 9 && preg_match('/^[1-9]/', $phone)) {
            $phone = $this->defaultCountryCode . $phone;
        }
        
        if (strlen($phone) === 10 && !str_starts_with($phone, $this->defaultCountryCode)) {
            if (preg_match('/^[1-9]/', $phone)) {
                $phone = $this->defaultCountryCode . $phone;
            }
        }
        
        if (strlen($phone) < 10) {
            $phone = $this->defaultCountryCode . $phone;
        }
        
        if (!str_starts_with($phone, $this->defaultCountryCode) && strlen($phone) >= 9 && strlen($phone) <= 10) {
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
        error_log("WhatsApp::send called - enabled: " . ($this->enabled ? 'yes' : 'no') . ", provider: " . $this->provider . ", phone: " . $phone);
        
        if (!$this->enabled) {
            error_log("WhatsApp::send - WhatsApp is disabled");
            return [
                'success' => false,
                'error' => 'WhatsApp is disabled',
                'method' => 'none'
            ];
        }
        
        if ($this->provider === 'web') {
            error_log("WhatsApp::send - using web provider (links only)");
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
        
        if ($this->provider === 'session') {
            error_log("WhatsApp::send - using session provider, calling sendViaSession");
            return $this->sendViaSession($phone, $message);
        }
        
        if (!$this->isApiConfigured()) {
            return [
                'success' => true,
                'method' => 'web',
                'whatsapp_link' => $this->generateLink($phone, $message),
                'web_link' => $this->generateWebLink($phone, $message),
                'message' => $message,
                'phone' => $this->formatPhone($phone),
                'note' => 'API not configured - Click the link to send via WhatsApp Web'
            ];
        }
        
        $formattedPhone = $this->formatPhone($phone);
        
        try {
            $ch = \curl_init();
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
            
            \curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            \curl_setopt($ch, CURLOPT_POST, true);
            \curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            \curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
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
        $settings = new Settings();
        $template = $settings->get('wa_template_ticket_updated', $settings->get('sms_template_ticket_updated', 'ISP Support - Ticket #{ticket_number} Status: {status}. {message}'));
        $text = str_replace(['{ticket_number}', '{status}', '{message}'], [$ticketNumber, $status, $message], $template);
        return $this->send($phone, $text);
    }
    
    public function notifyTechnician(string $phone, string $ticketNumber, string $customerName, string $subject, array $extra = []): array {
        $settings = new Settings();
        $template = $settings->get('wa_template_technician_assigned', $settings->get('sms_template_technician_assigned', 'New Ticket #{ticket_number} assigned to you. Customer: {customer_name} ({customer_phone}). Subject: {subject}. Priority: {priority}'));
        
        if (strpos($template, '{status_link}') === false && !empty($extra['{status_link}'])) {
            $template .= "\n\nUpdate status: {status_link}";
        }
        
        $placeholders = array_merge([
            '{ticket_number}' => $ticketNumber,
            '{customer_name}' => $customerName,
            '{subject}' => $subject,
            '{customer_phone}' => $extra['customer_phone'] ?? '',
            '{customer_address}' => $extra['customer_address'] ?? '',
            '{priority}' => $extra['priority'] ?? 'Medium',
            '{category}' => $extra['category'] ?? '',
            '{technician_name}' => $extra['technician_name'] ?? '',
            '{status_link}' => $extra['status_link'] ?? ''
        ], $extra);
        $text = str_replace(array_keys($placeholders), array_values($placeholders), $template);
        return $this->send($phone, $text);
    }
    
    public function sendMessage(string $phone, string $message): array {
        return $this->send($phone, $message);
    }
    
    public function formatTicketAssignmentMessage(array $ticket, ?array $customer, $settings, string $statusLink = ''): string {
        $ticketNumber = $ticket['ticket_number'] ?? $ticket['id'] ?? 'N/A';
        $subject = $ticket['subject'] ?? 'No subject';
        $category = ucfirst($ticket['category'] ?? 'General');
        $priority = ucfirst($ticket['priority'] ?? 'Medium');
        $status = ucfirst($ticket['status'] ?? 'Open');
        $createdAt = isset($ticket['created_at']) ? date('M j, Y g:i A', strtotime($ticket['created_at'])) : 'N/A';
        
        $customerName = $customer['name'] ?? 'Unknown';
        $customerPhone = $customer['phone'] ?? 'N/A';
        $customerAddress = $customer['address'] ?? 'N/A';
        $customerEmail = $customer['email'] ?? 'N/A';
        
        $assignedInfo = 'Unassigned';
        if (!empty($ticket['assigned_to_name'])) {
            $assignedInfo = "Assigned to: " . $ticket['assigned_to_name'];
        }
        
        $serviceFees = '';
        if (!empty($ticket['service_fees'])) {
            $feeLines = [];
            foreach ($ticket['service_fees'] as $fee) {
                $feeLines[] = "• " . ($fee['name'] ?? 'Fee') . ": " . ($fee['currency'] ?? 'KES') . " " . number_format($fee['amount'] ?? 0, 2);
            }
            if (!empty($feeLines)) {
                $serviceFees = "\n\n💰 *Service Fees:*\n" . implode("\n", $feeLines);
            }
        }
        
        $message = "🎫 *TICKET #{$ticketNumber}*\n\n";
        $message .= "📌 *Subject:* {$subject}\n";
        $message .= "🏷️ *Category:* {$category}\n";
        $message .= "⚡ *Priority:* {$priority}\n";
        $message .= "📊 *Status:* {$status}\n";
        $message .= "🕐 *Created:* {$createdAt}\n\n";
        $message .= "👤 *Customer:*\n";
        $message .= "• Name: {$customerName}\n";
        $message .= "• Phone: {$customerPhone}\n";
        $message .= "• Address: {$customerAddress}\n";
        $message .= "• Email: {$customerEmail}\n\n";
        $message .= "👷 *{$assignedInfo}*";
        $message .= $serviceFees;
        
        if (!empty($statusLink)) {
            $message .= "\n\n🔗 *Update Status:* {$statusLink}";
        }
        
        return $message;
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
        
        if ($this->provider === 'session') {
            return $this->getSessionStatus();
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
    
    public function getSessionStatus(): array {
        try {
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $this->sessionServiceUrl . '/status');
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            if ($error || $httpCode !== 200) {
                return [
                    'success' => false,
                    'status' => 'service_unavailable',
                    'error' => $error ?: 'Service not running',
                    'provider' => 'session'
                ];
            }
            
            $data = json_decode($response, true);
            return [
                'success' => $data['status'] === 'connected',
                'status' => $data['status'],
                'hasQR' => $data['hasQR'] ?? false,
                'info' => $data['info'] ?? null,
                'provider' => 'session',
                'gateway' => $this->getGatewayInfo()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
                'provider' => 'session'
            ];
        }
    }
    
    public function initializeSession(): array {
        try {
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $this->sessionServiceUrl . '/initialize');
            \curl_setopt($ch, CURLOPT_POST, true);
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            if ($error) {
                return ['success' => false, 'error' => $error];
            }
            
            return json_decode($response, true) ?? ['success' => false, 'error' => 'Invalid response'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getSessionQR(): array {
        try {
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $this->sessionServiceUrl . '/qr');
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            if ($error) {
                return ['success' => false, 'error' => $error];
            }
            
            return json_decode($response, true) ?? ['success' => false, 'error' => 'Invalid response'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function logoutSession(): array {
        try {
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $this->sessionServiceUrl . '/logout');
            \curl_setopt($ch, CURLOPT_POST, true);
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            if ($error) {
                return ['success' => false, 'error' => $error];
            }
            
            return json_decode($response, true) ?? ['success' => false, 'error' => 'Invalid response'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getSessionGroups(): array {
        try {
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $this->sessionServiceUrl . '/groups');
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            if ($error || $httpCode !== 200) {
                return ['success' => false, 'error' => $error ?: 'Failed to get groups', 'groups' => []];
            }
            
            $data = json_decode($response, true);
            return ['success' => true, 'groups' => $data['groups'] ?? []];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'groups' => []];
        }
    }
    
    public function sendViaSession(string $phone, string $message): array {
        $formattedPhone = $this->formatPhone($phone);
        
        try {
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $this->sessionServiceUrl . '/send');
            \curl_setopt($ch, CURLOPT_POST, true);
            \curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'phone' => $formattedPhone,
                'message' => $message
            ]));
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            if ($error) {
                return [
                    'success' => false,
                    'error' => $error,
                    'method' => 'session',
                    'provider' => 'session',
                    'whatsapp_link' => $this->generateLink($phone, $message)
                ];
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300 && ($data['success'] ?? false)) {
                return [
                    'success' => true,
                    'method' => 'session',
                    'provider' => 'session',
                    'messageId' => $data['messageId'] ?? null,
                    'phone' => $formattedPhone,
                    'message' => $message
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $data['error'] ?? "HTTP $httpCode",
                    'method' => 'session',
                    'provider' => 'session',
                    'http_code' => $httpCode,
                    'whatsapp_link' => $this->generateLink($phone, $message)
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'method' => 'session',
                'provider' => 'session',
                'whatsapp_link' => $this->generateLink($phone, $message)
            ];
        }
    }
    
    public function sendToGroup(string $groupId, string $message): array {
        error_log("WhatsApp sendToGroup called - Group: $groupId, Provider: {$this->provider}, URL: {$this->sessionServiceUrl}");
        
        if ($this->provider !== 'session') {
            error_log("WhatsApp sendToGroup failed - Provider not session: {$this->provider}");
            return ['success' => false, 'error' => 'Group messaging only available with session provider'];
        }
        
        try {
            $url = $this->sessionServiceUrl . '/send-group';
            error_log("WhatsApp sendToGroup - Calling URL: $url");
            
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $url);
            \curl_setopt($ch, CURLOPT_POST, true);
            \curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'groupId' => $groupId,
                'message' => $message
            ]));
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            error_log("WhatsApp sendToGroup response - HTTP: $httpCode, Error: $error, Response: " . substr($response, 0, 500));
            
            if ($error) {
                error_log("WhatsApp sendToGroup curl error: $error");
                return ['success' => false, 'error' => $error, 'method' => 'session'];
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300 && ($data['success'] ?? false)) {
                error_log("WhatsApp sendToGroup SUCCESS - MessageId: " . ($data['messageId'] ?? 'N/A'));
                return [
                    'success' => true,
                    'method' => 'session',
                    'messageId' => $data['messageId'] ?? null,
                    'groupId' => $groupId
                ];
            } else {
                error_log("WhatsApp sendToGroup FAILED - HTTP: $httpCode, Error: " . ($data['error'] ?? 'Unknown'));
                return [
                    'success' => false,
                    'error' => $data['error'] ?? "HTTP $httpCode",
                    'method' => 'session',
                    'http_code' => $httpCode
                ];
            }
        } catch (\Exception $e) {
            error_log("WhatsApp sendToGroup EXCEPTION: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'method' => 'session'];
        }
    }
    
    // ========== Chat System Methods ==========
    
    /**
     * Get all chats from WhatsApp service
     */
    public function getChats(): array {
        try {
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $this->sessionServiceUrl . '/chats');
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            if ($error || $httpCode !== 200) {
                return ['success' => false, 'error' => $error ?: "HTTP $httpCode", 'chats' => []];
            }
            
            $data = json_decode($response, true);
            return ['success' => true, 'chats' => $data['chats'] ?? []];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'chats' => []];
        }
    }
    
    /**
     * Get chat messages from WhatsApp service
     */
    public function getChatMessages(string $chatId, int $limit = 50, bool $includeMedia = true): array {
        try {
            $ch = \curl_init();
            $url = $this->sessionServiceUrl . '/chat/' . urlencode($chatId) . '/messages?limit=' . $limit;
            if ($includeMedia) {
                $url .= '&includeMedia=true';
            }
            \curl_setopt($ch, CURLOPT_URL, $url);
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Longer timeout for media download
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            if ($error || $httpCode !== 200) {
                return ['success' => false, 'error' => $error ?: "HTTP $httpCode", 'messages' => []];
            }
            
            $data = json_decode($response, true);
            return ['success' => true, 'messages' => $data['messages'] ?? [], 'chatId' => $chatId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'messages' => []];
        }
    }
    
    /**
     * Send message to a specific chat
     */
    public function sendToChat(string $chatId, string $message): array {
        try {
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $this->sessionServiceUrl . '/chat/' . urlencode($chatId) . '/send');
            \curl_setopt($ch, CURLOPT_POST, true);
            \curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['message' => $message]));
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            if ($error) {
                return ['success' => false, 'error' => $error];
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300 && ($data['success'] ?? false)) {
                return [
                    'success' => true,
                    'messageId' => $data['messageId'] ?? null,
                    'timestamp' => $data['timestamp'] ?? time(),
                    'chatId' => $chatId
                ];
            } else {
                return ['success' => false, 'error' => $data['error'] ?? "HTTP $httpCode"];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Mark chat as read
     */
    public function markChatAsRead(string $chatId): array {
        try {
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $this->sessionServiceUrl . '/chat/' . urlencode($chatId) . '/read');
            \curl_setopt($ch, CURLOPT_POST, true);
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            if ($error) {
                return ['success' => false, 'error' => $error];
            }
            
            return json_decode($response, true) ?? ['success' => false, 'error' => 'Invalid response'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get recent messages from WhatsApp service
     */
    public function getRecentMessages(?int $since = null): array {
        try {
            $url = $this->sessionServiceUrl . '/messages/recent';
            if ($since !== null) {
                $url .= '?since=' . $since;
            }
            
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $url);
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getSessionHeaders());
            
            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);
            
            if ($error || $httpCode !== 200) {
                return ['success' => false, 'error' => $error ?: "HTTP $httpCode", 'messages' => []];
            }
            
            $data = json_decode($response, true);
            return ['success' => true, 'messages' => $data['messages'] ?? []];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'messages' => []];
        }
    }
    
    /**
     * Match phone number to customer
     */
    public function findCustomerByPhone(string $phone): ?array {
        $db = \Database::getConnection();
        $formattedPhone = $this->formatPhone($phone);
        
        // Try multiple formats
        $formats = [
            $formattedPhone,
            '+' . $formattedPhone,
            '0' . substr($formattedPhone, strlen($this->defaultCountryCode)),
            ltrim($formattedPhone, '0')
        ];
        
        $placeholders = implode(',', array_fill(0, count($formats), '?'));
        $stmt = $db->prepare("SELECT id, account_number, name, phone, email, address FROM customers WHERE REPLACE(REPLACE(phone, '+', ''), ' ', '') IN ($placeholders) LIMIT 1");
        $stmt->execute(array_map(fn($p) => str_replace(['+', ' '], '', $p), $formats));
        $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $customer ?: null;
    }
    
    /**
     * Get or create conversation record
     */
    public function getOrCreateConversation(string $chatId, string $phone, ?string $contactName = null, bool $isGroup = false): array {
        $db = \Database::getConnection();
        
        // Check if exists
        $stmt = $db->prepare("SELECT * FROM whatsapp_conversations WHERE chat_id = ?");
        $stmt->execute([$chatId]);
        $conversation = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($conversation) {
            return $conversation;
        }
        
        // Find customer by phone
        $customer = $this->findCustomerByPhone($phone);
        $customerId = $customer ? $customer['id'] : null;
        
        // Create new conversation
        $stmt = $db->prepare("
            INSERT INTO whatsapp_conversations (chat_id, phone, contact_name, customer_id, is_group, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            RETURNING *
        ");
        $stmt->execute([$chatId, $phone, $contactName, $customerId, $isGroup ? 't' : 'f']);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Store message in database
     */
    public function storeMessage(int $conversationId, array $messageData): array {
        $db = \Database::getConnection();
        
        // Get message ID (service returns 'id', some calls use 'messageId')
        $messageId = $messageData['id'] ?? $messageData['messageId'] ?? null;
        
        // Check for duplicate
        if (!empty($messageId)) {
            $stmt = $db->prepare("SELECT id FROM whatsapp_messages WHERE message_id = ?");
            $stmt->execute([$messageId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Duplicate message'];
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO whatsapp_messages (conversation_id, message_id, direction, sender_phone, sender_name, message_type, body, is_read, timestamp, raw_data, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            RETURNING *
        ");
        
        $timestamp = isset($messageData['timestamp']) 
            ? date('Y-m-d H:i:s', $messageData['timestamp']) 
            : date('Y-m-d H:i:s');
        
        $stmt->execute([
            $conversationId,
            $messageId,
            $messageData['fromMe'] ?? false ? 'outgoing' : 'incoming',
            $messageData['senderPhone'] ?? null,
            $messageData['senderName'] ?? null,
            $messageData['type'] ?? 'text',
            $messageData['body'] ?? '',
            $messageData['fromMe'] ?? false ? 't' : 'f',
            $timestamp,
            json_encode($messageData)
        ]);
        
        $message = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Update conversation
        $db->prepare("
            UPDATE whatsapp_conversations 
            SET last_message_at = ?, last_message_preview = ?, updated_at = NOW(),
                unread_count = CASE WHEN ? = 'incoming' THEN unread_count + 1 ELSE unread_count END
            WHERE id = ?
        ")->execute([
            $timestamp,
            substr($messageData['body'] ?? '', 0, 100),
            $messageData['fromMe'] ?? false ? 'outgoing' : 'incoming',
            $conversationId
        ]);
        
        return ['success' => true, 'message' => $message];
    }
    
    /**
     * Get conversations from database
     */
    public function getConversations(int $limit = 50, int $offset = 0): array {
        $db = \Database::getConnection();
        
        $stmt = $db->prepare("
            SELECT c.*, 
                   cu.name as customer_name, cu.account_number,
                   u.name as assigned_to_name
            FROM whatsapp_conversations c
            LEFT JOIN customers cu ON c.customer_id = cu.id
            LEFT JOIN users u ON c.assigned_to = u.id
            ORDER BY c.last_message_at DESC NULLS LAST, c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get messages for a conversation from database
     */
    public function getConversationMessages(int $conversationId, int $limit = 100, int $since = 0): array {
        $db = \Database::getConnection();
        
        if ($since > 0) {
            $sinceTime = date('Y-m-d H:i:s', $since);
            $stmt = $db->prepare("
                SELECT m.*, u.name as sent_by_name
                FROM whatsapp_messages m
                LEFT JOIN users u ON m.sent_by = u.id
                WHERE m.conversation_id = ? AND m.timestamp > ?
                ORDER BY m.timestamp ASC
                LIMIT ?
            ");
            $stmt->execute([$conversationId, $sinceTime, $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        $stmt = $db->prepare("
            SELECT m.*, u.name as sent_by_name
            FROM whatsapp_messages m
            LEFT JOIN users u ON m.sent_by = u.id
            WHERE m.conversation_id = ?
            ORDER BY m.timestamp DESC
            LIMIT ?
        ");
        $stmt->execute([$conversationId, $limit]);
        return array_reverse($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
    
    /**
     * Link conversation to customer
     */
    public function linkConversationToCustomer(int $conversationId, int $customerId): bool {
        $db = \Database::getConnection();
        $stmt = $db->prepare("UPDATE whatsapp_conversations SET customer_id = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$customerId, $conversationId]);
    }
    
    /**
     * Assign conversation to user
     */
    public function assignConversation(int $conversationId, ?int $userId): bool {
        $db = \Database::getConnection();
        $stmt = $db->prepare("UPDATE whatsapp_conversations SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$userId, $conversationId]);
    }
    
    /**
     * Mark conversation messages as read
     */
    public function markConversationAsRead(int $conversationId): bool {
        $db = \Database::getConnection();
        
        $db->prepare("UPDATE whatsapp_messages SET is_read = true WHERE conversation_id = ? AND direction = 'incoming'")->execute([$conversationId]);
        $db->prepare("UPDATE whatsapp_conversations SET unread_count = 0, updated_at = NOW() WHERE id = ?")->execute([$conversationId]);
        
        return true;
    }
    
    /**
     * Get conversation by ID
     */
    public function getConversationById(int $id): ?array {
        $db = \Database::getConnection();
        $stmt = $db->prepare("
            SELECT c.*, 
                   cu.name as customer_name, cu.account_number, cu.phone as customer_phone,
                   u.name as assigned_to_name
            FROM whatsapp_conversations c
            LEFT JOIN customers cu ON c.customer_id = cu.id
            LEFT JOIN users u ON c.assigned_to = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Get unread count across all conversations
     */
    public function getTotalUnreadCount(): int {
        $db = \Database::getConnection();
        $stmt = $db->query("SELECT COALESCE(SUM(unread_count), 0) FROM whatsapp_conversations");
        return (int) $stmt->fetchColumn();
    }
}
