<?php

namespace App;

class WhatsApp {
    private bool $enabled = false;
    private string $defaultCountryCode = '';
    
    public function __construct() {
        $settings = new Settings();
        $this->enabled = $settings->get('whatsapp_enabled', '1') === '1';
        $this->defaultCountryCode = $settings->get('whatsapp_country_code', '254');
    }
    
    public function isEnabled(): bool {
        return $this->enabled;
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
    
    public function notifyCustomer(string $phone, string $ticketNumber, string $status, string $message = ''): array {
        $text = "ISP Support - Ticket #{$ticketNumber}\nStatus: {$status}";
        if ($message) {
            $text .= "\n{$message}";
        }
        
        return [
            'success' => true,
            'whatsapp_link' => $this->generateLink($phone, $text),
            'web_link' => $this->generateWebLink($phone, $text),
            'message' => $text,
            'phone' => $this->formatPhone($phone)
        ];
    }
    
    public function notifyTechnician(string $phone, string $ticketNumber, string $customerName, string $subject): array {
        $text = "New Ticket Assigned - #{$ticketNumber}\nCustomer: {$customerName}\nSubject: {$subject}";
        
        return [
            'success' => true,
            'whatsapp_link' => $this->generateLink($phone, $text),
            'web_link' => $this->generateWebLink($phone, $text),
            'message' => $text,
            'phone' => $this->formatPhone($phone)
        ];
    }
    
    public function sendMessage(string $phone, string $message): array {
        return [
            'success' => true,
            'whatsapp_link' => $this->generateLink($phone, $message),
            'web_link' => $this->generateWebLink($phone, $message),
            'message' => $message,
            'phone' => $this->formatPhone($phone)
        ];
    }
    
    public function logMessage(int $ticketId, string $phone, string $recipientType, string $message, string $status): void {
        $db = \Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO whatsapp_logs (ticket_id, recipient_phone, recipient_type, message, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ticketId, $phone, $recipientType, $message, $status]);
    }
    
    public function getRecentLogs(int $limit = 10): array {
        $db = \Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM whatsapp_logs ORDER BY sent_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
