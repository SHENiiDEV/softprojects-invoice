#!/usr/bin/env bash
# ==============================================================================
# 🚀 SoftProjects Invoice Helper - Production VPS Installer (Nginx + PHP-FPM)
# Domain: invoice.soft-projects.io
# Path:   /var/www/softprojects/invoice-helper
# ==============================================================================

set -e

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
RED='\033[0;31m'
NC='\033[0m'

DOMAIN="invoice.soft-projects.io"
TARGET_DIR="/var/www/softprojects/invoice-helper"

echo -e "${BLUE}============================================================${NC}"
echo -e "${GREEN}  ⚡ SoftProjects Invoice Helper - VPS Setup (No Docker)${NC}"
echo -e "${BLUE}============================================================${NC}"
echo -e "Domain:     ${CYAN}${DOMAIN}${NC}"
echo -e "Directory:  ${CYAN}${TARGET_DIR}${NC}"
echo -e "${BLUE}============================================================${NC}"

# 1. Check Root
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}❌ Please run as root: sudo bash deploy-vps.sh${NC}"
  exit 1
fi

# 2. Update and install packages
echo -e "\n${YELLOW}[1/5] Installing Nginx, PHP-FPM, wkhtmltopdf & dependencies...${NC}"
apt-get update -y
DEBIAN_FRONTEND=noninteractive apt-get install -y \
    nginx \
    curl \
    unzip \
    git \
    wkhtmltopdf \
    fonts-dejavu-core \
    fonts-noto-core \
    certbot \
    python3-certbot-nginx

# Install PHP and PHP-FPM packages
DEBIAN_FRONTEND=noninteractive apt-get install -y \
    php-fpm \
    php-cli \
    php-curl \
    php-mbstring \
    php-xml \
    php-json \
    php-zip || true

# 3. Detect PHP-FPM socket
PHP_SOCK=$(find /run/php/ -name "php*-fpm.sock" 2>/dev/null | head -n 1)
if [ -z "$PHP_SOCK" ]; then
    # Try starting default php-fpm service
    systemctl restart php*-fpm || true
    sleep 1
    PHP_SOCK=$(find /run/php/ -name "php*-fpm.sock" 2>/dev/null | head -n 1)
fi

if [ -z "$PHP_SOCK" ]; then
    PHP_SOCK="/run/php/php-fpm.sock"
fi
echo -e "${GREEN}✓ Found PHP-FPM socket: ${PHP_SOCK}${NC}"

# 4. Deploy git repository to target directory
echo -e "\n${YELLOW}[2/5] Deploying repository to ${TARGET_DIR}...${NC}"
mkdir -p "/var/www/softprojects"

if [ -d "${TARGET_DIR}/.git" ]; then
    echo -e "Updating existing repository..."
    cd "${TARGET_DIR}" && git pull origin main || true
else
    rm -rf "${TARGET_DIR}"
    git clone https://github.com/SHENiiDEV/softprojects-invoice.git "${TARGET_DIR}"
fi

mkdir -p "${TARGET_DIR}/platform/storage/catalogs"
mkdir -p "${TARGET_DIR}/platform/storage/temp"

# Set proper permissions
chown -R www-data:www-data "/var/www/softprojects"
chmod -R 755 "${TARGET_DIR}"
chmod -R 777 "${TARGET_DIR}/platform/storage"

# Safe directory for git
git config --global --add safe.directory "${TARGET_DIR}" || true

# 5. Create Nginx virtual host configuration
echo -e "\n${YELLOW}[3/5] Configuring Nginx virtual host...${NC}"
NGINX_CONF="/etc/nginx/sites-available/${DOMAIN}"

cat << EOF > "${NGINX_CONF}"
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    root ${TARGET_DIR}/platform;
    index index.php index.html;

    access_log /var/log/nginx/${DOMAIN}.access.log;
    error_log /var/log/nginx/${DOMAIN}.error.log;

    client_max_body_size 64M;

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 180s;
        fastcgi_send_timeout 180s;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
EOF

# Enable Nginx site
ln -sf "${NGINX_CONF}" "/etc/nginx/sites-enabled/${DOMAIN}"

# Remove default nginx site if active
if [ -f "/etc/nginx/sites-enabled/default" ]; then
    rm -f "/etc/nginx/sites-enabled/default"
fi

# 6. Test and reload Nginx
echo -e "\n${YELLOW}[4/5] Testing and reloading Nginx...${NC}"
nginx -t
systemctl reload nginx

# 7. Issue SSL Certificate via Certbot (if DNS is pointing)
echo -e "\n${YELLOW}[5/5] Checking SSL Certificate setup for ${DOMAIN}...${NC}"
echo -e "${CYAN}Do you want to obtain a free Let's Encrypt SSL certificate right now? (y/n)${NC}"
read -t 10 -p "Choice (auto-skips in 10s): " GET_SSL || GET_SSL="y"

if [ "$GET_SSL" = "y" ] || [ "$GET_SSL" = "Y" ]; then
    certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos --register-unsafely-without-email --redirect || {
        echo -e "${YELLOW}⚠️ Certbot automatic SSL was skipped or DNS not yet propagated. You can run later:${NC}"
        echo -e "${CYAN}sudo certbot --nginx -d ${DOMAIN}${NC}"
    }
fi

echo -e "\n${BLUE}============================================================${NC}"
echo -e "${GREEN}  ✓ DEPLOYMENT COMPLETED SUCCESSFULLY! 🎉${NC}"
echo -e "${BLUE}============================================================${NC}"
echo -e "Platform URL:   ${GREEN}https://${DOMAIN}${NC} (or http://${DOMAIN})"
echo -e "Root Directory: ${CYAN}${TARGET_DIR}${NC}"
echo -e "Login:          ${YELLOW}SoftProjects${NC}"
echo -e "Password:       ${YELLOW}7Gq\`W~<Bd82A${NC}"
echo -e "${BLUE}============================================================${NC}\n"
