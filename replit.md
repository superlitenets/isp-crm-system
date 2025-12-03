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
│   ├── SMS.php           # Twilio SMS integration
│   ├── SMSGateway.php    # Custom SMS gateway integration
│   └── Settings.php      # Company settings and ticket templates
├── templates/
│   ├── login.php         # Login page
│   ├── dashboard.php     # Dashboard view
│   ├── customers.php     # Customer management views
│   ├── tickets.php       # Ticket management views
│   ├── hr.php            # Human Resources module
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
- **Settings Module**: Comprehensive configuration including:
  - Company settings (name, contact info, branding)
  - SMS gateway configuration (supports any POST/GET API)
  - Ticket response templates (reusable templates with placeholders)
- **SMS Notifications**: Automatic notifications via custom gateway or Twilio
- **Dashboard**: Overview of ticket statistics and recent activity

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
- **performance_reviews**: Employee performance evaluations
- **company_settings**: System configuration key-value pairs
- **ticket_templates**: Reusable ticket response templates

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

## Security Features
- Session-based authentication with password hashing
- CSRF protection on all forms
- SQL injection prevention with prepared statements
- XSS protection with output escaping
- Role-based access control (admin/technician)

## Recent Changes
- December 2024: Added Settings page with company settings, SMS config, and ticket templates
- December 2024: Enhanced HR module with attendance, payroll, and performance reviews
- December 2024: Improved SMS gateway to support any POST or GET API
- December 2024: Added Human Resources module (employees, departments)
- December 2024: Added custom SMS gateway support with configurable API
- December 2024: Added authentication system with CSRF protection
- December 2024: Initial implementation with customer management, ticketing, and SMS integration
