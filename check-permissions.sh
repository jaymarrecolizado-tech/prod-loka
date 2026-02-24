#!/bin/bash

###############################################################################
# LOKA Fleet Management - File Permissions Checker & Fixer
# Run this on your production server to check and fix permissions
###############################################################################

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration - Update this path to match your server
APP_PATH="${1:-/var/www/loka}"  # Default path, can be passed as argument
WEB_USER="${2:-www-data}"        # Default web user, can be passed as argument

echo -e "${BLUE}=== LOKA Fleet Management - Permissions Checker ===${NC}\n"
echo -e "Application Path: ${GREEN}$APP_PATH${NC}"
echo -e "Web User: ${GREEN}$WEB_USER${NC}"
echo ""

# Check if directory exists
if [ ! -d "$APP_PATH" ]; then
    echo -e "${RED}Error: Directory $APP_PATH does not exist!${NC}"
    echo ""
    echo "Usage: $0 [path] [web_user]"
    echo "Example: $0 /var/www/loka www-data"
    exit 1
fi

cd "$APP_PATH"

# Counters
ISSUES_FOUND=0
ISSUES_FIXED=0

print_header() {
    echo -e "\n${BLUE}--- $1 ---${NC}"
}

print_ok() {
    echo -e "${GREEN}[OK]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
    ((ISSUES_FOUND++))
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
    ((ISSUES_FOUND++))
}

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_fixed() {
    echo -e "${GREEN}[FIXED]${NC} $1"
    ((ISSUES_FIXED++))
}

###############################################################################
# 1. Check Ownership
###############################################################################

print_header "Checking File Ownership"

echo -e "\nCurrent ownership summary:"
find "$APP_PATH" -maxdepth 1 -printf "%U:%G %p\n" | head -20

WRONG_OWNER=$(find "$APP_PATH" ! -user "$WEB_USER" ! -name "check-permissions.sh" | wc -l)
if [ "$WRONG_OWNER" -gt 0 ]; then
    print_warning "Found $WRONG_OWNER files/dirs not owned by $WEB_USER"
    echo -e "\nFiles with wrong ownership:"
    find "$APP_PATH" ! -user "$WEB_USER" ! -name "check-permissions.sh" -ls | head -10

    read -p $'\nFix ownership now? (y/n): ' -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_info "Setting ownership to $WEB_USER:$WEB_USER..."
        sudo chown -R "$WEB_USER:$WEB_USER" "$APP_PATH"
        print_fixed "Ownership fixed"
    fi
else
    print_ok "All files owned by $WEB_USER"
fi

###############################################################################
# 2. Check Directory Permissions
###############################################################################

print_header "Checking Directory Permissions"

# Check for directories with wrong permissions
echo -e "\nChecking directory permissions..."

# Directories should be 755 or 775 (for writable)
WRONG_DIR_PERMS=$(find "$APP_PATH" -type d ! -perm 755 ! -perm 775 ! -perm 777 2>/dev/null | wc -l)
if [ "$WRONG_DIR_PERMS" -gt 0 ]; then
    print_warning "Found $WRONG_DIR_PERMS directories with non-standard permissions"
    echo -e "\nDirectories with unusual permissions:"
    find "$APP_PATH" -type d ! -perm 755 ! -perm 775 ! -perm 777 2>/dev/null -ls | head -10

    read -p $'\nFix directory permissions to 755? (y/n): ' -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_info "Setting directories to 755..."
        find "$APP_PATH" -type d -exec chmod 755 {} \;
        print_fixed "Directory permissions fixed"
    fi
else
    print_ok "All directories have standard permissions (755/775/777)"
fi

###############################################################################
# 3. Check File Permissions
###############################################################################

print_header "Checking File Permissions"

# Regular files should be 644
WRONG_FILE_PERMS=$(find "$APP_PATH" -type f ! -perm 644 ! -perm 600 ! -perm 400 2>/dev/null | wc -l)
if [ "$WRONG_FILE_PERMS" -gt 0 ]; then
    print_warning "Found $WRONG_FILE_PERMS files with non-standard permissions"
    echo -e "\nSome files with unusual permissions:"
    find "$APP_PATH" -type f ! -perm 644 ! -perm 600 ! -perm 400 2>/dev/null -ls | head -10

    read -p $'\nFix file permissions to 644? (y/n): ' -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_info "Setting files to 644..."
        find "$APP_PATH" -type f -exec chmod 644 {} \;
        print_fixed "File permissions fixed"
    fi
else
    print_ok "All files have standard permissions"
fi

###############################################################################
# 4. Check Sensitive Files
###############################################################################

print_header "Checking Sensitive File Permissions"

# .env should be 600 (owner read/write only)
if [ -f "$APP_PATH/.env" ]; then
    ENV_PERM=$(stat -c "%a" "$APP_PATH/.env" 2>/dev/null || stat -f "%Lp" "$APP_PATH/.env" 2>/dev/null)
    if [ "$ENV_PERM" != "600" ] && [ "$ENV_PERM" != "400" ]; then
        print_error ".env file has insecure permissions: $ENV_PERM (should be 600)"

        read -p $'\nFix .env permissions to 600? (y/n): ' -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            chmod 600 "$APP_PATH/.env"
            print_fixed ".env permissions set to 600"
        fi
    else
        print_ok ".env file is secure (permissions: $ENV_PERM)"
    fi
