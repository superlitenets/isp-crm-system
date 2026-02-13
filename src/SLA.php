<?php

namespace App;

use PDO;

class SLA {
    private \PDO $db;
    
    public function __construct() {
        $this->db = \Database::getConnection();
    }
    
    public function getAllPolicies(): array {
        $stmt = $this->db->query("
            SELECT sp.*, u.name as escalation_name 
            FROM sla_policies sp
            LEFT JOIN users u ON sp.escalation_to = u.id
            ORDER BY 
                CASE sp.priority 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                END
        ");
        return $stmt->fetchAll();
    }
    
    public function getPolicy(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM sla_policies WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function getPolicyByPriority(string $priority): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM sla_policies 
            WHERE priority = ? AND is_active = TRUE 
            ORDER BY is_default DESC 
            LIMIT 1
        ");
        $stmt->execute([$priority]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function createPolicy(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO sla_policies (name, description, priority, response_time_hours, resolution_time_hours, escalation_time_hours, escalation_to, notify_on_breach, is_active, is_default)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?::boolean, ?::boolean, ?::boolean)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['priority'],
            (int)$data['response_time_hours'],
            (int)$data['resolution_time_hours'],
            !empty($data['escalation_time_hours']) ? (int)$data['escalation_time_hours'] : null,
            !empty($data['escalation_to']) ? (int)$data['escalation_to'] : null,
            !empty($data['notify_on_breach']) && $data['notify_on_breach'] !== '0' ? 'true' : 'false',
            !empty($data['is_active']) && $data['is_active'] !== '0' ? 'true' : 'false',
            !empty($data['is_default']) && $data['is_default'] !== '0' ? 'true' : 'false'
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    public function updatePolicy(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE sla_policies SET 
                name = ?, description = ?, priority = ?, 
                response_time_hours = ?, resolution_time_hours = ?, 
                escalation_time_hours = ?, escalation_to = ?, 
                notify_on_breach = ?::boolean, is_active = ?::boolean, is_default = ?::boolean,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['priority'],
            (int)$data['response_time_hours'],
            (int)$data['resolution_time_hours'],
            !empty($data['escalation_time_hours']) ? (int)$data['escalation_time_hours'] : null,
            !empty($data['escalation_to']) ? (int)$data['escalation_to'] : null,
            !empty($data['notify_on_breach']) && $data['notify_on_breach'] !== '0' ? 'true' : 'false',
            !empty($data['is_active']) && $data['is_active'] !== '0' ? 'true' : 'false',
            !empty($data['is_default']) && $data['is_default'] !== '0' ? 'true' : 'false',
            $id
        ]);
    }
    
    public function deletePolicy(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM sla_policies WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getBusinessHours(): array {
        $stmt = $this->db->query("SELECT * FROM sla_business_hours ORDER BY day_of_week");
        return $stmt->fetchAll();
    }
    
    public function updateBusinessHours(array $hours): bool {
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare("
                UPDATE sla_business_hours SET 
                    start_time = ?, end_time = ?, is_working_day = ?::boolean
                WHERE day_of_week = ?
            ");
            
            foreach ($hours as $dayHours) {
                $stmt->execute([
                    $dayHours['start_time'],
                    $dayHours['end_time'],
                    !empty($dayHours['is_working_day']) ? 'true' : 'false',
                    (int)$dayHours['day_of_week']
                ]);
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    public function getHolidays(): array {
        $stmt = $this->db->query("SELECT * FROM sla_holidays ORDER BY holiday_date");
        return $stmt->fetchAll();
    }
    
    public function addHoliday(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO sla_holidays (name, holiday_date, is_recurring)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['holiday_date'],
            isset($data['is_recurring']) ? (bool)$data['is_recurring'] : false
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function deleteHoliday(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM sla_holidays WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function calculateDueDate(\DateTime $startDate, int $hours, bool $useBusinessHours = true): \DateTime {
        if (!$useBusinessHours) {
            return (clone $startDate)->modify("+{$hours} hours");
        }
        
        $businessHours = $this->getBusinessHours();
        $holidays = $this->getHolidayDates();
        
        $current = clone $startDate;
        $remainingMinutes = $hours * 60;
        
        while ($remainingMinutes > 0) {
            $dayOfWeek = (int)$current->format('w');
            $dayHours = $businessHours[$dayOfWeek] ?? null;
            
            if (!$dayHours || !$dayHours['is_working_day'] || in_array($current->format('Y-m-d'), $holidays)) {
                $current->modify('+1 day');
                $current->setTime(8, 0, 0);
                continue;
            }
            
            $startTime = new \DateTime($current->format('Y-m-d') . ' ' . $dayHours['start_time']);
            $endTime = new \DateTime($current->format('Y-m-d') . ' ' . $dayHours['end_time']);
            
            if ($current < $startTime) {
                $current = $startTime;
            }
            
            if ($current >= $endTime) {
                $current->modify('+1 day');
                $current->setTime(8, 0, 0);
                continue;
            }
            
            $minutesLeftToday = (int)(($endTime->getTimestamp() - $current->getTimestamp()) / 60);
            
            if ($remainingMinutes <= $minutesLeftToday) {
                $current->modify("+{$remainingMinutes} minutes");
                $remainingMinutes = 0;
            } else {
                $remainingMinutes -= $minutesLeftToday;
                $current->modify('+1 day');
                $current->setTime(8, 0, 0);
            }
        }
        
        return $current;
    }
    
    private function getHolidayDates(): array {
        $holidays = $this->getHolidays();
        $dates = [];
        $currentYear = date('Y');
        
        foreach ($holidays as $holiday) {
            $dates[] = $holiday['holiday_date'];
            if ($holiday['is_recurring']) {
                $date = new \DateTime($holiday['holiday_date']);
                $date->setDate($currentYear, (int)$date->format('m'), (int)$date->format('d'));
                $dates[] = $date->format('Y-m-d');
            }
        }
        
        return array_unique($dates);
    }
    
    public function calculateSLAForTicket(string $priority, ?\DateTime $startAt = null): array {
        $policy = $this->getPolicyByPriority($priority);
        
        if (!$policy) {
            return [
                'policy_id' => null,
                'response_due' => null,
                'resolution_due' => null
            ];
        }
        
        $startDate = $startAt ?? new \DateTime();
        
        return [
            'policy_id' => $policy['id'],
            'response_due' => $this->calculateDueDate($startDate, $policy['response_time_hours']),
            'resolution_due' => $this->calculateDueDate($startDate, $policy['resolution_time_hours'])
        ];
    }
    
    public function getSLAStatus(array $ticket): array {
        $now = new \DateTime();
        $status = [
            'response' => ['status' => 'n/a', 'time_left' => null, 'breached' => false],
            'resolution' => ['status' => 'n/a', 'time_left' => null, 'breached' => false]
        ];
        
        if (empty($ticket['sla_response_due'])) {
            return $status;
        }
        
        $responseDue = new \DateTime($ticket['sla_response_due']);
        $resolutionDue = new \DateTime($ticket['sla_resolution_due']);
        
        $slaStart = !empty($ticket['sla_started_at']) ? strtotime($ticket['sla_started_at']) : strtotime($ticket['created_at']);

        if (!empty($ticket['first_response_at'])) {
            $responseTime = new \DateTime($ticket['first_response_at']);
            if ($responseTime <= $responseDue) {
                $status['response'] = ['status' => 'met', 'breached' => false];
            } else {
                $status['response'] = ['status' => 'breached', 'breached' => true];
            }
        } else {
            if ($ticket['sla_response_breached'] || $now > $responseDue) {
                $status['response'] = ['status' => 'breached', 'time_left' => null, 'breached' => true];
            } else {
                $timeLeft = $responseDue->getTimestamp() - $now->getTimestamp();
                $warningThreshold = ($responseDue->getTimestamp() - $slaStart) * 0.2;
                
                if ($timeLeft < $warningThreshold) {
                    $status['response'] = ['status' => 'at_risk', 'time_left' => $timeLeft, 'breached' => false];
                } else {
                    $status['response'] = ['status' => 'on_track', 'time_left' => $timeLeft, 'breached' => false];
                }
            }
        }
        
        if ($ticket['status'] === 'resolved') {
            $resolvedAt = new \DateTime($ticket['resolved_at'] ?? $ticket['updated_at']);
            if ($resolvedAt <= $resolutionDue) {
                $status['resolution'] = ['status' => 'met', 'breached' => false];
            } else {
                $status['resolution'] = ['status' => 'breached', 'breached' => true];
            }
        } else {
            if ($ticket['sla_resolution_breached'] || $now > $resolutionDue) {
                $status['resolution'] = ['status' => 'breached', 'time_left' => null, 'breached' => true];
            } else {
                $timeLeft = $resolutionDue->getTimestamp() - $now->getTimestamp();
                $warningThreshold = ($resolutionDue->getTimestamp() - $slaStart) * 0.2;
                
                if ($timeLeft < $warningThreshold) {
                    $status['resolution'] = ['status' => 'at_risk', 'time_left' => $timeLeft, 'breached' => false];
                } else {
                    $status['resolution'] = ['status' => 'on_track', 'time_left' => $timeLeft, 'breached' => false];
                }
            }
        }
        
        return $status;
    }
    
    public function formatTimeLeft(?int $seconds): string {
        if ($seconds === null || $seconds < 0) {
            return 'Overdue';
        }
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 24) {
            $days = floor($hours / 24);
            $hours = $hours % 24;
            return "{$days}d {$hours}h";
        }
        
        return "{$hours}h {$minutes}m";
    }
    
    public function logSLAEvent(int $ticketId, string $eventType, ?string $details = null): void {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_sla_logs (ticket_id, event_type, details)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$ticketId, $eventType, $details]);
    }
    
    public function getSLALogs(int $ticketId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM ticket_sla_logs 
            WHERE ticket_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }
    
    public function getBreachedTickets(?int $userId = null): array {
        $sql = "
            SELECT t.*, c.name as customer_name, u.name as assigned_name,
                   sp.name as sla_policy_name
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN sla_policies sp ON t.sla_policy_id = sp.id
            WHERE (t.sla_response_breached = TRUE OR t.sla_resolution_breached = TRUE)
                AND t.status != 'resolved'
        ";
        
        $params = [];
        if ($userId !== null) {
            $sql .= " AND (t.assigned_to = ? OR t.created_by = ?)";
            $params = [$userId, $userId];
        }
        
        $sql .= " ORDER BY t.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getAtRiskTickets(?int $userId = null): array {
        $sql = "
            SELECT t.*, c.name as customer_name, u.name as assigned_name,
                   sp.name as sla_policy_name, sp.response_time_hours, sp.resolution_time_hours
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN sla_policies sp ON t.sla_policy_id = sp.id
            WHERE t.status != 'resolved'
                AND t.sla_response_breached = FALSE
                AND t.sla_resolution_breached = FALSE
                AND (
                    (t.first_response_at IS NULL AND t.sla_response_due IS NOT NULL AND t.sla_response_due < ?)
                    OR (t.sla_resolution_due IS NOT NULL AND t.sla_resolution_due < ?)
                )
        ";
        
        $warningTime = (new \DateTime())->modify('+2 hours')->format('Y-m-d H:i:s');
        $params = [$warningTime, $warningTime];
        
        if ($userId !== null) {
            $sql .= " AND (t.assigned_to = ? OR t.created_by = ?)";
            $params[] = $userId;
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY COALESCE(t.sla_response_due, t.sla_resolution_due) ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getSLAStatistics(string $period = '30days', ?int $userId = null): array {
        $startDate = match($period) {
            '7days' => date('Y-m-d', strtotime('-7 days')),
            '30days' => date('Y-m-d', strtotime('-30 days')),
            '90days' => date('Y-m-d', strtotime('-90 days')),
            default => date('Y-m-d', strtotime('-30 days'))
        };
        
        $sql = "
            SELECT 
                COUNT(*) as total_tickets,
                SUM(CASE WHEN sla_policy_id IS NOT NULL THEN 1 ELSE 0 END) as with_sla,
                SUM(CASE WHEN sla_response_breached = TRUE THEN 1 ELSE 0 END) as response_breached,
                SUM(CASE WHEN sla_resolution_breached = TRUE THEN 1 ELSE 0 END) as resolution_breached,
                SUM(CASE WHEN first_response_at IS NOT NULL AND sla_response_due IS NOT NULL 
                         AND first_response_at <= sla_response_due THEN 1 ELSE 0 END) as response_met,
                SUM(CASE WHEN status = 'resolved' AND resolved_at IS NOT NULL 
                         AND sla_resolution_due IS NOT NULL AND resolved_at <= sla_resolution_due 
                         THEN 1 ELSE 0 END) as resolution_met
            FROM tickets
            WHERE created_at >= ?
        ";
        
        $params = [$startDate];
        if ($userId !== null) {
            $sql .= " AND (assigned_to = ? OR created_by = ?)";
            $params[] = $userId;
            $params[] = $userId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch();
        
        $withSLA = (int)$stats['with_sla'];
        if ($withSLA > 0) {
            $stats['response_compliance'] = round(((int)$stats['response_met'] / $withSLA) * 100, 1);
            $stats['resolution_compliance'] = round(((int)$stats['resolution_met'] / $withSLA) * 100, 1);
        } else {
            $stats['response_compliance'] = 0;
            $stats['resolution_compliance'] = 0;
        }
        
        return $stats;
    }
}
