# ISP CRM & Ticketing System - Docker Deployment

A complete CRM and ticketing system for Internet Service Providers with SMS notifications, WhatsApp messaging, biometric attendance, and HR management.

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

# Edit .env with your settings
nano .env
```

### 3. Configure Environment Variables

Edit the `.env` file with your settings:

```env
# Database (these work with the included PostgreSQL container)
POSTGRES_DB=isp_crm
POSTGRES_USER=crm_user
POSTGRES_PASSWORD=your_secure_password_here

# Security - IMPORTANT: Change this!
SESSION_SECRET=generate_a_random_32_character_string_here

# Optional: SMS Gateway (Advanta SMS Kenya)
ADVANTA_API_KEY=your_api_key
ADVANTA_PARTNER_ID=your_partner_id
ADVANTA_SHORTCODE=YourSenderID
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

**Default Admin Credentials:**
- Email: admin@isp.com
- Password: admin123

**Important:** Change these credentials after first login!

## Architecture

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Nginx     │────▶│   PHP-FPM   │────▶│  PostgreSQL │
│   (Port 80) │     │  (Port 9000)│     │  (Port 5432)│
└─────────────┘     └─────────────┘     └─────────────┘
```

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

## Features

- **Customer Management**: Track customers, service plans, connection details
- **Ticketing System**: Support tickets with priorities, assignments, status tracking
- **HR Module**: Employees, attendance, payroll, performance reviews
- **Biometric Integration**: ZKTeco and Hikvision device support
- **Late Deduction System**: Automated payroll deductions for late arrivals
- **SMS Notifications**: Advanta SMS, Twilio, or custom gateway
- **WhatsApp Messaging**: Direct messaging via WhatsApp Web
- **Public Landing Page**: Showcase service packages to potential customers
- **Service Package Management**: Dynamically manage packages from CRM

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
- Check database credentials in `.env` match `docker-compose.yml`

### Permission issues
```bash
docker exec -it isp_crm_php chown -R www-data:www-data /var/www/html
```

## Support

For issues or feature requests, please contact your system administrator.
