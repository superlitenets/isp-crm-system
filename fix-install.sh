#!/bin/bash

set -e

echo "=========================================="
echo "  ISP CRM Quick Fix Script"
echo "=========================================="
echo ""

cd ~/isp

# Generate new secrets
echo "Generating secure secrets..."
SESSION_SECRET=$(openssl rand -base64 32 | tr -d '/+=' | head -c 32)

# Backup and update .env
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

# Update SESSION_SECRET
sed -i "s/SESSION_SECRET=.*/SESSION_SECRET=$SESSION_SECRET/" .env

# Add WHATSAPP_SESSION_URL if missing
if ! grep -q "WHATSAPP_SESSION_URL" .env; then
    echo "WHATSAPP_SESSION_URL=http://whatsapp:3001" >> .env
fi

echo "Updated .env with new SESSION_SECRET"
echo ""

# Restart containers
echo "Restarting containers..."
docker compose down
docker compose up -d --build

# Wait for services
echo "Waiting for services to start (30 seconds)..."
sleep 30

# Check status
echo ""
docker compose ps

# Get WhatsApp secret
echo ""
echo "=========================================="
echo "  Fix Complete!"
echo "=========================================="
echo ""
echo "WhatsApp Session Secret (copy this):"
docker exec isp_crm_whatsapp cat /app/.api_secret_dir/secret 2>/dev/null || echo "Run again in 30 seconds if empty"
echo ""
echo ""
echo "IMPORTANT - You must now:"
echo "1. Login to CRM at https://crm.superlite.co.ke"
echo "2. Go to Settings > Biometric Devices - Edit and re-enter passwords"
echo "3. Go to Settings > WhatsApp - Enter the session secret shown above"
echo ""
