# ISP CRM & Ticketing System

## Overview
This project is a PHP-based Customer Relationship Management (CRM) and ticketing system designed for Internet Service Providers (ISPs). Its core purpose is to optimize customer interaction, streamline support operations, and automate various business processes. Key capabilities include comprehensive customer and ticket management, integrated HR and inventory modules, sales and marketing tools, a public-facing landing page with M-Pesa integration, secure authentication, SmartOLT integration, advanced reporting, and a mobile-optimized PWA for field technicians. The overarching vision is to provide an all-in-one platform that boosts operational efficiency, elevates customer satisfaction, and supports the growth trajectory of ISPs.

## User Preferences
I prefer detailed explanations and expect the agent to ask for confirmation before making major changes. I appreciate clean, well-structured code.

## System Architecture
The system employs a modular PHP architecture, promoting separation of concerns and centralizing configuration management via a `config/` directory and environment variables. Core functionalities are encapsulated within dedicated PHP classes, with a unified user/employee management system leveraging role-based access control (RBAC).

**UI/UX Decisions:**
The system features a responsive design, including a mobile PWA. Both the main CRM and Huawei OLT modules utilize Bootstrap 5 for fully responsive layouts, focusing on clarity and user-friendliness.

**Technical Implementations:**
- **Authentication**: Session-based authentication with password hashing, robust security measures (CSRF, SQL injection, XSS protection), and granular RBAC.
- **Database**: PostgreSQL serves as the primary database.
- **SMS & WhatsApp Integration**: Support for custom SMS gateways, Twilio, and a Node.js Baileys service for real-time WhatsApp chat.
- **Template Engine**: A custom `TemplateEngine.php` for dynamic content rendering.
- **Biometric Integration**: An abstract `BiometricDevice.php` with concrete implementations for ZKTeco, Hikvision, and BioTime Cloud.
- **M-Pesa Integration**: Handles STK Push payments, C2B, and real-time callback processing with error handling and retry logic. Supports multiple PayBill/Till numbers per NAS device.
- **Landing Page Templates**: Multiple switchable landing page designs managed through system settings.
- **Order System**: Public order form integration with CRM, lead capture, M-Pesa payments, and conversion to installation tickets.
- **Inventory Management**: Comprehensive multi-warehouse stock management, including intake, disbursement, field usage, returns, loss reporting, and audit trails.
- **SLA Management**: Assignment-based SLA timers with automatic policy application, WhatsApp notifications for impending deadlines, and escalation alerts.
- **SmartOLT Integration**: Real-time network monitoring, ONU status tracking, and provisioning via the SmartOLT cloud API.
- **Huawei OLT Module**: Standalone direct management for Huawei OLT devices via Telnet/SSH/SNMP.
  - **SNMP-First Architecture**: Prioritizes SNMP for read operations, CLI for write.
  - **Persistent Session Manager**: Node.js service for managing Telnet/SSH sessions.
  - **SSH Protocol Support**: Includes support for legacy SSH algorithms.
  - **TR-069/GenieACS Integration**: Remote ONU configuration via TR-069 CWMP for WiFi, password changes, reboots, and firmware upgrades.
  - **TR-069 Auto-Provisioning**: Automated TR-069 configuration during ONU authorization.
  - **Production Guardrails**: Features like NTP Gating, Cool-down/Debounce, ConnectionRequestURL Validation, and Post-Provision Verification.
  - **OLT Profile Sync**: Synchronizes line and service profiles from OLT with database caching.
