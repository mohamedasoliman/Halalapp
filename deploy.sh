#!/bin/bash
#
# HalalApp Deployment Script
# Deploys the Laravel application to HostGator server
#

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
SERVER_USER="halalapp"
SERVER_HOST="108.167.141.140"
APP_PATH="/home5/halalapp/halalapp"
COMPOSER_PATH="php ~/.composer/2022-10-27_14-39-29-2.4.4-old.phar"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}   HalalApp Deployment Script${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Step 1: Check local git status
echo -e "${YELLOW}[1/5] Checking local git status...${NC}"
if [[ -n $(git status --porcelain) ]]; then
    echo -e "${RED}Warning: You have uncommitted changes!${NC}"
    echo "Please commit or stash your changes before deploying."
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Step 2: Push to GitHub
echo -e "${YELLOW}[2/5] Pushing to GitHub...${NC}"
git push origin main

# Step 3: Deploy to server
echo -e "${YELLOW}[3/5] Deploying to server...${NC}"
ssh ${SERVER_USER}@${SERVER_HOST} << 'ENDSSH'
    set -e
    cd /home5/halalapp/halalapp

    echo "Backing up admin-managed files..."
    cp -f public/data/customData.json /tmp/customData_backup.json 2>/dev/null || true
    cp -f public/data/HalalRestaurantsList.json /tmp/HalalRestaurantsList_backup.json 2>/dev/null || true
    cp -f public/data/BusinessList.json /tmp/BusinessList_backup.json 2>/dev/null || true

    echo "Pulling latest changes..."
    git fetch origin
    git reset --hard origin/main

    echo "Restoring admin-managed files..."
    cp -f /tmp/customData_backup.json public/data/customData.json 2>/dev/null || true
    cp -f /tmp/HalalRestaurantsList_backup.json public/data/HalalRestaurantsList.json 2>/dev/null || true
    cp -f /tmp/BusinessList_backup.json public/data/BusinessList.json 2>/dev/null || true

    echo "Installing dependencies..."
    php ~/.composer/2022-10-27_14-39-29-2.4.4-old.phar install --no-dev --optimize-autoloader --no-interaction

    echo "Running migrations..."
    php artisan migrate --force 2>&1 || echo "Note: Migration had warnings (tables may already exist)"

    echo "Clearing caches..."
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan cache:clear 2>/dev/null || echo "Note: Cache clear skipped (no cache table)"

    echo "Rebuilding caches..."
    php artisan config:cache
    php artisan route:cache

    echo "Setting permissions..."
    chmod -R 775 storage bootstrap/cache

    echo "Syncing JSON data files to public_html..."
    mkdir -p /home5/halalapp/public_html/data/DirectoryJsons
    cp -f public/data/*.json /home5/halalapp/public_html/data/
    cp -f public/data/DirectoryJsons/*.json /home5/halalapp/public_html/data/DirectoryJsons/

    echo "Syncing assets to public_html..."
    cp -rf public/assets/images/* /home5/halalapp/public_html/assets/images/ 2>/dev/null || true
    cp -rf public/assets/css/* /home5/halalapp/public_html/assets/css/ 2>/dev/null || true
ENDSSH

# Step 4: Verify deployment
echo -e "${YELLOW}[4/5] Verifying deployment...${NC}"
ssh ${SERVER_USER}@${SERVER_HOST} "cd ${APP_PATH} && php artisan --version"

# Step 5: Done
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}   Deployment Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Your app is live at: https://halalapp.info"
