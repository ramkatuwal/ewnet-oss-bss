#!/bin/bash
echo "===== SYSTEM AUDIT REPORT ====="
echo "Date: $(date)"
echo ""
echo "--- ENVIRONMENT ---"
php -v | head -1
php artisan --version
node -v
npm -v
echo ""
echo "--- DATABASE ---"
php artisan db:show | grep -E "Database|Tables|Size"
echo ""
echo "--- MIGRATIONS ---"
php artisan migrate:status
echo ""
echo "--- ROUTES COUNT ---"
php artisan route:list | wc -l
echo ""
echo "--- PERMISSIONS ---"
php artisan permission:show | grep "Permission" | wc -l
echo ""
echo "--- STORAGE ---"
df -h /opt/misp
du -sh /opt/misp
echo ""
echo "================================"
