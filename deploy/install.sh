#!/bin/bash
set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

print_step() { echo -e "\n${CYAN}[STEP]${NC} $1"; }
print_ok() { echo -e "${GREEN}[OK]${NC} $1"; }
print_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
print_err() { echo -e "${RED}[ERROR]${NC} $1"; }

if [ "$EUID" -ne 0 ]; then
    print_err "Please run as root: sudo bash install.sh"
    exit 1
fi

echo -e "${CYAN}"
echo "============================================="
echo "   ISP CRM - Production Server Installer"
echo "============================================="
echo -e "${NC}"

DOMAIN=""
DB_NAME="isp_crm"
DB_USER="isp_crm"
DB_PASS=""
APP_DIR="/var/www/isp-crm"
TIMEZONE="Africa/Nairobi"
ADMIN_EMAIL=""

read -p "Enter your domain or subdomain (e.g. crm.superlite.co.ke): " DOMAIN
if [ -z "$DOMAIN" ]; then
    print_err "Domain is required"
    exit 1
fi

read -p "Enter admin email (for SSL certificate): " ADMIN_EMAIL
if [ -z "$ADMIN_EMAIL" ]; then
    ADMIN_EMAIL="admin@${DOMAIN}"
fi

DB_PASS=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)
SESSION_SECRET=$(openssl rand -hex 32)
ENCRYPTION_KEY=$(openssl rand -hex 32)

echo ""
echo -e "${CYAN}License Configuration (optional - can be set later in Settings > License):${NC}"
read -p "License Server URL (e.g. https://license.superlite.co.ke): " LICENSE_SERVER_URL
read -p "License Key: " LICENSE_KEY
if [ -z "$LICENSE_SERVER_URL" ] || [ -z "$LICENSE_KEY" ]; then
    echo -e "${YELLOW}License not configured. You can set it later in Settings > License.${NC}"
fi

echo ""
echo -e "${YELLOW}Configuration Summary:${NC}"
echo "  Domain:    ${DOMAIN}"
echo "  App Dir:   ${APP_DIR}"
echo "  Database:  ${DB_NAME}"
echo "  DB User:   ${DB_USER}"
echo "  Timezone:  ${TIMEZONE}"
echo "  License:   ${LICENSE_SERVER_URL:-Not configured}"
echo ""
read -p "Continue? (y/n): " CONFIRM
if [ "$CONFIRM" != "y" ]; then
    echo "Aborted."
    exit 0
fi

print_step "Updating system packages..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y

print_step "Installing required packages..."
apt-get install -y \
    nginx \
    certbot python3-certbot-nginx \
    postgresql postgresql-contrib \
    curl wget git unzip software-properties-common \
    cron \
    snmp snmp-mibs-downloader

print_step "Installing PHP 8.2..."
if ! dpkg -l | grep -q php8.2-fpm; then
    add-apt-repository -y ppa:ondrej/php 2>/dev/null || true
    apt-get update -y
fi
apt-get install -y \
    php8.2-fpm php8.2-pgsql php8.2-curl php8.2-mbstring php8.2-xml \
    php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath php8.2-snmp \
    php8.2-readline php8.2-soap php8.2-cli

PHP_FPM_SERVICE=$(systemctl list-units --all --type=service | grep php | grep fpm | awk '{print $1}' | head -1)
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.2-fpm}"

print_step "Installing Node.js 20 LTS..."
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi
print_ok "Node.js $(node -v), npm $(npm -v)"

print_step "Installing Composer..."
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

print_step "Setting up PostgreSQL database..."
systemctl enable --now postgresql
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}';"
sudo -u postgres psql -c "ALTER USER ${DB_USER} WITH PASSWORD '${DB_PASS}';" 2>/dev/null
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};"
sudo -u postgres psql -d "${DB_NAME}" -c "GRANT ALL ON SCHEMA public TO ${DB_USER};"
print_ok "Database '${DB_NAME}' ready"

print_step "Creating application directory..."
mkdir -p "${APP_DIR}"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

if [ -d "${PROJECT_ROOT}/src" ] && [ -d "${PROJECT_ROOT}/public" ]; then
    print_step "Copying application files from project..."
    rsync -av --exclude='deploy' --exclude='.git' --exclude='node_modules' \
        --exclude='.cache' --exclude='.local' --exclude='.config' \
        --exclude='backups/*.sql' --exclude='.replit' --exclude='replit.nix' \
        --exclude='replit.md' --exclude='attached_assets' \
        "${PROJECT_ROOT}/" "${APP_DIR}/"
