#!/usr/bin/env bash
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive
APP_DIR="/var/www/gamesense-ai"
SUBDOMAIN="gsense.humbertorandon.com"
DB_NAME="gamesense_ai"
DB_USER="gamesense_user"
DB_PASS="${DB_PASS:-change_this_strong_password}"

echo "=== [1/9] Normalizando quebras de linha (LF) ==="
find "${APP_DIR}" -type f \( -name "*.php" -o -name "*.sh" -o -name "*.conf" -o -name "*.cnf" -o -name "*.service" -o -name "*.env*" -o -name "artisan" \) -exec sed -i 's/\r$//' {} +
chmod +x "${APP_DIR}/artisan"

echo "=== [2/9] Configurando Swap de 2GB (Proteção OOM Killer para 954MB RAM) ==="
if ! swapon --show | grep -q '/swapfile'; then
    rm -f /swapfile
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    sysctl -w vm.swappiness=10
    grep -q 'vm.swappiness' /etc/sysctl.conf || echo 'vm.swappiness=10' >> /etc/sysctl.conf
fi
free -m

echo "=== [3/9] Instalando Nginx, MySQL 8.0, PHP 8.3-FPM, Composer e Certbot ==="
apt-get update -y
apt-get install -y \
    nginx \
    mysql-server \
    php8.3-fpm \
    php8.3-cli \
    php8.3-mysql \
    php8.3-sqlite3 \
    php8.3-xml \
    php8.3-mbstring \
    php8.3-curl \
    php8.3-zip \
    php8.3-bcmath \
    php8.3-intl \
    composer \
    unzip \
    git \
    certbot \
    python3-certbot-nginx

echo "=== [4/9] Liberando portas 80 e 443 no iptables da Oracle Cloud ==="
iptables -C INPUT -p tcp --dport 80 -j ACCEPT 2>/dev/null || iptables -I INPUT 1 -p tcp --dport 80 -j ACCEPT
iptables -C INPUT -p tcp --dport 443 -j ACCEPT 2>/dev/null || iptables -I INPUT 1 -p tcp --dport 443 -j ACCEPT
if [ -d /etc/iptables ]; then
    iptables-save > /etc/iptables/rules.v4 || true
fi

echo "=== [5/9] Aplicando Tuning Low-Memory no MySQL 8.0 (~180MB) e PHP 8.3-FPM ==="
cp "${APP_DIR}/deploy/oracle-e2-micro/mysql-low-memory.cnf" /etc/mysql/mysql.conf.d/z-gamesense-low-memory.cnf
cp "${APP_DIR}/deploy/oracle-e2-micro/php-fpm-e2micro.conf" /etc/php/8.3/fpm/pool.d/www.conf
systemctl enable --now mysql
systemctl restart mysql
systemctl enable --now php8.3-fpm
systemctl restart php8.3-fpm

echo "=== [6/9] Criando Base de Dados e Utilizador no MySQL ==="
mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

echo "=== [7/9] Configurando .env, Dependências Composer, Migrations e Seeders ==="
cd "${APP_DIR}"
cp .env.example .env
sed -i "s|^APP_URL=.*|APP_URL=http://${SUBDOMAIN}|g" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|g" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|g" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|g" .env

sudo -u ubuntu COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan migrate:fresh --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

echo "=== [8/9] Configurando Nginx e Systemd Queue Worker ==="
cp "${APP_DIR}/deploy/oracle-e2-micro/nginx-subdomain.conf" /etc/nginx/sites-available/gamesense.conf
ln -sf /etc/nginx/sites-available/gamesense.conf /etc/nginx/sites-enabled/gamesense.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now nginx
systemctl reload nginx

cp "${APP_DIR}/deploy/oracle-e2-micro/gamesense-worker.service" /etc/systemd/system/gamesense-worker.service
systemctl daemon-reload
systemctl enable --now gamesense-worker.service
systemctl restart gamesense-worker.service

echo "=== [9/9] Tentando emitir certificado SSL Let's Encrypt para ${SUBDOMAIN} ==="
if certbot --nginx -d "${SUBDOMAIN}" --non-interactive --agree-tos --register-unsafely-without-email --redirect; then
    sed -i "s|^APP_URL=.*|APP_URL=https://${SUBDOMAIN}|g" "${APP_DIR}/.env"
    cd "${APP_DIR}" && php artisan config:cache
    echo "SSL Let's Encrypt ativado com sucesso para https://${SUBDOMAIN}!"
else
    echo "AVISO: Certbot não conseguiu validar na porta 80 externa (verifique a Ingress Rule 80/443 na VCN da Oracle Cloud). O site já está ativo em HTTP!"
fi

echo "=== DEPLOY CONCLUÍDO COM SUCESSO ==="
free -m
systemctl status mysql php8.3-fpm nginx gamesense-worker --no-pager | grep -E "Loaded:|Active:|Memory:" || true

