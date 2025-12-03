<?php

namespace App;

class Ticket {
    private \PDO $db;
    private SMSGateway $sms;

    public function __construct() {
        $this->db = \Database::getConnection();
        $this->sms = new SMSGateway();
    }

    public function generateTicketNumber(): string {
        return 'TKT-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data): int {
        $ticketNumber = $this->generateTicketNumber();
        
        $stmt = $this->db->prepare("
            INSERT INTO tickets (ticket_number, customer_id, assigned_to, subject, description, category, priority, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $ticketNumber,
            $data['customer_id'],
            $data['assigned_to'] ?? null,
            $data['subject'],
            $data['description'],
            $data['category'],
            $data['priority'] ?? 'medium',
            'open'
        ]);

        $ticketId = (int) $this->db->lastInsertId();

        $customer = (new Customer())->find($data['customer_id']);
        if ($customer && $customer['phone']) {
            $result = $this->sms->notifyCustomer(
                $customer['phone'],
                $ticketNumber,
                'Created',
                'Your support ticket has been received. We will contact you shortly.'
            );
            $this->sms->logSMS($ticketId, $customer['phone'], 'customer', 'Ticket created notification', $result['success'] ? 'sent' : 'failed');
        }

        if (!empty($data['assigned_to'])) {
            $this->notifyAssignedTechnician($ticketId, $data['assigned_to']);
        }

        return $ticketId;
    }

    public function update(int $id, array $data): bool {
        $ticket = $this->find($id);
        if (!$ticket) {
            return false;
        }

        $fields = [];
        $values = [];
        
        foreach (['subject', 'description', 'category', 'priority', 'status', 'assigned_to'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }

        if (isset($data['status']) && $data['status'] === 'resolved' && $ticket['status'] !== 'resolved') {
            $fields[] = "resolved_at = CURRENT_TIMESTAMP";
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $id;
        
        $stmt = $this->db->prepare("UPDATE tickets SET " . implode(', ', $fields) . " WHERE id = ?");
        $result = $stmt->execute($values);

        if ($result && isset($data['status']) && $data['status'] !== $ticket['status']) {
            $customer = (new Customer())->find($ticket['customer_id']);
            if ($customer && $customer['phone']) {
                $statusMessage = $this->getStatusMessage($data['status']);
                $smsResult = $this->sms->notifyCustomer(
                    $customer['phone'],
                    $ticket['ticket_number'],
                    ucfirst($data['status']),
                    $statusMessage
                );
                $this->sms->logSMS($id, $customer['phone'], 'customer', "Status update: {$data['status']}", $smsResult['success'] ? 'sent' : 'failed');
            }
        }

        if ($result && isset($data['assigned_to']) && $data['assigned_to'] != $ticket['assigned_to']) {
            $this->notifyAssignedTechnician($id, $data['assigned_to']);
        }

        return $result;
    }

    private function notifyAssignedTechnician(int $ticketId, int $technicianId): void {
        $ticket = $this->find($ticketId);
        $technician = $this->getUser($technicianId);
        $customer = (new Customer())->find($ticket['customer_id']);

        if ($technician && $technician['phone'] && $customer) {
            $result = $this->sms->notifyTechnician(
                $technician['phone'],
                $ticket['ticket_number'],
                $customer['name'],
                $ticket['subject']
            );
            $this->sms->logSMS($ticketId, $technician['phone'], 'technician', 'Ticket assignment notification', $result['success'] ? 'sent' : 'failed');
        }
    }

    private function getStatusMessage(string $status): string {
        return match($status) {
            'in_progress' => 'A technician is now working on your issue.',
            'resolved' => 'Your issue has been resolved. Thank you for your patience.',
            'closed' => 'Your ticket has been closed.',
            'pending' => 'Your ticket is pending further information.',
            default => 'Your ticket status has been updated.'
        };
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM tickets WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT t.*, c.name as customer_name, c.phone as customer_phone, c.account_number,
                   u.name as assigned_name, u.phone as assigned_phone
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getAll(array $filters = [], int $limit = 50, int $offset = 0): array {
        $sql = "
            SELECT t.*, c.name as customer_name, c.account_number,
                   u.name as assigned_name
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND t.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (t.ticket_number ILIKE ? OR t.subject ILIKE ? OR c.name ILIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY 
            CASE t.priority 
                WHEN 'critical' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            t.created_at DESC
            LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int {
        $sql = "SELECT COUNT(*) FROM tickets t LEFT JOIN customers c ON t.customer_id = c.id WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND t.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (t.ticket_number ILIKE ? OR t.subject ILIKE ? OR c.name ILIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE status = 'open') as open,
                COUNT(*) FILTER (WHERE status = 'in_progress') as in_progress,
                COUNT(*) FILTER (WHERE status = 'resolved') as resolved,
                COUNT(*) FILTER (WHERE status = 'closed') as closed,
                COUNT(*) FILTER (WHERE priority = 'critical') as critical,
                COUNT(*) FILTER (WHERE priority = 'high') as high
            FROM tickets
        ");
        return $stmt->fetch();
    }

    public function addComment(int $ticketId, int $userId, string $comment, bool $isInternal = false): int {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$ticketId, $userId, $comment, $isInternal]);
        return (int) $this->db->lastInsertId();
    }

    public function getComments(int $ticketId): array {
        $stmt = $this->db->prepare("
            SELECT tc.*, u.name as user_name
            FROM ticket_comments tc
            LEFT JOIN users u ON tc.user_id = u.id
            WHERE tc.ticket_id = ?
            ORDER BY tc.created_at ASC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public function getUser(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getAllUsers(): array {
        $stmt = $this->db->query("SELECT * FROM users ORDER BY name");
        return $stmt->fetchAll();
    }

    public function getCategories(): array {
        return [
            'connectivity' => 'Connectivity Issue',
            'slow_speed' => 'Slow Speed',
            'installation' => 'New Installation',
            'billing' => 'Billing Inquiry',
            'equipment' => 'Equipment Problem',
            'outage' => 'Service Outage',
            'upgrade' => 'Plan Upgrade',
            'other' => 'Other'
        ];
    }

    public function getPriorities(): array {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical'
        ];
    }

    public function getStatuses(): array {
        return [
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'pending' => 'Pending',
            'resolved' => 'Resolved',
            'closed' => 'Closed'
        ];
    }
}
