#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  EWNET OSS/BSS Automated Installer${NC}"
echo -e "${GREEN}========================================${NC}"

# --- 1. Verify OS ---
if [ ! -f /etc/os-release ]; then
    echo -e "${RED}Error: Cannot determine OS. This script requires Ubuntu 24.04 LTS.${NC}"
    exit 1
fi
source /etc/os-release
if [[ "$VERSION_ID" != "24.04" ]]; then
    echo -e "${RED}Error: This script is designed for Ubuntu 24.04 LTS. Detected: $PRETTY_NAME${NC}"
    exit 1
fi
echo -e "${GREEN}[OK] OS Verified: $PRETTY_NAME${NC}"

# --- 2. Check Resources ---
TOTAL_RAM=$(free -m | awk '/^Mem:/{print $2}')
if [ "$TOTAL_RAM" -lt 2048 ]; then
    echo -e "${RED}Error: Minimum 2GB RAM required. Detected: ${TOTAL_RAM}MB${NC}"
    exit 1
fi
DISK_AVAIL=$(df -m / | awk 'NR==2{print $4}')
if [ "$DISK_AVAIL" -lt 5120 ]; then
    echo -e "${RED}Error: Minimum 5GB free disk space required. Detected: ${DISK_AVAIL}MB${NC}"
    exit 1
fi
echo -e "${GREEN}[OK] Resources Verified: ${TOTAL_RAM}MB RAM, ${DISK_AVAIL}MB Disk${NC}"

# --- 3. Install Prerequisites ---
echo -e "${YELLOW}Installing system prerequisites...${NC}"
apt-get update -qq
apt-get install -y -qq git curl ca-certificates gnupg lsb-release > /dev/null 2>&1
echo -e "${GREEN}[OK] Prerequisites installed.${NC}"

# --- 4. Install Docker & Compose ---
if ! command -v docker &> /dev/null; then
    echo -e "${YELLOW}Installing Docker Engine...${NC}"
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-compose-plugin > /dev/null 2>&1
    echo -e "${GREEN}[OK] Docker Engine installed.${NC}"
else
    echo -e "${GREEN}[OK] Docker already installed.${NC}"
fi

# --- 5. Prepare Directory & Clone ---
INSTALL_DIR="/opt/misp"
if [ -d "$INSTALL_DIR/.git" ]; then
    echo -e "${YELLOW}Existing installation found at $INSTALL_DIR. Pulling latest changes...${NC}"
    cd $INSTALL_DIR
    git pull origin develop
else
    echo -e "${YELLOW}Cloning repository to $INSTALL_DIR...${NC}"
    mkdir -p $INSTALL_DIR
    git clone https://github.com/ramkatuwal/ewnet-oss-bss.git $INSTALL_DIR
    cd $INSTALL_DIR
    git checkout develop
fi

# --- 6. Environment Configuration ---
if [ ! -f .env ]; then
    echo -e "${YELLOW}Creating .env file from example...${NC}"
    cp .env.example .env
    
    # Generate App Key
    APP_KEY=$(docker compose run --rm app php artisan key:generate --show 2>/dev/null || echo "base64:$(openssl rand -base64 32)")
    sed -i "s|APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
    
    # Prompt for URL
    read -p "Enter your domain (e.g., oss.ewnet.com.np): " DOMAIN
    sed -i "s|APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
    sed -i "s|SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=${DOMAIN},localhost,127.0.0.1|" .env
    sed -i "s|SESSION_DOMAIN=.*|SESSION_DOMAIN=.${DOMAIN}|" .env
else
    echo -e "${GREEN}[OK] .env file already exists. Skipping generation.${NC}"
fi

# --- 7. Build and Start ---
echo -e "${YELLOW}Building Docker images (this may take a few minutes)...${NC}"
docker compose build --no-cache

echo -e "${YELLOW}Starting services...${NC}"
docker compose up -d

# --- 8. Wait for Health ---
echo -e "${YELLOW}Waiting for PostgreSQL and Redis to be healthy...${NC}"
timeout 120 bash -c 'until docker compose exec postgres pg_isready -U ewnet -d ewnet; do sleep 2; done'
timeout 120 bash -c 'until docker compose exec redis redis-cli ping; do sleep 2; done'

# --- 9. Initialize Laravel ---
echo -e "${YELLOW}Initializing application...${NC}"
docker compose exec app php artisan migrate:fresh --force
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan storage:link
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

# --- 10. Frontend Build ---
# Frontend assets are compiled during the Docker image build stage.

# --- 11. Final Verification ---
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Installation Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Services Status:"
docker compose ps
echo ""
echo "Next Steps:"
echo "1. Configure Nginx/TLS for your domain."
echo "2. Visit: https://$(grep APP_URL .env | cut -d '=' -f2)"
echo "3. Login with default credentials (check database seeders)."
