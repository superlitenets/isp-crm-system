<?php

namespace App;

class Ticket {
    private \PDO $db;
    private SMSGateway $sms;
    private WhatsApp $whatsapp;
    private ?SLA $sla = null;
    private Settings $settings;
    private ActivityLog $activityLog;

    public function __construct() {
        $this->db = \Database::getConnection();
        $this->sms = new SMSGateway();
        $this->whatsapp = new WhatsApp();
        $this->settings = new Settings();
        $this->activityLog = new ActivityLog();
    }

    private function getSLA(): SLA {
        if ($this->sla === null) {
            $this->sla = new SLA();
        }
        return $this->sla;
    }

    public function generateTicketNumber(): string {
        return 'TKT-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data): int {
        $ticketNumber = $this->generateTicketNumber();
        
        $assignedTo = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null;
        $teamId = !empty($data['team_id']) ? (int)$data['team_id'] : null;
        $priority = $data['priority'] ?? 'medium';
        $createdBy = $data['created_by'] ?? ($_SESSION['user_id'] ?? null);
        
        $slaData = $this->getSLA()->calculateSLAForTicket($priority);
        
        $stmt = $this->db->prepare("
            INSERT INTO tickets (ticket_number, customer_id, assigned_to, team_id, subject, description, category, priority, status, sla_policy_id, sla_response_due, sla_resolution_due, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $ticketNumber,
            $data['customer_id'],
            $assignedTo,
            $teamId,
            $data['subject'],
            $data['description'],
            $data['category'],
            $priority,
            'open',
            $slaData['policy_id'],
            $slaData['response_due'] ? $slaData['response_due']->format('Y-m-d H:i:s') : null,
            $slaData['resolution_due'] ? $slaData['resolution_due']->format('Y-m-d H:i:s') : null,
            $createdBy
        ]);

        $ticketId = (int) $this->db->lastInsertId();
        
        if ($slaData['policy_id']) {
            $this->getSLA()->logSLAEvent($ticketId, 'sla_assigned', "SLA policy applied based on {$priority} priority");
        }

        $customer = (new Customer())->find($data['customer_id']);
        if ($customer && $customer['phone']) {
            $placeholders = [
                '{ticket_number}' => $ticketNumber,
                '{subject}' => $data['subject'] ?? '',
                '{description}' => substr($data['description'] ?? '', 0, 100),
                '{status}' => 'Open',
                '{category}' => ucfirst($data['category'] ?? ''),
                '{priority}' => ucfirst($priority),
                '{customer_name}' => $customer['name'] ?? 'Customer',
                '{customer_phone}' => $customer['phone'] ?? '',
                '{customer_address}' => $customer['address'] ?? '',
                '{customer_email}' => $customer['email'] ?? ''
            ];
            $message = $this->buildSMSFromTemplate('sms_template_ticket_created', $placeholders);
            
            $result = $this->sms->send($customer['phone'], $message);
            $this->sms->logSMS($ticketId, $customer['phone'], 'customer', 'Ticket created notification', $result['success'] ? 'sent' : 'failed');
            
            $waMessage = $this->buildSMSFromTemplate('wa_template_ticket_created', $placeholders);
            if (empty(trim(str_replace(array_keys($placeholders), '', $waMessage)))) {
                $waMessage = $message;
            }
            try {
                $waResult = $this->whatsapp->send($customer['phone'], $waMessage);
                if ($waResult['success']) {
                    $this->whatsapp->logMessage($ticketId, null, null, $customer['phone'], 'customer', $waMessage, 'sent', 'ticket_created');
                }
            } catch (\Throwable $e) {
                error_log("WhatsApp notification failed for ticket $ticketId: " . $e->getMessage());
            }
        }

        if ($assignedTo) {
            $this->notifyAssignedTechnician($ticketId, $assignedTo);
        }

        if ($teamId) {
            $this->notifyTeamMembers($ticketId, $teamId);
        }

        $this->activityLog->log('create', 'ticket', $ticketId, $ticketNumber, "Created ticket: {$data['subject']}");
        
        if ($assignedTo) {
            $user = $this->getUser($assignedTo);
            $this->activityLog->log('assign', 'ticket', $ticketId, $ticketNumber, "Assigned to: " . ($user['name'] ?? 'Unknown'));
        }

        return $ticketId;
    }

