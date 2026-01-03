<?php

namespace App;

class TemplateEngine {
    private array $placeholders = [];
    private Settings $settings;
    
    public function __construct() {
        $this->settings = new Settings();
    }
    
    public function getAvailablePlaceholders(): array {
        return [
            '{customer_name}' => 'Customer\'s full name',
            '{customer_phone}' => 'Customer\'s phone number',
            '{customer_email}' => 'Customer\'s email address',
            '{customer_address}' => 'Customer\'s address',
            '{customer_account}' => 'Customer\'s account number',
            '{ticket_number}' => 'Ticket reference number',
            '{ticket_status}' => 'Current ticket status',
            '{ticket_subject}' => 'Ticket subject/title',
            '{ticket_description}' => 'Ticket description',
            '{ticket_priority}' => 'Ticket priority level',
            '{ticket_category}' => 'Ticket category',
            '{ticket_created}' => 'Ticket creation date',
            '{technician_name}' => 'Assigned technician name',
            '{technician_phone}' => 'Assigned technician personal phone',
            '{technician_office_phone}' => 'Assigned technician office phone (shown to customers)',
            '{technician_email}' => 'Assigned technician email',
            '{company_name}' => 'Your company name',
            '{company_phone}' => 'Company phone number',
            '{company_email}' => 'Company email address',
            '{company_website}' => 'Company website URL',
            '{current_date}' => 'Current date',
            '{current_time}' => 'Current time',
        ];
    }
    
    public function getHRPlaceholders(): array {
        return [
            '{employee_name}' => 'Employee\'s full name',
            '{employee_id}' => 'Employee ID code',
            '{employee_phone}' => 'Employee\'s personal phone (for internal notifications)',
            '{employee_office_phone}' => 'Employee\'s office phone (shown to customers)',
            '{employee_email}' => 'Employee\'s email address',
            '{department_name}' => 'Employee\'s department',
            '{position}' => 'Employee\'s position/job title',
            '{clock_in_time}' => 'Actual clock-in time',
            '{clock_out_time}' => 'Actual clock-out time',
            '{work_start_time}' => 'Expected work start time',
            '{late_minutes}' => 'Minutes late',
            '{deduction_amount}' => 'Late deduction amount',
            '{currency}' => 'Currency code (e.g., KES)',
            '{attendance_date}' => 'Attendance date',
            '{hours_worked}' => 'Total hours worked',
            '{company_name}' => 'Your company name',
            '{company_phone}' => 'Company phone number',
            '{current_date}' => 'Current date',
            '{current_time}' => 'Current time',
        ];
    }
    
    public function getPlaceholderCategories(): array {
        return [
            'Customer' => [
                '{customer_name}' => 'Customer\'s full name',
                '{customer_phone}' => 'Customer\'s phone number',
                '{customer_email}' => 'Customer\'s email address',
                '{customer_address}' => 'Customer\'s address',
                '{customer_account}' => 'Customer\'s account number',
            ],
            'Ticket' => [
                '{ticket_number}' => 'Ticket reference number',
                '{ticket_status}' => 'Current ticket status',
                '{ticket_subject}' => 'Ticket subject/title',
                '{ticket_description}' => 'Ticket description',
                '{ticket_priority}' => 'Ticket priority level',
                '{ticket_category}' => 'Ticket category',
                '{ticket_created}' => 'Ticket creation date',
            ],
            'Technician' => [
                '{technician_name}' => 'Assigned technician name',
                '{technician_phone}' => 'Assigned technician personal phone',
                '{technician_office_phone}' => 'Assigned technician office phone (shown to customers)',
                '{technician_email}' => 'Assigned technician email',
            ],
            'Company' => [
                '{company_name}' => 'Your company name',
                '{company_phone}' => 'Company phone number',
                '{company_email}' => 'Company email address',
                '{company_website}' => 'Company website URL',
            ],
            'Date/Time' => [
                '{current_date}' => 'Current date',
                '{current_time}' => 'Current time',
            ],
        ];
    }
    
