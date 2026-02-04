<?php
namespace App;

class EmailService {
    private \PDO $db;
    private array $config = [];
    
    public function __construct(\PDO $db) {
        $this->db = $db;
        $this->loadConfig();
    }
    
    private function loadConfig(): void {
        $settings = new Settings();
        $this->config = [
            'smtp_host' => $settings->get('smtp_host', ''),
            'smtp_port' => (int)$settings->get('smtp_port', 587),
            'smtp_username' => $settings->get('smtp_username', ''),
            'smtp_password' => $settings->get('smtp_password', ''),
            'smtp_encryption' => $settings->get('smtp_encryption', 'tls'),
            'from_email' => $settings->get('smtp_from_email', ''),
            'from_name' => $settings->get('smtp_from_name', ''),
        ];
    }
    
    public function isConfigured(): bool {
        return !empty($this->config['smtp_host']) 
            && !empty($this->config['smtp_username'])
            && !empty($this->config['smtp_password'])
            && !empty($this->config['from_email']);
    }
    
    public function getConfig(): array {
        return $this->config;
    }
    
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null, array $attachments = []): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Email not configured. Please configure SMTP settings.'];
        }
        
        $maxRetries = 3;
        $lastError = '';
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $boundary = md5(uniqid(time()));
                $headers = $this->buildHeaders($boundary, !empty($attachments));
                $body = $this->buildBody($htmlBody, $textBody, $attachments, $boundary);
                
                $socket = $this->connect();
                if (!$socket) {
                    $lastError = 'Failed to connect to SMTP server';
                    if ($attempt < $maxRetries) {
                        usleep(500000); // 0.5 second delay before retry
                        continue;
                    }
                    $this->logEmail($to, $subject, 'failed', $lastError);
                    return ['success' => false, 'error' => $lastError];
                }
                
                $result = $this->sendSmtp($socket, $to, $subject, $headers, $body);
                @fclose($socket);
                
                if ($result['success']) {
                    $this->logEmail($to, $subject, 'sent');
                    return $result;
                }
                
                $lastError = $result['error'] ?? 'Unknown error';
                
                // Retry on connection-related errors
                if ($attempt < $maxRetries && (
                    strpos($lastError, 'Connection') !== false ||
                    strpos($lastError, 'TLS') !== false ||
                    strpos($lastError, 'pipe') !== false
                )) {
                    usleep(500000); // 0.5 second delay before retry
                    continue;
                }
                
                $this->logEmail($to, $subject, 'failed', $lastError);
                return $result;
                
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                if ($attempt >= $maxRetries) {
                    $this->logEmail($to, $subject, 'failed', $lastError);
                    return ['success' => false, 'error' => $lastError];
                }
                usleep(500000);
            }
        }
        
        $this->logEmail($to, $subject, 'failed', $lastError);
        return ['success' => false, 'error' => $lastError];
    }
    
    private function connect() {
        $host = $this->config['smtp_host'];
        $port = $this->config['smtp_port'];
        $encryption = $this->config['smtp_encryption'];
        
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
            ]
        ]);
        
        if ($encryption === 'ssl') {
            $host = 'ssl://' . $host;
        }
        
        $socket = @stream_socket_client(
            "{$host}:{$port}",
            $errno,
            $errstr,
            60, // Increased timeout for slow cPanel servers
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if ($socket) {
            // Set read/write timeouts
            stream_set_timeout($socket, 60);
        }
        
        return $socket;
    }
    
    private function sendSmtp($socket, string $to, string $subject, string $headers, string $body): array {
        $this->readResponse($socket);
        
        $this->sendCommand($socket, "EHLO " . gethostname());
        $response = $this->readResponse($socket);
        
        if ($this->config['smtp_encryption'] === 'tls') {
            if (!$this->sendCommand($socket, "STARTTLS")) {
                return ['success' => false, 'error' => 'Connection lost during STARTTLS'];
            }
            $starttlsResponse = $this->readResponse($socket);
            if (strpos($starttlsResponse, '220') === false) {
                return ['success' => false, 'error' => 'STARTTLS rejected by server: ' . $starttlsResponse];
            }
            
            // Try multiple TLS methods for compatibility
            $cryptoMethods = [
                STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            ];
            
            $cryptoEnabled = false;
            foreach ($cryptoMethods as $method) {
                $cryptoEnabled = @stream_socket_enable_crypto($socket, true, $method);
                if ($cryptoEnabled) break;
            }
            
            if (!$cryptoEnabled) {
                return ['success' => false, 'error' => 'Failed to enable TLS - server may not support required encryption'];
            }
            
            $this->sendCommand($socket, "EHLO " . gethostname());
            $this->readResponse($socket);
        }
        
        $this->sendCommand($socket, "AUTH LOGIN");
        $response = $this->readResponse($socket);
        if (strpos($response, '334') === false) {
            return ['success' => false, 'error' => 'AUTH LOGIN not supported'];
        }
        
        $this->sendCommand($socket, base64_encode($this->config['smtp_username']));
        $this->readResponse($socket);
        
        $this->sendCommand($socket, base64_encode($this->config['smtp_password']));
        $response = $this->readResponse($socket);
        if (strpos($response, '235') === false && strpos($response, '250') === false) {
            return ['success' => false, 'error' => 'Authentication failed'];
        }
        
        $this->sendCommand($socket, "MAIL FROM:<{$this->config['from_email']}>");
        $response = $this->readResponse($socket);
        if (strpos($response, '250') === false) {
            return ['success' => false, 'error' => 'MAIL FROM rejected: ' . $response];
        }
        
        $this->sendCommand($socket, "RCPT TO:<{$to}>");
        $response = $this->readResponse($socket);
        if (strpos($response, '250') === false) {
            return ['success' => false, 'error' => 'RCPT TO rejected: ' . $response];
        }
        
        $this->sendCommand($socket, "DATA");
        $response = $this->readResponse($socket);
        if (strpos($response, '354') === false) {
            return ['success' => false, 'error' => 'DATA rejected'];
        }
        
        $message = "Subject: {$subject}\r\n";
        $message .= $headers . "\r\n\r\n";
        $message .= $body;
        $message .= "\r\n.";
        
        $this->sendCommand($socket, $message);
        $response = $this->readResponse($socket);
        
        $this->sendCommand($socket, "QUIT");
        $this->readResponse($socket);
        
        if (strpos($response, '250') !== false) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Message not accepted: ' . $response];
    }
    
    private function sendCommand($socket, string $command): bool {
        if (!is_resource($socket) || feof($socket)) {
            return false;
        }
        $result = @fwrite($socket, $command . "\r\n");
        return $result !== false;
    }
    
    private function readResponse($socket): string {
        if (!is_resource($socket) || feof($socket)) {
            return '';
        }
        $response = '';
        while ($line = @fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    }
    
    private function buildHeaders(string $boundary, bool $hasAttachments): string {
        $headers = [];
        $headers[] = "From: {$this->config['from_name']} <{$this->config['from_email']}>";
        $headers[] = "Reply-To: {$this->config['from_email']}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "X-Mailer: ISP-CRM/1.0";
        
        if ($hasAttachments) {
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
        } else {
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        }
        
        return implode("\r\n", $headers);
    }
    
    private function buildBody(string $htmlBody, ?string $textBody, array $attachments, string $boundary): string {
        $textBody = $textBody ?: strip_tags($htmlBody);
        
        $body = "";
        
        if (!empty($attachments)) {
            $altBoundary = md5(uniqid(time() . 'alt'));
            
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
            
            $body .= "--{$altBoundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $textBody . "\r\n\r\n";
            
            $body .= "--{$altBoundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $htmlBody . "\r\n\r\n";
            
            $body .= "--{$altBoundary}--\r\n\r\n";
            
            foreach ($attachments as $attachment) {
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: {$attachment['mime']}; name=\"{$attachment['name']}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n\r\n";
                $body .= chunk_split(base64_encode($attachment['content'])) . "\r\n";
            }
            
            $body .= "--{$boundary}--";
        } else {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $textBody . "\r\n\r\n";
            
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $htmlBody . "\r\n\r\n";
            
            $body .= "--{$boundary}--";
        }
        
        return $body;
    }
    
    private function logEmail(string $to, string $subject, string $status, ?string $error = null): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_logs (recipient, subject, status, error_message, sent_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$to, $subject, $status, $error]);
        } catch (\Exception $e) {
        }
    }
    
    public function testConnection(): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Email not configured'];
        }
        
        try {
            $socket = $this->connect();
            if (!$socket) {
                return ['success' => false, 'error' => 'Could not connect to SMTP server'];
            }
            
            $response = $this->readResponse($socket);
            if (strpos($response, '220') === false) {
                fclose($socket);
                return ['success' => false, 'error' => 'Invalid SMTP response: ' . $response];
            }
            
            $this->sendCommand($socket, "EHLO " . gethostname());
            $response = $this->readResponse($socket);
            
            if ($this->config['smtp_encryption'] === 'tls') {
                $this->sendCommand($socket, "STARTTLS");
                $response = $this->readResponse($socket);
                
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($socket);
                    return ['success' => false, 'error' => 'TLS handshake failed'];
                }
                
                $this->sendCommand($socket, "EHLO " . gethostname());
                $this->readResponse($socket);
            }
            
            $this->sendCommand($socket, "AUTH LOGIN");
            $response = $this->readResponse($socket);
            
            $this->sendCommand($socket, base64_encode($this->config['smtp_username']));
            $this->readResponse($socket);
            
            $this->sendCommand($socket, base64_encode($this->config['smtp_password']));
            $response = $this->readResponse($socket);
            
            $this->sendCommand($socket, "QUIT");
            fclose($socket);
            
            if (strpos($response, '235') !== false || strpos($response, '250') !== false) {
                return ['success' => true, 'message' => 'Connection successful'];
            }
            
            return ['success' => false, 'error' => 'Authentication failed: ' . trim($response)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function sendInvoice(array $invoice, string $recipientEmail): array {
        $settings = new Settings();
        $companyName = $settings->get('company_name', 'ISP CRM');
        
        $subject = "Invoice #{$invoice['invoice_number']} from {$companyName}";
        
        $html = $this->buildInvoiceEmail($invoice, $companyName);
        
        return $this->send($recipientEmail, $subject, $html);
    }
    
    public function sendQuote(array $quote, string $recipientEmail): array {
        $settings = new Settings();
        $companyName = $settings->get('company_name', 'ISP CRM');
        
        $subject = "Quote #{$quote['quote_number']} from {$companyName}";
        
        $html = $this->buildQuoteEmail($quote, $companyName);
        
        return $this->send($recipientEmail, $subject, $html);
    }
    
    private function buildInvoiceEmail(array $invoice, string $companyName): string {
        $amount = number_format($invoice['total_amount'] ?? 0, 2);
        $dueDate = $invoice['due_date'] ?? 'N/A';
        $status = ucfirst($invoice['status'] ?? 'draft');
        $customerName = $invoice['customer_name'] ?? 'Valued Customer';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0d6efd; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .amount { font-size: 24px; font-weight: bold; color: #0d6efd; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .btn { display: inline-block; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$companyName}</h1>
                    <h2>Invoice #{$invoice['invoice_number']}</h2>
                </div>
                <div class='content'>
                    <p>Dear {$customerName},</p>
                    <p>Please find below your invoice details:</p>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Invoice Number:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>#{$invoice['invoice_number']}</td></tr>
                        <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Amount Due:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;' class='amount'>KES {$amount}</td></tr>
                        <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Due Date:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$dueDate}</td></tr>
                        <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Status:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$status}</td></tr>
                    </table>
                    <p style='margin-top: 20px;'>If you have any questions about this invoice, please contact us.</p>
                    <p>Thank you for your business!</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " {$companyName}. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function buildQuoteEmail(array $quote, string $companyName): string {
        $amount = number_format($quote['total_amount'] ?? 0, 2);
        $expiryDate = $quote['expiry_date'] ?? 'N/A';
        $status = ucfirst($quote['status'] ?? 'draft');
        $customerName = $quote['customer_name'] ?? 'Valued Customer';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #198754; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .amount { font-size: 24px; font-weight: bold; color: #198754; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$companyName}</h1>
                    <h2>Quote #{$quote['quote_number']}</h2>
                </div>
                <div class='content'>
                    <p>Dear {$customerName},</p>
                    <p>Thank you for your interest. Please find below your quote details:</p>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Quote Number:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>#{$quote['quote_number']}</td></tr>
                        <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Total Amount:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;' class='amount'>KES {$amount}</td></tr>
                        <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Valid Until:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$expiryDate}</td></tr>
                        <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Status:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$status}</td></tr>
                    </table>
                    <p style='margin-top: 20px;'>This quote is valid until {$expiryDate}. Please contact us if you have any questions or would like to proceed.</p>
                    <p>Thank you for considering our services!</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " {$companyName}. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
