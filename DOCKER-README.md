# ISP CRM & Ticketing System - Docker Deployment

A complete CRM and ticketing system for Internet Service Providers with SMS notifications, WhatsApp Web messaging, biometric attendance, M-Pesa payments, SmartOLT network monitoring, inventory management, complaints workflow, and comprehensive HR management.

## Quick Start

### 1. Prerequisites
- Docker and Docker Compose installed
- Git (optional)

### 2. Setup

```bash
# Extract the zip file
unzip isp-crm-docker.zip
cd isp-crm

# Copy environment file and configure
cp .env.example .env

# Edit .env with your settings (REQUIRED)
nano .env
```

### 3. Configure Environment Variables

**IMPORTANT:** You MUST edit the `.env` file before starting:

```env
# Database (REQUIRED - change the password!)
POSTGRES_DB=isp_crm
POSTGRES_USER=crm_user
POSTGRES_PASSWORD=your_secure_password_here

# Security - REQUIRED: Generate a random string!
SESSION_SECRET=generate_a_random_32_character_string_here

# Biometric API Key (minimum 16 characters)
BIOMETRIC_API_KEY=your_secure_api_key_here

# Optional: SMS Gateway (Advanta SMS Kenya)
ADVANTA_API_KEY=your_api_key
ADVANTA_PARTNER_ID=your_partner_id
ADVANTA_SHORTCODE=YourSenderID

# Optional: SmartOLT Network Monitoring
SMARTOLT_API_URL=https://your-instance.smartolt.com
SMARTOLT_API_KEY=your_api_key

# Optional: M-Pesa Integration
MPESA_ENV=sandbox
MPESA_CONSUMER_KEY=your_key
MPESA_CONSUMER_SECRET=your_secret
```

### 4. Start the Application

```bash
# Build and start all containers
docker-compose up -d

# View logs
docker-compose logs -f
```

### 5. Access the Application

- **Landing Page**: http://localhost (public, no login required)
- **CRM Login**: http://localhost/login
- **Mobile PWA**: http://localhost/mobile

**Default Admin Credentials:**
- Email: admin@isp.com
- Password: admin123

**IMPORTANT:** Change these credentials after first login!

## Architecture

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Nginx     │────▶│   PHP-FPM   │────▶│  PostgreSQL │
│   (Port 80) │     │  (Port 9000)│     │  (Port 5432)│
└─────────────┘     └─────────────┘     └─────────────┘
```

## Features

### Core CRM
- **Customer Management**: Track customers, service plans, connection details
- **Ticketing System**: Support tickets with SLA management, priorities, team assignments
- **Team-Based Assignment**: Assign tickets to teams and/or individuals
- **SLA Management**: Response/resolution time tracking with breach alerts, business hours, holidays

### Complaints Module (NEW)
- **Public Complaint Submission**: Customers can submit complaints from landing page
- **Approval Workflow**: Complaints go through review before becoming tickets
- **Status Tracking**: Pending, Approved, Rejected, Converted statuses
- **Priority Management**: Set priority before conversion to ticket

### Reports & Activity Logs (NEW)
- **Dashboard Overview**: Summary statistics for tickets, orders, complaints
- **Ticket Reports**: Resolution rates, SLA compliance, performance by user
- **Order Reports**: Sales performance by salesperson
- **Complaint Reports**: Review statistics by reviewer
- **Activity Log**: Detailed audit trail of all system actions
- **User Performance**: Comprehensive user activity summary
- **Date Range Filters**: Filter all reports by date range

### WhatsApp Web Notifications (NEW)
- **12 Message Templates**: Pre-configured templates for tickets, orders, complaints
- **Quick-Send Buttons**: One-click messaging from ticket/order/complaint views
- **Custom Messages**: Send custom WhatsApp messages
- **Template Variables**: Dynamic content like {customer_name}, {ticket_number}
- **Message Logging**: Track all WhatsApp messages sent
- **Configurable Templates**: Customize all templates from Settings

### SmartOLT Integration (NEW)
- **Real-Time OLT Monitoring**: View all OLT devices with status
- **ONU Status Dashboard**: Online, Offline, LOS, Power Fail tracking
- **Signal Monitoring**: Critical and low power alerts
- **ONU Provisioning**: Authorize unconfigured ONUs directly
- **Device Management**: Reboot, resync, enable/disable ONUs

### HR Module
- **Employee Management**: Full employee records and profiles
- **Biometric Attendance**: ZKTeco and Hikvision device integration (Push Protocol)
- **Real-Time Late Detection**: Automatic SMS notifications for late arrivals
- **Payroll**: Salary calculation with automated late deductions
- **Performance Reviews**: Employee performance tracking
- **Departments**: Organize employees by department

### Inventory Management
- **Equipment Tracking**: Serial numbers, categories, conditions
- **Bulk Import/Export**: Excel/CSV support with smart column detection
- **Assignment Tracking**: Track equipment assigned to customers/employees
- **Fault Reporting**: Log and track equipment issues

### Sales & Marketing
- **Salesperson Management**: Track salespeople and their performance
- **Commission Tracking**: Automatic commission calculations
- **Lead Capture**: Mobile app for capturing leads in the field
- **Order Management**: Track orders from submission to installation

### Mobile PWA
- **Installable App**: Works on Android devices
- **Salesperson Dashboard**: Orders, commissions, performance ratings
- **Technician Dashboard**: Tickets, attendance, resolution metrics
- **Ticket Creation**: Technicians can create tickets from mobile
- **Lead Capture**: Salespeople can capture leads on the go
- **Offline Support**: Service worker for offline functionality

### Integrations
- **SMS**: Advanta SMS, Twilio, or custom REST API with configurable templates
- **WhatsApp Web**: Direct messaging with 12 configurable templates
- **M-Pesa**: STK Push and C2B payments
- **Biometric Devices**: Real-time push from ZKTeco/Hikvision
- **SmartOLT**: Network monitoring and ONU provisioning

### Public Features
- **Landing Page**: Beautiful ISP landing page with service packages
- **Online Orders**: Customers can order services online
- **Complaint Submission**: Public complaint form with spam protection
- **Payment Integration**: M-Pesa payment option during order

### Security & Permissions
- **Role-Based Access Control**: Admin, Manager, Technician, Salesperson, Viewer
- **Granular Permissions**: Control access to each module
- **Activity Logging**: Track all user actions
- **Session Security**: Secure session management with CSRF protection

## Container Management

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f

# Rebuild after code changes
docker-compose up -d --build

# Access PHP container shell
docker exec -it isp_crm_php bash

# Access database
docker exec -it isp_crm_db psql -U crm_user -d isp_crm
```

