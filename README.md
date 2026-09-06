# EWNET OSS/BSS

A comprehensive Operations Support System / Business Support System built with Laravel 13, React, and PostGIS.

## Architecture

- **Frontend:** React 19, TypeScript, Vite, Material UI, Zustand.
- **Backend:** Laravel 13, PHP 8.4, Sanctum (SPA Auth).
- **Database:** PostgreSQL 17 with PostGIS 3.5.
- **Cache/Queue:** Redis 7.4, Laravel Horizon.
- **Web Server:** Nginx 1.26 (Reverse Proxy).
- **Containerization:** Docker & Docker Compose.

## Quick Start

For a fresh Ubuntu server with Docker installed:

```bash
git clone https://github.com/ramkatuwal/ewnet-oss-bss.git /opt/misp
cd /opt/misp
chmod +x install.sh
./install.sh
```

See **[INSTALLATION.md](INSTALLATION.md)** for the full step-by-step guide, `.env` variable reference, TLS options, and troubleshooting.

## Default Credentials

After seeding: `admin@ewnet.com.np` / `Admin@2026!`

## Monitoring

- **Horizon Dashboard:** `/<horizon_path>` (requires admin access)
- **Logs:** `docker compose logs -f app`

## Testing

```bash
# Run the test suite (requires a running PostgreSQL instance on port 5432)
DB_HOST=127.0.0.1 ./vendor/bin/phpunit
```

## License

Proprietary - EWNET OSS/BSS