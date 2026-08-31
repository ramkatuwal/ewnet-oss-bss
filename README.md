# EWNET OSS/BSS
A comprehensive Operations Support System / Business Support System built with Laravel 13, React, and PostGIS.

## 🏗️ Architecture

*   **Frontend:** React 19, TypeScript, Vite, Material UI, Zustand.
*   **Backend:** Laravel 13, PHP 8.4, Sanctum (SPA Auth).
*   **Database:** PostgreSQL 17 with PostGIS 3.5.
*   **Cache/Queue:** Redis 7.4, Laravel Horizon.
*   **Web Server:** Nginx 1.26 (Reverse Proxy).
*   **Containerization:** Docker & Docker Compose.

## 🚀 Quick Start (Automated)

For a fresh Ubuntu 24.04 LTS server:

```bash
curl -fsSL https://raw.githubusercontent.com/ramkatuwal/ewnet-oss-bss/develop/install.sh | sudo bash

🛠️ Manual Installation
1. Prerequisites
Ubuntu 24.04 LTS
Docker Engine & Docker Compose Plugin
Git
2. Clone & Configure

git clone https://github.com/ramkatuwal/ewnet-oss-bss.git /opt/misp
cd /opt/misp
cp .env.example .env

3. Environment Setup
Update .env with your production values:
APP_URL: Your public domain.
DB_PASSWORD: Secure password for PostgreSQL.
REDIS_PASSWORD: Secure password for Redis.
SANCTUM_STATEFUL_DOMAINS: Your domain.
4. Build & Run

docker compose build
docker compose up -d

5. Initialize Application

docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan storage:link
docker compose build # Includes frontend asset compilation

🔒 Security & TLS
The application uses Let's Encrypt for TLS. Ensure your Nginx configuration points to the correct certificate paths in /etc/letsencrypt.
📊 Monitoring
Horizon Dashboard: /horizon (Requires Admin access)
System Info: /audit/system-info
Logs: docker compose logs -f app
🧪 Testing

docker compose exec app php artisan test

📝 License
Proprietary - EWNET OSS/BSS