## Biometric Device Setup

### ZKTeco Devices (Push Protocol)
1. Access device web interface
2. Go to Communication Settings
3. Set Push Server URL: `http://your-server/biometric-api.php`
4. Add API key header: `X-API-Key: your_biometric_api_key`

### Hikvision Devices (ISAPI)
1. Access device configuration
2. Set ISAPI Push URL: `http://your-server/biometric-api.php?api_key=your_key`
3. Enable attendance event push

## SmartOLT Setup

1. Log in to your SmartOLT dashboard
2. Go to Settings > API
3. Generate an API key
4. Add to your `.env` file:
   ```env
   SMARTOLT_API_URL=https://your-instance.smartolt.com
   SMARTOLT_API_KEY=your_api_key
   ```
5. Or configure in CRM: Settings > SmartOLT tab

## WhatsApp Setup

WhatsApp Web integration requires no API key - it uses WhatsApp Web links.

1. Enable in Settings > WhatsApp tab
2. Customize message templates as needed
3. Users must be logged into WhatsApp Web in their browser
4. Click send buttons to open WhatsApp with pre-filled message

## SSL/HTTPS Setup (Production)

1. Create SSL directory:
```bash
mkdir -p docker/ssl
```

2. Add your SSL certificates:
```bash
cp your_certificate.crt docker/ssl/cert.crt
cp your_private_key.key docker/ssl/cert.key
```

3. Update `docker/nginx.conf` to enable SSL:
```nginx
server {
    listen 443 ssl;
    ssl_certificate /etc/nginx/ssl/cert.crt;
    ssl_certificate_key /etc/nginx/ssl/cert.key;
    # ... rest of config
}
```

4. Update docker-compose.yml to mount SSL volume:
```yaml
nginx:
  volumes:
    - ./docker/ssl:/etc/nginx/ssl:ro
  ports:
    - "443:443"
```

## Backup & Restore

### Backup Database
```bash
docker exec isp_crm_db pg_dump -U crm_user isp_crm > backup_$(date +%Y%m%d).sql
```

### Restore Database
```bash
cat backup.sql | docker exec -i isp_crm_db psql -U crm_user -d isp_crm
```

## Troubleshooting

### Container won't start
```bash
# Check logs
docker-compose logs php
docker-compose logs db

# Rebuild
docker-compose down
docker-compose up -d --build
```

### Database connection errors
- Ensure the `db` container is healthy: `docker-compose ps`
- Check database credentials in `.env` match requirements
- Wait for database to be ready (healthcheck takes ~30 seconds)

### Permission issues
```bash
docker exec -it isp_crm_php chown -R www-data:www-data /var/www/html
```

### Biometric devices not connecting
- Verify API key matches in `.env` and device configuration
- Check firewall allows incoming connections on port 80/443
- Review logs: `docker-compose logs php | grep biometric`

### SmartOLT API errors
- Verify API URL includes `https://` prefix
- Check API key is valid and has required permissions
- Some bulk endpoints require specific SmartOLT subscription tiers

## Environment Variables Reference

| Variable | Required | Description |
|----------|----------|-------------|
| POSTGRES_DB | Yes | Database name |
| POSTGRES_USER | Yes | Database username |
| POSTGRES_PASSWORD | Yes | Database password |
| SESSION_SECRET | Yes | Session encryption key (32+ chars) |
| BIOMETRIC_API_KEY | No | API key for biometric devices (16+ chars) |
| ADVANTA_API_KEY | No | Advanta SMS API key |
| WHATSAPP_ENABLED | No | Enable WhatsApp Web (default: true) |
| WHATSAPP_DEFAULT_COUNTRY_CODE | No | Default country code (default: 254) |
| SMARTOLT_API_URL | No | SmartOLT instance URL |
| SMARTOLT_API_KEY | No | SmartOLT API key |
| MPESA_CONSUMER_KEY | No | M-Pesa Daraja API key |
| APP_TIMEZONE | No | Timezone (default: Africa/Nairobi) |
| APP_DEBUG | No | Debug mode (default: false) |
| APP_URL | No | Base URL for callbacks |

## Database Tables

The system automatically creates 45+ tables including:
- `users`, `roles`, `permissions` - Authentication & RBAC
- `customers`, `tickets`, `ticket_comments` - CRM core
- `complaints`, `activity_logs` - Complaints & audit trail
- `employees`, `attendance`, `payroll` - HR module
- `equipment`, `equipment_assignments` - Inventory
- `orders`, `salespersons`, `sales_commissions` - Sales
- `sms_logs`, `whatsapp_logs` - Communication logs
- `sla_policies`, `sla_business_hours` - SLA management
- `biometric_devices`, `biometric_attendance_logs` - Biometric
- `mpesa_transactions`, `mpesa_config` - M-Pesa
- `settings`, `service_packages` - Configuration

## Support

For issues or feature requests, please contact your system administrator.
