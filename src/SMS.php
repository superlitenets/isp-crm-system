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

    public function logSMS(int $ticketId, string $phone, string $recipientType, string $message, string $status): void {
        $db = \Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO sms_logs (ticket_id, recipient_phone, recipient_type, message, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ticketId, $phone, $recipientType, $message, $status]);
    }
}
