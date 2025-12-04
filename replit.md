# ISP CRM & Ticketing System

## Recent Changes
- **December 2024**: Added Team-Based Ticket Assignment
  - Created `teams` and `team_members` database tables
  - Tickets can now be assigned to teams AND/OR individuals
  - Settings > Teams tab for managing teams and team members
  - Team notifications: all team members receive SMS when ticket is assigned to team
  - Ticket list and view pages show team assignment
- **December 2024**: Integrated Users with Employees
  - Users and employees are now unified: assign system roles when adding employees
  - HR employee form now includes role/permissions selection
  - When creating an employee, login details (email, password, role) are now required
  - Settings renamed to "Roles & Permissions" (users managed through HR)
  - Employee::createUserAccount() and updateUserRole() handle role assignment
- **December 2024**: Added Flexible Roles & Permissions System
  - Database tables: `roles`, `permissions`, `role_permissions`
  - Default roles: Administrator, Manager, Technician, Salesperson, Viewer
  - Granular permissions by category: customers, tickets, hr, inventory, orders, payments, settings, users, reports
  - Auth class methods: `can()`, `canAny()`, `canAll()`, `requirePermission()`
  - Roles & Permissions management UI in Settings
- **December 2024**: Added Mobile PWA for salespersons and technicians
  - Accessible at `/mobile/` - installable on Android devices
  - Salesperson features: orders, commissions, leads tracking
  - Technician features: tickets, attendance clock in/out, equipment
  - Offline support via service worker
- Added inventory database tables to auto-migration
- Fixed PHP 8.3 compatibility in Docker setup

## Overview
This project is a PHP-based Customer Relationship Management (CRM) and ticketing system specifically designed for Internet Service Providers (ISPs). Its core purpose is to streamline customer interactions, manage support tickets efficiently, and automate various operational tasks. The system aims to provide a comprehensive solution for ISPs to manage their customer base, track equipment, process orders, handle HR functions including employee attendance and payroll, and facilitate communication through integrated SMS and WhatsApp messaging.

Key capabilities include:
- End-to-end customer and ticket management.
- Robust Human Resources module with biometric attendance and automated late deductions.
- Inventory management for ISP equipment with tracking and fault reporting.
- Sales and marketing tools, including salesperson commission tracking and an online order system.
- Public-facing landing page with dynamic service packages and M-Pesa integration for payments.
- Secure authentication and communication features like SMS notifications and WhatsApp Web integration.

The business vision is to offer a powerful, all-in-one platform that enhances operational efficiency, improves customer satisfaction, and supports the growth of ISPs by automating critical business processes.

## User Preferences
I prefer detailed explanations and expect the agent to ask for confirmation before making major changes. I appreciate clean, well-structured code.

## System Architecture
The system is built on PHP, utilizing a modular architecture to separate concerns.

**UI/UX Decisions:**
The system features a clean, responsive design. The public-facing landing page is designed to be beautiful and modern, with dynamic content, customizable hero sections, and package cards to showcase service offerings. Internal CRM interfaces prioritize clarity and ease of use for administrative and technical staff.

**Technical Implementations:**
- **Authentication**: Session-based authentication with password hashing, CSRF protection, SQL injection prevention (prepared statements), XSS protection, and role-based access control (admin/technician).
- **Database**: PostgreSQL is used as the primary database, with a clearly defined schema for various modules like users, customers, tickets, employees, inventory, and transactions.
- **SMS Integration**: Supports custom SMS gateways (any POST/GET API) and Twilio as a fallback.
- **WhatsApp Integration**: Facilitates direct messaging via WhatsApp Web without requiring an API key.
- **Template Engine**: A custom `TemplateEngine.php` class provides rich placeholder replacement for dynamic content in messages and templates.
- **Biometric Integration**: Abstract `BiometricDevice.php` class with concrete implementations for ZKTeco (Push Protocol) and Hikvision (ISAPI) devices for attendance synchronization.
- **M-Pesa Integration**: Handles STK Push for customer payments, C2B payments, and real-time callback processing for payment status updates, including sandbox mode for testing.
- **Order System**: Public order form integration with the CRM, enabling customer detail collection, optional M-Pesa payments, and conversion of confirmed orders to installation tickets.
- **Inventory Management**: Features bulk import/export (Excel/CSV), smart column header detection, and comprehensive tracking of equipment lifecycle.

**Feature Specifications:**
- **Customer Management**: CRUD operations for customer data with ISP-specific fields.
- **Ticketing System**: Comprehensive ticket lifecycle management (creation, assignment, status, priority, comments).
- **Human Resources**: Employee records, attendance tracking (biometric integration, clock in/out), payroll (salary, deductions, taxes), and performance reviews. Automated late deduction system.
- **Inventory Management**: Tracking equipment categories, serial numbers, MAC addresses, assignments, loans, fault reporting, and audit trails.
- **Sales & Marketing**: Salesperson management, commission tracking, performance leaderboards, and order attribution.
- **Settings Module**: Centralized configuration for company details, SMS gateway, WhatsApp, and ticket templates.
- **Public Landing Page**: Dynamic display of service packages, customizable content, and online order submission.

**System Design Choices:**
The system adopts a modular design allowing for extensibility. Configuration is managed through a `config/` directory and environment variables. Key functionalities are encapsulated in dedicated PHP classes (e.g., `Auth.php`, `Customer.php`, `Ticket.php`, `SMS.php`, `Inventory.php`, `BiometricDevice.php`).

## External Dependencies
- **PostgreSQL**: Primary database for data storage.
- **Twilio**: Optional fallback for SMS notifications.
- **Advanta SMS (Kenya)**: Recommended SMS gateway integration.
- **Custom SMS Gateways**: Supports integration with any REST API (POST/GET) for SMS.
- **WhatsApp Web**: Used for direct messaging.
- **ZKTeco Biometric Devices**: Integrated for attendance tracking via Push Protocol.
- **Hikvision Biometric Devices**: Integrated for attendance tracking via ISAPI.
- **M-Pesa**: Integrated for mobile money payments (STK Push, C2B).