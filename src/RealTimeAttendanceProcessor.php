<?php

namespace App;

require_once __DIR__ . '/LateDeductionCalculator.php';
require_once __DIR__ . '/SMSGateway.php';
require_once __DIR__ . '/TemplateEngine.php';
require_once __DIR__ . '/Employee.php';
require_once __DIR__ . '/Settings.php';

class RealTimeAttendanceProcessor {
    private \PDO $db;
    private LateDeductionCalculator $lateCalculator;
    private SMSGateway $smsGateway;
    private TemplateEngine $templateEngine;
    private Employee $employeeModel;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
        $this->lateCalculator = new LateDeductionCalculator($db);
        $this->smsGateway = new SMSGateway();
        $this->templateEngine = new TemplateEngine();
        $this->employeeModel = new Employee($db);
    }
    
    public function processClockIn(int $employeeId, string $clockInTime, ?string $date = null, string $source = 'biometric'): array {
        $date = $date ?? date('Y-m-d');
        $result = [
            'success' => false,
            'employee_id' => $employeeId,
            'clock_in' => $clockInTime,
            'date' => $date,
            'is_late' => false,
            'late_minutes' => 0,
            'deduction' => 0,
            'notification_sent' => false,
            'message' => ''
        ];
        
        $employee = $this->getEmployeeWithDepartment($employeeId);
        if (!$employee) {
            $result['message'] = 'Employee not found';
            return $result;
        }
        
        // Check if late penalties are globally enabled
        $settings = new \App\Settings();
        $latePenaltiesEnabled = $settings->get('late_penalties_enabled', '1') === '1';
        
        $rule = $this->lateCalculator->getRuleForEmployee($employeeId);
        
        $lateMinutes = 0;
        $deduction = 0;
        $isLate = false;
        
        if ($rule && $latePenaltiesEnabled) {
            $lateMinutes = $this->lateCalculator->calculateLateMinutes($employeeId, $clockInTime);
            
            if ($lateMinutes > 0) {
                $isLate = true;
                $deduction = $this->lateCalculator->calculateDeduction($lateMinutes, $rule);
            }
        }
        
        $saved = $this->saveAttendance($employeeId, $date, $clockInTime, null, $lateMinutes, $source);
        
        if (!$saved) {
            $result['message'] = 'Failed to save attendance record';
            return $result;
        }
        
        $result['success'] = true;
        $result['is_late'] = $isLate;
        $result['late_minutes'] = $lateMinutes;
        $result['deduction'] = $deduction;
        
        if ($isLate && $lateMinutes > 0) {
            $notificationResult = $this->sendLateNotification($employee, $clockInTime, $date, $lateMinutes, $deduction, $rule);
            $result['notification_sent'] = $notificationResult['success'];
            $result['notification_message'] = $notificationResult['message'] ?? '';
        } else {
            $clockInConfirmResult = $this->sendClockInConfirmation($employee, $clockInTime, $date);
            $result['notification_sent'] = $clockInConfirmResult['success'];
            $result['notification_message'] = $clockInConfirmResult['message'] ?? '';
        }
        
        $result['message'] = $isLate 
            ? "Clocked in {$lateMinutes} minutes late. Deduction: {$deduction} " . ($rule['currency'] ?? 'KES')
            : 'Clocked in successfully';
        
        return $result;
    }
    
    public function processClockOut(int $employeeId, string $clockOutTime, ?string $date = null, string $source = 'biometric'): array {
        $date = $date ?? date('Y-m-d');
        $result = [
            'success' => false,
            'employee_id' => $employeeId,
            'clock_out' => $clockOutTime,
            'date' => $date,
            'hours_worked' => null,
            'message' => ''
        ];
        
        // Check minimum clock out time (configurable, default 5:00 PM)
        $settings = new \App\Settings();
        $minClockOutHour = (int)$settings->get('min_clock_out_hour', '17');
        $clockOutHour = (int)date('H', strtotime($clockOutTime));
        if ($clockOutHour < $minClockOutHour) {
            $minTimeFormatted = date('g:i A', strtotime("$minClockOutHour:00"));
            $result['message'] = "Clock out is only allowed after $minTimeFormatted";
            return $result;
        }
        
        $attendance = $this->getAttendance($employeeId, $date);
        
        if (!$attendance || !$attendance['clock_in']) {
            $result['message'] = 'No clock-in record found for today';
            return $result;
        }
        
        $clockIn = strtotime($attendance['clock_in']);
        $clockOut = strtotime($clockOutTime);
        $hoursWorked = null;
        
        if ($clockOut > $clockIn) {
            $hoursWorked = round(($clockOut - $clockIn) / 3600, 2);
        }
        
        $stmt = $this->db->prepare("
            UPDATE attendance 
            SET clock_out = ?, hours_worked = ?, source = ?, updated_at = CURRENT_TIMESTAMP
            WHERE employee_id = ? AND date = ?
        ");
        
        $saved = $stmt->execute([$clockOutTime, $hoursWorked, $source, $employeeId, $date]);
        
        if ($saved) {
            $result['success'] = true;
            $result['hours_worked'] = $hoursWorked;
            $result['message'] = "Clocked out. Hours worked: " . ($hoursWorked ? number_format($hoursWorked, 1) : 'N/A');
        } else {
            $result['message'] = 'Failed to update attendance record';
        }
        
        return $result;
    }
    
    public function processBiometricEvent(int $deviceId, string $deviceUserId, string $logTime, string $direction = 'unknown', string $verificationType = 'unknown'): array {
        $result = [
            'success' => false,
            'message' => '',
            'employee_id' => null,
            'device_id' => $deviceId,
            'device_user_id' => $deviceUserId,
            'verification_type' => $verificationType,
            'processed' => false
        ];
        
        $employeeId = $this->getEmployeeIdFromDeviceUser($deviceId, $deviceUserId);
        
        if (!$employeeId) {
            $result['message'] = "Device user '{$deviceUserId}' on device {$deviceId} is not mapped to any employee. Please configure user mapping in Settings > Biometric Devices.";
            $result['error_code'] = 'UNMAPPED_USER';
            return $result;
        }
        
        $result['employee_id'] = $employeeId;
        
        $date = date('Y-m-d', strtotime($logTime));
        $time = date('H:i:s', strtotime($logTime));
        
        if ($direction === 'in' || $direction === 'unknown') {
            $existingAttendance = $this->getAttendance($employeeId, $date);
            
            if (!$existingAttendance || !$existingAttendance['clock_in']) {
                $clockInResult = $this->processClockIn($employeeId, $time, $date, 'biometric');
                $result = array_merge($result, $clockInResult);
                $result['processed'] = true;
                return $result;
            }
        }
        
        if ($direction === 'out') {
            $clockOutResult = $this->processClockOut($employeeId, $time, $date, 'biometric');
            $result = array_merge($result, $clockOutResult);
            $result['processed'] = true;
            return $result;
        }
        
        if ($direction === 'unknown') {
            $existingAttendance = $this->getAttendance($employeeId, $date);
            if ($existingAttendance && $existingAttendance['clock_in']) {
                $clockOutResult = $this->processClockOut($employeeId, $time, $date, 'biometric');
                $result = array_merge($result, $clockOutResult);
                $result['processed'] = true;
            }
        }
        
        $result['success'] = true;
        $result['message'] = $result['message'] ?: 'Event processed';
        
        return $result;
    }
    
    private function sendLateNotification(array $employee, string $clockInTime, string $date, int $lateMinutes, float $deduction, ?array $rule): array {
        $result = [
            'success' => false,
            'message' => ''
        ];
        
        $template = $this->getLateArrivalTemplate();
        
        if (!$template || !$template['is_active']) {
            $result['message'] = 'Late notification template not active';
            return $result;
        }
        
        $phone = $employee['phone'] ?? null;
        if (!$phone) {
            $result['message'] = 'Employee phone number not available';
            return $result;
        }
        
        $attendanceData = [
            'clock_in' => $clockInTime,
            'work_start_time' => $rule['work_start_time'] ?? '09:00',
            'late_minutes' => $lateMinutes,
            'deduction_amount' => $deduction,
            'currency' => $rule['currency'] ?? 'KES',
            'date' => $date
        ];
        
        $message = $this->templateEngine->renderForEmployee(
            $template['sms_template'],
            $employee,
            $attendanceData
        );
        
        $sendResult = ['success' => false];
        $channel = 'sms';

        $whatsapp = new \App\WhatsApp();
        if ($whatsapp->isEnabled()) {
            $channel = 'whatsapp';
            $sendResult = $whatsapp->send($phone, $message);
        }

        if (!$sendResult['success'] && $template['send_sms']) {
            $channel = 'sms';
            $sendResult = $this->smsGateway->send($phone, $message);
        }
        
        $this->logNotification(
            $employee['id'],
            $template['id'],
            $date,
            $clockInTime,
            $lateMinutes,
            $deduction,
            $phone,
            $message,
            $sendResult['success'] ? 'sent' : 'failed',
            array_merge($sendResult, ['channel' => $channel])
        );
        
        if ($sendResult['success']) {
            $result['success'] = true;
            $result['message'] = "Late arrival notification sent via $channel";
        } else {
            $result['message'] = 'Failed to send notification: ' . ($sendResult['error'] ?? 'Unknown error');
        }
        
        return $result;
    }
    
    private function sendClockInConfirmation(array $employee, string $clockInTime, string $date): array {
        $result = ['success' => false, 'message' => ''];

        $phone = $employee['phone'] ?? null;
        if (!$phone) {
            $result['message'] = 'Employee phone number not available';
            return $result;
        }

        $whatsapp = new \App\WhatsApp();
        if (!$whatsapp->isEnabled()) {
            $result['message'] = 'WhatsApp not enabled';
            return $result;
        }

        $settings = new \App\Settings();
        $companyName = $settings->get('company_name', 'Your ISP');
        $clockInFormatted = date('g:i A', strtotime($clockInTime));
        $dateFormatted = date('l, d M Y', strtotime($date));

        $message = "Good morning {$employee['full_name']}!\n\n";
        $message .= "Clock-in recorded at *{$clockInFormatted}*\n";
        $message .= "Date: {$dateFormatted}\n\n";
        $message .= "Have a productive day!\n";
        $message .= "_{$companyName} - HR_";

        $sendResult = $whatsapp->send($phone, $message);

        if ($sendResult['success'] ?? false) {
            $result['success'] = true;
            $result['message'] = 'Clock-in confirmation sent via WhatsApp';
        } else {
            $result['message'] = 'Failed to send clock-in confirmation: ' . ($sendResult['error'] ?? 'Unknown');
        }

        return $result;
    }

    private function getLateArrivalTemplate(): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM hr_notification_templates 
            WHERE event_type = 'late_arrival' AND is_active = TRUE 
            LIMIT 1
        ");
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    private function getEmployeeWithDepartment(int $employeeId): ?array {
        $stmt = $this->db->prepare("
            SELECT e.*, d.name as department_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.id = ?
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    private function getEmployeeIdFromDeviceUser(int $deviceId, string $deviceUserId): ?int {
        $stmt = $this->db->prepare("
            SELECT employee_id FROM device_user_mapping 
            WHERE device_id = ? AND device_user_id = ?
        ");
        $stmt->execute([$deviceId, $deviceUserId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? (int)$result['employee_id'] : null;
    }
    
    private function saveAttendance(int $employeeId, string $date, ?string $clockIn, ?string $clockOut, int $lateMinutes, string $source): bool {
        $stmt = $this->db->prepare("
            INSERT INTO attendance (employee_id, date, clock_in, clock_out, late_minutes, status, source)
            VALUES (?, ?, ?, ?, ?, 'present', ?)
            ON CONFLICT (employee_id, date) 
            DO UPDATE SET 
                clock_in = COALESCE(EXCLUDED.clock_in, attendance.clock_in),
                clock_out = COALESCE(EXCLUDED.clock_out, attendance.clock_out),
                late_minutes = EXCLUDED.late_minutes,
                source = EXCLUDED.source,
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([$employeeId, $date, $clockIn, $clockOut, $lateMinutes, $source]);
    }
    
    private function getAttendance(int $employeeId, string $date): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM attendance WHERE employee_id = ? AND date = ?
        ");
        $stmt->execute([$employeeId, $date]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    private function logNotification(
        int $employeeId,
        int $templateId,
        string $date,
        string $clockInTime,
        int $lateMinutes,
        float $deduction,
        string $phone,
        string $message,
        string $status,
        array $response
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO attendance_notification_logs 
            (employee_id, notification_template_id, attendance_date, clock_in_time, late_minutes, 
             deduction_amount, notification_type, phone, message, status, response_data, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, 'sms', ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([
            $employeeId,
            $templateId,
            $date,
            $clockInTime,
            $lateMinutes,
            $deduction,
            $phone,
            $message,
            $status,
            json_encode($response)
        ]);
    }
    
    public function getNotificationLogs(?int $employeeId = null, ?string $dateFrom = null, ?string $dateTo = null, int $limit = 100): array {
        try {
            $sql = "
                SELECT anl.*, e.name as employee_name, e.employee_id as employee_code, 
                       ht.name as template_name
                FROM attendance_notification_logs anl
                LEFT JOIN employees e ON anl.employee_id = e.id
                LEFT JOIN hr_notification_templates ht ON anl.notification_template_id = ht.id
                WHERE 1=1
            ";
            $params = [];
            
            if ($employeeId) {
                $sql .= " AND anl.employee_id = ?";
                $params[] = $employeeId;
            }
            
            if ($dateFrom) {
                $sql .= " AND anl.attendance_date >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $sql .= " AND anl.attendance_date <= ?";
                $params[] = $dateTo;
            }
            
            $sql .= " ORDER BY anl.created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("getNotificationLogs error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getHRTemplates(?string $category = null, bool $activeOnly = false): array {
        $sql = "SELECT * FROM hr_notification_templates WHERE 1=1";
        $params = [];
        
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        if ($activeOnly) {
            $sql .= " AND is_active = TRUE";
        }
        
        $sql .= " ORDER BY category, name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getHRTemplate(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM hr_notification_templates WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createHRTemplate(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO hr_notification_templates 
            (name, category, event_type, subject, sms_template, email_template, is_active, send_sms, send_email)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['category'] ?? 'attendance',
            $data['event_type'],
            $data['subject'] ?? null,
            $data['sms_template'] ?? null,
            $data['email_template'] ?? null,
            isset($data['is_active']) ? (bool)$data['is_active'] : true,
            isset($data['send_sms']) ? (bool)$data['send_sms'] : true,
            isset($data['send_email']) ? (bool)$data['send_email'] : false
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    public function updateHRTemplate(int $id, array $data): bool {
        $fields = [];
        $params = [];
        
        $stringFields = ['name', 'category', 'event_type', 'subject', 'sms_template', 'email_template'];
        foreach ($stringFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        $boolFields = ['is_active', 'send_sms', 'send_email'];
        foreach ($boolFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = (bool)$data[$field];
            }
        }
        
        if (empty($fields)) {
            return true;
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $sql = "UPDATE hr_notification_templates SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function deleteHRTemplate(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM hr_notification_templates WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getEventTypes(): array {
        return [
            'late_arrival' => 'Late Arrival',
            'early_departure' => 'Early Departure',
            'overtime' => 'Overtime Notification',
            'absent' => 'Absence Alert',
            'attendance_reminder' => 'Attendance Reminder',
            'payroll_ready' => 'Payroll Ready'
        ];
    }
    
    public function getTodayLateArrivals(): array {
        $today = date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT a.*, e.name as employee_name, e.employee_id as employee_code, 
                   e.phone, d.name as department_name
            FROM attendance a
            JOIN employees e ON a.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE a.date = ? AND a.late_minutes > 0
            ORDER BY a.late_minutes DESC
        ");
        $stmt->execute([$today]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getRealtimeStats(): array {
        $today = date('Y-m-d');
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'active'");
        $totalEmployees = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM attendance WHERE date = ?");
        $stmt->execute([$today]);
        $clockedIn = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND late_minutes > 0");
        $stmt->execute([$today]);
        $lateToday = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND clock_out IS NOT NULL");
        $stmt->execute([$today]);
        $clockedOut = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM attendance_notification_logs 
            WHERE attendance_date = ? AND status = 'sent'
        ");
        $stmt->execute([$today]);
        $notificationsSent = (int)$stmt->fetchColumn();
        
        return [
            'total_employees' => $totalEmployees,
            'clocked_in' => $clockedIn,
            'not_clocked_in' => max(0, $totalEmployees - $clockedIn),
            'late_today' => $lateToday,
            'on_time' => $clockedIn - $lateToday,
            'clocked_out' => $clockedOut,
            'notifications_sent' => $notificationsSent
        ];
    }
}
