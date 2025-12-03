# ISP CRM & Ticketing System

## Overview
A PHP-based Customer Relationship Management (CRM) and ticketing system designed for Internet Service Providers. Features customer management, ticket tracking, and SMS notifications via Twilio.

## Project Structure
```
/
├── config/
│   ├── database.php      # Database connection configuration
│   └── init_db.php       # Database initialization and schema
├── src/
│   ├── Auth.php          # Authentication and CSRF protection
│   ├── Customer.php      # Customer model and operations
│   ├── Ticket.php        # Ticket model and operations
│   ├── Employee.php      # HR/Employee management
│   ├── Inventory.php     # Inventory/equipment management
│   ├── SMS.php           # Twilio SMS integration
│   ├── SMSGateway.php    # Custom SMS gateway integration
│   ├── Settings.php      # Company settings and ticket templates
│   ├── WhatsApp.php      # WhatsApp Web messaging integration
│   ├── TemplateEngine.php # Template placeholder engine
│   ├── BiometricDevice.php # Abstract biometric device class
│   ├── ZKTecoDevice.php  # ZKTeco biometric device implementation
│   ├── HikvisionDevice.php # Hikvision biometric device implementation
│   ├── BiometricSyncService.php # Biometric sync and attendance import
│   └── LateDeductionCalculator.php # Late arrival deduction calculations
├── templates/
│   ├── login.php         # Login page
│   ├── dashboard.php     # Dashboard view
│   ├── customers.php     # Customer management views
│   ├── tickets.php       # Ticket management views
│   ├── hr.php            # Human Resources module
│   ├── inventory.php     # Inventory management module
│   └── settings.php      # Settings (company, SMS, templates)
├── public/
│   └── index.php         # Main application entry point
├── vendor/               # Composer dependencies
├── composer.json         # PHP dependencies
└── replit.md             # This file
```

## Features
- **Customer Management**: Add, edit, view, and delete customers with ISP-specific fields
- **Ticketing System**: Create and manage support tickets with priority levels and status tracking
- **Human Resources**: Complete HR management including:
  - Employee management with departments and positions
  - Attendance tracking (clock in/out, overtime, work-from-home)
  - Payroll management (salary, bonuses, deductions, tax calculations)
  - Performance reviews (ratings, goals, strengths, improvement areas)
  - Late arrivals reporting with monthly statistics
  - Automatic late deduction integration with payroll
- **Biometric Integration**: Support for attendance devices:
  - ZKTeco biometric devices (Push Protocol)
  - Hikvision biometric devices (ISAPI)
  - Automatic attendance import from devices
  - Device user mapping to employees
- **Late Deduction System**: Automated late arrival penalties:
  - Configurable grace periods and work start times
  - Tiered deduction rules (e.g., 1-15 min = $5, 16-30 min = $10)
  - Department-specific or company-wide rules
  - Automatic payroll integration
- **Inventory Management**: Complete equipment tracking system:
  - Equipment categories (routers, modems, switches, cables, etc.)
  - Track serial numbers, MAC addresses, purchase details
  - Assign equipment to employees with full history
  - Loan equipment to customers with deposit tracking
  - Fault reporting and repair tracking
  - Equipment condition and status monitoring
  - Full equipment history and audit trail
- **Settings Module**: Comprehensive configuration including:
  - Company settings (name, contact info, branding)
  - SMS gateway configuration (supports any POST/GET API)
  - WhatsApp Web integration (no API key required)
  - Ticket response templates with rich placeholders
- **SMS Notifications**: Automatic notifications via custom gateway or Twilio
- **WhatsApp Web Messaging**: Send messages directly from tickets (opens WhatsApp Web)
- **Template Placeholders**: Rich placeholder system for templates
- **Dashboard**: Overview of ticket statistics and recent activity
- **Public Landing Page**: Beautiful public-facing website with:
  - Dynamic service packages managed from CRM
  - Customizable hero section, colors, and content
  - Responsive design with modern animations
  - Package cards with features, pricing, and badges
  - Contact section with company details
  - Accessible at `/` without login

## Database Schema
- **users**: Staff members (technicians, admins)
- **customers**: Customer accounts with service plans and connection details
- **tickets**: Support tickets with status, priority, and assignment
- **ticket_comments**: Comments and activity on tickets
- **sms_logs**: Log of all SMS notifications sent
- **departments**: Organization departments
- **employees**: Employee records with HR data
- **attendance**: Daily attendance records with clock in/out times
- **payroll**: Payroll records with salary, bonuses, deductions
- **payroll_deductions**: Breakdown of payroll deductions (late arrivals, etc.)
- **performance_reviews**: Employee performance evaluations
- **company_settings**: System configuration key-value pairs
- **ticket_templates**: Reusable ticket response templates
- **whatsapp_logs**: Log of WhatsApp messages sent
- **biometric_devices**: Registered biometric attendance devices
- **biometric_attendance_logs**: Raw attendance logs from devices
- **device_user_mapping**: Maps device user IDs to employees
- **late_rules**: Late arrival deduction rules configuration
- **service_packages**: ISP service plans displayed on landing page
- **equipment_categories**: Equipment category definitions
- **equipment**: Inventory items with serial numbers, MAC addresses
- **equipment_assignments**: Equipment assigned to employees
- **equipment_loans**: Equipment loaned to customers
- **equipment_faults**: Fault reports and repair tracking

