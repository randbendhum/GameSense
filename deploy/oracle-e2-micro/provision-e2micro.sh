#!/usr/bin/env bash
# ==========================================================================
# SCRIPT DE PROVISIONAMENTO — ORACLE CLOUD ALWAYS FREE (AMD E2.1.Micro 1GB)
# OS Recomendado: Ubuntu 24.04 LTS Minimal
# Uso: sudo SUBDOMAIN="gamesense.seudominio.com" bash provision-e2micro.sh
# ==========================================================================

set -euo pipefail

SUBDOMAIN="${SUBDOMAIN:-gamesense.seudominio.com}"
APP_DIR="/var/www/gamesense-ai"

echo "[1/7] Criando Swap de 2GB (Válvula de segurança obrigatória para 1GB RAM)..."
if [ ! -f /swapfile ]; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
    sysctl vm.swappiness=10
    echo 'vm.swappiness=10' >> /etc/sysctl.conf
fi

echo "[2/7] Instalando Nginx, MySQL 8.0, PHP 8.3-FPM e Certbot (Let's Encrypt)..."
apt-get update -y
apt-get install -y nginx mysql-server php8.3-fpm php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip unzip git certbot python3-certbot-nginx iptables-persistent

echo "[3/7] Liberando portas 80 e 443 no iptables nativo da imagem Ubuntu da Oracle Cloud..."
iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
netfilter-persistent save

echo "[4/7] Aplicando tuning de baixa memória no MySQL 8.0 (~180MB RAM) e PHP 8.3-FPM..."
cp "${APP_DIR}/deploy/oracle-e2-micro/mysql-low-memory.cnf" /etc/mysql/mysql.conf.d/z-gamesense-low-memory.cnf
cp "${APP_DIR}/deploy/oracle-e2-micro/php-fpm-e2micro.conf" /etc/php/8.3/fpm/pool.d/www.conf
systemctl restart mysql
systemctl restart php8.3-fpm

echo "[5/7] Configurando subdomínio ${SUBDOMAIN} no Nginx..."
sed "s/gamesense.seudominio.com/${SUBDOMAIN}/g" "${APP_DIR}/deploy/oracle-e2-micro/nginx-subdomain.conf" > /etc/nginx/sites-available/gamesense.conf
ln -sf /etc/nginx/sites-available/gamesense.conf /etc/nginx/sites-enabled/gamesense.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

echo "[6/7] Ativando o Worker da Fila do Laravel via systemd..."
cp "${APP_DIR}/deploy/oracle-e2-micro/gamesense-worker.service" /etc/systemd/system/gamesense-worker.service
systemctl daemon-reload
systemctl enable --now gamesense-worker.service

echo "[7/7] Pronto! Para emitir o certificado HTTPS gratuito no seu subdomínio, execute:"
echo "      sudo certbot --nginx -d ${SUBDOMAIN}"

