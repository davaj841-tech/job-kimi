#!/bin/bash
# JobAzmoon - Phase 11 Step 3.1: Install Nginx, Certbot, PHP 8.3 packages
# Run on Ubuntu/Debian production server as root (or with sudo).
set -e

echo "=== JobAzmoon: Installing web server packages ==="

sudo apt update
sudo apt install -y nginx certbot python3-certbot-nginx
sudo apt install -y \
  php8.3-fpm \
  php8.3-mysql \
  php8.3-redis \
  php8.3-mbstring \
  php8.3-xml \
  php8.3-curl \
  php8.3-zip \
  php8.3-gd

echo "=== Packages installed ==="
nginx -v
php8.3 -v
systemctl is-active nginx || true
systemctl is-active php8.3-fpm || true