else
    print_warning ".env file not found"
fi

# Check for other sensitive files
SENSITIVE_FILES=(
    ".env.production"
    ".env.local"
    "*.key"
    "*.pem"
    "config/*.php"
)

for pattern in "${SENSITIVE_FILES[@]}"; do
    for file in $APP_PATH/$pattern; do
        if [ -f "$file" ]; then
            FILE_PERM=$(stat -c "%a" "$file" 2>/dev/null || stat -f "%Lp" "$file" 2>/dev/null)
            print_info "$file: $FILE_PERM"
        fi
    done
done

###############################################################################
# 5. Check Writable Directories
###############################################################################

print_header "Checking Writable Directories"

WRITABLE_DIRS=("logs" "cache" "cache/data" "logs/sessions")

for dir in "${WRITABLE_DIRS[@]}"; do
    if [ -d "$APP_PATH/$dir" ]; then
        if [ -w "$APP_PATH/$dir" ]; then
            print_ok "$dir is writable"
        else
            print_error "$dir is NOT writable"

            read -p $'\nFix '$dir' permissions to 777? (y/n): ' -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                chmod -R 777 "$APP_PATH/$dir"
                print_fixed "$dir is now writable"
            fi
        fi
    else
        print_warning "$dir does not exist"

        read -p $'\nCreate '$dir'? (y/n): ' -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            mkdir -p "$APP_PATH/$dir"
            chmod -R 777 "$APP_PATH/$dir"
            print_fixed "$dir created and set to writable"
        fi
    fi
done

###############################################################################
# 6. Check for Dangerous Permissions
###############################################################################

print_header "Security Check - Dangerous Permissions"

# Check for world-writable files (except allowed directories)
WORLD_WRITABLE=$(find "$APP_PATH" -type f -perm -o=w ! -path "*/logs/*" ! -path "*/cache/*" 2>/dev/null | wc -l)
if [ "$WORLD_WRITABLE" -gt 0 ]; then
    print_warning "Found $WORLD_WRITABLE world-writable files outside logs/cache"
    echo -e "\nWorld-writable files:"
    find "$APP_PATH" -type f -perm -o=w ! -path "*/logs/*" ! -path "*/cache/*" 2>/dev/null -ls | head -10

    read -p $'\nRemove world-writable permissions? (y/n): ' -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        find "$APP_PATH" -type f -perm -o=w ! -path "*/logs/*" ! -path "*/cache/*" 2>/dev/null -exec chmod o-w {} \;
        print_fixed "World-writable permissions removed"
    fi
else
    print_ok "No dangerous world-writable files found"
fi

# Check for executable PHP files (should not be executable)
EXECUTABLE_PHP=$(find "$APP_PATH" -type f -name "*.php" -perm /111 2>/dev/null | wc -l)
if [ "$EXECUTABLE_PHP" -gt 0 ]; then
    print_warning "Found $EXECUTABLE_PHP PHP files with execute bit set"

    read -p $'\nRemove execute bit from PHP files? (y/n): ' -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        find "$APP_PATH" -type f -name "*.php" -exec chmod a-x {} \;
        print_fixed "Execute bits removed from PHP files"
    fi
else
    print_ok "No PHP files with execute permissions"
fi

###############################################################################
# 7. Summary & Recommended Fix
###############################################################################

print_header "Summary"

echo ""
echo "Issues found: $ISSUES_FOUND"
echo "Issues fixed: $ISSUES_FIXED"
echo ""

if [ $ISSUES_FOUND -gt 0 ]; then
    if [ $ISSUES_FOUND -eq $ISSUES_FIXED ]; then
        echo -e "${GREEN}All issues have been fixed!${NC}"
    else
        echo -e "${YELLOW}Some issues remain unfixed.${NC}"
    fi
else
    echo -e "${GREEN}No permission issues found!${NC}"
fi

echo ""
echo -e "${BLUE}=== Recommended Permissions ===${NC}"
echo ""
echo "Standard directories:  755"
echo "Standard files:        644"
echo "Sensitive files:       600 (.env, .key, etc)"
echo "Writable directories:  777 (logs, cache)"
echo ""

# One-command fix option
echo -e "${BLUE}=== Quick Fix Commands ===${NC}"
echo ""
echo "To fix all permissions at once, run:"
echo ""
echo -e "${YELLOW}cd $APP_PATH${NC}"
echo -e "${YELLOW}sudo chown -R $WEB_USER:$WEB_USER .${NC}"
echo -e "${YELLOW}find . -type d -exec chmod 755 {} \;${NC}"
echo -e "${YELLOW}find . -type f -exec chmod 644 {} \;${NC}"
echo -e "${YELLOW}chmod 600 .env${NC}"
echo -e "${YELLOW}chmod -R 777 logs cache data${NC}"
echo ""

echo "Done."
