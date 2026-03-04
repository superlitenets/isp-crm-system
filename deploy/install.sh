#!/bin/bash
set -e

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
echo -e "${CYAN}License Configuration (required for new deployments):${NC}"
read -p "License Server URL (e.g. https://license.superlite.co.ke): " LICENSE_SERVER_URL
read -p "License Key: " LICENSE_KEY
if [ -z "$LICENSE_SERVER_URL" ] || [ -z "$LICENSE_KEY" ]; then
    echo -e "${YELLOW}Warning: License not configured. The CRM will require license activation before use.${NC}"
    echo -e "${YELLOW}You can configure it later in Settings > License.${NC}"
fi

echo ""
echo -e "${YELLOW}Configuration:${NC}"
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
if ! command -v php &> /dev/null || php -v | grep -q "PHP 7"; then
    add-apt-repository -y ppa:ondrej/php 2>/dev/null || true
    apt-get update -y
fi
apt-get install -y \
    php8.2-fpm php8.2-pgsql php8.2-curl php8.2-mbstring php8.2-xml \
    php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath php8.2-snmp \
    php8.2-readline php8.2-soap php8.2-cli

PHP_FPM_SOCK="/run/php/php8.2-fpm.sock"
if [ ! -S "$PHP_FPM_SOCK" ]; then
    PHP_FPM_SOCK=$(find /run/php/ -name "*.sock" 2>/dev/null | head -1)
fi
PHP_FPM_SERVICE=$(systemctl list-units --type=service | grep php | grep fpm | awk '{print $1}' | head -1)
if [ -z "$PHP_FPM_SERVICE" ]; then
    PHP_FPM_SERVICE="php8.2-fpm"
fi

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
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}';"
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
        "${PROJECT_ROOT}/" "${APP_DIR}/"
else
    print_warn "Project files not found in parent directory."
    print_warn "Please copy your project files to ${APP_DIR} manually, then re-run this script."
    echo "  Example: rsync -av /path/to/project/ ${APP_DIR}/"
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

print_step "Configuring PHP to load environment..."
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

ENV_LINES=""
while IFS='=' read -r key value; do
    key=$(echo "$key" | xargs)
    if [ -n "$key" ] && [[ ! "$key" =~ ^# ]]; then
        value=$(echo "$value" | xargs)
        ENV_LINES="${ENV_LINES}env[${key}] = ${value}\n"
    fi
done < "${APP_DIR}/.env"
echo -e "$ENV_LINES" >> "/etc/php/8.2/fpm/pool.d/isp-crm.conf"

PHP_FPM_SOCK="/run/php/isp-crm.sock"

print_step "Installing PHP dependencies..."
cd "${APP_DIR}"
if [ -f composer.json ]; then
    composer install --no-dev --optimize-autoloader 2>/dev/null || composer install --optimize-autoloader
    print_ok "PHP dependencies installed"
fi

print_step "Installing Node.js dependencies (OLT Service)..."
if [ -d "${APP_DIR}/olt-service" ]; then
    cd "${APP_DIR}/olt-service"
    npm install --production
    print_ok "OLT service dependencies installed"
fi

print_step "Installing Node.js dependencies (WhatsApp Service)..."
if [ -d "${APP_DIR}/whatsapp-service" ]; then
    cd "${APP_DIR}/whatsapp-service"
    npm install --production
    print_ok "WhatsApp service dependencies installed"
fi

print_step "Setting file permissions..."
chown -R www-data:www-data "${APP_DIR}"
chmod -R 755 "${APP_DIR}"
chmod -R 775 "${APP_DIR}/public"
mkdir -p "${APP_DIR}/data" "${APP_DIR}/whatsapp-service/auth_info" /tmp/auth_progress
chown -R www-data:www-data "${APP_DIR}/data" "${APP_DIR}/whatsapp-service/auth_info"

print_step "Configuring Nginx..."
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
        fastcgi_pass unix:${PHP_FPM_SOCK};
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

if [ ! -f /etc/nginx/snippets/fastcgi-params.conf ]; then
    cat > /etc/nginx/snippets/fastcgi-params.conf << 'FCGIEOF'
fastcgi_split_path_info ^(.+\.php)(/.+)$;
fastcgi_index index.php;
include fastcgi_params;
FCGIEOF
fi

ln -sf "/etc/nginx/sites-available/${DOMAIN}" /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
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
php -r "
require_once 'vendor/autoload.php';
require_once 'config/database.php';
require_once 'config/init_db.php';
initializeDatabase();
echo \"Database initialized successfully.\n\";
" 2>&1 && print_ok "Database schema created" || print_warn "Database init had warnings (check manually)"

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
mkdir -p "${APP_DIR}/backups"
chown www-data:www-data "${APP_DIR}/backups"
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
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw allow 22/tcp
    ufw --force enable 2>/dev/null || true
    print_ok "Firewall configured (HTTP, HTTPS, SSH)"
fi

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
echo "    Cron:      tail -f /var/log/isp-crm-cron.log"
echo ""
echo -e "  ${YELLOW}Management:${NC}"
echo "    Restart all:  systemctl restart ${PHP_FPM_SERVICE} isp-olt isp-whatsapp nginx"
echo "    Backup DB:    pg_dump -h localhost -U ${DB_USER} ${DB_NAME} > backup.sql"
echo "    Update code:  cd ${APP_DIR} && git pull && composer install && systemctl restart ${PHP_FPM_SERVICE} isp-olt isp-whatsapp"
echo ""

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

echo -e "${YELLOW}Credentials saved to: ${APP_DIR}/deploy/credentials.txt${NC}"
echo -e "${RED}IMPORTANT: Change the default admin password after first login!${NC}"
echo ""
