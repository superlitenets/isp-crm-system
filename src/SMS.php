<?php

namespace App;

use Twilio\Rest\Client;

class SMS {
    private ?Client $client = null;
    private ?string $fromNumber = null;
    private bool $enabled = false;

    public function __construct() {
        $sid = getenv('TWILIO_ACCOUNT_SID');
        $token = getenv('TWILIO_AUTH_TOKEN');
        $this->fromNumber = getenv('TWILIO_PHONE_NUMBER');

        if ($sid && $token && $this->fromNumber) {
            try {
                $this->client = new Client($sid, $token);
                $this->enabled = true;
            } catch (\Exception $e) {
                error_log("Twilio initialization failed: " . $e->getMessage());
            }
        }
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function send(string $to, string $message): array {
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'SMS service not configured',
                'simulated' => true
            ];
        }

        try {
            $result = $this->client->messages->create(
                $to,
                [
                    'from' => $this->fromNumber,
                    'body' => $message
                ]
            );

            return [
                'success' => true,
                'sid' => $result->sid,
                'status' => $result->status
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function notifyCustomer(string $phone, string $ticketNumber, string $status, string $message = ''): array {
        $settings = new Settings();
        $template = $settings->get('sms_template_ticket_updated', 'ISP Support - Ticket #{ticket_number} Status: {status}. {message}');
        $text = str_replace(['{ticket_number}', '{status}', '{message}'], [$ticketNumber, $status, $message], $template);
        return $this->send($phone, $text);
    }

    public function notifyTechnician(string $phone, string $ticketNumber, string $customerName, string $subject, array $extra = []): array {
        $settings = new Settings();
        $template = $settings->get('sms_template_technician_assigned', 'New Ticket #{ticket_number} assigned to you. Customer: {customer_name} ({customer_phone}). Subject: {subject}. Priority: {priority}');
        $placeholders = array_merge([
            '{ticket_number}' => $ticketNumber,
            '{customer_name}' => $customerName,
            '{subject}' => $subject,
            '{customer_phone}' => $extra['customer_phone'] ?? '',
            '{customer_address}' => $extra['customer_address'] ?? '',
            '{priority}' => $extra['priority'] ?? 'Medium',
            '{category}' => $extra['category'] ?? '',
            '{technician_name}' => $extra['technician_name'] ?? ''
        ], $extra);
        $text = str_replace(array_keys($placeholders), array_values($placeholders), $template);
        return $this->send($phone, $text);
    }

    public function logSMS(int $ticketId, string $phone, string $recipientType, string $message, string $status): void {
        $db = \Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO sms_logs (ticket_id, recipient_phone, recipient_type, message, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ticketId, $phone, $recipientType, $message, $status]);
    }
}
