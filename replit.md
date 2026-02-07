# ISP CRM & Ticketing System

## Overview
This project is a PHP-based Customer Relationship Management (CRM) and ticketing system for Internet Service Providers (ISPs). Its primary goal is to streamline customer interactions, manage support tickets efficiently, and automate operational tasks. The system offers end-to-end customer and ticket management, robust HR and inventory modules, sales and marketing tools, a public-facing landing page with M-Pesa integration, secure authentication, SmartOLT integration, comprehensive reporting, and an enhanced mobile PWA for field personnel. The business vision is to offer an all-in-one platform to enhance operational efficiency, improve customer satisfaction, and support ISP growth.

## User Preferences
I prefer detailed explanations and expect the agent to ask for confirmation before making major changes. I appreciate clean, well-structured code.

## System Architecture
The system is built on PHP with a modular architecture, separating concerns and managing configuration via a `config/` directory and environment variables. Key functionalities are encapsulated in dedicated PHP classes, unifying users/employees with central role management via HR.

**UI/UX Decisions:**
The system features a clean, responsive design, including a mobile PWA for field personnel. Both the main CRM and Huawei OLT modules feature fully responsive layouts with Bootstrap 5, prioritizing clarity and ease of use.

**Technical Implementations:**
- **Authentication**: Session-based with password hashing, security protections (CSRF/SQL injection/XSS), and granular role-based access control (RBAC).
- **Database**: PostgreSQL is the primary database.
- **SMS & WhatsApp Integration**: Supports custom gateways and Twilio for SMS; full-featured WhatsApp integration with real-time chat via a Node.js Baileys service.
- **Template Engine**: Custom `TemplateEngine.php` for dynamic content.
- **Biometric Integration**: Abstract `BiometricDevice.php` with implementations for ZKTeco, Hikvision, and BioTime Cloud.
- **M-Pesa Integration**: Handles STK Push for payments, C2B, and real-time callback processing with robust error handling and retry logic.
- **Order System**: Public order form integration with CRM, lead capture, M-Pesa payments, and conversion to installation tickets.
- **Inventory Management**: Comprehensive multi-warehouse stock management with intake, disbursement, field usage, returns, loss reporting, and audit trails.
- **SLA Management**: Automatic policy application based on ticket priority and business hours.
- **SmartOLT Integration**: Real-time network monitoring, ONU status tracking, and provisioning via SmartOLT cloud API.
- **Huawei OLT Module**: Standalone direct management for Huawei OLT devices (Telnet/SSH/SNMP).
  - **SNMP-First Architecture**: Prioritizes SNMP for read operations, with CLI for write operations.
  - **Persistent Session Manager**: Node.js service for persistent Telnet/SSH sessions and API integration.
  - **SSH Protocol Support**: Supports SSH for OLT communication, including legacy algorithms.
  - **TR-069/GenieACS Integration**: Remote ONU configuration via TR-069 CWMP with GenieACS for WiFi, password changes, reboots, and firmware upgrades.
  - **TR-069 Auto-Provisioning**: Automated TR-069 configuration during ONU authorization via OMCI.
  - **Production Guardrails**: Includes NTP Gating, Cool-down/Debounce, ConnectionRequestURL Validation, and Post-Provision Verification.
  - **OLT Profile Sync**: Syncs line and service profiles from OLT with database caching.
- **Reporting & Activity Logs**: Comprehensive reports and detailed activity logging.
- **Ticket Management**: Features timeline/activity, status changes, customer satisfaction, escalation, and secure status updates.
- **Multi-Branch Support**: Manages operations across multiple physical locations.
- **HR & Payroll**: Salary advance, leave management, and HR notification systems.
- **Accounting Module**: Dashboard, Chart of Accounts, Invoices, Expenses, Quotes, Purchase Orders, financial reports, recurring invoice automation, and document delivery via email/WhatsApp (invoices, quotes, receipts as PDF).
- **Database Backup System**: Manual PostgreSQL backup functionality.
- **WireGuard VPN Integration**: Secure VPN connectivity management (server/peer, key generation, MikroTik script generation, traffic statistics).
- **ISP RADIUS Billing Module**: Comprehensive MikroTik RADIUS billing with AAA support.
  - **NAS Device Management**: Register and manage MikroTik routers.
  - **Service Packages**: Configurable speeds, data quotas, validity, billing cycles, and FUP.
  - **Package Speed Schedules & Overrides**: Time-based and temporary speed adjustments.
  - **Customer Subscriptions**: PPPoE, Hotspot, Static IP, DHCP access with automated expiry and suspension.
  - **Session Tracking**: Real-time active session monitoring.
  - **Hotspot Vouchers**: Batch generation for prepaid access.
  - **Billing History & Dashboard**: Invoice generation, payment tracking, and statistics.
  - **M-Pesa Integration**: Automatic subscription renewal.
  - **Captive Portal Expiry Page**: Public page for expired subscribers with M-Pesa STK Push.
  - **Customer Self-Service Portal**: Usage viewing, session history, invoices, payments, and WiFi management.
  - **CoA Support**: Change of Authorization for immediate package changes or disconnections.
  - **MAC Binding, IP Pool Management, Bulk Import**: For advanced subscription management.
  - **VLAN Management**: Define VLANs per NAS and sync to MikroTik via RouterOS API.
  - **Static IP Provisioning**: Provision static IPs and manage DHCP leases on MikroTik.
  - **MikroTik API Integration**: Full RouterOS API support for network configuration.
  - **Live Traffic Monitoring**: Real-time traffic graph for PPPoE, DHCP, and Static IP subscribers with on-demand Chart.js visualization polling MikroTik every 2 seconds.
- **Licensing System**: Standalone license server and client for feature gating (Starter/Professional/Enterprise tiers). License settings configurable via Settings > License page (server URL, key, activation, renewal) with database persistence. Config priority: DB settings > environment variables > defaults.
- **Hotspot Captive Portal**: URL-based NAS routing (`/hotspot/{nas_ip}`) for per-NAS package selection, MAC-based auto-login, M-Pesa STK Push, voucher redemption, and MikroTik CHAP integration. PHP built-in server uses `public/router.php` for URL path routing; Apache uses `.htaccess` rewrite rules.

## External Dependencies
- **PostgreSQL**: Primary database.
- **Twilio**: Optional SMS gateway.
- **Advanta SMS (Kenya)**: Recommended SMS gateway.
- **Custom SMS Gateways**: For integrating any REST API for SMS.
- **ZKTeco Biometric Devices**: For attendance tracking.
- **Hikvision Biometric Devices**: For attendance tracking and remote fingerprint enrollment.
- **M-Pesa**: For mobile money payments.
- **SmartOLT API**: For network monitoring and ONU management.
- **One-ISP Billing API**: For customer data lookup and import.
- **Huawei OLT Devices**: Direct Telnet/SSH/SNMP connectivity.
- **GenieACS**: Open-source TR-069 ACS server.