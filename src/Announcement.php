<?php

namespace App;

class Announcement {
    private \PDO $db;
    private SMS $sms;
    private SMSGateway $smsGateway;
    private WhatsApp $whatsapp;
    
    public function __construct(\PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
        $this->sms = new SMS();
        $this->smsGateway = new SMSGateway();
        $this->whatsapp = new WhatsApp($this->db);
    }
    
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO announcements (title, message, priority, target_audience, target_branch_id, target_team_id, send_sms, send_notification, scheduled_at, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['title'],
            $data['message'],
            $data['priority'] ?? 'normal',
            $data['target_audience'] ?? 'all',
            $data['target_branch_id'] ?: null,
            $data['target_team_id'] ?: null,
            isset($data['send_sms']) ? (bool)$data['send_sms'] : false,
            isset($data['send_notification']) ? (bool)$data['send_notification'] : true,
            $data['scheduled_at'] ?: null,
            $data['status'] ?? 'draft',
            $data['created_by'] ?? null
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    public function getAll(array $filters = []): array {
        $sql = "SELECT a.*, u.name as created_by_name, 
                       b.name as branch_name, t.name as team_name,
                       (SELECT COUNT(*) FROM announcement_recipients WHERE announcement_id = a.id) as recipient_count,
                       (SELECT COUNT(*) FROM announcement_recipients WHERE announcement_id = a.id AND notification_read = TRUE) as read_count
                FROM announcements a
                LEFT JOIN users u ON a.created_by = u.id
                LEFT JOIN branches b ON a.target_branch_id = b.id
                LEFT JOIN teams t ON a.target_team_id = t.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY a.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT a.*, u.name as created_by_name,
                   b.name as branch_name, t.name as team_name
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN branches b ON a.target_branch_id = b.id
            LEFT JOIN teams t ON a.target_team_id = t.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [];
        
        $allowedFields = ['title', 'message', 'priority', 'target_audience', 'target_branch_id', 'target_team_id', 'send_sms', 'send_notification', 'scheduled_at', 'status'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $stmt = $this->db->prepare("UPDATE announcements SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($params);
    }
    
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM announcements WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getTargetEmployees(array $announcement): array {
        $statusCondition = "(e.employment_status = 'active' OR e.employment_status = 'Active' OR e.employment_status IS NULL)";
        
        $sql = "SELECT e.id, e.name, e.phone, e.email 
                FROM employees e 
                WHERE $statusCondition";
        $params = [];
        
        if ($announcement['target_audience'] === 'branch' && $announcement['target_branch_id']) {
            $sql = "SELECT DISTINCT e.id, e.name, e.phone, e.email 
                    FROM employees e 
                    JOIN employee_branches eb ON e.id = eb.employee_id
                    WHERE $statusCondition AND eb.branch_id = ?";
            $params[] = $announcement['target_branch_id'];
        } elseif ($announcement['target_audience'] === 'team' && $announcement['target_team_id']) {
            $sql = "SELECT DISTINCT e.id, e.name, e.phone, e.email 
                    FROM employees e 
                    JOIN team_members tm ON e.id = tm.employee_id
                    WHERE $statusCondition AND tm.team_id = ?";
            $params[] = $announcement['target_team_id'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function send(int $id): array {
        $result = ['success' => false, 'sms_sent' => 0, 'whatsapp_sent' => 0, 'notifications_sent' => 0, 'errors' => []];
        
        $announcement = $this->getById($id);
        if (!$announcement) {
            $result['errors'][] = 'Announcement not found';
            return $result;
        }
        
        if ($announcement['status'] === 'sent') {
            $result['errors'][] = 'Announcement already sent';
            return $result;
        }
        
        $employees = $this->getTargetEmployees($announcement);
        
        if (empty($employees)) {
            $result['errors'][] = 'No employees found for target audience';
            return $result;
        }
        
        $this->db->beginTransaction();
        
        try {
            foreach ($employees as $employee) {
                $smsSent = false;
                $notificationSent = false;
                
                $messageText = "[" . strtoupper($announcement['priority']) . "] " . $announcement['title'] . "\n\n" . $announcement['message'];
                
                if ($announcement['send_sms'] && !empty($employee['phone'])) {
                    $smsResult = $this->smsGateway->send($employee['phone'], $messageText);
                    if ($smsResult['success'] ?? false) {
                        $smsSent = true;
                        $result['sms_sent']++;
                    } else {
                        $waResult = $this->whatsapp->sendMessage($employee['phone'], $messageText);
                        if ($waResult['success'] ?? false) {
                            $smsSent = true;
                            $result['whatsapp_sent']++;
                        }
                    }
                }
                
                if ($announcement['send_notification']) {
                    $this->createNotification($employee['id'], $announcement);
                    $notificationSent = true;
                    $result['notifications_sent']++;
                }
                
                $stmt = $this->db->prepare("
                    INSERT INTO announcement_recipients (announcement_id, employee_id, sms_sent, sms_sent_at, notification_sent)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id,
                    $employee['id'],
                    $smsSent,
                    $smsSent ? date('Y-m-d H:i:s') : null,
                    $notificationSent
                ]);
            }
            
            $this->update($id, ['status' => 'sent']);
            $stmt = $this->db->prepare("UPDATE announcements SET sent_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
            
            $this->db->commit();
            $result['success'] = true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
    
    private function createNotification(int $employeeId, array $announcement): void {
        $stmt = $this->db->prepare("SELECT user_id FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($employee && $employee['user_id']) {
            $stmt = $this->db->prepare("
                INSERT INTO user_notifications (user_id, title, message, type, link, created_at)
                VALUES (?, ?, ?, 'announcement', ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $employee['user_id'],
                $announcement['title'],
                $announcement['message'],
                '?page=hr&subpage=announcements&id=' . $announcement['id']
            ]);
        }
    }
    
    public function getRecipients(int $announcementId): array {
        $stmt = $this->db->prepare("
            SELECT ar.*, e.name as employee_name, e.phone, e.email
            FROM announcement_recipients ar
            JOIN employees e ON ar.employee_id = e.id
            WHERE ar.announcement_id = ?
            ORDER BY e.name
        ");
        $stmt->execute([$announcementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getEmployeeAnnouncements(int $employeeId, int $limit = 20): array {
        $stmt = $this->db->prepare("
            SELECT a.*, ar.notification_read, ar.notification_read_at
            FROM announcements a
            JOIN announcement_recipients ar ON a.id = ar.announcement_id
            WHERE ar.employee_id = ? AND a.status = 'sent'
            ORDER BY a.sent_at DESC
            LIMIT ?
        ");
        $stmt->execute([$employeeId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function markAsRead(int $announcementId, int $employeeId): bool {
        $stmt = $this->db->prepare("
            UPDATE announcement_recipients 
            SET notification_read = TRUE, notification_read_at = CURRENT_TIMESTAMP
            WHERE announcement_id = ? AND employee_id = ?
        ");
        return $stmt->execute([$announcementId, $employeeId]);
    }
    
    public function getUnreadCount(int $employeeId): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM announcement_recipients ar
            JOIN announcements a ON ar.announcement_id = a.id
            WHERE ar.employee_id = ? AND ar.notification_read = FALSE AND a.status = 'sent'
        ");
        $stmt->execute([$employeeId]);
        return (int)$stmt->fetchColumn();
    }
}
