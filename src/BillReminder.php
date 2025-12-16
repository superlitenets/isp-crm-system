<?php

namespace App;

class BillReminder {
    private \PDO $db;
    private SMSGateway $smsGateway;
    private Settings $settings;
    
    public function __construct(\PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
        $this->smsGateway = new SMSGateway();
        $this->settings = new Settings();
    }
    
    public function getUpcomingBills(int $days = 7): array {
        $stmt = $this->db->prepare("
            SELECT vb.*, v.name as vendor_name, v.phone as vendor_phone,
                   EXTRACT(DAY FROM vb.due_date - CURRENT_DATE) as days_until_due
            FROM vendor_bills vb
            JOIN vendors v ON vb.vendor_id = v.id
            WHERE vb.status NOT IN ('paid', 'cancelled')
              AND vb.due_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '? days'
            ORDER BY vb.due_date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getOverdueBills(): array {
        $stmt = $this->db->query("
            SELECT vb.*, v.name as vendor_name, v.phone as vendor_phone,
                   EXTRACT(DAY FROM CURRENT_DATE - vb.due_date) as days_overdue
            FROM vendor_bills vb
            JOIN vendors v ON vb.vendor_id = v.id
            WHERE vb.status NOT IN ('paid', 'cancelled')
              AND vb.due_date < CURRENT_DATE
            ORDER BY vb.due_date ASC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getBillsDueSoon(): array {
        $stmt = $this->db->query("
            SELECT vb.*, v.name as vendor_name, v.phone as vendor_phone,
                   EXTRACT(DAY FROM vb.due_date - CURRENT_DATE) as days_until_due
            FROM vendor_bills vb
            JOIN vendors v ON vb.vendor_id = v.id
            WHERE vb.status NOT IN ('paid', 'cancelled')
              AND vb.due_date >= CURRENT_DATE
              AND vb.reminder_enabled = TRUE
              AND EXTRACT(DAY FROM vb.due_date - CURRENT_DATE) <= COALESCE(vb.reminder_days_before, 3)
            ORDER BY vb.due_date ASC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function enableReminder(int $billId, int $daysBefore = 3): bool {
        $stmt = $this->db->prepare("
            UPDATE vendor_bills 
            SET reminder_enabled = TRUE, reminder_days_before = ?
            WHERE id = ?
        ");
        return $stmt->execute([$daysBefore, $billId]);
    }
    
    public function disableReminder(int $billId): bool {
        $stmt = $this->db->prepare("
            UPDATE vendor_bills SET reminder_enabled = FALSE WHERE id = ?
        ");
        return $stmt->execute([$billId]);
    }
    
    public function createReminder(int $billId, string $reminderDate): int {
        $stmt = $this->db->prepare("
            INSERT INTO bill_reminders (bill_id, reminder_date, notification_type)
            VALUES (?, ?, 'both')
        ");
        $stmt->execute([$billId, $reminderDate]);
        return (int)$this->db->lastInsertId();
    }
    
    public function sendReminders(): array {
        $result = ['sent' => 0, 'failed' => 0, 'errors' => []];
        
        $bills = $this->getBillsDueSoon();
        $admins = $this->getAdminUsers();
        
        foreach ($bills as $bill) {
            $daysText = (int)$bill['days_until_due'] === 0 ? 'today' : 'in ' . (int)$bill['days_until_due'] . ' day(s)';
            $message = "BILL REMINDER: " . $bill['vendor_name'] . " - " . $bill['bill_number'] . 
                      "\nAmount: " . $bill['currency'] . ' ' . number_format($bill['balance_due'], 2) .
                      "\nDue: " . $daysText;
            
            foreach ($admins as $admin) {
                if (!empty($admin['phone'])) {
                    $smsResult = $this->smsGateway->send($admin['phone'], $message);
                    if ($smsResult['success'] ?? false) {
                        $result['sent']++;
                    } else {
                        $result['failed']++;
                        $result['errors'][] = "Failed to send to " . $admin['name'];
                    }
                }
                
                $this->createAdminNotification($admin['id'], $bill);
            }
            
            $stmt = $this->db->prepare("
                UPDATE vendor_bills 
                SET last_reminder_sent = CURRENT_TIMESTAMP, reminder_count = reminder_count + 1
                WHERE id = ?
            ");
            $stmt->execute([$bill['id']]);
        }
        
        return $result;
    }
    
    private function getAdminUsers(): array {
        $stmt = $this->db->query("
            SELECT u.id, u.name, e.phone
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE u.role IN ('admin', 'super_admin')
              AND u.is_active = TRUE
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function createAdminNotification(int $userId, array $bill): void {
        $daysText = (int)$bill['days_until_due'] === 0 ? 'due today' : 'due in ' . (int)$bill['days_until_due'] . ' day(s)';
        $stmt = $this->db->prepare("
            INSERT INTO user_notifications (user_id, title, message, type, link, created_at)
            VALUES (?, ?, ?, 'bill_reminder', ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $userId,
            'Bill Reminder: ' . $bill['vendor_name'],
            'Bill ' . $bill['bill_number'] . ' is ' . $daysText . '. Amount: ' . $bill['currency'] . ' ' . number_format($bill['balance_due'], 2),
            '?page=accounting&subpage=bills&action=view&id=' . $bill['id']
        ]);
    }
    
    public function getDashboardStats(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(CASE WHEN due_date < CURRENT_DATE AND status NOT IN ('paid', 'cancelled') THEN 1 END) as overdue_count,
                COALESCE(SUM(CASE WHEN due_date < CURRENT_DATE AND status NOT IN ('paid', 'cancelled') THEN balance_due END), 0) as overdue_amount,
                COUNT(CASE WHEN due_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days' AND status NOT IN ('paid', 'cancelled') THEN 1 END) as upcoming_count,
                COALESCE(SUM(CASE WHEN due_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days' AND status NOT IN ('paid', 'cancelled') THEN balance_due END), 0) as upcoming_amount
            FROM vendor_bills
        ");
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'overdue_count' => 0,
            'overdue_amount' => 0,
            'upcoming_count' => 0,
            'upcoming_amount' => 0
        ];
    }
    
    public function getRemindersForBill(int $billId): array {
        $stmt = $this->db->prepare("
            SELECT br.*, u.name as sent_to_name
            FROM bill_reminders br
            LEFT JOIN users u ON br.sent_to = u.id
            WHERE br.bill_id = ?
            ORDER BY br.reminder_date DESC
        ");
        $stmt->execute([$billId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