## Authentication
The system includes built-in authentication:
- **Admin**: admin@isp.com / admin123
- **Technician**: john@isp.com / tech123
- **Technician**: jane@isp.com / tech123

Admin users can delete customers. All users can manage tickets and customers.

## Environment Variables Required
- `DATABASE_URL`, `PGHOST`, `PGPORT`, `PGUSER`, `PGPASSWORD`, `PGDATABASE` - PostgreSQL connection (auto-configured)

### Advanta SMS (Recommended)
The system has built-in support for Advanta SMS Kenya:
- `ADVANTA_API_KEY` - Your Advanta API Key
- `ADVANTA_PARTNER_ID` - Your Partner ID (numeric)
- `ADVANTA_SHORTCODE` - Sender ID (appears as sender name, e.g., "MyISP")
- `ADVANTA_URL` - API Endpoint (optional, defaults to https://quicksms.advantasms.com/api/services/sendsms/)

### Custom SMS Gateway (Alternative)
Supports any REST API with POST or GET methods:
- `SMS_API_URL` - Your SMS gateway API endpoint
- `SMS_API_KEY` - API key or authentication token
- `SMS_SENDER_ID` - Sender ID or phone number (default: ISP-CRM)
- `SMS_API_METHOD` - HTTP method: POST or GET (default: POST)
- `SMS_CONTENT_TYPE` - Request content type: json or form (default: json)
- `SMS_AUTH_HEADER` - Auth header type: Bearer, Basic, X-API-Key, or custom (default: Bearer)
- `SMS_PHONE_PARAM` - Parameter name for phone number (default: phone)
- `SMS_MESSAGE_PARAM` - Parameter name for message (default: message)
- `SMS_SENDER_PARAM` - Parameter name for sender ID (default: sender)

### Twilio Fallback (Optional)
- `TWILIO_ACCOUNT_SID` - Twilio Account SID
- `TWILIO_AUTH_TOKEN` - Twilio Auth Token
- `TWILIO_PHONE_NUMBER` - Twilio Phone Number

## Running the Application
The application runs on PHP's built-in web server:
```bash
php -S 0.0.0.0:5000 -t public
```

## SMS Notifications
SMS notifications are sent when:
- A new ticket is created (customer notified)
- A ticket is assigned to a technician (technician notified)
- Ticket status changes (customer notified)

The system supports:
1. **Custom SMS Gateway** - Configure your own SMS provider via environment variables
2. **Twilio** - Falls back to Twilio if custom gateway is not configured

If no SMS credentials are configured, the system operates normally without SMS.

## WhatsApp Web Integration
WhatsApp messaging works alongside SMS. From any ticket, you can:
- Send WhatsApp messages to customers with pre-filled ticket info
- Contact assigned technicians via WhatsApp
- No API key required - opens WhatsApp Web with pre-filled message

Configure default country code in Settings > WhatsApp.

## Template Placeholders
Available placeholders for ticket templates:

**Customer:**
- `{customer_name}` - Customer's full name
- `{customer_phone}` - Customer's phone number
- `{customer_email}` - Customer's email address
- `{customer_address}` - Customer's address
- `{customer_account}` - Customer's account number

**Ticket:**
- `{ticket_number}` - Ticket reference number
- `{ticket_status}` - Current ticket status
- `{ticket_subject}` - Ticket subject/title
- `{ticket_description}` - Ticket description
- `{ticket_priority}` - Ticket priority level
- `{ticket_category}` - Ticket category
- `{ticket_created}` - Ticket creation date

**Technician:**
- `{technician_name}` - Assigned technician name
- `{technician_phone}` - Assigned technician phone
- `{technician_email}` - Assigned technician email

**Company:**
- `{company_name}` - Your company name
- `{company_phone}` - Company phone number
- `{company_email}` - Company email address
- `{company_website}` - Company website URL

**Date/Time:**
- `{current_date}` - Current date
- `{current_time}` - Current time

## Security Features
- Session-based authentication with password hashing
- CSRF protection on all forms
- SQL injection prevention with prepared statements
- XSS protection with output escaping
- Role-based access control (admin/technician)

## Recent Changes
- December 2024: Added Inventory Management module for equipment tracking
- December 2024: Equipment assignment to employees and loans to customers
- December 2024: Fault reporting and repair tracking for equipment
- December 2024: Added public landing page with dynamic service packages
- December 2024: Added package management in Settings (Service Packages tab)
- December 2024: Added landing page customization in Settings (Landing Page tab)
- December 2024: Added automatic database migrations for existing installations
- December 2024: Added biometric device integration (ZKTeco, Hikvision) for attendance import
- December 2024: Added late arrival deduction system with configurable rules
- December 2024: Integrated late deductions with payroll system (automatic application)
- December 2024: Added Late Arrivals reporting tab in HR module
- December 2024: Added Settings UI for managing biometric devices and late rules
- December 2024: Added WhatsApp Web integration for messaging customers and technicians
- December 2024: Added rich template placeholders including {customer_phone}, {technician_phone}
- December 2024: Added TemplateEngine class for placeholder replacement
- December 2024: Added Settings page with company settings, SMS config, and ticket templates
- December 2024: Enhanced HR module with attendance, payroll, and performance reviews
- December 2024: Improved SMS gateway to support any POST or GET API
- December 2024: Added Human Resources module (employees, departments)
- December 2024: Added custom SMS gateway support with configurable API
- December 2024: Added authentication system with CSRF protection
- December 2024: Initial implementation with customer management, ticketing, and SMS integration
