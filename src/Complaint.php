<?php

namespace App;

use PDO;

class Complaint {
    private PDO $db;
    private SMSGateway $sms;
    private Settings $settings;
    private ActivityLog $activityLog;

    public function __construct() {
        $this->db = \Database::getConnection();
        $this->sms = new SMSGateway();
        $this->settings = new Settings();
        $this->activityLog = new ActivityLog();
    }

    public function create(array $data): int {
        $complaintNumber = 'CMP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $createdBy = $data['created_by'] ?? ($_SESSION['user_id'] ?? null);
        
        $stmt = $this->db->prepare("
            INSERT INTO complaints (
                complaint_number, customer_id, customer_name, customer_phone, 
                customer_email, customer_location, category, subject, description, 
                status, priority, source, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
            RETURNING id
        ");
        
        $stmt->execute([
            $complaintNumber,
            $data['customer_id'] ?? null,
            $data['customer_name'],
            $data['customer_phone'],
            $data['customer_email'] ?? null,
            $data['customer_location'] ?? null,
            $data['category'],
            $data['subject'],
            $data['description'],
            $data['priority'] ?? 'medium',
            $data['source'] ?? 'public',
            $createdBy
        ]);
        
        $complaintId = $stmt->fetchColumn();
        
        // Send SMS receipt to customer (non-blocking - don't fail complaint if SMS fails)
        if (!empty($data['customer_phone'])) {
            try {
                $this->sendComplaintReceivedSMS($complaintNumber, $data);
            } catch (\Throwable $e) {
                error_log("Failed to send complaint SMS: " . $e->getMessage());
            }
        }
        
        $this->activityLog->log('create', 'complaint', $complaintId, $complaintNumber, "Complaint received: " . ($data['subject'] ?? 'No subject'));
        
        return $complaintId;
    }
    
    private function sendComplaintReceivedSMS(string $complaintNumber, array $data): void {
        $template = $this->settings->get('sms_template_complaint_received', 
            'Dear {customer_name}, your complaint #{complaint_number} has been received. Category: {category}. Our team will review and respond within 24 hours.');
        
        $placeholders = [
            '{customer_name}' => $data['customer_name'] ?? 'Customer',
            '{complaint_number}' => $complaintNumber,
            '{category}' => ucfirst($data['category'] ?? 'General'),
            '{subject}' => $data['subject'] ?? '',
            '{customer_phone}' => $data['customer_phone'] ?? ''
        ];
        
        $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
        $this->sms->send($data['customer_phone'], $message);
    }

    public function getAll(array $filters = []): array {
        $sql = "
            SELECT c.*, 
                   u.name as reviewer_name,
                   t.ticket_number as converted_ticket_number
            FROM complaints c
            LEFT JOIN users u ON c.reviewed_by = u.id
            LEFT JOIN tickets t ON c.converted_ticket_id = t.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND c.category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (c.complaint_number ILIKE ? OR c.customer_name ILIKE ? OR c.customer_phone ILIKE ? OR c.subject ILIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND (c.reviewed_by = ? OR c.created_by = ?)";
            $params[] = (int)$filters['user_id'];
            $params[] = (int)$filters['user_id'];
        }
        
        $sql .= " ORDER BY c.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   u.name as reviewer_name,
                   t.ticket_number as converted_ticket_number
            FROM complaints c
            LEFT JOIN users u ON c.reviewed_by = u.id
            LEFT JOIN tickets t ON c.converted_ticket_id = t.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getPendingCount(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM complaints WHERE status = 'pending'");
        return (int)$stmt->fetchColumn();
    }

    public function approve(int $id, int $userId, ?string $notes = null): bool {
        $complaint = $this->getById($id);
        $stmt = $this->db->prepare("
            UPDATE complaints 
            SET status = 'approved', 
                reviewed_by = ?, 
                reviewed_at = CURRENT_TIMESTAMP,
                review_notes = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status = 'pending'
        ");
        $result = $stmt->execute([$userId, $notes, $id]);
        
        if ($result && $complaint) {
            $this->activityLog->log('approve', 'complaint', $id, $complaint['complaint_number'], "Complaint approved");
        }
        
        return $result;
    }

    public function reject(int $id, int $userId, string $notes): bool {
        if (empty(trim($notes))) {
            throw new \InvalidArgumentException('Rejection reason is required');
        }
        
        $complaint = $this->getById($id);
        $stmt = $this->db->prepare("
            UPDATE complaints 
            SET status = 'rejected', 
                reviewed_by = ?, 
                reviewed_at = CURRENT_TIMESTAMP,
                review_notes = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status = 'pending'
        ");
        $result = $stmt->execute([$userId, $notes, $id]);
        
        if ($result && $complaint) {
            $this->activityLog->log('reject', 'complaint', $id, $complaint['complaint_number'], "Complaint rejected: " . substr($notes, 0, 100));
        }
        
        return $result;
    }

    public function convertToTicket(int $complaintId, int $userId, ?int $assignTo = null, ?int $teamId = null): ?int {
        $complaint = $this->getById($complaintId);
        if (!$complaint) {
            error_log("Complaint not found: $complaintId");
            return null;
        }
        if ($complaint['status'] !== 'approved') {
            error_log("Complaint status is not approved: " . $complaint['status']);
            return null;
        }
        
        $this->db->beginTransaction();
        
        try {
            $customerId = $complaint['customer_id'];
            if (!$customerId) {
                $accountNumber = 'CMP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $this->db->prepare("
                    INSERT INTO customers (account_number, name, phone, email, address, service_plan) 
                    VALUES (?, ?, ?, ?, ?, ?) 
                    RETURNING id
                ");
                $stmt->execute([
                    $accountNumber,
                    $complaint['customer_name'],
                    $complaint['customer_phone'],
                    $complaint['customer_email'],
                    $complaint['customer_location'] ?: 'Not provided',
                    'Complaint'
                ]);
                $customerId = $stmt->fetchColumn();
                
                $this->db->prepare("UPDATE complaints SET customer_id = ? WHERE id = ?")
                    ->execute([$customerId, $complaintId]);
            }
            
            $ticketNumber = 'TKT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $categoryMapping = [
                'connectivity' => 'fault',
                'speed' => 'fault',
                'billing' => 'billing',
                'equipment' => 'fault',
                'service' => 'general',
                'other' => 'general'
            ];
            $ticketCategory = $categoryMapping[$complaint['category']] ?? 'general';
            
            $ticketStmt = $this->db->prepare("
                INSERT INTO tickets (
                    ticket_number, customer_id, subject, description, category, 
                    priority, status, assigned_to, team_id, source
                ) VALUES (?, ?, ?, ?, ?, ?, 'open', ?, ?, 'complaint')
                RETURNING id
            ");
            $ticketStmt->execute([
                $ticketNumber,
                $customerId,
                $complaint['subject'],
                $complaint['description'],
                $ticketCategory,
                $complaint['priority'],
                $assignTo,
                $teamId
            ]);
            $ticketId = $ticketStmt->fetchColumn();
            
            $ticket = new Ticket();
            $ticket->applySLA($ticketId, $complaint['priority']);
            
            $this->db->prepare("
                UPDATE complaints 
                SET status = 'converted', 
                    converted_ticket_id = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$ticketId, $complaintId]);
            
            $this->db->commit();
            
            $this->activityLog->log('convert', 'complaint', $complaintId, $complaint['complaint_number'], "Converted to ticket: {$ticketNumber}");
            
            return $ticketId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error converting complaint to ticket: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function updatePriority(int $id, string $priority): bool {
        $stmt = $this->db->prepare("
            UPDATE complaints 
            SET priority = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$priority, $id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM complaints WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getStats(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE status = 'pending') as pending,
                COUNT(*) FILTER (WHERE status = 'approved') as approved,
                COUNT(*) FILTER (WHERE status = 'rejected') as rejected,
                COUNT(*) FILTER (WHERE status = 'converted') as converted,
                COUNT(*) FILTER (WHERE created_at >= CURRENT_DATE) as today
            FROM complaints
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
