#!/bin/bash
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'

APP_DIR="/var/www/isp-crm"
PHP_FPM_SERVICE=$(systemctl list-units --type=service | grep php | grep fpm | awk '{print $1}' | head -1)
if [ -z "$PHP_FPM_SERVICE" ]; then
    PHP_FPM_SERVICE="php8.2-fpm"
fi

echo -e "${CYAN}=== ISP CRM Update ===${NC}"

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root: sudo bash update.sh${NC}"
    exit 1
fi

if [ ! -d "$APP_DIR" ]; then
    echo -e "${RED}App directory not found: ${APP_DIR}${NC}"
    exit 1
fi

echo -e "${CYAN}[1/5]${NC} Backing up database..."
BACKUP_FILE="${APP_DIR}/backups/pre_update_$(date +%Y%m%d_%H%M%S).sql.gz"
mkdir -p "${APP_DIR}/backups"
source "${APP_DIR}/.env"
pg_dump -h localhost -U "${PGUSER}" "${PGDATABASE}" | gzip > "${BACKUP_FILE}"
echo -e "${GREEN}  Backup: ${BACKUP_FILE}${NC}"

echo -e "${CYAN}[2/5]${NC} Updating application files..."
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
if [ -d "${PROJECT_ROOT}/src" ] && [ -d "${PROJECT_ROOT}/public" ]; then
    rsync -av --exclude='deploy' --exclude='.git' --exclude='node_modules' \
        --exclude='.cache' --exclude='.local' --exclude='.config' \
        --exclude='.env' --exclude='backups' --exclude='.replit' --exclude='replit.nix' \
        --exclude='whatsapp-service/auth_info' \
        "${PROJECT_ROOT}/" "${APP_DIR}/"
    chown -R www-data:www-data "${APP_DIR}"
fi

echo -e "${CYAN}[3/5]${NC} Installing dependencies..."
cd "${APP_DIR}" && composer install --no-dev --optimize-autoloader 2>/dev/null || true
cd "${APP_DIR}/olt-service" && npm install --production 2>/dev/null || true
cd "${APP_DIR}/whatsapp-service" && npm install --production 2>/dev/null || true

echo -e "${CYAN}[4/5]${NC} Running database migrations..."
cd "${APP_DIR}"
php -r "
require_once 'vendor/autoload.php';
require_once 'config/database.php';
require_once 'config/init_db.php';
initializeDatabase();
echo \"Migrations complete.\n\";
" 2>&1 || echo "  (check manually if needed)"

echo -e "${CYAN}[5/5]${NC} Restarting services..."
systemctl restart "${PHP_FPM_SERVICE}" isp-olt isp-whatsapp
systemctl reload nginx

echo ""
echo -e "${GREEN}=== Update Complete ===${NC}"
echo "  Check services: systemctl status isp-olt isp-whatsapp ${PHP_FPM_SERVICE}"
echo "  Check logs:     journalctl -u isp-olt -f"
