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
        $branchId = !empty($data['branch_id']) ? (int)$data['branch_id'] : null;
        $priority = $data['priority'] ?? 'medium';
        $createdBy = $data['created_by'] ?? ($_SESSION['user_id'] ?? null);
        
        $slaData = $this->getSLA()->calculateSLAForTicket($priority);
        
        $stmt = $this->db->prepare("
            INSERT INTO tickets (ticket_number, customer_id, assigned_to, team_id, branch_id, subject, description, category, priority, status, sla_policy_id, sla_response_due, sla_resolution_due, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $ticketNumber,
            $data['customer_id'],
            $assignedTo,
            $teamId,
            $branchId,
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
            $viewLink = '';
            try {
                require_once __DIR__ . '/CustomerTicketLink.php';
                $customerLinkService = new \CustomerTicketLink($this->db);
                $viewLink = $customerLinkService->generateViewUrl($ticketId, $data['customer_id']);
            } catch (\Throwable $e) {
                error_log("Failed to generate customer view link for ticket $ticketId: " . $e->getMessage());
            }
            
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
                '{customer_email}' => $customer['email'] ?? '',
                '{view_link}' => $viewLink
            ];
            $message = $this->buildSMSFromTemplate('sms_template_ticket_created', $placeholders);
            if (!empty($viewLink) && strpos($message, $viewLink) === false) {
                $message .= " Track: " . $viewLink;
            }
            
            $result = $this->sms->send($customer['phone'], $message);
            $this->sms->logSMS($ticketId, $customer['phone'], 'customer', 'Ticket created notification', $result['success'] ? 'sent' : 'failed');
            
            $waMessage = $this->buildSMSFromTemplate('wa_template_ticket_created', $placeholders);
            if (empty(trim(str_replace(array_keys($placeholders), '', $waMessage)))) {
                $waMessage = $message;
            }
            if (!empty($viewLink) && strpos($waMessage, $viewLink) === false) {
                $waMessage .= "\n\nTrack your ticket: " . $viewLink;
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
        
        if ($branchId) {
            try {
                $this->notifyBranchWhatsAppGroup($ticketId, $branchId);
            } catch (\Throwable $e) {
                error_log("Failed to notify branch WhatsApp group: " . $e->getMessage());
            }
        }

        try {
            $this->activityLog->log('create', 'ticket', $ticketId, $ticketNumber, "Created ticket: {$data['subject']}");
            
            if ($assignedTo) {
                $user = $this->getUser($assignedTo);
                $this->activityLog->log('assign', 'ticket', $ticketId, $ticketNumber, "Assigned to: " . ($user['name'] ?? 'Unknown'));
            }
        } catch (\Throwable $e) {
            error_log("Activity log failed for ticket $ticketId: " . $e->getMessage());
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
        
        foreach (['subject', 'description', 'category', 'priority', 'status', 'assigned_to', 'team_id', 'branch_id'] as $field) {
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
                $assignmentChanged = (isset($data['assigned_to']) && $data['assigned_to'] != $ticket['assigned_to']) 
                    || (isset($data['team_id']) && $data['team_id'] != $ticket['team_id']);
                $branchId = $data['branch_id'] ?? $ticket['branch_id'];
                if ($assignmentChanged && $branchId) {
                    $this->notifyBranchWhatsAppGroup($id, (int)$branchId);
                }
            } catch (\Throwable $e) {
                error_log("Failed to notify branch WhatsApp group: " . $e->getMessage());
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

            try {
                if (isset($data['status']) && in_array($data['status'], ['resolved', 'closed']) && !in_array($ticket['status'], ['resolved', 'closed'])) {
                    $ticketCommission = new TicketCommission($this->db);
                    $commissionResult = $ticketCommission->processTicketClosure($id);
                    if ($commissionResult['success']) {
                        $this->activityLog->log('commission', 'ticket', $id, $ticket['ticket_number'], $commissionResult['message']);
                    }
                }
            } catch (\Throwable $e) {
                error_log("Failed to process ticket commission: " . $e->getMessage());
            }
        }

        return $result;
    }

    private function notifyAssignedTechnician(int $ticketId, int $technicianId): void {
        $ticket = $this->find($ticketId);
        $technician = $this->getUser($technicianId);
        $customer = (new Customer())->find($ticket['customer_id']);

        if ($technician && $technician['phone'] && $customer) {
            $statusLink = '';
            try {
                require_once __DIR__ . '/TicketStatusLink.php';
                $statusLinkService = new \TicketStatusLink($this->db);
                $statusLink = $statusLinkService->generateStatusUpdateUrl($ticketId, $technicianId);
            } catch (\Throwable $e) {
                error_log("Failed to generate status link for ticket $ticketId: " . $e->getMessage());
            }
            
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
                '{technician_phone}' => $technician['phone'] ?? '',
                '{status_link}' => $statusLink
            ];
            
            $message = $this->buildSMSFromTemplate('sms_template_technician_assigned', $placeholders);
            if (!empty($statusLink) && strpos($message, $statusLink) === false) {
                $message .= " Update: " . $statusLink;
            }
            $result = $this->sms->send($technician['phone'], $message);
            $this->sms->logSMS($ticketId, $technician['phone'], 'technician', 'Ticket assignment notification', $result['success'] ? 'sent' : 'failed');
            
            $waMessage = $this->buildSMSFromTemplate('wa_template_technician_assigned', $placeholders);
            if (empty(trim(str_replace(array_keys($placeholders), '', $waMessage)))) {
                $waMessage = $message;
            }
            if (!empty($statusLink) && strpos($waMessage, $statusLink) === false) {
                $waMessage .= "\n\nUpdate status: " . $statusLink;
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
            'wa_template_branch_ticket_assigned' => "🎫 *NEW TICKET ASSIGNED*\n\n📋 *Ticket:* #{ticket_number}\n📌 *Subject:* {subject}\n🏷️ *Category:* {category}\n⚡ *Priority:* {priority}\n🕐 *Created:* {created_at}\n\n👤 *Customer Details:*\n• Name: {customer_name}\n• Phone: {customer_phone}\n• Email: {customer_email}\n• Account: {customer_account}\n• Username: {customer_username}\n• Address: {customer_address}\n• Location: {customer_location}\n• GPS: {customer_coordinates}\n• Plan: {service_plan}\n\n👷 *{assignment_info}*\n📞 Tech Phone: {technician_phone}\n👥 Team: {team_name}\n👥 Members: {team_members}\n\n🏢 Branch: {branch_name}",
        ];
        
        $waToSmsMapping = [
            'wa_template_ticket_created' => 'sms_template_ticket_created',
            'wa_template_ticket_assigned' => 'sms_template_ticket_assigned',
            'wa_template_technician_assigned' => 'sms_template_technician_assigned',
            'wa_template_status_update' => 'sms_template_ticket_updated',
            'wa_template_resolved' => 'sms_template_ticket_resolved',
        ];
        
        $placeholders['{company_name}'] = $this->settings->get('company_name', 'ISP Support');
        
        $template = $this->settings->get($templateKey, '');
        
        if (empty(trim($template)) && str_starts_with($templateKey, 'wa_template_')) {
            $smsKey = $waToSmsMapping[$templateKey] ?? str_replace('wa_template_', 'sms_template_', $templateKey);
            $template = $this->settings->get($smsKey, $defaults[$smsKey] ?? '');
        }
        
        if (empty(trim($template))) {
            $template = $defaults[$templateKey] ?? 'ISP Support - Ticket #{ticket_number} - {status}';
        }
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    public function delete(int $id): bool {
        $ticket = $this->find($id);
        if (!$ticket) {
            $this->activityLog->log('delete_failed', 'ticket', $id, 'Unknown', "Delete failed - ticket not found");
            return false;
        }
        
        $ticketNumber = $ticket['ticket_number'];
        
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM ticket_comments WHERE ticket_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM ticket_service_fees WHERE ticket_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM ticket_sla_logs WHERE ticket_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM ticket_satisfaction_ratings WHERE ticket_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM ticket_earnings WHERE ticket_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM whatsapp_logs WHERE ticket_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM sms_logs WHERE ticket_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM ticket_escalations WHERE ticket_id = ?")->execute([$id]);
            
            $stmt = $this->db->prepare("DELETE FROM tickets WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $this->db->commit();
                $this->activityLog->log('delete', 'ticket', $id, $ticketNumber, "Ticket deleted");
                return true;
            }
            
            $this->db->rollBack();
            $this->activityLog->log('delete_failed', 'ticket', $id, $ticketNumber, "Delete failed - database error");
            return false;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("Failed to delete ticket $id: " . $e->getMessage());
            try {
                $this->activityLog->log('delete_failed', 'ticket', $id, $ticketNumber, "Delete failed: " . $e->getMessage());
            } catch (\Throwable $logError) {
                error_log("Failed to log delete failure: " . $logError->getMessage());
            }
            return false;
        }
    }
    
    public function getCustomerAssignmentHistory(int $customerId): ?array {
        $stmt = $this->db->prepare("
            SELECT 
                t.assigned_to,
                t.team_id,
                u.name as technician_name,
                u.phone as technician_phone,
                tm.name as team_name,
                COUNT(*) as ticket_count,
                MAX(t.resolved_at) as last_resolved
            FROM tickets t
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN teams tm ON t.team_id = tm.id
            WHERE t.customer_id = ?
              AND t.status IN ('resolved', 'closed')
              AND (t.assigned_to IS NOT NULL OR t.team_id IS NOT NULL)
            GROUP BY t.assigned_to, t.team_id, u.name, u.phone, tm.name
            ORDER BY ticket_count DESC, last_resolved DESC
            LIMIT 1
        ");
        $stmt->execute([$customerId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function getCustomerTicketHistory(int $customerId, int $limit = 10): array {
        $stmt = $this->db->prepare("
            SELECT 
                t.id, t.ticket_number, t.subject, t.category, t.status, t.priority,
                t.created_at, t.resolved_at,
                u.name as technician_name,
                tm.name as team_name
            FROM tickets t
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN teams tm ON t.team_id = tm.id
            WHERE t.customer_id = ?
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$customerId, $limit]);
        return $stmt->fetchAll();
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
        
        if (isset($filters['escalated']) && $filters['escalated'] !== '') {
            $sql .= " AND t.is_escalated = ?";
            $params[] = $filters['escalated'] === '1' || $filters['escalated'] === true;
        }
        
        if (!empty($filters['sla_breached'])) {
            $sql .= " AND (t.sla_resolution_breached = TRUE OR t.sla_response_breached = TRUE)";
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
        try {
            $stmt = $this->db->query("SELECT key, label FROM ticket_categories WHERE is_active = true ORDER BY display_order, label");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $categories = [];
                foreach ($rows as $row) {
                    $categories[$row['key']] = $row['label'];
                }
                return $categories;
            }
        } catch (\Exception $e) {
        }
        
        return $this->getDefaultCategories();
    }
    
    public function getDefaultCategories(): array {
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
    
    public function getAllCategories(): array {
        $stmt = $this->db->query("SELECT * FROM ticket_categories ORDER BY display_order, label");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getCategory(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM ticket_categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function addCategory(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_categories (key, label, description, color, display_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $data['key'])),
            $data['label'],
            $data['description'] ?? null,
            $data['color'] ?? 'primary',
            $data['display_order'] ?? 0,
            isset($data['is_active']) ? (bool)$data['is_active'] : true
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateCategory(int $id, array $data): bool {
        $fields = [];
        $params = [];
        
        if (isset($data['key'])) {
            $fields[] = "key = ?";
            $params[] = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $data['key']));
        }
        if (isset($data['label'])) {
            $fields[] = "label = ?";
            $params[] = $data['label'];
        }
        if (array_key_exists('description', $data)) {
            $fields[] = "description = ?";
            $params[] = $data['description'];
        }
        if (isset($data['color'])) {
            $fields[] = "color = ?";
            $params[] = $data['color'];
        }
        if (isset($data['display_order'])) {
            $fields[] = "display_order = ?";
            $params[] = (int)$data['display_order'];
        }
        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $params[] = (bool)$data['is_active'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $stmt = $this->db->prepare("UPDATE ticket_categories SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($params);
    }
    
    public function deleteCategory(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM ticket_categories WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function seedDefaultCategories(): void {
        $defaults = $this->getDefaultCategories();
        $order = 0;
        foreach ($defaults as $key => $label) {
            $order++;
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO ticket_categories (key, label, display_order) 
                    VALUES (?, ?, ?)
                    ON CONFLICT (key) DO NOTHING
                ");
                $stmt->execute([$key, $label, $order]);
            } catch (\Exception $e) {
            }
        }
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
        $leaderId = isset($data['leader_id']) && $data['leader_id'] !== '' ? (int)$data['leader_id'] : null;
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $leaderId
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
                $statusLink = '';
                try {
                    require_once __DIR__ . '/TicketStatusLink.php';
                    $statusLinkService = new \TicketStatusLink($this->db);
                    $statusLink = $statusLinkService->generateStatusUpdateUrl($ticketId, $member['id']);
                } catch (\Throwable $e) {
                    error_log("Failed to generate status link for team member: " . $e->getMessage());
                }
                
                $message = $this->buildSMSFromTemplate('sms_template_technician_assigned', [
                    '{ticket_number}' => $ticket['ticket_number'],
                    '{customer_name}' => $customer['name'] ?? 'Customer',
                    '{customer_phone}' => $customer['phone'] ?? '',
                    '{customer_address}' => $customer['address'] ?? '',
                    '{subject}' => $ticket['subject'] ?? '',
                    '{category}' => $ticket['category'] ?? '',
                    '{priority}' => ucfirst($ticket['priority'] ?? 'medium'),
                    '{technician_name}' => $member['user_name'] ?? $member['name'] ?? 'Team Member',
                    '{status_link}' => $statusLink
                ]);
                if (!empty($statusLink) && strpos($message, $statusLink) === false) {
                    $message .= " Update: " . $statusLink;
                }
                $result = $this->sms->send($member['phone'], $message);
                $this->sms->logSMS($ticketId, $member['phone'], 'team_member', 'Team assignment notification', $result['success'] ? 'sent' : 'failed');
            }
        }
    }
    
    public function notifyBranchWhatsAppGroup(int $ticketId, ?int $branchId = null): void {
        error_log("notifyBranchWhatsAppGroup called - ticketId: $ticketId, branchId: " . ($branchId ?? 'null'));
        
        $ticket = $this->find($ticketId);
        if (!$ticket) {
            error_log("notifyBranchWhatsAppGroup - Ticket not found: $ticketId");
            return;
        }
        
        $branchId = $branchId ?? $ticket['branch_id'];
        if (!$branchId) {
            error_log("notifyBranchWhatsAppGroup - No branch ID for ticket $ticketId");
            return;
        }
        
        $branch = new Branch();
        $branchData = $branch->find($branchId);
        if (!$branchData || empty($branchData['whatsapp_group'])) {
            error_log("notifyBranchWhatsAppGroup - Branch not found or no WhatsApp group. BranchId: $branchId, HasGroup: " . (isset($branchData['whatsapp_group']) ? 'yes' : 'no'));
            return;
        }
        
        error_log("notifyBranchWhatsAppGroup - Sending to group: {$branchData['whatsapp_group']} for branch: {$branchData['name']}");
        
        $customer = (new Customer())->find($ticket['customer_id']);
        if (!$customer) return;
        
        $technicianName = '';
        $technicianPhone = '';
        $teamName = '';
        $teamMembers = [];
        
        if ($ticket['assigned_to']) {
            $technician = $this->getUser($ticket['assigned_to']);
            $technicianName = $technician['name'] ?? 'Unknown';
            $technicianPhone = $technician['phone'] ?? '';
        }
        
        if ($ticket['team_id']) {
            $team = $this->getTeam($ticket['team_id']);
            $teamName = $team['name'] ?? '';
            $teamMembers = $this->getTeamMembers($ticket['team_id']);
        }
        
        $assignmentInfo = '';
        $teamMembersList = '';
        if ($technicianName && $teamName) {
            $assignmentInfo = "Assigned to: {$technicianName} (Team: {$teamName})";
        } elseif ($technicianName) {
            $assignmentInfo = "Assigned to: {$technicianName}";
        } elseif ($teamName) {
            $assignmentInfo = "Assigned to Team: {$teamName}";
        } else {
            $assignmentInfo = "Unassigned";
        }
        
        if (!empty($teamMembers)) {
            $memberNames = array_map(function($m) { return $m['name'] ?? 'Unknown'; }, $teamMembers);
            $teamMembersList = implode(', ', $memberNames);
        }
        
        $placeholders = [
            '{ticket_number}' => $ticket['ticket_number'],
            '{subject}' => $ticket['subject'] ?? '',
            '{description}' => substr($ticket['description'] ?? '', 0, 200),
            '{category}' => ucfirst($ticket['category'] ?? 'General'),
            '{priority}' => ucfirst($ticket['priority'] ?? 'Medium'),
            '{customer_name}' => $customer['name'] ?? 'Customer',
            '{customer_phone}' => $customer['phone'] ?? '',
            '{customer_address}' => $customer['address'] ?? '',
            '{customer_email}' => $customer['email'] ?? '',
            '{customer_account}' => $customer['account_number'] ?? '',
            '{customer_username}' => $customer['username'] ?? '',
            '{customer_location}' => $customer['location'] ?? '',
            '{customer_coordinates}' => (!empty($customer['latitude']) && !empty($customer['longitude'])) 
                ? "{$customer['latitude']}, {$customer['longitude']}" : '',
            '{service_plan}' => $customer['service_plan'] ?? '',
            '{technician_name}' => $technicianName,
            '{technician_phone}' => $technicianPhone,
            '{team_name}' => $teamName,
            '{team_members}' => $teamMembersList,
            '{assignment_info}' => $assignmentInfo,
            '{branch_name}' => $branchData['name'] ?? '',
            '{branch_code}' => $branchData['code'] ?? '',
            '{created_at}' => date('d M Y H:i', strtotime($ticket['created_at'] ?? 'now'))
        ];
        
        $message = $this->buildSMSFromTemplate('wa_template_branch_ticket_assigned', $placeholders);
        
        if (empty(trim(str_replace(array_keys($placeholders), '', $message)))) {
            $message = "🎫 *NEW TICKET ASSIGNED*\n\n"
                . "📋 *Ticket:* #{$ticket['ticket_number']}\n"
                . "📌 *Subject:* {$ticket['subject']}\n"
                . "🏷️ *Category:* " . ucfirst($ticket['category'] ?? 'General') . "\n"
                . "⚡ *Priority:* " . ucfirst($ticket['priority'] ?? 'Medium') . "\n"
                . "🕐 *Created:* " . date('d M Y H:i', strtotime($ticket['created_at'] ?? 'now')) . "\n\n"
                . "👤 *Customer Details:*\n"
                . "• Name: {$customer['name']}\n"
                . "• Phone: {$customer['phone']}\n"
                . (!empty($customer['email']) ? "• Email: {$customer['email']}\n" : "")
                . (!empty($customer['account_number']) ? "• Account: {$customer['account_number']}\n" : "")
                . (!empty($customer['username']) ? "• Username: {$customer['username']}\n" : "")
                . "• Address: {$customer['address']}\n"
                . (!empty($customer['location']) ? "• Location: {$customer['location']}\n" : "")
                . (!empty($customer['latitude']) && !empty($customer['longitude']) 
                    ? "• GPS: {$customer['latitude']}, {$customer['longitude']}\n" 
                    : "")
                . (!empty($customer['service_plan']) ? "• Plan: {$customer['service_plan']}\n" : "")
                . "\n👷 *{$assignmentInfo}*\n"
                . (!empty($technicianPhone) ? "📞 Tech Phone: {$technicianPhone}\n" : "")
                . (!empty($teamMembersList) ? "👥 Team Members: {$teamMembersList}\n" : "")
                . "\n🏢 Branch: {$branchData['name']}";
        }
        
        try {
            $result = $this->whatsapp->sendToGroup($branchData['whatsapp_group'], $message);
            if ($result['success']) {
                $this->whatsapp->logMessage($ticketId, null, null, $branchData['whatsapp_group'], 'branch_group', $message, 'sent', 'ticket_assigned_branch');
                error_log("WhatsApp branch group notification sent for ticket {$ticket['ticket_number']} to branch {$branchData['name']}");
            } else {
                error_log("WhatsApp branch group notification failed: " . ($result['error'] ?? 'Unknown error'));
            }
        } catch (\Throwable $e) {
            error_log("WhatsApp branch group notification error for ticket $ticketId: " . $e->getMessage());
        }
    }
    
    public function getTimeline(int $ticketId): array {
        $timeline = [];
        
        $ticket = $this->find($ticketId);
        if ($ticket) {
            $timeline[] = [
                'type' => 'created',
                'icon' => 'plus-circle',
                'color' => 'primary',
                'title' => 'Ticket Created',
                'description' => "Ticket #{$ticket['ticket_number']} was created",
                'user' => $ticket['created_by_name'] ?? 'System',
                'timestamp' => $ticket['created_at']
            ];
        }
        
        $comments = $this->getComments($ticketId);
        foreach ($comments as $comment) {
            $timeline[] = [
                'type' => $comment['is_internal'] ? 'internal_note' : 'comment',
                'icon' => $comment['is_internal'] ? 'lock' : 'chat-dots',
                'color' => $comment['is_internal'] ? 'warning' : 'info',
                'title' => $comment['is_internal'] ? 'Internal Note' : 'Comment Added',
                'description' => substr($comment['comment'], 0, 200) . (strlen($comment['comment']) > 200 ? '...' : ''),
                'user' => $comment['user_name'] ?? 'Unknown',
                'timestamp' => $comment['created_at']
            ];
        }
        
        $stmt = $this->db->prepare("
            SELECT sl.*, u.name as user_name
            FROM ticket_sla_logs sl
            LEFT JOIN users u ON sl.created_by = u.id
            WHERE sl.ticket_id = ?
            ORDER BY sl.created_at ASC
        ");
        $stmt->execute([$ticketId]);
        $slaLogs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($slaLogs as $log) {
            $eventIcons = [
                'sla_assigned' => 'clock',
                'sla_response_breach' => 'exclamation-triangle',
                'sla_resolution_breach' => 'exclamation-circle',
                'first_response' => 'reply',
                'resolved' => 'check-circle',
                'paused' => 'pause-circle',
                'resumed' => 'play-circle'
            ];
            $eventColors = [
                'sla_assigned' => 'secondary',
                'sla_response_breach' => 'danger',
                'sla_resolution_breach' => 'danger',
                'first_response' => 'success',
                'resolved' => 'success',
                'paused' => 'warning',
                'resumed' => 'info'
            ];
            $timeline[] = [
                'type' => 'sla_event',
                'icon' => $eventIcons[$log['event_type']] ?? 'info-circle',
                'color' => $eventColors[$log['event_type']] ?? 'secondary',
                'title' => ucwords(str_replace('_', ' ', $log['event_type'])),
                'description' => $log['notes'] ?? '',
                'user' => $log['user_name'] ?? 'System',
                'timestamp' => $log['created_at']
            ];
        }
        
        $stmt = $this->db->prepare("
            SELECT e.*, eb.name as escalated_by_name, et.name as escalated_to_name
            FROM ticket_escalations e
            LEFT JOIN users eb ON e.escalated_by = eb.id
            LEFT JOIN users et ON e.escalated_to = et.id
            WHERE e.ticket_id = ?
            ORDER BY e.created_at ASC
        ");
        $stmt->execute([$ticketId]);
        $escalations = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($escalations as $esc) {
            $timeline[] = [
                'type' => 'escalation',
                'icon' => 'arrow-up-circle',
                'color' => 'danger',
                'title' => 'Ticket Escalated',
                'description' => $esc['reason'] . ($esc['escalated_to_name'] ? " - Assigned to {$esc['escalated_to_name']}" : ''),
                'user' => $esc['escalated_by_name'] ?? 'System',
                'timestamp' => $esc['created_at']
            ];
        }
        
        usort($timeline, fn($a, $b) => strtotime($a['timestamp']) <=> strtotime($b['timestamp']));
        
        return $timeline;
    }
    
    public function quickStatusChange(int $ticketId, string $newStatus, int $userId): bool {
        $ticket = $this->find($ticketId);
        if (!$ticket) return false;
        
        $oldStatus = $ticket['status'];
        $updates = ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')];
        
        if ($newStatus === 'closed' && $oldStatus !== 'closed') {
            $updates['closed_at'] = date('Y-m-d H:i:s');
        }
        if (in_array($newStatus, ['resolved', 'closed']) && !$ticket['resolved_at']) {
            $updates['resolved_at'] = date('Y-m-d H:i:s');
        }
        
        $fields = [];
        $values = [];
        foreach ($updates as $field => $value) {
            $fields[] = "$field = ?";
            $values[] = $value;
        }
        $values[] = $ticketId;
        
        $stmt = $this->db->prepare("UPDATE tickets SET " . implode(', ', $fields) . " WHERE id = ?");
        $result = $stmt->execute($values);
        
        if ($result) {
            $this->addComment($ticketId, $userId, "Status changed from {$oldStatus} to {$newStatus}", true);
            $this->activityLog->log('status_change', 'ticket', $ticketId, $ticket['ticket_number'], 
                "Status changed: {$oldStatus} -> {$newStatus}");
            
            if (in_array($newStatus, ['resolved', 'closed'])) {
                $ticketCommission = new TicketCommission($this->db);
                $ticketCommission->calculateForTicket($ticketId);
            }
        }
        
        return $result;
    }
    
    public function escalate(int $ticketId, int $escalatedBy, array $data): bool {
        $ticket = $this->find($ticketId);
        if (!$ticket) return false;
        
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ticket_escalations (ticket_id, escalated_by, escalated_to, reason, 
                    previous_priority, new_priority, previous_assigned_to)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ticketId,
                $escalatedBy,
                $data['escalated_to'] ?? null,
                $data['reason'],
                $ticket['priority'],
                $data['new_priority'] ?? $ticket['priority'],
                $ticket['assigned_to']
            ]);
            
            $updates = [
                'is_escalated' => true,
                'escalation_count' => ($ticket['escalation_count'] ?? 0) + 1,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            if (!empty($data['new_priority'])) {
                $updates['priority'] = $data['new_priority'];
            }
            if (!empty($data['escalated_to'])) {
                $updates['assigned_to'] = $data['escalated_to'];
            }
            
            $fields = [];
            $values = [];
            foreach ($updates as $field => $value) {
                $fields[] = "$field = ?";
                $values[] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            }
            $values[] = $ticketId;
            
            $stmt = $this->db->prepare("UPDATE tickets SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($values);
            
            $this->activityLog->log('escalate', 'ticket', $ticketId, $ticket['ticket_number'], 
                "Ticket escalated: {$data['reason']}");
            
            if (!empty($data['escalated_to'])) {
                $this->notifyAssignedTechnician($ticketId, (int)$data['escalated_to']);
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Escalation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function submitSatisfactionRating(int $ticketId, array $data): bool {
        $ticket = $this->find($ticketId);
        if (!$ticket) return false;
        
        $stmt = $this->db->prepare("
            INSERT INTO ticket_satisfaction_ratings (ticket_id, customer_id, rating, feedback, rated_by_name)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT (ticket_id) DO UPDATE SET 
                rating = EXCLUDED.rating, 
                feedback = EXCLUDED.feedback,
                rated_at = CURRENT_TIMESTAMP
        ");
        $result = $stmt->execute([
            $ticketId,
            $ticket['customer_id'],
            (int)$data['rating'],
            $data['feedback'] ?? null,
            $data['rated_by_name'] ?? null
        ]);
        
        if ($result) {
            $stmt = $this->db->prepare("UPDATE tickets SET satisfaction_rating = ? WHERE id = ?");
            $stmt->execute([(int)$data['rating'], $ticketId]);
            
            $this->activityLog->log('rating', 'ticket', $ticketId, $ticket['ticket_number'], 
                "Customer rated ticket: {$data['rating']}/5 stars");
        }
        
        return $result;
    }
    
    public function getSatisfactionRating(int $ticketId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM ticket_satisfaction_ratings WHERE ticket_id = ?");
        $stmt->execute([$ticketId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    public function getEscalations(int $ticketId): array {
        $stmt = $this->db->prepare("
            SELECT e.*, eb.name as escalated_by_name, et.name as escalated_to_name
            FROM ticket_escalations e
            LEFT JOIN users eb ON e.escalated_by = eb.id
            LEFT JOIN users et ON e.escalated_to = et.id
            WHERE e.ticket_id = ?
            ORDER BY e.created_at DESC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getDashboardStats(): array {
        $thisMonth = date('Y-m-01');
        $lastMonth = date('Y-m-01', strtotime('-1 month'));
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                COUNT(*) FILTER (WHERE status = 'open') as open_tickets,
                COUNT(*) FILTER (WHERE status = 'in_progress') as in_progress_tickets,
                COUNT(*) FILTER (WHERE status = 'resolved') as resolved_tickets,
                COUNT(*) FILTER (WHERE status = 'closed') as closed_tickets,
                COUNT(*) FILTER (WHERE is_escalated = TRUE) as escalated_tickets,
                COUNT(*) FILTER (WHERE sla_resolution_breached = TRUE) as sla_breached,
                AVG(satisfaction_rating) FILTER (WHERE satisfaction_rating IS NOT NULL) as avg_satisfaction,
                COUNT(*) FILTER (WHERE created_at >= ?) as this_month_tickets,
                COUNT(*) FILTER (WHERE created_at >= ? AND created_at < ?) as last_month_tickets,
                AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))/3600) 
                    FILTER (WHERE resolved_at IS NOT NULL AND created_at >= ?) as avg_resolution_hours
            FROM tickets
        ");
        $stmt->execute([$thisMonth, $lastMonth, $thisMonth, $thisMonth]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $stmt = $this->db->query("
            SELECT category, COUNT(*) as count
            FROM tickets
            WHERE created_at >= NOW() - INTERVAL '30 days'
            GROUP BY category
            ORDER BY count DESC
            LIMIT 5
        ");
        $stats['top_categories'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $stmt = $this->db->query("
            SELECT u.name, COUNT(t.id) as ticket_count,
                   COUNT(*) FILTER (WHERE t.status IN ('resolved', 'closed')) as resolved_count
            FROM users u
            LEFT JOIN tickets t ON u.id = t.assigned_to AND t.created_at >= NOW() - INTERVAL '30 days'
            WHERE u.role IN ('technician', 'admin')
            GROUP BY u.id, u.name
            HAVING COUNT(t.id) > 0
            ORDER BY resolved_count DESC
            LIMIT 5
        ");
        $stats['top_technicians'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $stats;
    }
    
    public function getRecentActivity(int $limit = 10): array {
        $stmt = $this->db->prepare("
            SELECT t.id, t.ticket_number, t.subject, t.status, t.priority, 
                   t.updated_at, t.created_at, c.name as customer_name,
                   u.name as assigned_name
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.assigned_to = u.id
            ORDER BY t.updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getOverdueTickets(): array {
        $stmt = $this->db->query("
            SELECT t.*, c.name as customer_name, c.phone as customer_phone,
                   u.name as assigned_name
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.status NOT IN ('resolved', 'closed')
              AND (t.sla_resolution_breached = TRUE 
                   OR (t.sla_resolution_due IS NOT NULL AND t.sla_resolution_due < NOW()))
            ORDER BY t.sla_resolution_due ASC
            LIMIT 20
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
