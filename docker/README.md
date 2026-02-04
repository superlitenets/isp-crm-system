# ISP CRM Docker Setup

This Docker setup allows you to run the ISP CRM & Ticketing System locally or on any server with Docker installed.

## Prerequisites

- Docker (version 20.10+)
- Docker Compose (version 2.0+)

## Quick Start

1. **Extract and navigate to docker directory:**
   ```bash
   unzip isp-crm-docker.zip
   cd isp-crm/docker
   ```

2. **Build and start the containers:**
   ```bash
   docker-compose up -d --build
   ```

3. **Wait for database to be ready** (about 30 seconds on first run)

4. **Access the application:**
   - Open your browser and go to: http://localhost:8080
   - Default admin credentials: **admin@isp.com / admin123**

## Services

| Service | Port | Description |
|---------|------|-------------|
| App     | 8080 | PHP Application (Apache) |
| Database| 5432 | PostgreSQL 15 |

## Directory Structure

```
docker/
├── Dockerfile          # PHP/Apache container configuration (standalone)
├── docker-compose.yml  # Multi-container orchestration (development)
├── apache.conf         # Apache virtual host configuration
├── nginx.conf          # Nginx configuration (production)
├── php-fpm.conf        # PHP-FPM pool configuration (production)
├── security.ini        # PHP security hardening
├── .env.example        # Environment variables template
└── README.md           # This file

# Root directory (production)
├── Dockerfile          # PHP-FPM container (used with Nginx)
└── docker-compose.yml  # Full production stack
```

## Common Commands

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f app

# Rebuild containers after code changes
docker-compose up -d --build

# Access PHP container shell
docker-compose exec app bash

# Access PostgreSQL
docker-compose exec db psql -U ispcrm -d ispcrm

# Reset database (WARNING: deletes all data)
docker-compose down -v
docker-compose up -d
```

## Configuration

### Database Connection
The app automatically connects to the PostgreSQL container. No manual database setup is required - tables are created on first access.

### SMS Gateway
Configure your SMS provider in the Settings page or via environment variables.

### WhatsApp Gateway
Supports multiple providers:
- **Meta Business API**: For official WhatsApp Business
- **WAHA**: Self-hosted WhatsApp API
- **UltraMsg**: Third-party WhatsApp API
- **Custom**: Any REST API

### M-Pesa Integration
Configure your Safaricom M-Pesa credentials for payment processing.

### SmartOLT Integration
Add your SmartOLT API credentials for network equipment monitoring.

## Production Deployment

For production:

1. Change all passwords in `.env`
2. Set `APP_DEBUG=false`
3. Use HTTPS (configure SSL in Apache or use a reverse proxy)
4. Set proper `SESSION_SECRET`
5. Configure proper backup for PostgreSQL data

## PHP-FPM Configuration

The production setup uses PHP-FPM with Nginx. The `php-fpm.conf` file configures:
- **pm.max_children = 20**: Maximum worker processes
- **pm.start_servers = 5**: Initial workers on startup
- **pm.min_spare_servers = 3**: Minimum idle workers
- **pm.max_spare_servers = 10**: Maximum idle workers
- **pm.max_requests = 500**: Requests per worker before respawn

Adjust these values based on your server RAM (each worker uses ~30-50MB).

## Troubleshooting

**Container won't start:**
```bash
docker-compose logs app
```

**Database connection issues:**
- Ensure the db container is healthy: `docker-compose ps`
- Check database logs: `docker-compose logs db`

**PHP-FPM max_children reached:**
If you see `server reached pm.max_children setting` warnings:
1. Increase `pm.max_children` in `docker/php-fpm.conf`
2. Rebuild the container: `docker-compose up -d --build php`

**Permission issues:**
```bash
docker-compose exec app chown -R www-data:www-data /var/www/html
```

**Email SSL broken pipe errors:**
This typically happens with unstable SMTP connections. The EmailService now handles these gracefully.

## Support

For issues and feature requests, please contact your system administrator.
