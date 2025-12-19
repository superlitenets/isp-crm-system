<?php

class CustomerTicketLink {
    private $pdo;
    private $tokenExpireHours = 168; // 7 days
    private $maxUses = 50;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function generateToken($ticketId, $customerId = null) {
        $plainToken = bin2hex(random_bytes(32));
        $tokenLookup = substr(hash('sha256', $plainToken), 0, 32);
        $tokenHash = password_hash($plainToken, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->tokenExpireHours} hours"));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO customer_ticket_tokens 
            (ticket_id, customer_id, token_hash, token_lookup, expires_at, max_uses, used_count, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 0, TRUE)
        ");
        $stmt->execute([$ticketId, $customerId, $tokenHash, $tokenLookup, $expiresAt, $this->maxUses]);
        
        return $plainToken;
    }
    
    public function validateToken($plainToken) {
        if (empty($plainToken) || strlen($plainToken) !== 64) {
            return null;
        }
        
        $tokenLookup = substr(hash('sha256', $plainToken), 0, 32);
        
        $stmt = $this->pdo->prepare("
            SELECT ctt.*, t.id as ticket_exists, t.ticket_number, t.status, t.subject, t.priority,
                   t.description, t.category, t.created_at as ticket_created, t.updated_at as ticket_updated,
                   t.resolved_at, t.satisfaction_rating as existing_rating,
                   c.name as customer_name, c.phone as customer_phone,
                   e.name as assigned_to_name, e.phone as technician_phone
            FROM customer_ticket_tokens ctt
            JOIN tickets t ON ctt.ticket_id = t.id
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN employees e ON t.assigned_to = e.id
            WHERE ctt.token_lookup = ?
            AND ctt.is_active = TRUE 
            AND ctt.expires_at > NOW()
            AND ctt.used_count < ctt.max_uses
        ");
        $stmt->execute([$tokenLookup]);
        $candidates = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($candidates as $tokenRecord) {
            if (password_verify($plainToken, $tokenRecord['token_hash'])) {
                return $tokenRecord;
            }
        }
        
        return null;
    }
    
    public function useToken($tokenId) {
        $stmt = $this->pdo->prepare("
            UPDATE customer_ticket_tokens 
            SET used_count = used_count + 1, last_used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$tokenId]);
    }
    
    public function invalidateToken($tokenId) {
        $stmt = $this->pdo->prepare("
            UPDATE customer_ticket_tokens SET is_active = FALSE WHERE id = ?
        ");
        $stmt->execute([$tokenId]);
    }
    
    public function generateViewUrl($ticketId, $customerId = null) {
        $token = $this->generateToken($ticketId, $customerId);
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/ticket-view.php?t=' . $token;
    }
    
    private function getBaseUrl() {
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $protocol . '://' . $_SERVER['HTTP_HOST'];
        }
        
        $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_url'");
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['setting_value'])) {
            return rtrim($result['setting_value'], '/');
        }
        
        return 'https://' . ($_ENV['REPLIT_DEV_DOMAIN'] ?? 'localhost');
    }
    
    public function getTicketTimeline($ticketId) {
        $stmt = $this->pdo->prepare("
            SELECT 'comment' as type, tc.comment as content, tc.created_at, u.name as author
            FROM ticket_comments tc
            LEFT JOIN users u ON tc.user_id = u.id
            WHERE tc.ticket_id = ? AND tc.is_internal = FALSE
            UNION ALL
            SELECT 'activity' as type, al.description as content, al.created_at, NULL as author
            FROM activity_logs al
            WHERE al.entity_type = 'ticket' AND al.entity_id = ?
            AND al.action IN ('status_change', 'resolved', 'closed')
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$ticketId, $ticketId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function cleanupExpiredTokens() {
        $stmt = $this->pdo->prepare("
            DELETE FROM customer_ticket_tokens 
            WHERE expires_at < NOW() OR is_active = FALSE
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
