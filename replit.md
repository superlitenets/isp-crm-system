# ISP CRM & Ticketing System

## Overview
This project is a PHP-based Customer Relationship Management (CRM) and ticketing system specifically designed for Internet Service Providers (ISPs). Its core purpose is to streamline customer interactions, manage support tickets efficiently, and automate various operational tasks. The system aims to provide a comprehensive solution for ISPs to manage their customer base, track equipment, process orders, handle HR functions including employee attendance and payroll, and facilitate communication through integrated SMS and WhatsApp messaging.

Key capabilities include:
- End-to-end customer and ticket management with SLA, team-based assignment, and a complaints approval workflow.
- Robust Human Resources module with biometric attendance, automated late deductions, and unified user/employee management.
- Inventory management for ISP equipment with tracking and fault reporting.
- Sales and marketing tools, including salesperson commission tracking, mobile lead capture, and an online order system.
- Public-facing landing page with dynamic service packages and M-Pesa integration for payments.
- Secure authentication and communication features like SMS notifications and WhatsApp Web integration.
- SmartOLT integration for real-time network monitoring and ONU provisioning.
- Comprehensive reporting and activity logging.
- Enhanced mobile PWA for salespersons and technicians with performance tracking and offline support.

The business vision is to offer a powerful, all-in-one platform that enhances operational efficiency, improves customer satisfaction, and supports the growth of ISPs by automating critical business processes.

## User Preferences
I prefer detailed explanations and expect the agent to ask for confirmation before making major changes. I appreciate clean, well-structured code.

## System Architecture
The system is built on PHP, utilizing a modular architecture to separate concerns.

**UI/UX Decisions:**
The system features a clean, responsive design. The public-facing landing page is designed to be modern with dynamic content, customizable hero sections, and package cards. Internal CRM interfaces prioritize clarity and ease of use for administrative and technical staff, including a mobile PWA for field personnel.

**Technical Implementations:**
- **Authentication**: Session-based authentication with password hashing, CSRF protection, SQL injection prevention (prepared statements), XSS protection, and a flexible role-based access control (RBAC) system with granular permissions.
- **Database**: PostgreSQL is used as the primary database with a clearly defined schema for modules like customers, tickets, employees, inventory, transactions, and HR.
- **SMS Integration**: Supports custom SMS gateways (any POST/GET API) and Twilio. SMS notifications use configurable templates.
- **WhatsApp Integration**: Facilitates direct messaging via WhatsApp Web.
- **Template Engine**: A custom `TemplateEngine.php` class provides rich placeholder replacement for dynamic content in messages and templates.
- **Biometric Integration**: Abstract `BiometricDevice.php` with concrete implementations for ZKTeco (Push Protocol) and Hikvision (ISAPI) for real-time attendance synchronization and late notifications.
- **M-Pesa Integration**: Handles STK Push for customer payments, C2B payments, and real-time callback processing.
- **Order System**: Public order form integration with CRM, lead capture, M-Pesa payments, and conversion to installation tickets.
- **Inventory Management**: Features bulk import/export (Excel/CSV), smart column header detection, and comprehensive equipment lifecycle tracking.
- **SLA Management**: Auto-applies policies based on ticket priority, considering business hours and holidays for accurate timer calculations.
- **Complaints Module**: Implements an approval workflow for public complaints before conversion to tickets.
- **SmartOLT Integration**: Real-time network monitoring, ONU status tracking, and provisioning capabilities.
- **Reporting & Activity Logs**: Comprehensive reports for tickets, orders, complaints, and user performance, with detailed activity logging for key system actions.
- **Ticket Commission System**: Auto-calculates employee earnings when tickets are resolved/closed. Supports configurable rates per ticket category, individual assignment, and team-based split (equal share among active members). Integrates with payroll processing.

**System Design Choices:**
The system adopts a modular design allowing for extensibility. Configuration is managed through a `config/` directory and environment variables. Key functionalities are encapsulated in dedicated PHP classes (e.g., `Auth.php`, `Customer.php`, `Ticket.php`, `SMS.php`, `Inventory.php`, `BiometricDevice.php`, `SLA.php`, `SmartOLT.php`, `ActivityLog.php`, `Reports.php`). Users and employees are unified, and roles are managed centrally via HR.

## External Dependencies
- **PostgreSQL**: Primary database.
- **Twilio**: Optional fallback for SMS notifications.
- **Advanta SMS (Kenya)**: Recommended SMS gateway integration.
- **Custom SMS Gateways**: Supports integration with any REST API (POST/GET) for SMS.
- **WhatsApp Web**: Used for direct messaging.
- **ZKTeco Biometric Devices**: Integrated for attendance tracking via Push Protocol.
- **Hikvision Biometric Devices**: Integrated for attendance tracking via ISAPI.
- **M-Pesa**: Integrated for mobile money payments (STK Push, C2B).
- **SmartOLT API**: Integrated for network monitoring and ONU management.