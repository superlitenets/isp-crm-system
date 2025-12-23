# ISP CRM & Ticketing System

## Overview
This project is a PHP-based Customer Relationship Management (CRM) and ticketing system for Internet Service Providers (ISPs). Its primary goal is to streamline customer interactions, manage support tickets efficiently, and automate operational tasks. The system provides a comprehensive solution for managing customer bases, tracking equipment, processing orders, handling HR functions (attendance, payroll), and facilitating communication via SMS and WhatsApp. Key capabilities include end-to-end customer and ticket management, robust HR and inventory modules, sales and marketing tools, a public-facing landing page with M-Pesa integration, secure authentication, SmartOLT integration, comprehensive reporting, and an enhanced mobile PWA for field personnel. The business vision is to offer an all-in-one platform to enhance operational efficiency, improve customer satisfaction, and support ISP growth.

## User Preferences
I prefer detailed explanations and expect the agent to ask for confirmation before making major changes. I appreciate clean, well-structured code.

## System Architecture
The system is built on PHP, utilizing a modular architecture to separate concerns.

**UI/UX Decisions:**
The system features a clean, responsive design. The public-facing landing page is modern with dynamic content, customizable hero sections, and package cards. Internal CRM interfaces prioritize clarity and ease of use for administrative and technical staff, including a mobile PWA for field personnel.
- **Mobile Responsive Layout**: Both the main CRM and OMS (Huawei OLT) modules feature fully responsive layouts with Bootstrap 5 offcanvas navigation. On screens below 992px, the desktop sidebar is hidden and replaced with a hamburger menu that opens a slide-out navigation drawer. Touch-friendly form controls, responsive tables, and optimized card layouts ensure a seamless mobile experience.

**Technical Implementations:**
- **Authentication**: Session-based with password hashing, CSRF/SQL injection/XSS protection, and granular role-based access control (RBAC).
- **Database**: PostgreSQL is the primary database with a defined schema for all modules.
- **SMS Integration**: Supports custom gateways (any POST/GET API) and Twilio, using configurable templates.
- **WhatsApp Integration**: Full-featured WhatsApp Web integration with real-time chat, automatic customer linking, message history storage, media support, and a Node.js Puppeteer service.
- **Template Engine**: A custom `TemplateEngine.php` class for dynamic content replacement.
- **Biometric Integration**: Abstract `BiometricDevice.php` with concrete implementations for ZKTeco (Push Protocol), Hikvision (ISAPI for remote fingerprint enrollment), and BioTime Cloud (REST API for attendance sync).
- **M-Pesa Integration**: Handles STK Push for payments, C2B payments, and real-time callback processing.
- **Order System**: Public order form integration with CRM, lead capture, M-Pesa payments, and conversion to installation tickets.
- **Inventory Management**: Comprehensive warehouse and stock management, including multi-warehouse support, stock intake (PO, receipts with serial/MAC), disbursement workflow (requests, pick, handover), field usage tracking, returns/RMA, loss reporting, stock movements audit, and ISP-specific equipment categories with low stock alerts and various reports.
- **SLA Management**: Automatic policy application based on ticket priority, considering business hours.
- **Complaints Module**: Approval workflow for public complaints before ticket conversion.
- **SmartOLT Integration**: Real-time network monitoring, ONU status tracking, and provisioning via SmartOLT cloud API.
- **Huawei OLT Module**: Standalone direct management module for Huawei OLT devices (opens in new tab). Features include OLT device management with Telnet/SSH/SNMP connectivity, ONU inventory and status monitoring, service profile management (VLAN, QoS, speed profiles), auto-provisioning with unconfigured ONU detection, ONU operations (authorize, reboot, delete), CLI terminal for direct commands, provisioning logs and alerts. Credentials stored encrypted with AES-256-CBC using SESSION_SECRET.
  - **Persistent Session Manager**: Node.js service (olt-service/) that maintains single persistent Telnet sessions per OLT for improved performance. Features include:
    - One persistent connection per OLT (reused for all commands)
    - Command queue with sequential execution (prevents session conflicts)
    - Automatic reconnection on connection loss
    - 60-second keepalive to prevent session timeout
    - HTTP API on port 3001 for PHP integration
    - ~50-100ms command execution (vs 2-3s with reconnect each time)
  - **OLT-Level Device Management**: Enhanced device discovery with system info sync (firmware version, hardware model, software version, uptime). Visual chassis layout displaying 22 slots (0-21) with color-coded board types (GPON, EPON, uplink, control, power) and real-time ONU counts per slot.
  - **Port Configuration**: PON port enable/disable controls, per-port VLAN assignment (tagged/untagged modes), bulk VLAN operations for multiple ports.
  - **Uplink Configuration**: Trunk/access/hybrid mode selection, allowed VLANs management, PVID (native VLAN) settings, port descriptions.
  - **Service Templates**: Pre-defined service profiles for fast ONU provisioning with bandwidth profiles (download/upload speed), VLAN/QoS settings, and service flags (IPTV, VoIP, TR-069). Full CRUD operations with default template marking.
  - **TR-069/GenieACS Integration**: Remote ONU configuration via TR-069 CWMP protocol with GenieACS ACS server integration. Features WiFi configuration (SSID, password, channel), device reboot/refresh, factory reset, device sync from ACS. GenieACS connects via NBI API (port 7557).