else
    print_err "Project files not found in parent directory."
    print_err "Please copy your project files to ${APP_DIR} manually, then re-run this script."
    exit 1
fi

print_step "Creating environment file..."
cat > "${APP_DIR}/.env" << ENVEOF
PGHOST=localhost
PGPORT=5432
PGDATABASE=${DB_NAME}
PGUSER=${DB_USER}
PGPASSWORD=${DB_PASS}

DATABASE_URL=postgresql://${DB_USER}:${DB_PASS}@localhost:5432/${DB_NAME}

APP_URL=https://${DOMAIN}
APP_TIMEZONE=${TIMEZONE}
SESSION_SECRET=${SESSION_SECRET}
ENCRYPTION_KEY=${ENCRYPTION_KEY}

OLT_SERVICE_URL=http://127.0.0.1:3002
OLT_SERVICE_PORT=3002
OLT_ENCRYPTION_KEY=${ENCRYPTION_KEY}
PHP_API_URL=http://127.0.0.1:9000

WHATSAPP_SESSION_URL=http://127.0.0.1:3001
WA_PORT=3001
WA_HOST=0.0.0.0

LICENSE_SERVER_URL=${LICENSE_SERVER_URL}
LICENSE_KEY=${LICENSE_KEY}

TZ=${TIMEZONE}
ENVEOF

chmod 600 "${APP_DIR}/.env"
print_ok "Environment file created at ${APP_DIR}/.env"

print_step "Configuring PHP-FPM pool..."
cat > "/etc/php/8.2/fpm/pool.d/isp-crm.conf" << 'PHPPOOL'
[isp-crm]
user = www-data
group = www-data
listen = /run/php/isp-crm.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500

request_terminate_timeout = 300
php_admin_value[max_execution_time] = 300
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 50M
php_admin_value[date.timezone] = Africa/Nairobi

PHPPOOL

while IFS='=' read -r key value; do
    key=$(echo "$key" | xargs)
    if [ -n "$key" ] && [[ ! "$key" =~ ^# ]] && [ -n "$value" ]; then
        value=$(echo "$value" | xargs)
        echo "env[${key}] = ${value}" >> "/etc/php/8.2/fpm/pool.d/isp-crm.conf"
    fi
done < "${APP_DIR}/.env"

print_step "Installing PHP dependencies..."
cd "${APP_DIR}"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --optimize-autoloader 2>&1 || {
    print_warn "Composer install from lock file failed, regenerating..."
    rm -f composer.lock
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --optimize-autoloader
}
print_ok "PHP dependencies installed"

print_step "Installing Node.js dependencies (OLT Service)..."
if [ -d "${APP_DIR}/olt-service" ]; then
    cd "${APP_DIR}/olt-service"
    npm install --omit=dev 2>&1 | tail -3
    print_ok "OLT service dependencies installed"
fi

print_step "Installing Node.js dependencies (WhatsApp Service)..."
if [ -d "${APP_DIR}/whatsapp-service" ]; then
    cd "${APP_DIR}/whatsapp-service"
    npm install --omit=dev 2>&1 | tail -3
    print_ok "WhatsApp service dependencies installed"
fi

print_step "Setting file permissions..."
chown -R www-data:www-data "${APP_DIR}"
chmod -R 755 "${APP_DIR}"
chmod -R 775 "${APP_DIR}/public"
mkdir -p "${APP_DIR}/data" "${APP_DIR}/whatsapp-service/auth_info" "${APP_DIR}/backups" /tmp/auth_progress
chown -R www-data:www-data "${APP_DIR}/data" "${APP_DIR}/whatsapp-service/auth_info" "${APP_DIR}/backups"
chmod 600 "${APP_DIR}/.env"

print_step "Configuring Nginx..."

mkdir -p /etc/nginx/snippets
if [ ! -f /etc/nginx/snippets/fastcgi-params.conf ]; then
    cat > /etc/nginx/snippets/fastcgi-params.conf << 'FCGIEOF'
fastcgi_split_path_info ^(.+\.php)(/.+)$;
fastcgi_index index.php;
include fastcgi_params;
FCGIEOF
fi

cat > "/etc/nginx/sites-available/${DOMAIN}" << NGINXEOF
server {
    listen 80;
    server_name ${DOMAIN};

    root ${APP_DIR}/public;
    index index.php;

    client_max_body_size 50M;

    location / {
        try_files \$uri \$uri/ /router.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-params.conf;
        fastcgi_pass unix:/run/php/isp-crm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 300;
    }

    location /whatsapp-api/ {
        proxy_pass http://127.0.0.1:3001/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_read_timeout 86400;
    }

    location /olt-api/ {
        proxy_pass http://127.0.0.1:3002/;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_read_timeout 120;
    }

    location ~ /\.(env|git|htaccess) {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2?)$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
    }

    access_log /var/log/nginx/${DOMAIN}_access.log;
    error_log /var/log/nginx/${DOMAIN}_error.log;
}
NGINXEOF

ln -sf "/etc/nginx/sites-available/${DOMAIN}" /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

print_step "Starting PHP-FPM..."
systemctl restart "${PHP_FPM_SERVICE}" || {
    print_warn "PHP-FPM failed to start. Check: journalctl -xeu ${PHP_FPM_SERVICE}"
}

nginx -t && systemctl reload nginx || {
    print_warn "Nginx config test failed. Check: nginx -t"
}
print_ok "Nginx configured for ${DOMAIN}"

print_step "Obtaining SSL certificate with Let's Encrypt..."
certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${ADMIN_EMAIL}" --redirect || {
    print_warn "SSL certificate setup failed. You can retry later with:"
    echo "  certbot --nginx -d ${DOMAIN}"
}

