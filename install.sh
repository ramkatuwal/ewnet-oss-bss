#!/bin/bash
set -e

echo "=== EWNET OSS/BSS Fresh Installation Script ==="

# 1. Check Docker
if ! command -v docker &> /dev/null; then
    echo "Docker is not installed. Please install Docker first."
    exit 1
fi

# 2. Build Containers
echo "Building containers..."
docker compose build --no-cache

# 3. Start Services
echo "Starting services..."
docker compose up -d

# 4. Wait for Database
echo "Waiting for PostgreSQL to be ready..."
until docker compose exec postgres pg_isready -U ewnet; do
  sleep 2
done

# 5. Install Dependencies
echo "Installing PHP dependencies..."
docker compose exec app composer install --no-interaction

echo "Installing Node dependencies..."
docker compose exec app npm ci --legacy-peer-deps

# 6. Generate App Key
echo "Generating application key..."
docker compose exec app php artisan key:generate

# 7. Run Migrations with Fresh Flag (Drops all tables first)
echo "Running fresh migrations..."
docker compose exec app php artisan migrate:fresh --force

# 8. Seed Database
echo "Seeding database..."
docker compose exec app php artisan db:seed --force

# 9. Build Frontend
echo "Building frontend assets..."
docker compose exec app npm run build

# 10. Optimize
echo "Optimizing application..."
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

echo "=== Installation Complete ==="
echo "Default Admin: admin@ewnet.com.np / Admin@2026!"
