# ISP CRM & Ticketing System

## Overview
This project is a PHP-based Customer Relationship Management (CRM) and ticketing system for Internet Service Providers (ISPs). Its primary goal is to streamline customer interactions, manage support tickets efficiently, and automate operational tasks. The system offers end-to-end customer and ticket management, robust HR and inventory modules, sales and marketing tools, a public-facing landing page with M-Pesa integration, secure authentication, SmartOLT integration, comprehensive reporting, and an enhanced mobile PWA for field personnel. The business vision is to offer an all-in-one platform to enhance operational efficiency, improve customer satisfaction, and support ISP growth.

## User Preferences
I prefer detailed explanations and expect the agent to ask for confirmation before making major changes. I appreciate clean, well-structured code.

## System Architecture
The system is built on PHP, utilizing a modular architecture to separate concerns. Configuration is managed via a `config/` directory and environment variables. Key functionalities are encapsulated in dedicated PHP classes, and users/employees are unified with central role management via HR.

**UI/UX Decisions:**
The system features a clean, responsive design, including a mobile PWA for field personnel. Both the main CRM and Huawei OLT modules feature fully responsive layouts with Bootstrap 5. Internal CRM interfaces prioritize clarity and ease of use.

**Technical Implementations:**
- **Authentication**: Session-based with password hashing, CSRF/SQL injection/XSS protection, and granular role-based access control (RBAC).
- **Database**: PostgreSQL is the primary database.
- **SMS & WhatsApp Integration**: Supports custom gateways (any POST/GET API) and Twilio for SMS; full-featured WhatsApp Web integration with real-time chat, automatic customer linking, and a Node.js Puppeteer service.
- **Template Engine**: A custom `TemplateEngine.php` class for dynamic content replacement.
- **Biometric Integration**: Abstract `BiometricDevice.php` with concrete implementations for ZKTeco, Hikvision, and BioTime Cloud.
- **M-Pesa Integration**: Handles STK Push for payments, C2B payments, and real-time callback processing.
- **Order System**: Public order form integration with CRM, lead capture, M-Pesa payments, and conversion to installation tickets.
- **Inventory Management**: Comprehensive warehouse and stock management with multi-warehouse support, stock intake, disbursement workflow, field usage tracking, returns/RMA, loss reporting, and audit trails.
- **SLA Management**: Automatic policy application based on ticket priority and business hours.
- **SmartOLT Integration**: Real-time network monitoring, ONU status tracking, and provisioning via SmartOLT cloud API.
- **Huawei OLT Module**: Standalone direct management module for Huawei OLT devices (Telnet/SSH/SNMP). Features include device management, ONU inventory and status monitoring, service profile management, auto-provisioning, ONU operations, and a CLI terminal.
  - **Persistent Session Manager**: Node.js service for persistent Telnet sessions, command queuing, auto-reconnection, and HTTP API integration.
  - **TR-069/GenieACS Integration**: Remote ONU configuration via TR-069 CWMP protocol with GenieACS ACS server integration for WiFi configuration, admin password change, device reboots, factory resets, and firmware upgrades. WiFi interfaces are dynamically detected from TR-069 data - single-band ONUs show only 2.4GHz tab, dual-band show both. Ethernet port configuration is done via OMCI through the OLT.
  - **TR-069 Auto-Provisioning via OMCI**: Automatic TR-069 configuration during ONU authorization. Auto-detects TR-069 VLAN, configures native VLAN on ETH port, sets DHCP mode, pushes ACS URL via OMCI, and enables periodic inform. Provides detailed success/failure notifications with manual fallback option.
  - **OLT Profile Sync**: Sync line profiles and service profiles directly from OLT with caching in database for quick access.
  - **SmartOLT Migration**: Toolkit for migrating from SmartOLT to direct OLT management.
