# =====================================================================
# EWNET OSS/BSS — Nginx image
#
# Customised build of the official nginx image that adds the OpenSSL CLI
# (needed by 50-ensure-tls.sh to bootstrap a self-signed certificate)
# and bakes in the site template + TLS bootstrap hook.
#
# The rendered result is still driven entirely by .env at runtime:
#  - template      -> /etc/nginx/templates/default.conf.template
#                     (rendered by 20-envsubst-on-templates.sh at start)
#  - TLS bootstrap -> /docker-entrypoint.d/50-ensure-tls.sh
# =====================================================================

FROM nginx:1.26-alpine

RUN apk add --no-cache openssl

COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/nginx/ensure-tls.sh /docker-entrypoint.d/50-ensure-tls.sh

RUN chmod +x /docker-entrypoint.d/50-ensure-tls.sh

EXPOSE 80 443