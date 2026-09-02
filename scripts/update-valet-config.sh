#!/usr/bin/env bash
# ==============================================================================
# Script: update-valet-config.sh
# Purpose: Automatically generates Laravel Valet Nginx configuration directly
#          from nginx.conf.example (single source of truth).
# ==============================================================================

set -euo pipefail

# Project directories & settings
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEMPLATE_FILE="${PROJECT_DIR}/nginx.conf.example"
SITE_NAME="${1:-slim}"
DOMAIN="${SITE_NAME}.test"
PUBLIC_DIR="${PROJECT_DIR}/public"

if [ ! -f "${TEMPLATE_FILE}" ]; then
    echo "❌ Error: Template file ${TEMPLATE_FILE} not found."
    exit 1
fi

# Detect Valet configuration directory
VALET_HOME="${HOME}/.config/valet"
if [ ! -d "${VALET_HOME}" ]; then
    VALET_HOME="${HOME}/.valet"
fi

if [ ! -d "${VALET_HOME}" ]; then
    echo "❌ Error: Laravel Valet directory not found (~/.config/valet or ~/.valet)."
    exit 1
fi

NGINX_CONF_DIR="${VALET_HOME}/Nginx"
CERT_DIR="${VALET_HOME}/Certificates"
LOG_DIR="${VALET_HOME}/Log"
VALET_SOCK="${VALET_HOME}/valet.sock"

mkdir -p "${NGINX_CONF_DIR}" "${CERT_DIR}" "${LOG_DIR}"

echo "🔧 Processing ${TEMPLATE_FILE} for Laravel Valet [${DOMAIN}]..."

# 1. Ensure site TLS certificate exists via Valet
if [ ! -f "${CERT_DIR}/${DOMAIN}.crt" ] || [ ! -f "${CERT_DIR}/${DOMAIN}.key" ]; then
    echo "🔒 Generating local TLS certificate via 'valet secure ${SITE_NAME}'..."
    (cd "${PROJECT_DIR}" && valet secure "${SITE_NAME}")
fi

# 2. Transform nginx.conf.example directly into Valet Nginx configuration
CONF_FILE="${NGINX_CONF_DIR}/${DOMAIN}"

sed \
  -e "s|ssl_certificate /etc/letsencrypt/live/[^;]*;|ssl_certificate \"${CERT_DIR}/${DOMAIN}.crt\";|g" \
  -e "s|ssl_certificate_key /etc/letsencrypt/live/[^;]*;|ssl_certificate_key \"${CERT_DIR}/${DOMAIN}.key\";|g" \
  -e "s|ssl_trusted_certificate .*|# ssl_trusted_certificate disabled for local Valet|g" \
  -e "s|ssl_stapling .*|ssl_stapling off;|g" \
  -e "s|ssl_stapling_verify .*|# ssl_stapling_verify off;|g" \
  -e "s|resolver .*|# resolver disabled for local Valet|g" \
  -e "s|api.example.com|${DOMAIN} www.${DOMAIN} *.${DOMAIN}|g" \
  -e "s|/var/www/slim/public|${PUBLIC_DIR}|g" \
  -e "s|unix:/var/run/php/php[0-9.]*-fpm.sock|unix:${VALET_SOCK}|g" \
  -e "s|listen 80;|listen 127.0.0.1:80;|g" \
  -e "s|listen \[::\]:80;|# listen [::]:80;|g" \
  -e "s|listen 443 ssl http2;|listen 127.0.0.1:443 ssl;\n    http2 on;|g" \
  -e "s|listen \[::\]:443 ssl http2;|# listen [::]:443 ssl;|g" \
  "${TEMPLATE_FILE}" > "${CONF_FILE}"

echo "✅ Generated Valet configuration at: ${CONF_FILE}"

# 3. Restart Valet Nginx
echo "🔄 Restarting Valet Nginx..."
valet restart nginx

echo "🎉 Done! https://${DOMAIN} is active using nginx.conf.example settings."