    public function setTicketData(array $ticket): self {
        $this->placeholders['{ticket_number}'] = $ticket['ticket_number'] ?? '';
        $this->placeholders['{ticket_status}'] = ucfirst($ticket['status'] ?? '');
        $this->placeholders['{ticket_subject}'] = $ticket['subject'] ?? '';
        $this->placeholders['{ticket_description}'] = $ticket['description'] ?? '';
        $this->placeholders['{ticket_priority}'] = ucfirst($ticket['priority'] ?? '');
        $this->placeholders['{ticket_category}'] = $ticket['category'] ?? '';
        $this->placeholders['{ticket_created}'] = isset($ticket['created_at']) 
            ? date('M j, Y', strtotime($ticket['created_at'])) 
            : '';
        return $this;
    }
    
    public function setCustomerData(array $customer): self {
        $this->placeholders['{customer_name}'] = $customer['name'] ?? '';
        $this->placeholders['{customer_phone}'] = $customer['phone'] ?? '';
        $this->placeholders['{customer_email}'] = $customer['email'] ?? '';
        $this->placeholders['{customer_address}'] = $customer['address'] ?? '';
        $this->placeholders['{customer_account}'] = $customer['account_number'] ?? '';
        return $this;
    }
    
    public function setTechnicianData(?array $technician): self {
        $this->placeholders['{technician_name}'] = $technician['name'] ?? 'Not Assigned';
        $this->placeholders['{technician_phone}'] = $technician['phone'] ?? '';
        $this->placeholders['{technician_office_phone}'] = $technician['office_phone'] ?? ($technician['phone'] ?? '');
        $this->placeholders['{technician_email}'] = $technician['email'] ?? '';
        return $this;
    }
    
    public function setCompanyData(): self {
        $companyInfo = $this->settings->getCompanyInfo();
        $this->placeholders['{company_name}'] = $companyInfo['company_name'] ?? '';
        $this->placeholders['{company_phone}'] = $companyInfo['company_phone'] ?? '';
        $this->placeholders['{company_email}'] = $companyInfo['company_email'] ?? '';
        $this->placeholders['{company_website}'] = $companyInfo['company_website'] ?? '';
        return $this;
    }
    
    public function setDateTime(): self {
        $companyInfo = $this->settings->getCompanyInfo();
        $dateFormat = $companyInfo['date_format'] ?? 'Y-m-d';
        $timeFormat = $companyInfo['time_format'] ?? 'H:i';
        
        $this->placeholders['{current_date}'] = date($dateFormat);
        $this->placeholders['{current_time}'] = date($timeFormat);
        return $this;
    }
    
    public function render(string $template): string {
        $this->setCompanyData();
        $this->setDateTime();
        
        return str_replace(
            array_keys($this->placeholders),
            array_values($this->placeholders),
            $template
        );
    }
    
    public function renderForTicket(string $template, array $ticket, ?array $customer = null, ?array $technician = null): string {
        $this->setTicketData($ticket);
        
        if ($customer) {
            $this->setCustomerData($customer);
        } elseif (isset($ticket['customer_name'])) {
            $this->placeholders['{customer_name}'] = $ticket['customer_name'];
            $this->placeholders['{customer_phone}'] = $ticket['customer_phone'] ?? '';
            $this->placeholders['{customer_account}'] = $ticket['account_number'] ?? '';
        }
        
        if ($technician) {
            $this->setTechnicianData($technician);
        } elseif (isset($ticket['assigned_name'])) {
            $this->placeholders['{technician_name}'] = $ticket['assigned_name'] ?? 'Not Assigned';
            $this->placeholders['{technician_phone}'] = $ticket['assigned_phone'] ?? '';
        }
        
        return $this->render($template);
    }
    
