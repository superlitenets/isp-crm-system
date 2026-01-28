# ISP CRM License Server

Standalone license server for distributing and managing ISP CRM licenses.

## Quick Start (Docker)

```bash
# 1. Copy and configure environment
cp .env.example .env
nano .env

# 2. Start the server
docker compose up -d

# 3. Access admin panel
open http://localhost:8081/admin.php
```

## Production Deployment with HTTPS

```bash
# 1. Get SSL certificate
certbot certonly --standalone -d license.yourdomain.com

# 2. Update nginx.conf with your domain

# 3. Start with nginx profile
docker compose --profile production up -d
```

## Manual Deployment (without Docker)

1. **Upload files** to your license server
2. **Create database** and run the schema:
   ```bash
   psql -U postgres -c "CREATE DATABASE license_db;"
   psql -U postgres -d license_db -f schema.sql
   ```
3. **Set environment variables**:
   ```bash
   export LICENSE_DB_HOST=localhost
   export LICENSE_DB_PORT=5432
   export LICENSE_DB_NAME=license_db
   export LICENSE_DB_USER=postgres
   export LICENSE_DB_PASSWORD=your_secure_password
   export LICENSE_JWT_SECRET=your_very_long_random_secret_key
   export LICENSE_ADMIN_PASSWORD=your_secure_admin_password
   ```
4. **Run PHP server** or configure Nginx/Apache:
   ```bash
   php -S 0.0.0.0:8081 -t public
   ```

## Configuration

| Variable | Description | Default |
|----------|-------------|---------|
| `POSTGRES_DB` | Database name | `license_db` |
| `POSTGRES_USER` | Database user | `license` |
| `POSTGRES_PASSWORD` | Database password | Required |
| `LICENSE_PORT` | HTTP port | `8081` |
| `LICENSE_ADMIN_PASSWORD` | Admin panel password | Required |
| `LICENSE_JWT_SECRET` | JWT signing secret | Required |
| `LICENSE_SERVER_URL` | Public URL for callbacks | Required |

### M-Pesa Configuration (Optional)
| Variable | Description |
|----------|-------------|
| `MPESA_CONSUMER_KEY` | Safaricom API key |
| `MPESA_CONSUMER_SECRET` | Safaricom API secret |
| `MPESA_SHORTCODE` | Your Paybill/Till number |
| `MPESA_PASSKEY` | STK Push passkey |
| `MPESA_ENV` | `sandbox` or `production` |

## URLs

| Endpoint | Description |
|----------|-------------|
| `/admin.php` | Admin dashboard |
| `/subscribe.php` | Customer subscription & payment page |
| `/health` | Health check |

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/validate` | POST | Validate a license key |
| `/api/activate` | POST | Activate a license |
| `/api/heartbeat` | POST | Check-in from active installation |
| `/api/deactivate` | POST | Deactivate an installation |
| `/api/pay.php?action=initiate` | POST | Initiate M-Pesa payment |
| `/api/pay.php?action=callback` | POST | M-Pesa callback webhook |
| `/api/pay.php?action=check` | POST | Check license status/pricing |

## Client Integration

Add these to each CRM instance's `.env`:
```bash
LICENSE_SERVER_URL=https://license.yourdomain.com
LICENSE_KEY=XXXX-XXXX-XXXX-XXXX
```

The CRM will automatically validate against your license server on startup and send daily heartbeats.

## Default Tiers

| Tier | Users | Customers | ONUs | Price |
|------|-------|-----------|------|-------|
| Starter | 3 | 100 | 50 | KES 30/mo |
| Professional | 10 | 500 | 200 | KES 80/mo |
| Enterprise | Unlimited | Unlimited | Unlimited | KES 200/mo |

Manage tiers in the admin panel under the "Tiers" tab.

## Security Notes

- Always use HTTPS in production
- Set strong values for `LICENSE_JWT_SECRET` and `LICENSE_ADMIN_PASSWORD`
- Never commit real credentials to version control
- Restrict access to admin.php by IP if possible
