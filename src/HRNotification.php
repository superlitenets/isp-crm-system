<?php

namespace App;

class HRNotification {
    private \PDO $db;
    private SMS $sms;
    private WhatsApp $whatsapp;
    private Settings $settings;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
        $this->sms = new SMS();
        $this->whatsapp = new WhatsApp();
        $this->settings = new Settings();
    }
    
    private function sendNotification(string $phone, string $message): array {
        $results = ['sms' => false, 'whatsapp' => false];
        
        if ($this->settings->get('hr_notify_sms', '1') === '1') {
            $smsResult = $this->sms->send($phone, $message);
            $results['sms'] = $smsResult['success'] ?? false;
        }
        
        if ($this->settings->get('hr_notify_whatsapp', '1') === '1') {
            $waResult = $this->whatsapp->send($phone, $message);
            $results['whatsapp'] = $waResult['success'] ?? false;
        }
        
        return $results;
    }
    
    public function getTemplate(string $eventType): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM hr_notification_templates 
            WHERE event_type = ? AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute([$eventType]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    public function sendLeaveNotification(string $eventType, array $request): bool {
        $template = $this->getTemplate($eventType);
        if (!$template || !$template['send_sms']) {
            return false;
        }
        
        $employee = $this->getEmployee($request['employee_id']);
        $employeeName = $employee['name'] ?? $request['employee_name'] ?? 'Employee';
        $employeeCode = $employee['employee_id'] ?? $request['employee_code'] ?? '';
        
        $companyName = $this->settings->get('company_name', 'ISP CRM');
        $currency = $this->settings->get('currency', 'KES');
        
        $placeholders = [
            '{employee_name}' => $employeeName,
            '{employee_code}' => $employeeCode,
            '{leave_type}' => $request['leave_type_name'] ?? $request['leave_type'] ?? '',
            '{total_days}' => $request['total_days'] ?? '',
            '{start_date}' => isset($request['start_date']) ? date('M j, Y', strtotime($request['start_date'])) : '',
            '{end_date}' => isset($request['end_date']) ? date('M j, Y', strtotime($request['end_date'])) : '',
            '{reason}' => $request['reason'] ?? 'Not specified',
            '{rejection_reason}' => $request['rejection_reason'] ?? 'No reason provided',
            '{status}' => $request['status'] ?? '',
            '{company_name}' => $companyName,
            '{currency}' => $currency
        ];
        
        $message = str_replace(array_keys($placeholders), array_values($placeholders), $template['sms_template']);
        
        if ($eventType === 'leave_request_created') {
            $adminPhone = $this->getAdminPhone();
            if ($adminPhone) {
                $result = $this->sendNotification($adminPhone, $message);
                return $result['sms'] || $result['whatsapp'];
            }
            return false;
        } else {
            if ($employee && !empty($employee['phone'])) {
                $result = $this->sendNotification($employee['phone'], $message);
                return $result['sms'] || $result['whatsapp'];
            }
            return false;
        }
    }
    
    public function sendAdvanceNotification(string $eventType, array $advance): bool {
        $template = $this->getTemplate($eventType);
        if (!$template || !$template['send_sms']) {
            return false;
        }
        
        $employee = $this->getEmployee($advance['employee_id']);
        $employeeName = $employee['name'] ?? $advance['employee_name'] ?? 'Employee';
        $employeeCode = $employee['employee_id'] ?? $advance['employee_code'] ?? '';
        
        $companyName = $this->settings->get('company_name', 'ISP CRM');
        $currency = $this->settings->get('currency', 'KES');
        
        $placeholders = [
            '{employee_name}' => $employeeName,
            '{employee_code}' => $employeeCode,
            '{amount}' => number_format($advance['amount'] ?? 0, 2),
            '{balance}' => number_format($advance['balance'] ?? $advance['amount'] ?? 0, 2),
            '{reason}' => $advance['reason'] ?? 'Not specified',
            '{rejection_reason}' => $advance['notes'] ?? 'No reason provided',
            '{repayment_type}' => $advance['repayment_type'] ?? 'monthly',
            '{repayment_installments}' => $advance['repayment_installments'] ?? 1,
            '{repayment_amount}' => number_format($advance['repayment_amount'] ?? 0, 2),
            '{next_deduction_date}' => isset($advance['next_deduction_date']) ? date('M j, Y', strtotime($advance['next_deduction_date'])) : 'TBD',
            '{status}' => $advance['status'] ?? '',
            '{company_name}' => $companyName,
            '{currency}' => $currency
        ];
        
        $message = str_replace(array_keys($placeholders), array_values($placeholders), $template['sms_template']);
        
        if ($eventType === 'advance_request_created') {
            $adminPhone = $this->getAdminPhone();
            if ($adminPhone) {
                $result = $this->sendNotification($adminPhone, $message);
                return $result['sms'] || $result['whatsapp'];
            }
            return false;
        } else {
            if ($employee && !empty($employee['phone'])) {
                $result = $this->sendNotification($employee['phone'], $message);
                return $result['sms'] || $result['whatsapp'];
            }
            return false;
        }
    }
    
    public function createInAppNotification(int $userId, string $title, string $message, string $type = 'info', ?string $link = null): int {
        $stmt = $this->db->prepare("
            INSERT INTO user_notifications (user_id, title, message, type)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $title, $message, $type]);
        return (int)$this->db->lastInsertId();
    }
    
    public function notifyAdminsOfLeaveRequest(array $request): void {
        $admins = $this->getAdminUsers();
        foreach ($admins as $admin) {
            $this->createInAppNotification(
                $admin['id'],
                'New Leave Request',
                "{$request['employee_name']} has requested {$request['total_days']} days leave ({$request['leave_type_name']}).",
                'info',
                '?page=hr&subpage=leave&tab=requests'
            );
        }
    }
    
    public function notifyAdminsOfAdvanceRequest(array $advance): void {
        $admins = $this->getAdminUsers();
        $currency = $this->settings->get('currency', 'KES');
        foreach ($admins as $admin) {
            $this->createInAppNotification(
                $admin['id'],
                'New Salary Advance Request',
                "{$advance['employee_name']} has requested a salary advance of {$currency} " . number_format($advance['amount'], 2),
                'warning',
                '?page=hr&subpage=advances'
            );
        }
    }
    
    public function notifyEmployeeOfLeaveDecision(array $request): void {
        $userId = $this->getEmployeeUserId($request['employee_id']);
        if (!$userId) return;
        
        $status = strtoupper($request['status']);
        $type = $request['status'] === 'approved' ? 'success' : 'danger';
        
        $this->createInAppNotification(
            $userId,
            "Leave Request {$status}",
            "Your leave request for {$request['total_days']} days has been {$request['status']}.",
            $type,
            '?page=hr&subpage=leave&tab=requests'
        );
    }
    
    public function notifyEmployeeOfAdvanceDecision(array $advance): void {
        $userId = $this->getEmployeeUserId($advance['employee_id']);
        if (!$userId) return;
        
        $currency = $this->settings->get('currency', 'KES');
        $status = strtoupper($advance['status']);
        $type = $advance['status'] === 'approved' ? 'success' : 'danger';
        
        $this->createInAppNotification(
            $userId,
            "Salary Advance {$status}",
            "Your salary advance request for {$currency} " . number_format($advance['amount'], 2) . " has been {$advance['status']}.",
            $type,
            '?page=hr&subpage=advances'
        );
    }
    
    private function getEmployee(int $employeeId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    private function getEmployeeUserId(int $employeeId): ?int {
        $stmt = $this->db->prepare("SELECT user_id FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $result = $stmt->fetchColumn();
        return $result ? (int)$result : null;
    }
    
    private function getAdminPhone(): ?string {
        $phone = $this->settings->get('admin_notification_phone');
        if ($phone) return $phone;
        
        $stmt = $this->db->query("
            SELECT e.phone FROM employees e
            JOIN users u ON e.user_id = u.id
            WHERE u.role = 'admin' AND e.phone IS NOT NULL
            LIMIT 1
        ");
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }
    
    private function getAdminUsers(): array {
        $stmt = $this->db->query("
            SELECT id, name FROM users WHERE role = 'admin' AND is_active = TRUE
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAllTemplates(): array {
        $stmt = $this->db->query("SELECT * FROM hr_notification_templates ORDER BY category, name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function updateTemplate(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE hr_notification_templates 
            SET sms_template = ?, is_active = ?, send_sms = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['sms_template'],
            !empty($data['is_active']),
            !empty($data['send_sms']),
            $id
        ]);
    }
}