    public function preview(string $template): string {
        $sampleData = [
            '{customer_name}' => 'John Doe',
            '{customer_phone}' => '+254712345678',
            '{customer_email}' => 'john@example.com',
            '{customer_address}' => '123 Main Street, Nairobi',
            '{customer_account}' => 'CUS-2024-0001',
            '{ticket_number}' => 'TKT-20241203-0001',
            '{ticket_status}' => 'In Progress',
            '{ticket_subject}' => 'Internet Connection Issue',
            '{ticket_description}' => 'Unable to connect to the internet since yesterday',
            '{ticket_priority}' => 'High',
            '{ticket_category}' => 'Connectivity',
            '{ticket_created}' => date('M j, Y'),
            '{technician_name}' => 'Jane Smith',
            '{technician_phone}' => '+254798765432',
            '{technician_email}' => 'jane@company.com',
            '{company_name}' => $this->settings->get('company_name', 'ISP Company'),
            '{company_phone}' => $this->settings->get('company_phone', '+254700000000'),
            '{company_email}' => $this->settings->get('company_email', 'support@company.com'),
            '{company_website}' => $this->settings->get('company_website', 'www.company.com'),
            '{current_date}' => date('Y-m-d'),
            '{current_time}' => date('H:i'),
        ];
        
        return str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $template
        );
    }
    
    public function setEmployeeData(array $employee): self {
        $this->placeholders['{employee_name}'] = $employee['name'] ?? '';
        $this->placeholders['{employee_id}'] = $employee['employee_id'] ?? '';
        $this->placeholders['{employee_phone}'] = $employee['phone'] ?? '';
        $this->placeholders['{employee_office_phone}'] = $employee['office_phone'] ?? ($employee['phone'] ?? '');
        $this->placeholders['{employee_email}'] = $employee['email'] ?? '';
        $this->placeholders['{department_name}'] = $employee['department_name'] ?? '';
        $this->placeholders['{position}'] = $employee['position'] ?? '';
        return $this;
    }
    
    public function setAttendanceData(array $attendance): self {
        $this->placeholders['{clock_in_time}'] = isset($attendance['clock_in']) 
            ? date('h:i A', strtotime($attendance['clock_in'])) 
            : '';
        $this->placeholders['{clock_out_time}'] = isset($attendance['clock_out']) 
            ? date('h:i A', strtotime($attendance['clock_out'])) 
            : '';
        $this->placeholders['{work_start_time}'] = isset($attendance['work_start_time']) 
            ? date('h:i A', strtotime($attendance['work_start_time'])) 
            : '';
        $this->placeholders['{late_minutes}'] = $attendance['late_minutes'] ?? '0';
        $this->placeholders['{deduction_amount}'] = isset($attendance['deduction_amount']) 
            ? number_format((float)$attendance['deduction_amount'], 2) 
            : '0.00';
        $this->placeholders['{currency}'] = $attendance['currency'] ?? 'KES';
        $this->placeholders['{attendance_date}'] = isset($attendance['date']) 
            ? date('M j, Y', strtotime($attendance['date'])) 
            : date('M j, Y');
        $this->placeholders['{hours_worked}'] = isset($attendance['hours_worked']) 
            ? number_format((float)$attendance['hours_worked'], 1) 
            : '0';
        return $this;
    }
    
    public function renderForEmployee(string $template, array $employee, ?array $attendance = null): string {
        $this->setEmployeeData($employee);
        
        if ($attendance) {
            $this->setAttendanceData($attendance);
        }
        
        return $this->render($template);
    }
    
    public function previewHR(string $template): string {
        $sampleData = [
            '{employee_name}' => 'John Kamau',
            '{employee_id}' => 'EMP-2024-0012',
            '{employee_phone}' => '+254712345678',
            '{employee_office_phone}' => '+254700123456',
            '{employee_email}' => 'john.kamau@company.com',
            '{department_name}' => 'Technical Support',
            '{position}' => 'Senior Technician',
            '{clock_in_time}' => '09:25 AM',
            '{clock_out_time}' => '05:30 PM',
            '{work_start_time}' => '09:00 AM',
            '{late_minutes}' => '25',
            '{deduction_amount}' => '150.00',
            '{currency}' => 'KES',
            '{attendance_date}' => date('M j, Y'),
            '{hours_worked}' => '8.0',
            '{company_name}' => $this->settings->get('company_name', 'ISP Company'),
            '{company_phone}' => $this->settings->get('company_phone', '+254700000000'),
            '{current_date}' => date('Y-m-d'),
            '{current_time}' => date('H:i'),
        ];
        
        return str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $template
        );
    }
}
