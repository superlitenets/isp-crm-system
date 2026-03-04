#!/bin/bash
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

print_step() { echo -e "\n${CYAN}[STEP]${NC} $1"; }
print_ok() { echo -e "${GREEN}[OK]${NC} $1"; }

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root: sudo bash install.sh${NC}"
    exit 1
fi

echo -e "${CYAN}"
echo "============================================="
echo "   License Server - Production Installer"
echo "============================================="
echo -e "${NC}"

DOMAIN=""
DB_NAME="license_server"
DB_USER="license_admin"
DB_PASS=""
APP_DIR="/var/www/license-server"
ADMIN_EMAIL=""

read -p "Enter license server domain (e.g. license.superlite.co.ke): " DOMAIN
if [ -z "$DOMAIN" ]; then echo -e "${RED}Domain is required${NC}"; exit 1; fi

read -p "Enter admin email (for SSL): " ADMIN_EMAIL
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@${DOMAIN}}"

read -sp "Set admin dashboard password: " ADMIN_PASS
echo ""
if [ -z "$ADMIN_PASS" ]; then echo -e "${RED}Password is required${NC}"; exit 1; fi

DB_PASS=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)
JWT_SECRET=$(openssl rand -hex 32)

echo ""
echo -e "${CYAN}M-Pesa Configuration (for license payments):${NC}"
read -p "M-Pesa Consumer Key (leave blank to skip): " MPESA_KEY
read -p "M-Pesa Consumer Secret: " MPESA_SECRET
read -p "M-Pesa Shortcode: " MPESA_SHORTCODE
read -p "M-Pesa Passkey: " MPESA_PASSKEY
read -p "M-Pesa Account Type (paybill/till) [paybill]: " MPESA_ACCT_TYPE
MPESA_ACCT_TYPE="${MPESA_ACCT_TYPE:-paybill}"
read -p "M-Pesa Environment (sandbox/production) [production]: " MPESA_ENV_VAL
MPESA_ENV_VAL="${MPESA_ENV_VAL:-production}"

echo ""
echo -e "${YELLOW}Configuration:${NC}"
echo "  Domain:    ${DOMAIN}"
echo "  App Dir:   ${APP_DIR}"
echo "  Database:  ${DB_NAME}"
echo "  M-Pesa:    ${MPESA_KEY:+Configured}${MPESA_KEY:-Not configured}"
echo ""
read -p "Continue? (y/n): " CONFIRM
if [ "$CONFIRM" != "y" ]; then echo "Aborted."; exit 0; fi

print_step "Installing required packages..."
apt-get update -y
apt-get install -y nginx certbot python3-certbot-nginx postgresql postgresql-contrib

if ! command -v php &> /dev/null; then
    add-apt-repository -y ppa:ondrej/php 2>/dev/null || true
    apt-get update -y
fi
apt-get install -y php8.2-fpm php8.2-pgsql php8.2-curl php8.2-mbstring php8.2-xml php8.2-cli

print_step "Setting up PostgreSQL database..."
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}';"
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};"
sudo -u postgres psql -d "${DB_NAME}" -c "GRANT ALL ON SCHEMA public TO ${DB_USER};"
print_ok "Database '${DB_NAME}' ready"

print_step "Setting up application directory..."
mkdir -p "${APP_DIR}"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
if [ -f "${SCRIPT_DIR}/schema.sql" ]; then
    rsync -av --exclude='.env' --exclude='install.sh' "${SCRIPT_DIR}/" "${APP_DIR}/"
fi

