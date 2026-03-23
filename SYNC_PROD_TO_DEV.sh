#!/bin/bash
# Sync PROD2PROD to PUBLIC_HTML (Development)
# This script copies production code to development, keeping .env and configs

PROD_DIR="C:/wamp64/www/Projects/loka2/prod2prod"
DEV_DIR="C:/wamp64/www/Projects/loka2/public_html"

echo "======================================"
echo "Syncing PROD2PROD -> PUBLIC_HTML"
echo "======================================"
echo ""

# Files and directories to EXCLUDE
EXCLUDE=(
    ".env"
    ".env.production"
    ".env.example"
    "logs/*"
    "vendor/*"
    "cache/*"
    "proddata3182026/*"
    "weell.zip"
    "debug.php"
)

# Create backup
BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="C:/wamp64/www/Projects/loka2/backups/sync_${BACKUP_DATE}"
mkdir -p "$BACKUP_DIR"
echo "✓ Backup directory created: $BACKUP_DIR"

# List what would be synced
echo ""
echo "Files to sync from prod2prod:"
echo "  - classes/"
echo "  - config/"
echo "  - includes/"
echo "  - pages/"
echo "  - api/"
echo "  - assets/"
echo "  - cron/"
echo "  - index.php"
echo "  - migrate.php"
echo ""

# Sync core PHP files (keeping .env)
echo "Syncing core files..."

# Classes
cp -r "$PROD_DIR/classes"/* "$DEV_DIR/classes/"
echo "  ✓ classes/"

# Config (except .env)
for file in "$PROD_DIR/config"/*.php; do
    filename=$(basename "$file")
    if [ "$filename" != ".env" ]; then
        cp "$file" "$DEV_DIR/config/"
    fi
done
echo "  ✓ config/"

# Includes
cp -r "$PROD_DIR/includes"/* "$DEV_DIR/includes/"
echo "  ✓ includes/"

# Pages
cp -r "$PROD_DIR/pages"/* "$DEV_DIR/pages/"
echo "  ✓ pages/"

# API
cp -r "$PROD_DIR/api"/* "$DEV_DIR/api/"
echo "  ✓ api/"

# Assets
cp -r "$PROD_DIR/assets"/* "$DEV_DIR/assets/"
echo "  ✓ assets/"

# Cron
cp -r "$PROD_DIR/cron"/* "$DEV_DIR/cron/"
echo "  ✓ cron/"

# Root files
cp "$PROD_DIR/index.php" "$DEV_DIR/index.php"
cp "$PROD_DIR/migrate.php" "$DEV_DIR/migrate.php"
cp "$PROD_DIR/reset_admin_password.php" "$DEV_DIR/reset_admin_password.php"
echo "  ✓ index.php, migrate.php, reset_admin_password.php"

echo ""
echo "======================================"
echo "Sync Complete!"
echo "======================================"
echo ""
echo "NOTE: Development .env file was NOT overwritten."
echo "      Production .env settings are in: $PROD_DIR/.env.production"
echo ""
echo "Please test at: http://localhost/Projects/loka2/public_html/"
