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
            '{technician_phone}' => 'Assigned technician phone',
            '{technician_email}' => 'Assigned technician email',
            '{company_name}' => 'Your company name',
            '{company_phone}' => 'Company phone number',
            '{company_email}' => 'Company email address',
            '{company_website}' => 'Company website URL',
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
                '{technician_phone}' => 'Assigned technician phone',
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
}
