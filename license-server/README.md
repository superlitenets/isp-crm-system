# ISP CRM License Server

A standalone license management server for distributing the ISP CRM system.

## Deployment

1. **Upload files** to your license server (separate from CRM installations)

2. **Create database** and run the schema:
   ```bash
   psql -U postgres -c "CREATE DATABASE license_server;"
   psql -U postgres -d license_server -f schema.sql
   ```

3. **Set environment variables** (REQUIRED for security):
   ```bash
   export LICENSE_DB_HOST=localhost
   export LICENSE_DB_PORT=5432
   export LICENSE_DB_NAME=license_server
   export LICENSE_DB_USER=postgres
   export LICENSE_DB_PASSWORD=your_secure_password
   export LICENSE_JWT_SECRET=your_very_long_random_secret_key_here
   export LICENSE_ADMIN_PASSWORD=your_secure_admin_password
   ```

4. **Configure web server** to point to `/public/` directory

5. **Access admin panel**: `https://your-license-server.com/admin.php`

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/validate` | POST | Validate a license key |
| `/api/activate` | POST | Activate a license |
| `/api/heartbeat` | POST | Check-in from active installation |
| `/api/deactivate` | POST | Deactivate an installation |

## Security Notes

- Always use HTTPS in production
- Set strong values for `LICENSE_JWT_SECRET` and `LICENSE_ADMIN_PASSWORD`
- Never commit real credentials to version control
- Restrict access to admin.php by IP if possible