print_step "Creating environment file..."
cat > "${APP_DIR}/.env" << ENVEOF
LICENSE_DB_HOST=localhost
LICENSE_DB_PORT=5432
LICENSE_DB_NAME=${DB_NAME}
LICENSE_DB_USER=${DB_USER}
LICENSE_DB_PASSWORD=${DB_PASS}
LICENSE_JWT_SECRET=${JWT_SECRET}
LICENSE_ADMIN_PASSWORD=${ADMIN_PASS}
LICENSE_SERVER_URL=https://${DOMAIN}
LICENSE_SERVER_PUBLIC_URL=https://${DOMAIN}
MPESA_CONSUMER_KEY=${MPESA_KEY}
MPESA_CONSUMER_SECRET=${MPESA_SECRET}
MPESA_SHORTCODE=${MPESA_SHORTCODE}
MPESA_PASSKEY=${MPESA_PASSKEY}
MPESA_ACCOUNT_TYPE=${MPESA_ACCT_TYPE}
MPESA_ENV=${MPESA_ENV_VAL}
ENVEOF
chmod 600 "${APP_DIR}/.env"

print_step "Configuring PHP-FPM pool..."
cat > "/etc/php/8.2/fpm/pool.d/license-server.conf" << PHPPOOL
[license-server]
user = www-data
group = www-data
listen = /run/php/license-server.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

PHPPOOL

while IFS='=' read -r key value; do
    key=$(echo "$key" | xargs)
    if [ -n "$key" ] && [[ ! "$key" =~ ^# ]]; then
        value=$(echo "$value" | xargs)
        echo "env[${key}] = ${value}" >> "/etc/php/8.2/fpm/pool.d/license-server.conf"
    fi
done < "${APP_DIR}/.env"

print_step "Initializing database schema..."
PGPASSWORD="${DB_PASS}" psql -h localhost -U "${DB_USER}" -d "${DB_NAME}" -f "${APP_DIR}/schema.sql"
print_ok "Schema initialized"

print_step "Setting permissions..."
chown -R www-data:www-data "${APP_DIR}"
chmod -R 755 "${APP_DIR}"

print_step "Configuring Nginx..."
cat > "/etc/nginx/sites-available/${DOMAIN}" << NGINXEOF
server {
    listen 80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
    index index.php admin.php;

    client_max_body_size 10M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-params.conf;
        fastcgi_pass unix:/run/php/license-server.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 60;
    }

    location ~ /\.(env|git|htaccess) {
        deny all;
    }

    access_log /var/log/nginx/${DOMAIN}_access.log;
    error_log /var/log/nginx/${DOMAIN}_error.log;
}
NGINXEOF

ln -sf "/etc/nginx/sites-available/${DOMAIN}" /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

print_step "Obtaining SSL certificate..."
certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${ADMIN_EMAIL}" --redirect || {
    echo -e "${YELLOW}SSL setup failed. Retry later: certbot --nginx -d ${DOMAIN}${NC}"
}

PHP_FPM_SERVICE=$(systemctl list-units --type=service | grep php | grep fpm | awk '{print $1}' | head -1)
systemctl restart "${PHP_FPM_SERVICE:-php8.2-fpm}"

echo ""
echo -e "${GREEN}============================================="
echo "   License Server Installed!"
echo "=============================================${NC}"
echo ""
echo -e "  ${CYAN}Admin Panel:${NC}   https://${DOMAIN}/admin.php"
echo -e "  ${CYAN}API Endpoint:${NC}  https://${DOMAIN}/api/"
echo -e "  ${CYAN}Subscribe:${NC}     https://${DOMAIN}/subscribe.php"
echo ""
echo -e "  ${CYAN}Admin Password:${NC} (the one you entered)"
echo -e "  ${CYAN}DB Name:${NC}       ${DB_NAME}"
echo -e "  ${CYAN}DB User:${NC}       ${DB_USER}"
echo -e "  ${CYAN}DB Pass:${NC}       ${DB_PASS}"
echo ""

cat > "${APP_DIR}/credentials.txt" << CREDEOF
=== License Server Credentials ===
Generated: $(date)

URL: https://${DOMAIN}
Admin Panel: https://${DOMAIN}/admin.php
API: https://${DOMAIN}/api/

Database:
  Host: localhost
  Name: ${DB_NAME}
  User: ${DB_USER}
  Password: ${DB_PASS}

JWT Secret: ${JWT_SECRET}
CREDEOF
chmod 600 "${APP_DIR}/credentials.txt"
echo -e "${YELLOW}Credentials saved to: ${APP_DIR}/credentials.txt${NC}"
