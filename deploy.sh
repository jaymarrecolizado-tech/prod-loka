#!/bin/bash

###############################################################################
# LOKA Fleet Management System - Production Deployment Script
# Domain: https://lokafleet.dictr2.online
###############################################################################

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PRODUCTION_USER="hostinger_user"  # CHANGE THIS
PRODUCTION_HOST="lokafleet.dictr2.online"  # CHANGE THIS
PRODUCTION_PATH="/var/www/loka"  # CHANGE THIS - typically provided by host
LOCAL_PATH="public_html"

echo -e "${GREEN}=== LOKA Fleet Management - Deployment Script ===${NC}\n"

# Function to print colored output
print_step() {
    echo -e "${YELLOW}[STEP]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Confirm deployment
read -p "Deploy to production server: $PRODUCTION_HOST? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_error "Deployment cancelled"
    exit 1
fi

###############################################################################
# Pre-Deployment Checks
###############################################################################

print_step "Running pre-deployment checks..."

# Check if .env.production exists
if [ ! -f "$LOCAL_PATH/.env.production" ]; then
    print_error ".env.production not found!"
    exit 1
fi

# Check if composer dependencies are installed
if [ ! -d "$LOCAL_PATH/vendor" ]; then
    print_error "Vendor directory not found. Run 'composer install' first."
    exit 1
fi

print_success "Pre-deployment checks passed"

###############################################################################
# Create Deployment Package
###############################################################################

print_step "Creating deployment package..."

# Create temp directory
TEMP_DIR=$(mktemp -d)
DEPLOY_DIR="$TEMP_DIR/loka-deploy"

mkdir -p "$DEPLOY_DIR"

# Copy files excluding development files
rsync -av \
    --exclude='*.log' \
    --exclude='.git*' \
    --exclude='.env' \
    --exclude='.env.local' \
    --exclude='.env.development' \
    --exclude='cache/data/*.json' \
    --exclude='logs/*.log' \
    --exclude='logs/sessions/*' \
    --exclude='tests/' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    --exclude='NUL' \
    --exclude='*.md' \
    --exclude='diagnostic.php' \
    --exclude='import_users.php' \
    --exclude='db-test.php' \
    --exclude='setup_*.php' \
    --exclude='migrate.php' \
    --exclude='welcome.php' \
    --exclude='ref/' \
    --exclude='docs/' \
    --exclude='AUTH_FIX*.md' \
    --exclude='EMAIL_QUEUE*.md' \
    --exclude='GLM*.md' \
    --exclude='NOTIFICATION*.md' \
    --exclude='VERIFICATION*.md' \
    "$LOCAL_PATH/" "$DEPLOY_DIR/"

print_success "Deployment package created"

###############################################################################
# Verify Deployment Package
###############################################################################

print_step "Verifying deployment package..."

# Check critical files exist
CRITICAL_FILES=(
    "$DEPLOY_DIR/index.php"
    "$DEPLOY_DIR/config/bootstrap.php"
    "$DEPLOY_DIR/classes/Database.php"
    "$DEPLOY_dir/classes/Auth.php"
    "$DEPLOY_DIR/.htaccess.production"
    "$DEPLOY_DIR/run-migrations.php"
)

for file in "${CRITICAL_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        print_error "Critical file missing: $file"
        exit 1
    fi
done

print_success "Deployment package verified"

###############################################################################
# Create .env for production
###############################################################################

print_step "Creating production .env file..."

cat "$DEPLOY_DIR/.env.production" > "$DEPLOY_DIR/.env"

echo ""
echo -e "${YELLOW}WARNING: Please update the following in .env:${NC}"
echo "  - DB_PASSWORD (set a strong password)"
echo "  - SMTP_PASSWORD (if using app-specific password)"
echo ""
read -p "Press Enter to continue after updating credentials..."

print_success "Production .env created"

###############################################################################
# Deploy to Server
###############################################################################

print_step "Deploying to production server..."

# Upload files
print_step "Uploading files (this may take a while)..."

rsync -avz --delete \
    -e "ssh" \
    "$DEPLOY_DIR/" \
    "$PRODUCTION_USER@$PRODUCTION_HOST:$PRODUCTION_PATH/"

print_success "Files uploaded"

###############################################################################
# Post-Deployment Setup
###############################################################################

print_step "Running post-deployment setup..."

# Run commands on remote server
ssh "$PRODUCTION_USER@$PRODUCTION_HOST" << 'ENDSSH'
set -e

cd /var/www/loka  # Or your actual production path

echo "Setting file permissions..."
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod 600 .env

echo "Setting up writable directories..."
mkdir -p logs/sessions cache/data
sudo chmod -R 777 logs cache/data

echo "Copying production .htaccess..."
cp .htaccess.production .htaccess

echo "Running migrations..."
php run-migrations.php

echo "Clearing cache..."
rm -rf cache/data/*.json

echo "Post-deployment setup complete"
ENDSSH

print_success "Post-deployment setup complete"

###############################################################################
# Cleanup
###############################################################################

print_step "Cleaning up temporary files..."
rm -rf "$TEMP_DIR"
print_success "Cleanup complete"

###############################################################################
# Verification
###############################################################################

print_step "Running deployment verification..."

echo "Testing website accessibility..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://$PRODUCTION_HOST" || echo "000")

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "301" ] || [ "$HTTP_CODE" = "302" ]; then
    print_success "Website is accessible (HTTP $HTTP_CODE)"
else
    print_error "Website returned HTTP code: $HTTP_CODE"
fi

echo ""
echo -e "${GREEN}=== Deployment Complete ===${NC}"
echo ""
echo "Next steps:"
echo "1. Login to: https://$PRODUCTION_HOST"
echo "2. Verify all features are working"
echo "3. Set up cron jobs for email queue processing"
echo ""
echo "Cron job example (add to crontab -e):"
echo "  */2 * * * * php /var/www/loka/cron/process_queue.php"
echo ""
