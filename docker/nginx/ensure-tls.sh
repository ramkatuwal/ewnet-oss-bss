#!/bin/sh
# =====================================================================
# EWNET OSS/BSS — auto TLS bootstrap for self-signed deployments
#
# Mounted into the `web` container at /docker-entrypoint.d/50-ensure-tls.sh
# and executed by the official nginx image entrypoint BEFORE nginx starts
# (after 20-envsubst-on-templates.sh renders the site config).
#
# When SSL_MODE=self-signed and the certificate configured in
# NGINX_SSL_CERT_PATH does not exist yet, a durable self-signed
# certificate (825 days, with SAN for the app domain) is generated into
# the writable certificate volume (default: the `certs_data` named
# volume), so a fresh `docker compose up` always boots a working HTTPS
# site without any manual steps.
#
# For letsencrypt/manual modes this script does NOTHING (the certificates
# are provisioned by install.sh / the operator before `up`).
# =====================================================================

set -e

ME=$(basename "$0")

CERT="${NGINX_SSL_CERT_PATH:-/etc/nginx/certs/fullchain.pem}"
KEY="${NGINX_SSL_KEY_PATH:-/etc/nginx/certs/privkey.pem}"
DOMAIN="${APP_DOMAIN:-localhost}"
MODE="${SSL_MODE:-self-signed}"

# Only auto-generate for self-signed / unset SSL_MODE.
if [ "$MODE" != "self-signed" ] && [ "$MODE" != "off" ]; then
    exit 0
fi

# Already provisioned (self-signed or, e.g., a bind-mounted cert dir).
if [ -f "$CERT" ] && [ -f "$KEY" ]; then
    exit 0
fi

CERT_DIR=$(dirname "$CERT")
if [ ! -d "$CERT_DIR" ] || [ ! -w "$CERT_DIR" ]; then
    printf '%s: certificate %s not found and %s is not writable. Mount a writable cert volume or set SSL_MODE=manual/letsencrypt with valid certificates.\n' "$ME" "$CERT" "$CERT_DIR" >&2
    exit 0
fi

mkdir -p "$CERT_DIR" 2>/dev/null || true

openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
    -keyout "$KEY" -out "$CERT" \
    -subj "/CN=${DOMAIN}" \
    -addext "subjectAltName=DNS:${DOMAIN},DNS:localhost,IP:127.0.0.1" 2>/dev/null

chmod 644 "$CERT" 2>/dev/null || true
chmod 640 "$KEY" 2>/dev/null || true

printf '%s: generated self-signed certificate for %s (%s)\n' "$ME" "$DOMAIN" "$CERT"
exit 0