    public function applySLA(int $ticketId, string $priority): bool {
        $slaData = $this->getSLA()->calculateSLAForTicket($priority);
        
        if (!$slaData['policy_id']) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            UPDATE tickets 
            SET sla_policy_id = ?,
                sla_response_due = ?,
                sla_resolution_due = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $slaData['policy_id'],
            $slaData['response_due'] ? $slaData['response_due']->format('Y-m-d H:i:s') : null,
            $slaData['resolution_due'] ? $slaData['resolution_due']->format('Y-m-d H:i:s') : null,
            $ticketId
        ]);
        
        $this->getSLA()->logSLAEvent($ticketId, 'sla_assigned', "SLA policy applied based on {$priority} priority");
        
        return true;
    }

    public function update(int $id, array $data): bool {
        $ticket = $this->find($id);
        if (!$ticket) {
            return false;
        }

        $fields = [];
        $values = [];
        
        foreach (['subject', 'description', 'category', 'priority', 'status', 'assigned_to', 'team_id'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field] === '' ? null : $data[$field];
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

        // Post-update operations wrapped in try-catch to prevent blocking the update response
        if ($result) {
            try {
                if (isset($data['status']) && $data['status'] !== $ticket['status']) {
                    $customer = (new Customer())->find($ticket['customer_id']);
                    $technician = $ticket['assigned_to'] ? $this->getUser($ticket['assigned_to']) : null;
                    if ($customer && $customer['phone']) {
                        $statusMessage = $this->getStatusMessage($data['status']);
                        $placeholders = [
                            '{ticket_number}' => $ticket['ticket_number'],
                            '{subject}' => $ticket['subject'] ?? '',
                            '{description}' => substr($ticket['description'] ?? '', 0, 100),
                            '{status}' => ucfirst($data['status']),
                            '{message}' => $statusMessage,
                            '{category}' => ucfirst($ticket['category'] ?? ''),
                            '{priority}' => ucfirst($ticket['priority'] ?? 'medium'),
                            '{customer_name}' => $customer['name'] ?? 'Customer',
                            '{customer_phone}' => $customer['phone'] ?? '',
                            '{technician_name}' => $technician['name'] ?? '',
                            '{technician_phone}' => $technician['phone'] ?? ''
                        ];
                        
                        $templateKey = $data['status'] === 'resolved' ? 'sms_template_ticket_resolved' : 'sms_template_ticket_updated';
                        $message = $this->buildSMSFromTemplate($templateKey, $placeholders);
                        $smsResult = $this->sms->send($customer['phone'], $message);
                        $this->sms->logSMS($id, $customer['phone'], 'customer', "Status update: {$data['status']}", $smsResult['success'] ? 'sent' : 'failed');
                        
                        $waTemplateKey = $data['status'] === 'resolved' ? 'wa_template_resolved' : 'wa_template_status_update';
                        $waMessage = $this->buildSMSFromTemplate($waTemplateKey, $placeholders);
                        try {
                            $waResult = $this->whatsapp->send($customer['phone'], $waMessage);
                            if ($waResult['success']) {
                                $this->whatsapp->logMessage($id, null, null, $customer['phone'], 'customer', $waMessage, 'sent', 'status_update');
                            }
                        } catch (\Exception $e) {
                            error_log("WhatsApp notification failed for ticket $id status update: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log("Failed to send ticket status SMS: " . $e->getMessage());
            }

            try {
                if (isset($data['assigned_to']) && $data['assigned_to'] != $ticket['assigned_to']) {
                    $this->notifyAssignedTechnician($id, $data['assigned_to']);
                }
            } catch (\Throwable $e) {
                error_log("Failed to notify technician: " . $e->getMessage());
            }

            try {
                if (isset($data['team_id']) && $data['team_id'] != $ticket['team_id'] && !empty($data['team_id'])) {
                    $this->notifyTeamMembers($id, (int)$data['team_id']);
                }
            } catch (\Throwable $e) {
                error_log("Failed to notify team: " . $e->getMessage());
            }

            try {
                if (isset($data['status']) && $data['status'] !== $ticket['status']) {
                    $this->activityLog->log('update', 'ticket', $id, $ticket['ticket_number'], "Status changed to: " . ucfirst($data['status']));
                }
                if (isset($data['assigned_to']) && $data['assigned_to'] != $ticket['assigned_to']) {
                    $user = $this->getUser($data['assigned_to']);
                    $this->activityLog->log('assign', 'ticket', $id, $ticket['ticket_number'], "Assigned to: " . ($user['name'] ?? 'Unassigned'));
                }
                if (isset($data['priority']) && $data['priority'] !== $ticket['priority']) {
                    $this->activityLog->log('update', 'ticket', $id, $ticket['ticket_number'], "Priority changed to: " . ucfirst($data['priority']));
                }
            } catch (\Throwable $e) {
                error_log("Failed to log activity: " . $e->getMessage());
            }
        }

        return $result;
    }

    private function notifyAssignedTechnician(int $ticketId, int $technicianId): void {
        $ticket = $this->find($ticketId);
        $technician = $this->getUser($technicianId);
        $customer = (new Customer())->find($ticket['customer_id']);

        if ($technician && $technician['phone'] && $customer) {
            $placeholders = [
                '{ticket_number}' => $ticket['ticket_number'],
                '{subject}' => $ticket['subject'] ?? '',
                '{description}' => substr($ticket['description'] ?? '', 0, 100),
                '{category}' => ucfirst($ticket['category'] ?? ''),
                '{priority}' => ucfirst($ticket['priority'] ?? 'medium'),
                '{customer_name}' => $customer['name'] ?? 'Customer',
                '{customer_phone}' => $customer['phone'] ?? '',
                '{customer_address}' => $customer['address'] ?? '',
                '{customer_email}' => $customer['email'] ?? '',
                '{technician_name}' => $technician['name'] ?? 'Technician',
                '{technician_phone}' => $technician['phone'] ?? ''
            ];
            
            $message = $this->buildSMSFromTemplate('sms_template_technician_assigned', $placeholders);
            $result = $this->sms->send($technician['phone'], $message);
            $this->sms->logSMS($ticketId, $technician['phone'], 'technician', 'Ticket assignment notification', $result['success'] ? 'sent' : 'failed');
            
            $waMessage = $this->buildSMSFromTemplate('wa_template_technician_assigned', $placeholders);
            if (empty(trim(str_replace(array_keys($placeholders), '', $waMessage)))) {
                $waMessage = $message;
            }
            try {
                $waResult = $this->whatsapp->send($technician['phone'], $waMessage);
                if ($waResult['success']) {
                    $this->whatsapp->logMessage($ticketId, null, null, $technician['phone'], 'technician', $waMessage, 'sent', 'technician_assigned');
                }
            } catch (\Throwable $e) {
                error_log("WhatsApp notification failed for technician on ticket $ticketId: " . $e->getMessage());
            }
            
            if ($customer['phone']) {
                $customerPlaceholders = [
                    '{ticket_number}' => $ticket['ticket_number'],
                    '{subject}' => $ticket['subject'] ?? '',
                    '{description}' => substr($ticket['description'] ?? '', 0, 100),
                    '{category}' => ucfirst($ticket['category'] ?? ''),
                    '{priority}' => ucfirst($ticket['priority'] ?? 'medium'),
                    '{customer_name}' => $customer['name'] ?? 'Customer',
                    '{customer_phone}' => $customer['phone'] ?? '',
                    '{technician_name}' => $technician['name'] ?? 'Technician',
                    '{technician_phone}' => $technician['phone'] ?? ''
                ];
                
                $customerMessage = $this->buildSMSFromTemplate('sms_template_ticket_assigned', $customerPlaceholders);
                $customerResult = $this->sms->send($customer['phone'], $customerMessage);
                $this->sms->logSMS($ticketId, $customer['phone'], 'customer', 'Technician assignment notification', $customerResult['success'] ? 'sent' : 'failed');
                
                $waCustomerMessage = $this->buildSMSFromTemplate('wa_template_ticket_assigned', $customerPlaceholders);
                if (empty(trim(str_replace(array_keys($customerPlaceholders), '', $waCustomerMessage)))) {
                    $waCustomerMessage = $customerMessage;
                }
                try {
                    $waResult = $this->whatsapp->send($customer['phone'], $waCustomerMessage);
                    if ($waResult['success']) {
                        $this->whatsapp->logMessage($ticketId, null, null, $customer['phone'], 'customer', $waCustomerMessage, 'sent', 'ticket_assigned');
                    }
                } catch (\Throwable $e) {
                    error_log("WhatsApp notification failed for customer on ticket $ticketId: " . $e->getMessage());
                }
            }
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
    
    private function buildSMSFromTemplate(string $templateKey, array $placeholders): string {
        $defaults = [
            'sms_template_ticket_created' => 'ISP Support - Ticket #{ticket_number} created. Subject: {subject}. Status: {status}. We will contact you shortly.',
            'sms_template_ticket_updated' => 'ISP Support - Ticket #{ticket_number} Status: {status}. {message}',
            'sms_template_ticket_resolved' => 'ISP Support - Ticket #{ticket_number} has been RESOLVED. Thank you for your patience.',
            'sms_template_ticket_assigned' => 'ISP Support - Technician {technician_name} ({technician_phone}) has been assigned to your ticket #{ticket_number}.',
            'sms_template_technician_assigned' => 'New Ticket #{ticket_number} assigned to you. Customer: {customer_name} ({customer_phone}). Subject: {subject}. Priority: {priority}. Address: {customer_address}',
            'wa_template_ticket_created' => "Hi {customer_name},\n\nYour support ticket #{ticket_number} has been created.\n\nSubject: {subject}\nStatus: {status}\n\nWe will contact you shortly.\n\nThank you!",
            'wa_template_ticket_assigned' => "Hi {customer_name},\n\nTechnician {technician_name} ({technician_phone}) has been assigned to your ticket #{ticket_number}.\n\nThey will contact you soon.\n\nThank you!",
            'wa_template_technician_assigned' => "New Ticket #{ticket_number} assigned to you.\n\nCustomer: {customer_name}\nPhone: {customer_phone}\nSubject: {subject}\nPriority: {priority}\nAddress: {customer_address}",
            'wa_template_status_update' => "Hi {customer_name},\n\nThis is an update on your ticket #{ticket_number}.\n\nCurrent Status: {status}\n\nWe're working on resolving your issue. Thank you for your patience.",
            'wa_template_resolved' => "Hi {customer_name},\n\nGreat news! Your ticket #{ticket_number} has been resolved.\n\nIf you have any further questions or issues, please don't hesitate to contact us.\n\nThank you for choosing our services!"
        ];
        
        $placeholders['{company_name}'] = $this->settings->get('company_name', 'ISP Support');
        
        $template = $this->settings->get($templateKey, $defaults[$templateKey] ?? 'ISP Support - Ticket #{ticket_number} - {status}');
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM tickets WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT t.*, c.name as customer_name, c.phone as customer_phone, c.account_number,
                   u.name as assigned_name, u.phone as assigned_phone,
                   tm.name as team_name, sp.name as sla_policy_name,
                   sp.response_time_hours, sp.resolution_time_hours
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN teams tm ON t.team_id = tm.id
            LEFT JOIN sla_policies sp ON t.sla_policy_id = sp.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function recordFirstResponse(int $ticketId): bool {
        $ticket = $this->find($ticketId);
        if (!$ticket || $ticket['first_response_at']) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            UPDATE tickets SET first_response_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND first_response_at IS NULL
        ");
        $result = $stmt->execute([$ticketId]);
        
        if ($result && $ticket['sla_response_due']) {
            $now = new \DateTime();
            $responseDue = new \DateTime($ticket['sla_response_due']);
            $breached = $now > $responseDue;
            
            if ($breached) {
                $this->db->prepare("UPDATE tickets SET sla_response_breached = TRUE WHERE id = ?")->execute([$ticketId]);
                $this->getSLA()->logSLAEvent($ticketId, 'response_breached', 'Response SLA was breached');
            } else {
                $this->getSLA()->logSLAEvent($ticketId, 'response_met', 'Response SLA was met');
            }
        }
        
        return $result;
    }
    
    public function checkAndUpdateSLABreaches(): array {
        $now = date('Y-m-d H:i:s');
        
        $responseBreached = $this->db->prepare("
            UPDATE tickets SET sla_response_breached = TRUE, updated_at = CURRENT_TIMESTAMP
            WHERE sla_response_due < ? 
            AND first_response_at IS NULL 
            AND sla_response_breached = FALSE
            AND status NOT IN ('resolved', 'closed')
            RETURNING id
        ");
        $responseBreached->execute([$now]);
        $responseIds = $responseBreached->fetchAll(\PDO::FETCH_COLUMN);
        
        $resolutionBreached = $this->db->prepare("
            UPDATE tickets SET sla_resolution_breached = TRUE, updated_at = CURRENT_TIMESTAMP
            WHERE sla_resolution_due < ? 
            AND sla_resolution_breached = FALSE
            AND status NOT IN ('resolved', 'closed')
            RETURNING id
        ");
        $resolutionBreached->execute([$now]);
        $resolutionIds = $resolutionBreached->fetchAll(\PDO::FETCH_COLUMN);
        
        foreach ($responseIds as $ticketId) {
            $this->getSLA()->logSLAEvent($ticketId, 'response_breached', 'Response SLA breached automatically');
        }
        
        foreach ($resolutionIds as $ticketId) {
            $this->getSLA()->logSLAEvent($ticketId, 'resolution_breached', 'Resolution SLA breached automatically');
        }
        
        return [
            'response_breached' => count($responseIds),
            'resolution_breached' => count($resolutionIds)
        ];
    }
    
    public function getSLAStatus(int $ticketId): array {
        $ticket = $this->find($ticketId);
        if (!$ticket) {
            return ['response' => ['status' => 'n/a'], 'resolution' => ['status' => 'n/a']];
        }
        return $this->getSLA()->getSLAStatus($ticket);
    }

    public function getAll(array $filters = [], int $limit = 50, int $offset = 0): array {
        $sql = "
            SELECT t.*, c.name as customer_name, c.account_number,
                   u.name as assigned_name, tm.name as team_name,
                   sp.name as sla_policy_name
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN teams tm ON t.team_id = tm.id
            LEFT JOIN sla_policies sp ON t.sla_policy_id = sp.id
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
        
        if (!empty($filters['team_id'])) {
            $sql .= " AND t.team_id = ?";
            $params[] = $filters['team_id'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (t.ticket_number ILIKE ? OR t.subject ILIKE ? OR c.name ILIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND (t.assigned_to = ? OR t.created_by = ?)";
            $params[] = (int)$filters['user_id'];
            $params[] = (int)$filters['user_id'];
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
        
        if (!empty($filters['team_id'])) {
            $sql .= " AND t.team_id = ?";
            $params[] = $filters['team_id'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (t.ticket_number ILIKE ? OR t.subject ILIKE ? OR c.name ILIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND (t.assigned_to = ? OR t.created_by = ?)";
            $params[] = (int)$filters['user_id'];
            $params[] = (int)$filters['user_id'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(?int $userId = null): array {
        $sql = "
            SELECT 
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE status = 'open') as open,
                COUNT(*) FILTER (WHERE status = 'in_progress') as in_progress,
                COUNT(*) FILTER (WHERE status = 'resolved') as resolved,
                COUNT(*) FILTER (WHERE status = 'closed') as closed,
                COUNT(*) FILTER (WHERE priority = 'critical') as critical,
                COUNT(*) FILTER (WHERE priority = 'high') as high
            FROM tickets
        ";
        
        $params = [];
        if ($userId !== null) {
            $sql .= " WHERE (assigned_to = ? OR created_by = ?)";
            $params = [$userId, $userId];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function addComment(int $ticketId, int $userId, string $comment, bool $isInternal = false): int {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$ticketId, $userId, $comment, $isInternal]);
        $commentId = (int) $this->db->lastInsertId();
        
        if (!$isInternal) {
            $this->recordFirstResponse($ticketId);
        }
        
        return $commentId;
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
            'speed' => 'Speed Issue',
            'installation' => 'New Installation',
            'billing' => 'Billing Inquiry',
            'equipment' => 'Equipment Problem',
            'outage' => 'Service Outage',
            'service' => 'Service Quality',
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

    public function getAllTeams(): array {
        $stmt = $this->db->query("SELECT * FROM teams WHERE is_active = true ORDER BY name");
        return $stmt->fetchAll();
    }

    public function getTeam(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function createTeam(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO teams (name, description, leader_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['leader_id'] ?? null
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateTeam(int $id, array $data): bool {
        $fields = [];
        $values = [];
        
        foreach (['name', 'description', 'leader_id', 'is_active'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field] === '' ? null : $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $id;
        
        $stmt = $this->db->prepare("UPDATE teams SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    public function deleteTeam(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM teams WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getTeamMembers(int $teamId): array {
        $stmt = $this->db->prepare("
            SELECT e.*, tm.joined_at, u.name as user_name
            FROM team_members tm
            JOIN employees e ON tm.employee_id = e.id
            LEFT JOIN users u ON e.user_id = u.id
            WHERE tm.team_id = ?
            ORDER BY e.name
        ");
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }

    public function addTeamMember(int $teamId, int $employeeId): bool {
        $stmt = $this->db->prepare("
            INSERT INTO team_members (team_id, employee_id)
            VALUES (?, ?)
            ON CONFLICT (team_id, employee_id) DO NOTHING
        ");
        return $stmt->execute([$teamId, $employeeId]);
    }

    public function removeTeamMember(int $teamId, int $employeeId): bool {
        $stmt = $this->db->prepare("DELETE FROM team_members WHERE team_id = ? AND employee_id = ?");
        return $stmt->execute([$teamId, $employeeId]);
    }

    public function notifyTeamMembers(int $ticketId, int $teamId): void {
        $ticket = $this->find($ticketId);
        $members = $this->getTeamMembers($teamId);
        $customer = (new Customer())->find($ticket['customer_id']);

        foreach ($members as $member) {
            if ($member['phone'] && $customer) {
                $message = $this->buildSMSFromTemplate('sms_template_technician_assigned', [
                    '{ticket_number}' => $ticket['ticket_number'],
                    '{customer_name}' => $customer['name'] ?? 'Customer',
                    '{customer_phone}' => $customer['phone'] ?? '',
                    '{customer_address}' => $customer['address'] ?? '',
                    '{subject}' => $ticket['subject'] ?? '',
                    '{category}' => $ticket['category'] ?? '',
                    '{priority}' => ucfirst($ticket['priority'] ?? 'medium'),
                    '{technician_name}' => $member['user_name'] ?? $member['name'] ?? 'Team Member'
                ]);
                $result = $this->sms->send($member['phone'], $message);
                $this->sms->logSMS($ticketId, $member['phone'], 'team_member', 'Team assignment notification', $result['success'] ? 'sent' : 'failed');
            }
        }
    }
}
