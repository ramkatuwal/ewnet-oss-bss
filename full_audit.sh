#!/bin/bash
echo "====================================="
echo "EWNET OSS/BSS - FULL SYSTEM AUDIT"
echo "Date: $(date)"
echo "====================================="

echo -e "\n📦 DOCKER CONTAINERS"
docker compose ps

echo -e "\n🖥️  APPLICATION INFO"
docker compose exec app php artisan about

echo -e "\n📊 DATABASE TABLES"
docker compose exec app php artisan db:table

echo -e "\n🔌 ROUTES LIST"
docker compose exec app php artisan route:list | head -30

echo -e "\n🔐 PERMISSIONS"
docker compose exec app php artisan permission:show

echo -e "\n📈 MIGRATION STATUS"
docker compose exec app php artisan migrate:status

echo -e "\n💾 STORAGE USAGE"
df -h /opt/misp
du -sh /opt/misp

echo -e "\n📝 ENVIRONMENT VARIABLES (Sanitized)"
docker compose exec app env | grep -E "APP_|DB_|REDIS_" | grep -v "KEY\|PASSWORD"

echo -e "\n🔧 PHP EXTENSIONS"
docker compose exec app php -m | grep -E "pgsql|redis|curl|json"

echo -e "\n📦 NPM PACKAGES"
docker compose exec app npm list --depth=0

echo -e "\n====================================="
echo "AUDIT COMPLETE"
echo "====================================="
