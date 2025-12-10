#!/bin/bash

set -e

echo "=========================================="
echo "  ISP CRM Fresh Installation Script"
echo "=========================================="
echo ""

# Check Docker
if ! command -v docker &> /dev/null; then
    echo "ERROR: Docker is not installed. Please install Docker first."
    exit 1
fi

if ! command -v docker compose &> /dev/null; then
    echo "ERROR: Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

echo "Docker found: $(docker --version)"
echo ""

# Generate secrets
echo "Generating secure secrets..."
SESSION_SECRET=$(openssl rand -base64 32 | tr -d '/+=' | head -c 32)
ENCRYPTION_KEY=$(openssl rand -base64 32 | tr -d '/+=' | head -c 32)

# Create .env file
if [ -f .env ]; then
    echo "Backing up existing .env to .env.backup"
    cp .env .env.backup
fi

cat > .env << EOF
# ISP CRM Environment Configuration
# Generated on $(date)

# Database Configuration
POSTGRES_USER=crm
POSTGRES_PASSWORD=Mgathoni.2016
POSTGRES_DB=isp_crm
DATABASE_URL=postgresql://crm:Mgathoni.2016@db:5432/isp_crm

# Security (Auto-generated - DO NOT CHANGE after first install)
SESSION_SECRET=${SESSION_SECRET}
ENCRYPTION_KEY=${ENCRYPTION_KEY}

# WhatsApp Service
WHATSAPP_SESSION_URL=http://whatsapp:3001

# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crm.superlite.co.ke
EOF

echo ".env file created with secure secrets"
echo ""

# Stop existing containers
echo "Stopping existing containers..."
docker compose down 2>/dev/null || true

# Build and start
echo ""
echo "Building and starting containers..."
docker compose up -d --build

# Wait for services
echo ""
echo "Waiting for services to start..."
sleep 15

# Check status
echo ""
echo "Container Status:"
docker compose ps

# Get WhatsApp secret
echo ""
echo "=========================================="
echo "  Installation Complete!"
echo "=========================================="
echo ""
echo "WhatsApp Session Secret:"
docker exec isp_crm_whatsapp cat /app/.api_secret_dir/secret 2>/dev/null || echo "(WhatsApp service still starting, run: docker exec isp_crm_whatsapp cat /app/.api_secret_dir/secret)"
echo ""
echo "IMPORTANT: After installation, you must:"
echo "1. Go to Settings > Biometric Devices and re-enter device passwords"
echo "2. Go to Settings > WhatsApp and enter the session secret shown above"
echo ""
echo "Access your CRM at: https://crm.superlite.co.ke"
echo ""
