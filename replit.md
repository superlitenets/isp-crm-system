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
- **M-Pesa Integration**: Handles STK Push for payments, C2B, and real-time callback processing with robust error handling and retry logic. Supports multiple PayBill/Till numbers per NAS device (site) via centralized `mpesa_accounts` table managed in CRM Settings, with NAS devices referencing accounts via `mpesa_account_id` foreign key, falling back to global config. Revenue-per-site reporting with date filters, collection rates, and ARPU.
- **Landing Page Templates**: Multiple switchable landing page designs, selectable from Settings > Landing Page. Templates stored in `templates/landing/` directory. Available templates: `dark-tech` (dark hero with particle animation), `clean-modern` (light professional layout), `bold-gradient` (vibrant gradients with glassmorphism), `minimalist` (whitespace-heavy elegant design). Active template stored as `landing_template` in `company_settings`. All templates use same PHP variables: `$company`, `$landingSettings`, `$packages`, `$contactSettings`.
- **Order System**: Public order form integration with CRM, lead capture, M-Pesa payments, and conversion to installation tickets.
- **Inventory Management**: Comprehensive multi-warehouse stock management with intake, disbursement, field usage, returns, loss reporting, and audit trails.
- **SLA Management**: Assignment-based SLA timers (starts when ticket is assigned, not created). Automatic policy application based on ticket priority and business hours. WhatsApp notifications for SLA approaching deadlines (20% time remaining) and breach alerts to assigned technicians, with supervisor escalation summary.
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
- **Ticket Management**: Features timeline/activity, status changes, customer satisfaction, escalation, and secure status updates. "Resolved" is the only terminal status (no "Closed" status). Valid statuses: open, in_progress, pending, on_hold, resolved.
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
- **Licensing System**: Standalone license server and client for feature gating (Starter/Professional/Enterprise tiers). **License is mandatory for new deployments** — the app blocks access and shows a license activation page until a valid license is configured. License settings configurable via Settings > License page (server URL, key, activation, renewal, software updates) with database persistence. Config priority: DB settings > environment variables > defaults. License enforcement is skipped in Replit dev environment (`REPLIT_DEV_DOMAIN`).
  - **License Server Admin** (`license-server/public/admin.php`): Dashboard with server monitoring (online/stale/offline), update management (create/publish/install logs), license/customer/tier CRUD, M-Pesa settings management via Settings tab (stored in `license_settings` DB table, falls back to `.env` values).
  - **Server Monitoring**: Heartbeat with full stats (user_count, customer_count, onu_count, ticket_count, disk_usage, db_size, app_version). Stats history recorded hourly.
  - **Software Updates**: `/api/check-update` and `/api/report-update` endpoints; CRM settings page shows update availability with changelog, download link, critical badges. **Push Update System**: License server admin can push updates to individual servers or all servers at once via `pushUpdateToServer()`/`pushUpdateToAll()`. CRM receives pushes at `/api/update-webhook.php` (validates activation_token, downloads zip, verifies SHA-256 hash, backs up, extracts, runs migrations, restarts services, reports back). Auto-Apply toggle marks updates for automatic installation during heartbeat. `src/UpdateManager.php` handles the full update lifecycle with lock-file protection. CRM settings includes "Remote Updates ON/OFF" toggle and update history. Push logs stored in `license_update_push_log` table.
  - **M-Pesa License Payments**: Users can pay for license subscriptions via M-Pesa STK Push directly from Settings > License. License server endpoints: `/api/subscription-info`, `/api/pay/initiate`, `/api/pay/callback`, `/api/pay/status`. CRM AJAX handlers: `?page=license_pay_initiate`, `?page=license_pay_status`, `?page=license_subscription_info`. Auto-extends license on successful payment.
  - **License Server Installer** (`license-server/install.sh`): Standalone VPS installer for the license server with PostgreSQL, PHP-FPM, Nginx, SSL, M-Pesa configuration.
  - **Default Admin User**: New deployments auto-create admin user (`admin@isp.com` / `admin123` via `init_db.php`; `admin@{domain}` / `admin123` via deploy script).
- **Hotspot Captive Portal**: URL-based NAS routing (`/hotspot/{nas_ip}`) for per-NAS package selection, MAC-based auto-login, M-Pesa STK Push, voucher redemption, and MikroTik CHAP integration. PHP built-in server uses `public/router.php` for URL path routing; Apache uses `.htaccess` rewrite rules.
- **Core Network Monitoring**: Ping-based uptime monitoring for core equipment (routers, switches, OLTs, UPS, etc.) with WhatsApp notifications.
  - **Ping Status**: Real-time ICMP ping checks with online/offline/unknown status badges.
  - **Auto-Polling**: Equipment status checked every 2 minutes on the Core Network page.
  - **WhatsApp Alerts**: Status change notifications (down/recovered) sent to the Dying Gasp WhatsApp group.
  - **Uptime Logging**: All status changes logged to `isp_equipment_uptime_log` for historical reporting.
  - **Per-Equipment Toggle**: Enable/disable monitoring per device via the equipment form.
  - **API Endpoint**: `/api/core-monitor.php` for ping_all, ping_one, get_status, uptime_report.
- **Fleet Management (Protrack365 GPS)**: Vehicle fleet tracking integrated as a tab within the Inventory module.
  - **Vehicle Management**: CRUD for vehicles with IMEI, plate number, type, make/model, employee assignment tracking.
  - **Real-Time GPS Tracking**: Live map view with auto-refresh using Leaflet.js and OpenStreetMap tiles via Protrack365 API.
  - **Route Playback**: Historical route playback with animation controls for any vehicle and date range.
  - **Geofencing**: Create/delete circle geofences with entry/exit alarms.
  - **Remote Commands**: Engine stop/restore, door lock/unlock via Protrack device commands.
  - **Alarm Monitoring**: SOS, overspeed, geofence, vibration, power disconnect alerts with acknowledgement workflow.
  - **Device Sync**: Bulk import devices from Protrack365 account.
  - **Employee Assignment**: Track vehicle assignment history with timestamps.
  - Settings configurable via Settings > Fleet / Protrack page (account, password, API base URL) with connection testing.

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
- **Protrack365 GPS API**: Vehicle GPS tracking, commands, geofencing, and alarm monitoring.

## VPS Deployment
The `deploy/` directory contains production deployment scripts:
- **`deploy/install.sh`**: Full automated VPS installer — installs PHP 8.2, Node.js 20, PostgreSQL, Nginx, SSL (Let's Encrypt), creates systemd services for OLT, WhatsApp, and SNMP workers, initializes the database (migration.sql + init_db.php + fix_missing_columns.sql), sets up comprehensive cron jobs, and configures all required directories/permissions. Run with `sudo bash install.sh` on a fresh Ubuntu/Debian VPS.
- **`deploy/update.sh`**: Code update script — backs up DB, syncs files, installs dependencies, runs migrations, restarts services. Run with `sudo bash update.sh`.
- **`database/fix_missing_columns.sql`**: Comprehensive SQL fix script (474 lines) that adds all missing columns and tables not in migration.sql — covers ISP inventory, fleet management, radius, employees, TR069, and more. Safe to run multiple times.
- Production services: `isp-olt` (port 3002), `isp-whatsapp` (port 3001), `isp-snmp` (port 3003), PHP-FPM (unix socket), Nginx (80/443).
- Cron jobs: schedule checker (5min), SLA notifications (15min), recurring billing (hourly), daily summary (6AM), attendance sync (30min workdays), leave accrual (monthly), DB backup (2AM), cleanup (weekly).
- Credentials saved to `deploy/credentials.txt` after installation.