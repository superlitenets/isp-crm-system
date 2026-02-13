<?php

class TicketStatusLink {
    private $pdo;
    private $tokenExpireHours = 72;
    private $maxUses = 10;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function generateToken($ticketId, $employeeId = null, $allowedStatuses = null) {
        if ($allowedStatuses === null) {
            $allowedStatuses = 'In Progress,Resolved';
        }
        
        $plainToken = bin2hex(random_bytes(32));
        $tokenLookup = substr(hash('sha256', $plainToken), 0, 32);
        $tokenHash = password_hash($plainToken, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->tokenExpireHours} hours"));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO ticket_status_tokens 
            (ticket_id, employee_id, token_hash, token_lookup, allowed_statuses, expires_at, max_uses, used_count, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, TRUE)
        ");
        $stmt->execute([$ticketId, $employeeId, $tokenHash, $tokenLookup, $allowedStatuses, $expiresAt, $this->maxUses]);
        
        return $plainToken;
    }
    
    public function validateToken($plainToken) {
        if (empty($plainToken) || strlen($plainToken) !== 64) {
            return null;
        }
        
        $tokenLookup = substr(hash('sha256', $plainToken), 0, 32);
        
        $stmt = $this->pdo->prepare("
            SELECT tst.*, t.id as ticket_exists, t.status as current_status, t.subject, t.priority,
                   c.name as customer_name, e.name as assigned_to_name
            FROM ticket_status_tokens tst
            JOIN tickets t ON tst.ticket_id = t.id
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN employees e ON t.assigned_to = e.id
            WHERE tst.token_lookup = ?
            AND tst.is_active = TRUE 
            AND tst.expires_at > NOW()
            AND tst.used_count < tst.max_uses
        ");
        $stmt->execute([$tokenLookup]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($candidates as $tokenRecord) {
            if (password_verify($plainToken, $tokenRecord['token_hash'])) {
                return $tokenRecord;
            }
        }
        
        return null;
    }
    
    public function useToken($tokenId) {
        $stmt = $this->pdo->prepare("
            UPDATE ticket_status_tokens 
            SET used_count = used_count + 1, last_used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$tokenId]);
    }
    
    public function invalidateToken($tokenId) {
        $stmt = $this->pdo->prepare("
            UPDATE ticket_status_tokens SET is_active = FALSE WHERE id = ?
        ");
        $stmt->execute([$tokenId]);
    }
    
    public function invalidateTokensForTicket($ticketId) {
        $stmt = $this->pdo->prepare("
            UPDATE ticket_status_tokens SET is_active = FALSE WHERE ticket_id = ?
        ");
        $stmt->execute([$ticketId]);
    }
    
    public function generateStatusUpdateUrl($ticketId, $employeeId = null) {
        $token = $this->generateToken($ticketId, $employeeId);
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/ticket-status.php?t=' . $token;
    }
    
    private function getBaseUrl() {
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $protocol . '://' . $_SERVER['HTTP_HOST'];
        }
        
        $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_url'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['setting_value'])) {
            return rtrim($result['setting_value'], '/');
        }
        
        return 'https://' . ($_ENV['REPLIT_DEV_DOMAIN'] ?? 'localhost');
    }
    
    public function cleanupExpiredTokens() {
        $stmt = $this->pdo->prepare("
            DELETE FROM ticket_status_tokens 
            WHERE expires_at < NOW() OR is_active = FALSE
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    public function getAllowedStatuses($tokenRecord) {
        if (empty($tokenRecord['allowed_statuses'])) {
            return ['In Progress', 'Resolved'];
        }
        return array_map('trim', explode(',', $tokenRecord['allowed_statuses']));
    }
}
