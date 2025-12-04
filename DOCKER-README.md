# ISP CRM & Ticketing System - Docker Deployment

A complete CRM and ticketing system for Internet Service Providers with SMS notifications, WhatsApp messaging, biometric attendance, M-Pesa payments, and HR management.

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
- **SLA Management**: Response/resolution time tracking with breach alerts

### HR Module
- **Employee Management**: Full employee records and profiles
- **Biometric Attendance**: ZKTeco and Hikvision device integration
- **Real-Time Late Detection**: Automatic SMS notifications for late arrivals
- **Payroll**: Salary calculation with automated late deductions
- **Performance Reviews**: Employee performance tracking

### Mobile PWA
- **Installable App**: Works on Android devices
- **Salesperson Dashboard**: Orders, commissions, performance ratings
- **Technician Dashboard**: Tickets, attendance, resolution metrics
- **Ticket Creation**: Technicians can create tickets from mobile
- **Offline Support**: Service worker for offline functionality

### Integrations
- **SMS**: Advanta SMS, Twilio, or custom REST API
- **WhatsApp**: Direct messaging via WhatsApp Web
- **M-Pesa**: STK Push and C2B payments
- **Biometric Devices**: Real-time push from ZKTeco/Hikvision

### Public Features
- **Landing Page**: Beautiful ISP landing page with service packages
- **Online Orders**: Customers can order services online
- **Payment Integration**: M-Pesa payment option during order

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

### ZKTeco Devices
1. Access device web interface
2. Go to Communication Settings
3. Set Push Server URL: `http://your-server/biometric-api.php`
4. Add API key header: `X-API-Key: your_biometric_api_key`

### Hikvision Devices
1. Access device configuration
2. Set ISAPI Push URL: `http://your-server/biometric-api.php?api_key=your_key`
3. Enable attendance event push

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

## Environment Variables Reference

| Variable | Required | Description |
|----------|----------|-------------|
| POSTGRES_DB | Yes | Database name |
| POSTGRES_USER | Yes | Database username |
| POSTGRES_PASSWORD | Yes | Database password |
| SESSION_SECRET | Yes | Session encryption key (32+ chars) |
| BIOMETRIC_API_KEY | No | API key for biometric devices (16+ chars) |
| ADVANTA_API_KEY | No | Advanta SMS API key |
| MPESA_CONSUMER_KEY | No | M-Pesa Daraja API key |
| APP_TIMEZONE | No | Timezone (default: Africa/Nairobi) |

## Support

For issues or feature requests, please contact your system administrator.