- **Reporting & Activity Logs**: Comprehensive reports and detailed activity logging for key system actions.
- **Ticket Commission System**: Auto-calculates employee earnings based on resolved/closed tickets, configurable rates, and payroll integration.
- **Ticket Enhancements**: Includes timeline/activity history, quick status changes, customer satisfaction ratings, escalation features, statistics dashboard, and secure status update links for technicians via WhatsApp/SMS.
- **Ticket Status Links**: When tickets are assigned or reposted, technicians receive clickable links in WhatsApp/SMS messages that allow them to update ticket status (In Progress, Resolved, Closed) without logging in. Links use secure tokens with expiration (72 hours) and usage limits.
- **Customer Ticket View Links**: Customers receive a {view_link} in ticket creation notifications (SMS/WhatsApp) to view their ticket progress and submit satisfaction ratings. Links use secure tokens (7-day expiry, 50-use limit) with the same O(1) lookup security model as technician links.
- **Multi-Branch Support**: Manages operations across multiple physical locations, including branch-specific assignments for employees, tickets, and teams, with daily summary reports.
- **Salary Advance System**: Employee request, approval, and disbursement workflow with flexible repayment schedules and payroll integration. Mobile API available.
- **Leave Management System**: Comprehensive tracking of multiple leave types, monthly accrual, request/approval workflow with balance validation, public holidays, and mobile API support.
- **HR Notification System**: Configurable SMS templates for HR events (leave, salary advance) with placeholders.
- **Accounting Module**: Dashboard, Chart of Accounts, Products/Services catalog, Customer Invoices (create, track payments, M-Pesa integration), Vendors/Suppliers, Expense tracking, Quotes, Bills/Purchase Orders, and reports (P&L, AR/AP Aging).
- **Billing System Integration**: One-ISP API integration for customer data lookup, auto-filling details during ticket creation, and importing customers.
- **Database Backup System**: Built-in functionality for manual PostgreSQL backups (pg_dump), download, deletion, and history tracking.
- **WireGuard VPN Integration**: Secure VPN connectivity between VPS and OLT sites for remote management. Features include:
  - Dedicated VPN management page with connection status monitoring
  - VPN server and peer management with visual status indicators (connected/stale/disconnected)
  - Automatic WireGuard key generation with AES-256-CBC encryption
  - Configuration file export (server/peer .conf files)
  - MikroTik RouterOS script generator for easy router configuration
  - TR-069 ACS URL integration (uses VPN gateway IP when enabled)
  - Docker container deployment with linuxserver/wireguard image
  - OLT site linking for organized peer management
  - Real-time traffic statistics (RX/TX bytes)

**System Design Choices:**
The system features a modular and extensible design. Configuration is managed via a `config/` directory and environment variables. Key functionalities are encapsulated in dedicated PHP classes, and users/employees are unified with central role management via HR.

## External Dependencies
- **PostgreSQL**: Primary database.
- **Twilio**: Optional SMS gateway.
- **Advanta SMS (Kenya)**: Recommended SMS gateway.
- **Custom SMS Gateways**: For integrating any REST API for SMS.
- **WhatsApp Web**: For direct messaging.
- **ZKTeco Biometric Devices**: For attendance tracking.
- **Hikvision Biometric Devices**: For attendance tracking and remote fingerprint enrollment.
- **M-Pesa**: For mobile money payments.
- **SmartOLT API**: For network monitoring and ONU management.
- **One-ISP Billing API**: For customer data lookup and import.
- **Huawei OLT Devices**: Direct Telnet/SSH/SNMP connectivity for fiber network provisioning and management.
- **GenieACS**: Open-source TR-069 ACS server for remote ONU configuration (WiFi, VoIP, firmware).

## Database Initialization
A comprehensive database initialization script is available at `db_init.sql`. This single file contains:
- All table definitions (100+ tables across all modules)
- All indexes for performance optimization
- All foreign key relationships
- Seed data (roles, permissions, leave types, ONU types, equipment categories, etc.)

**Usage:**
```bash
# Local development
psql -U crm -d isp_crm < db_init.sql

# Docker deployment
docker exec -i isp_crm_db psql -U crm -d isp_crm < db_init.sql
```

**Important:** This script uses `CREATE TABLE IF NOT EXISTS` and `ON CONFLICT DO NOTHING` for idempotency - safe to run multiple times without data loss.