- **Reporting & Activity Logs**: Comprehensive reports and detailed activity logging.
- **Ticket Management**: Includes timeline/activity history, quick status changes, customer satisfaction ratings, escalation features, statistics dashboard, and secure status update links for technicians and customers via WhatsApp/SMS.
- **Multi-Branch Support**: Manages operations across multiple physical locations with branch-specific assignments and daily reports.
- **HR & Payroll**: Salary advance system, leave management system with accruals and approval workflows, and configurable HR notification system.
- **Accounting Module**: Dashboard, Chart of Accounts, Products/Services, Customer Invoices, Vendors/Suppliers, Expense tracking, Quotes, Bills/Purchase Orders, and financial reports.
- **Billing System Integration**: One-ISP API integration for customer data lookup and import.
- **Database Backup System**: Built-in functionality for manual PostgreSQL backups (pg_dump).
- **WireGuard VPN Integration**: Secure VPN connectivity management between VPS and OLT sites, including server/peer management, key generation (via `wg genkey`), configuration export, MikroTik script generation with auto-detected public IP, and real-time traffic statistics. OLT service uses host network mode to access VPN routes for ping functionality.
- **ISP RADIUS Billing Module**: Comprehensive MikroTik RADIUS billing system with full AAA (Authentication, Authorization, Accounting) support. Features include:
  - **NAS Device Management**: Register and manage MikroTik routers with RADIUS secret configuration and optional RouterOS API access. Real-time NAS status monitoring with ping checks and active session counts.
  - **Service Packages**: Create packages with configurable speeds, data quotas, validity periods, billing cycles (daily/weekly/monthly/quarterly/yearly), and simultaneous session limits. FUP (Fair Usage Policy) support with throttled speeds after quota.
  - **Customer Subscriptions**: PPPoE, Hotspot, Static IP, and DHCP access types with encrypted password storage, automatic expiry handling, and suspension management. Auto-generated PPPoE credentials (username from customer name + 4 digits, 8-char random password).
  - **Session Tracking**: Real-time active session monitoring with data usage tracking (upload/download octets).
  - **Hotspot Vouchers**: Batch voucher generation for prepaid hotspot access with unique codes.
  - **Billing History**: Invoice generation, payment tracking, and billing records with M-Pesa transaction references.
  - **Dashboard**: Real-time statistics for active subscriptions, sessions, expiring accounts, monthly revenue, and data usage.
  - **Expiring Subscriptions**: Dedicated view showing subscriptions expiring in the next 14 days with quick renewal actions and SMS alert functionality.
  - **Revenue Reports**: Monthly revenue breakdown, package popularity analytics, and subscription statistics (active/suspended/expired).
  - **Usage Analytics**: Top users by data consumption, peak usage hours heatmap, and bandwidth trends.
  - **M-Pesa Integration**: Automatic subscription renewal on M-Pesa payment, matching by phone number or username.
  - **Captive Portal Expiry Page**: Public `/expired.php` page for expired subscribers. Detects client IP via X-Forwarded-For/proxy headers, matches to subscription in database, shows account details and renewal amount. Includes M-Pesa STK Push for instant payment. MikroTik redirects expired users to this page via walled garden.
  - **CoA Support**: Change of Authorization for disconnecting users when package changes or subscription expires.
  - **MAC Binding**: Bind subscriptions to specific MAC addresses for security.
  - **IP Pool Management**: Create and manage IP address pools for dynamic allocation.
  - **Bulk Import**: CSV import for batch subscription creation with validation.
  - **Package Upgrade/Downgrade**: Change packages with prorated billing calculations.
- **Licensing System**: Standalone license server for redistributing the CRM to other ISPs. Features include:
  - **License Server** (`license-server/`): Deployable standalone PHP app with admin dashboard, REST API, customer/license/activation management, usage analytics.
  - **License Client** (`src/LicenseClient.php`, `src/LicenseMiddleware.php`): Integrated validation with 7-day grace period for offline operation.
  - **Feature Gating**: Tier-based access control (Starter/Professional/Enterprise) with limits on users, customers, and ONUs.
  - **Environment Variables**: `LICENSE_SERVER_URL`, `LICENSE_KEY` enable licensing; disabled by default for self-hosted use.

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
- **GenieACS**: Open-source TR-069 ACS server for remote ONU configuration.