- **Reporting & Activity Logs**: Comprehensive reporting and detailed activity logging.
- **Ticket Management**: Features timeline/activity tracking, status changes, customer satisfaction, escalation, and secure status updates. "Resolved" is the sole terminal status.
- **Multi-Branch Support**: Functionality to manage operations across multiple physical locations.
- **HR & Payroll**: Modules for salary advance, leave management, and HR notifications.
- **Accounting Module**: Dashboard, Chart of Accounts, Invoices, Expenses, Quotes, Purchase Orders, financial reports, recurring invoice automation, and document delivery.
- **Database Backup System**: Manual PostgreSQL backup functionality.
- **WireGuard VPN Integration**: Management of WireGuard VPN servers and peers, key generation, and MikroTik script generation.
- **ISP RADIUS Billing Module**: Comprehensive MikroTik RADIUS billing with AAA support.
  - **NAS Device Management**: Registration and management of MikroTik routers.
  - **Service Packages**: Configurable speeds, data quotas, validity, and billing cycles.
  - **Customer Subscriptions**: PPPoE, Hotspot, Static IP, DHCP access with automated expiry.
  - **Session Tracking**: Real-time active session monitoring.
  - **Hotspot Vouchers**: Batch generation for prepaid access.
  - **Billing History & Dashboard**: Invoice generation and payment tracking.
  - **M-Pesa Integration**: Automatic subscription renewal.
  - **Captive Portal Expiry Page**: Public page for expired subscribers with M-Pesa STK Push.
  - **Customer Self-Service Portal**: For usage viewing, session history, and invoices.
  - **CoA Support**: Change of Authorization for immediate package changes.
  - **Static IP Online Detection**: Detects static IP customer online status via MikroTik ARP and Simple Queue checks.
  - **MAC Binding, IP Pool Management, Bulk Import**: Advanced subscription management features.
  - **VLAN Management**: Define VLANs per NAS and sync to MikroTik.
  - **Static IP Provisioning**: Provisioning and management of static IPs and DHCP leases on MikroTik.
  - **MikroTik API Integration**: Full RouterOS API support.
  - **Live Traffic Monitoring**: Real-time traffic graphs for subscribers.
- **Licensing System**: Standalone license server and client for feature gating across Starter/Professional/Enterprise tiers. A valid license is mandatory for new deployments.
  - **License Server Admin**: Dashboard for server monitoring, update management, license/customer/tier CRUD, and M-Pesa settings.
  - **Server Monitoring**: Heartbeat with full statistics and history.
  - **Software Updates**: Endpoints for checking and reporting updates; CRM shows update availability, changelog, and allows remote updates. A push update system enables direct updates from the license server.
  - **M-Pesa License Payments**: Users can pay for license subscriptions directly via M-Pesa STK Push.
- **Hotspot Captive Portal**: URL-based NAS routing for package selection, MAC-based auto-login, M-Pesa STK Push, voucher redemption, and MikroTik CHAP integration.
- **Core Network Monitoring**: Ping-based uptime monitoring for core equipment with WhatsApp notifications for status changes and uptime logging.
- **Fleet Management (Protrack365 GPS)**: Integrated vehicle tracking within the Inventory module.
  - **Vehicle Management**: CRUD operations for vehicles, including assignment tracking.
  - **Real-Time GPS Tracking**: Live map view with color-coded status markers (green=moving, blue=idle, red=offline), auto-refresh every 30s.
  - **Live Vehicle Status Table**: Overview shows all vehicles with real-time status, speed, ACC state, and last update time.
  - **Route Playback**: Historical route playback with animation, slider, and play/pause controls.
  - **Geofencing**: Creation and management of circular geofences with alarms.
  - **Remote Commands**: Engine stop/restore, door lock/unlock.
  - **Alarm Monitoring**: SOS, overspeed, geofence, vibration, and power disconnect alerts.
  - **Reports**: Daily vehicle report, fuel consumption estimates, vehicle swap history, and mileage trend charts.
  - **Mileage Conversion**: Protrack API returns meters; automatically converted to km for display and reports.

## External Dependencies
- **PostgreSQL**: Primary database.
- **Twilio**: Optional SMS gateway.
- **Advanta SMS (Kenya)**: Recommended SMS gateway.
- **ZKTeco Biometric Devices**: For attendance tracking.
- **Hikvision Biometric Devices**: For attendance tracking and remote fingerprint enrollment.
- **M-Pesa**: For mobile money payments.
- **SmartOLT API**: For network monitoring and ONU management.
- **One-ISP Billing API**: For customer data lookup and import.
- **Huawei OLT Devices**: Direct connectivity via Telnet/SSH/SNMP.
- **GenieACS**: Open-source TR-069 ACS server.
- **Protrack365 GPS API**: For vehicle GPS tracking, commands, and monitoring.