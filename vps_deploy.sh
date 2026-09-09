#!/bin/bash
# ==============================================================================
# Hold My / Taqamul Expert - VPS Automated Deployment Script
# Target OS: Ubuntu 24.04 LTS
# Target Port: 9000
# ==============================================================================

export DEBIAN_FRONTEND=noninteractive
APT_OPTS="-y -o Dpkg::Options::=\"--force-confdef\" -o Dpkg::Options::=\"--force-confold\""

echo "🚀 [Hold My Deploy] Starting automated deployment on Port 9000..."

# 1. System Update & Essential Packages
echo "📦 [1/6] Updating Ubuntu packages and installing dependencies..."
sudo apt update && sudo apt upgrade $APT_OPTS
sudo apt install $APT_OPTS software-properties-common curl git unzip zip ufw

# 2. Add PHP Repository & Install PHP 8.3 + Extensions
echo "🐘 [2/6] Setting up PHP 8.3 & required extensions..."
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install $APT_OPTS php8.3 php8.3-cli php8.3-fpm php8.3-sqlite3 php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl php8.3-readline

# 3. Install Composer & Node.js
echo "🛠️ [3/6] Installing Composer & Node.js..."
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt install -y nodejs
fi

# 4. Configure Application Directory & Environment
echo "📁 [4/6] Setting up Laravel application directory & permissions..."
APP_DIR="/var/www/hold_my"
sudo mkdir -p $APP_DIR
git config --global --add safe.directory $APP_DIR 2>/dev/null || true

if [ -d "$APP_DIR/.git" ]; then
    echo "🔄 Updating existing repository..."
    cd $APP_DIR
    sudo git pull origin main
else
    echo "📥 Cloning repository from GitHub..."
    sudo git clone https://github.com/tanjilhossen/hold_my.git $APP_DIR
    cd $APP_DIR
fi

# Environment File Setup
echo "⚙️ Configuring .env file for SQLite..."
if [ ! -f "$APP_DIR/.env" ]; then
    sudo cp $APP_DIR/.env.example $APP_DIR/.env 2>/dev/null || true
fi

# Ensure SQLite Database config in .env
sudo sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' $APP_DIR/.env
sudo sed -i 's|^DB_DATABASE=.*|DB_DATABASE=/var/www/hold_my/database/database.sqlite|' $APP_DIR/.env
sudo sed -i 's|^APP_URL=.*|APP_URL=http://200.234.41.119:9000|' $APP_DIR/.env

# Create SQLite database file
sudo touch $APP_DIR/database/database.sqlite
sudo chmod 777 $APP_DIR/database/database.sqlite

# Copy Taqamul Professions & Metadata to Storage
echo "📋 Syncing Taqamul Professions & Metadata..."
sudo mkdir -p $APP_DIR/storage/app
sudo cp $APP_DIR/database/data/*.json $APP_DIR/storage/app/ 2>/dev/null || true

# Install PHP Dependencies
echo "📦 Running composer install..."
cd $APP_DIR
sudo composer install --no-dev --optimize-autoloader

# Generate APP_KEY
echo "🔑 Generating Application Key..."
sudo php artisan key:generate --force

# Run Migrations & Seed
echo "🗄️ Running migrations & database seed..."
sudo php artisan migrate --force
sudo php artisan db:seed --force
sudo php artisan config:clear
sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache

# Set Storage & Cache Directory Permissions
sudo chown -R www-data:www-data $APP_DIR
sudo chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache

# 5. Setup Systemd Service on Port 9000
echo "⚙️ [5/6] Creating Systemd Service for Port 9000..."
sudo cat << 'EOF' | sudo tee /etc/systemd/system/hold_my.service
[Unit]
Description=Hold My Engine Service (Port 9000)
After=network.target

[Service]
User=root
Group=root
WorkingDirectory=/var/www/hold_my
ExecStart=/usr/bin/php artisan serve --host=0.0.0.0 --port=9000
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable hold_my.service
sudo systemctl restart hold_my.service

# 6. Configure Firewall (UFW) for Port 9000
echo "🛡️ [6/6] Opening Port 9000 in firewall..."
sudo ufw allow 9000/tcp
sudo ufw allow 22/tcp
echo "y" | sudo ufw enable 2>/dev/null || true

echo "=============================================================================="
echo "✅ [SUCCESS] Hold My Engine deployed successfully on Port 9000!"
echo "🌐 Access Website: http://200.234.41.119:9000"
echo "🔐 Default Admin Credentials:"
echo "   - Email: admin@taqamul.com"
echo "   - Password: admin123"
echo "=============================================================================="