print_step "Creating systemd service for OLT Session Manager..."
cat > /etc/systemd/system/isp-olt.service << OLTEOF
[Unit]
Description=ISP CRM OLT Session Manager
After=network.target postgresql.service
Wants=postgresql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}/olt-service
EnvironmentFile=${APP_DIR}/.env
ExecStart=/usr/bin/node index.js
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal
SyslogIdentifier=isp-olt

[Install]
WantedBy=multi-user.target
OLTEOF

print_step "Creating systemd service for WhatsApp Service..."
cat > /etc/systemd/system/isp-whatsapp.service << WAEOF
[Unit]
Description=ISP CRM WhatsApp Service (Baileys)
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}/whatsapp-service
EnvironmentFile=${APP_DIR}/.env
ExecStart=/usr/bin/node index.js
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal
SyslogIdentifier=isp-whatsapp

[Install]
WantedBy=multi-user.target
WAEOF

print_step "Initializing database schema..."
cd "${APP_DIR}"
export PGHOST=localhost PGPORT=5432 PGDATABASE="${DB_NAME}" PGUSER="${DB_USER}" PGPASSWORD="${DB_PASS}"

print_step "Step 1/4: Loading complete schema from migration.sql..."
if [ -f "${APP_DIR}/database/migration.sql" ]; then
    sudo -u postgres psql -d "${DB_NAME}" -f "${APP_DIR}/database/migration.sql" 2>&1 | grep -c "CREATE TABLE" | xargs -I{} echo "  Created/verified {} tables"
    print_ok "Complete schema loaded"
else
    print_warn "migration.sql not found, relying on init_db.php only"
fi

print_step "Step 2/4: Running PHP migrations and seeding..."
php -r "
require_once 'vendor/autoload.php';
require_once 'config/database.php';
require_once 'config/init_db.php';
initializeDatabase();
echo \"Database initialized.\n\";
" 2>&1 && print_ok "Migrations and seeding complete" || print_warn "Some migrations had warnings (non-fatal)"

print_step "Step 3/4: Applying column fixes..."
if [ -f "${APP_DIR}/database/fix_missing_columns.sql" ]; then
    sudo -u postgres psql -d "${DB_NAME}" -f "${APP_DIR}/database/fix_missing_columns.sql" 2>&1 | grep -cv "already exists\|GRANT\|ALTER" | xargs -I{} echo "  Applied {} fixes"
    print_ok "Column fixes applied"
fi

print_step "Step 4/4: Configuring database permissions..."
sudo -u postgres psql -d "${DB_NAME}" -c "
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO ${DB_USER};
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO ${DB_USER};
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO ${DB_USER};
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO ${DB_USER};
" 2>/dev/null
print_ok "Database fully initialized with all tables, columns, and permissions"

print_step "Creating default admin user..."
ADMIN_HASH=$(php -r "echo password_hash('admin123', PASSWORD_DEFAULT);")
sudo -u postgres psql -d "${DB_NAME}" -c "
INSERT INTO users (name, email, phone, password_hash, role)
VALUES ('Admin', 'admin@${DOMAIN}', '0700000000', '${ADMIN_HASH}', 'admin')
ON CONFLICT (email) DO NOTHING;
" 2>/dev/null || true
print_ok "Default admin: admin@${DOMAIN} / admin123 (CHANGE THIS!)"

print_step "Setting up cron jobs..."
CRON_FILE="/etc/cron.d/isp-crm"
cat > "${CRON_FILE}" << CRONEOF
PGHOST=localhost
PGPORT=5432
PGDATABASE=${DB_NAME}
PGUSER=${DB_USER}
PGPASSWORD=${DB_PASS}
APP_URL=https://${DOMAIN}
APP_TIMEZONE=${TIMEZONE}

