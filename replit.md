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
- Enhanced mobile PWA for salespersons and technicians with performance tracking, offline support, dark mode, customer search, GPS navigation, notifications center, attendance history, leave requests, and salary advance requests.

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
- **WhatsApp Integration**: Full-featured WhatsApp Web integration with:
  - Real-time chat interface with conversation list and message history
  - Automatic customer linking based on phone number matching
  - Database storage for conversations and messages (whatsapp_conversations, whatsapp_messages)
  - Unread message tracking and conversation previews
  - Media message support (images, audio, documents)
  - Session status monitoring with QR code display
  - Node.js service running Puppeteer for WhatsApp Web automation
- **Template Engine**: A custom `TemplateEngine.php` class provides rich placeholder replacement for dynamic content in messages and templates.
- **Biometric Integration**: Abstract `BiometricDevice.php` with concrete implementations for ZKTeco (Push Protocol) and Hikvision (ISAPI) for real-time attendance synchronization and late notifications.
  - **Hikvision Remote Fingerprint Enrollment**: ISAPI-based remote fingerprint capture matching IVMS-4200 functionality:
    - `captureFingerprint()` - Triggers device to enter capture mode, employee places finger on scanner
    - `setupFingerprint()` - Upload fingerprint template data directly to device
    - `getFingerprints()` - Retrieve enrolled fingerprints for an employee
    - `deleteFingerprint()` - Remove fingerprints from device
    - `getFingerprintCapabilities()` - Check device remote capture support
  - **ZKTeco K40 (Push Protocol Only)**: The ZKTeco K40 uses Push Protocol where the device pushes attendance logs TO the server. Remote user/fingerprint management via UDP requires direct network access to the device (same LAN or VPN), which is not available when the CRM runs in a datacenter. Users and fingerprints must be enrolled directly on the device.
    - Attendance logs sync automatically via Push Protocol (device → server)
    - The ZKTecoDevice.php class contains UDP protocol implementation for future use if VPN is configured
- **M-Pesa Integration**: Handles STK Push for customer payments, C2B payments, and real-time callback processing.
- **Order System**: Public order form integration with CRM, lead capture, M-Pesa payments, and conversion to installation tickets.
- **Inventory Management**: Features bulk import/export (Excel/CSV), smart column header detection, and comprehensive equipment lifecycle tracking.
- **SLA Management**: Auto-applies policies based on ticket priority, considering business hours and holidays for accurate timer calculations.
- **Complaints Module**: Implements an approval workflow for public complaints before conversion to tickets.
- **SmartOLT Integration**: Real-time network monitoring, ONU status tracking, and provisioning capabilities.
- **Reporting & Activity Logs**: Comprehensive reports for tickets, orders, complaints, and user performance, with detailed activity logging for key system actions.
- **Ticket Commission System**: Auto-calculates employee earnings when tickets are resolved/closed. Supports configurable rates per ticket category, individual assignment, and team-based split (equal share among active members). Integrates with payroll processing.
- **Ticket Enhancements (Dec 2025)**: Comprehensive improvements including:
  - Timeline/activity history showing chronological ticket events (comments, status changes, SLA logs, escalations)
  - Quick status change buttons with automatic commission calculation
  - Customer satisfaction rating system (5-star with feedback) after ticket closure
  - Statistics dashboard cards (open, in-progress, resolved, SLA breached, escalated, avg satisfaction)
  - Ticket escalation feature with reason, reassignment, priority change, and notifications
  - Advanced filters for escalated tickets and SLA breached tickets
- **Multi-Branch Support (Dec 2025)**: Organize operations across multiple physical locations:
  - Branch management with code, address, phone, email, manager assignment
  - Each branch can have its own WhatsApp group for daily summaries
  - Employees can be attached/detached to branches (many-to-many relationship)
  - Tickets can be assigned to specific branches
  - Teams can be linked to branches
  - Branch-specific daily summary reports sent to WhatsApp groups via cron job
  - Settings UI for branch CRUD and employee assignment
- **Salary Advance System (Dec 2025)**: Employee salary advance management:
  - Request, approve, reject, and disburse salary advances
  - Flexible repayment schedules (weekly, bi-weekly, monthly)
  - Track repayments and outstanding balances
  - Automatic integration with payroll as deductions
  - Mobile API for employees to request advances
  - SMS notifications on request creation, approval, rejection, and disbursement
  - In-app notifications for admins and employees
- **Leave Management System (Dec 2025)**: Comprehensive leave tracking:
  - Multiple leave types (Annual, Sick, Unpaid, Maternity, Paternity, Compassionate)
  - 21 days annual leave limit enforced with validation (defaults to 21 if not configured)
  - Monthly accrual (trickle-down: 1.75 days/month)
  - Leave request and approval workflow with balance validation
  - Balance tracking with carryover support
  - Public holidays calendar
  - Mobile API for leave requests from PWA
  - Cron job for monthly leave accrual
  - SMS notifications on request creation, approval, and rejection
  - In-app notifications for admins and employees
- **HR Notification System (Dec 2025)**: Configurable SMS templates for HR events:
  - Template management in hr_notification_templates table
  - Supports leave and salary advance events
  - Customizable message placeholders for employee name, dates, amounts, etc.
  - Admin phone notification for new requests
  - Employee phone notification for approvals/rejections
- **Accounting Module (Dec 2025)**: Comprehensive financial management:
  - Dashboard with receivables, payables, and monthly summaries
  - Chart of Accounts with predefined categories
  - Products/Services catalog for invoices
  - Customer Invoices: create, edit, view with line items, VAT calculation
  - Invoice payment recording with balance tracking
  - Vendors/Suppliers management
  - Expense tracking by category
  - Customer payments with M-Pesa integration
  - Reports: Profit & Loss, AR Aging, AP Aging
  - Configurable tax rates (16% VAT default)
  - Auto-generated invoice/quote/payment numbers
  - **Quotes Module**: Create, edit, view quotes with convert-to-invoice functionality
  - **Bills/Purchase Orders**: Track vendor bills with line items and due dates
  - **M-Pesa Invoice Payments**: Direct STK Push integration from invoice view for quick payment collection
  - **Unified Payments Subpage**: M-Pesa STK Push form with customer selection, transaction history, and invoice linking all consolidated under Accounting → Payments
- **Billing System Integration (Dec 2025)**: One-ISP API integration for customer data:
  - Configurable API token in Settings → Billing API
  - Search billing customers when creating tickets ("From Billing" option)
  - Auto-fill customer details (name, phone, email, address, service plan)
  - Username field added to customers table for billing system usernames
  - On-demand query approach keeps data fresh without duplication
  - Imports customer to local database when ticket is created
  - API endpoint at `/api/billing.php` for customer search

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
- **One-ISP Billing API**: Integrated for customer data lookup and import.