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
│   ├── Customer.php      # Customer model and operations
│   ├── Ticket.php        # Ticket model and operations
│   └── SMS.php           # Twilio SMS integration
├── templates/
│   ├── dashboard.php     # Dashboard view
│   ├── customers.php     # Customer management views
│   └── tickets.php       # Ticket management views
├── public/
│   └── index.php         # Main application entry point
├── vendor/               # Composer dependencies
├── composer.json         # PHP dependencies
└── replit.md             # This file
```

## Features
- **Customer Management**: Add, edit, view, and delete customers with ISP-specific fields
- **Ticketing System**: Create and manage support tickets with priority levels and status tracking
- **SMS Notifications**: Automatic notifications to customers and technicians via Twilio
- **Dashboard**: Overview of ticket statistics and recent activity

## Database Schema
- **users**: Staff members (technicians, admins)
- **customers**: Customer accounts with service plans and connection details
- **tickets**: Support tickets with status, priority, and assignment
- **ticket_comments**: Comments and activity on tickets
- **sms_logs**: Log of all SMS notifications sent

## Authentication
The system includes built-in authentication:
- **Admin**: admin@isp.com / admin123
- **Technician**: john@isp.com / tech123
- **Technician**: jane@isp.com / tech123

Admin users can delete customers. All users can manage tickets and customers.

## Environment Variables Required
- `DATABASE_URL`, `PGHOST`, `PGPORT`, `PGUSER`, `PGPASSWORD`, `PGDATABASE` - PostgreSQL connection (auto-configured)
- `TWILIO_ACCOUNT_SID` - Twilio Account SID (optional, for SMS)
- `TWILIO_AUTH_TOKEN` - Twilio Auth Token (optional, for SMS)
- `TWILIO_PHONE_NUMBER` - Twilio Phone Number (optional, for SMS)

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

If Twilio credentials are not configured, the system operates normally without SMS.

## Security Features
- Session-based authentication with password hashing
- CSRF protection on all forms
- SQL injection prevention with prepared statements
- XSS protection with output escaping
- Role-based access control (admin/technician)

## Recent Changes
- December 2024: Added authentication system with CSRF protection
- December 2024: Initial implementation with customer management, ticketing, and SMS integration