*/5 * * * * www-data cd ${APP_DIR}/public && php cron.php >> /var/log/isp-crm-cron.log 2>&1
0 2 * * * www-data pg_dump -h localhost -U ${DB_USER} ${DB_NAME} | gzip > ${APP_DIR}/backups/daily_\$(date +\%Y\%m\%d).sql.gz 2>/dev/null
0 3 * * 0 www-data find ${APP_DIR}/backups/ -name "daily_*.sql.gz" -mtime +30 -delete 2>/dev/null
CRONEOF

chmod 644 "${CRON_FILE}"
print_ok "Cron jobs configured"

print_step "Enabling and starting all services..."
systemctl daemon-reload
systemctl restart "${PHP_FPM_SERVICE}"
systemctl enable --now isp-olt
systemctl enable --now isp-whatsapp
systemctl enable nginx
systemctl enable "${PHP_FPM_SERVICE}"
print_ok "All services started"

print_step "Configuring firewall (UFW)..."
if command -v ufw &> /dev/null; then
    ufw allow 80/tcp >/dev/null 2>&1
    ufw allow 443/tcp >/dev/null 2>&1
    ufw allow 22/tcp >/dev/null 2>&1
    ufw --force enable 2>/dev/null || true
    print_ok "Firewall configured (HTTP, HTTPS, SSH)"
fi

mkdir -p "${APP_DIR}/deploy"
cat > "${APP_DIR}/deploy/credentials.txt" << CREDEOF
=== ISP CRM Production Credentials ===
Generated: $(date)

Domain: https://${DOMAIN}
Admin Login: admin@${DOMAIN} / admin123

Database:
  Host: localhost
  Port: 5432
  Name: ${DB_NAME}
  User: ${DB_USER}
  Password: ${DB_PASS}

Session Secret: ${SESSION_SECRET}
Encryption Key: ${ENCRYPTION_KEY}

IMPORTANT: Change the admin password immediately after first login!
CREDEOF
chmod 600 "${APP_DIR}/deploy/credentials.txt"

echo ""
echo -e "${GREEN}============================================="
echo "   Installation Complete!"
echo "=============================================${NC}"
echo ""
echo -e "  ${CYAN}URL:${NC}        https://${DOMAIN}"
echo -e "  ${CYAN}Admin:${NC}      admin@${DOMAIN}"
echo -e "  ${CYAN}Password:${NC}   admin123 ${RED}(CHANGE IMMEDIATELY!)${NC}"
echo ""
echo -e "  ${CYAN}Database:${NC}   ${DB_NAME}"
echo -e "  ${CYAN}DB User:${NC}    ${DB_USER}"
echo -e "  ${CYAN}DB Pass:${NC}    ${DB_PASS}"
echo -e "  ${CYAN}App Dir:${NC}    ${APP_DIR}"
echo -e "  ${CYAN}Env File:${NC}   ${APP_DIR}/.env"
echo ""
echo -e "  ${YELLOW}Services:${NC}"
echo "    PHP-FPM:     systemctl status ${PHP_FPM_SERVICE}"
echo "    OLT Service: systemctl status isp-olt"
echo "    WhatsApp:    systemctl status isp-whatsapp"
echo "    Nginx:       systemctl status nginx"
echo ""
echo -e "  ${YELLOW}Logs:${NC}"
echo "    OLT:       journalctl -u isp-olt -f"
echo "    WhatsApp:  journalctl -u isp-whatsapp -f"
echo "    Nginx:     tail -f /var/log/nginx/${DOMAIN}_error.log"
echo "    PHP-FPM:   journalctl -u ${PHP_FPM_SERVICE} -f"
echo "    Cron:      tail -f /var/log/isp-crm-cron.log"
echo ""
echo -e "  ${YELLOW}Management:${NC}"
echo "    Restart all:  systemctl restart ${PHP_FPM_SERVICE} isp-olt isp-whatsapp nginx"
echo "    Backup DB:    pg_dump -h localhost -U ${DB_USER} ${DB_NAME} > backup.sql"
echo "    Update code:  cd ${APP_DIR} && git pull && composer install --no-dev && systemctl restart ${PHP_FPM_SERVICE} isp-olt isp-whatsapp"
echo ""
echo -e "${YELLOW}Credentials saved to: ${APP_DIR}/deploy/credentials.txt${NC}"
echo -e "${RED}IMPORTANT: Change the default admin password after first login!${NC}"
echo ""
