# LOKA Fleet Management System - Production Deployment Guide

## Overview
This guide covers deploying the LOKA Fleet Management System to a production server (Hostinger VPS).

## Server Requirements
- PHP 8.3 or higher
- MySQL/MariaDB 5.7+
- Apache with mod_rewrite enabled
- Composer (for dependency management)
- SSH access

## Pre-Deployment Checklist

### 1. Server Configuration
- [ ] Create database `fleet_management`
- [ ] Create database user with privileges
- [ ] Note down database credentials
- [ ] Ensure PHP extensions: pdo, pdo_mysql, mysqli, mbstring, json, gd, zip

### 2. Domain Configuration
- [ ] Point domain to server IP
- [ ] SSL certificate installed (Let's Encrypt recommended)

### 3. Local Preparation
- [ ] Update `.env.production` with production credentials
- [ ] Install composer dependencies: `composer install`
- [ ] Run migrations locally to verify

---

## Deployment Options

### Option A: Automated Deployment (Recommended)

#### 1. Configure Deployment Script
Edit `deploy.sh` with your server details:

```bash
PRODUCTION_USER="your_ssh_user"
PRODUCTION_HOST="lokafleet.dictr2.online"
PRODUCTION_PATH="/var/www/loka"  # Check with your host
```

#### 2. Update Production Credentials
Edit `public_html/.env.production`:
- Set strong `DB_PASSWORD`
- Verify `SMTP_HOST` and `SMTP_USER`
- Set `APP_DEBUG=false`
- Set `APP_ENV=production`

#### 3. Run Deployment Script
```bash
chmod +x deploy.sh
./deploy.sh
```

### Option B: Manual Deployment via SFTP/FTP

#### 1. Create Production Package
```bash
cd public_html
tar -czf ../loka-deploy.tar.gz \
    --exclude='.git' \
    --exclude='.env' \
    --exclude='.env.local' \
    --exclude='cache/data/*.json' \
    --exclude='logs/*.log' \
    --exclude='logs/sessions/*' \
    --exclude='*.md' \
    --exclude='tests/' \
    --exclude='ref/' \
    --exclude='docs/' \
    .
```

#### 2. Upload Files
- Upload `loka-deploy.tar.gz` to server
- Extract in web root: `tar -xzf loka-deploy.tar.gz`

#### 3. Configure Environment
```bash
cp .env.production .env
nano .env  # Update with real credentials
```

#### 4. Copy Production .htaccess
```bash
cp .htaccess.production .htaccess
```

#### 5. Set Permissions
```bash
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod 600 .env
sudo chmod -R 777 logs cache data
```

---

## Post-Deployment Steps

### 1. Run Database Migrations
```bash
cd /var/www/loka  # Your production path
php run-migrations.php
```

### 2. Clear Cache
```bash
rm -rf cache/data/*.json
```

### 3. Create Admin User (if needed)
```bash
# Run seeder or manually create admin user
# See FINAL.md for default credentials
```

### 4. Set Up Cron Jobs
Edit crontab:
```bash
crontab -e
```

Add email queue processor:
```
*/2 * * * * php /var/www/loka/cron/process_queue.php >> /var/www/loka/logs/cron.log 2>&1
```

---

## Verification

### 1. Check Website
```bash
curl -I https://lokafleet.dictr2.online
```

Expected: HTTP 200, 301, or 302

### 2. Check Security Headers
```bash
curl -I https://lokafleet.dictr2.online | grep -i header
```

### 3. Test Login
- Visit: https://lokafleet.dictr2.online
- Login with admin credentials
- Verify dashboard loads

### 4. Test Database
- Create a test request
- Verify data persists

### 5. Test Email
- Trigger a notification
- Check email queue: `php cron/process_queue.php`

---

## Troubleshooting

### Website Returns 500 Error
```bash
# Check PHP error log
tail -f /var/log/php_errors.log

# Check .htaccess syntax
apachectl configtest

# Verify file permissions
ls -la /var/www/loka
```

### Database Connection Failed
```bash
# Verify credentials in .env
cat .env | grep DB_

# Test database connection
mysql -h localhost -u loka_db_user -p fleet_management

# Check database exists
mysql -u root -p -e "SHOW DATABASES LIKE 'fleet_management';"
```

### Emails Not Sending
```bash
# Test email configuration
php cron/test_email_config.php

# Check email queue
mysql -u root -p fleet_management -e "SELECT * FROM email_queue LIMIT 5;"

# Process queue manually
php cron/process_queue.php
```

### Permission Issues
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/loka

# Fix permissions
sudo find /var/www/loka -type d -exec chmod 755 {} \;
sudo find /var/www/loka -type f -exec chmod 644 {} \;
sudo chmod 600 /var/www/loka/.env
sudo chmod -R 777 /var/www/loka/logs /var/www/loka/cache/data
```

---

## Security Hardening

### 1. Protect .env File
```bash
sudo chmod 600 .env
sudo chown www-data:www-data .env
```

### 2. Disable Directory Browsing
Already configured in `.htaccess`

### 3. Enable HTTPS
Already configured in `.htaccess.production`

### 4. Set Strong Passwords
- Database user password
- Admin account password (default: password123 - CHANGE IMMEDIATELY!)

### 5. Review Security Headers
```bash
curl -I https://lokafleet.dictr2.online
```

Look for:
- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY
- X-XSS-Protection: 1; mode=block

---

## Rollback Procedure

If deployment fails:

### 1. Enable Debug Mode
```bash
nano .env
# Set APP_DEBUG=true
```

### 2. Check Error Logs
```bash
tail -f /var/log/nginx/error.log
tail -f /var/log/php_errors.log
```

### 3. Restore Previous Version
```bash
# If you have a backup
cp -r /var/www/loka.backup/* /var/www/loka/
```

### 4. Revert Database (if needed)
```bash
mysql -u root -p fleet_management < backup.sql
```

---

## Default Login Credentials

After deployment, use these credentials (from FINAL.md):

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@fleet.local | password123 |

**IMPORTANT: Change the admin password immediately after first login!**

---

## Maintenance

### Regular Tasks
1. **Backup Database** (daily recommended):
   ```bash
   mysqldump -u root -p fleet_management > backup_$(date +%Y%m%d).sql
   ```

2. **Clean Old Logs** (weekly):
   ```bash
   find logs/ -name "*.log" -mtime +7 -delete
   ```

3. **Update Dependencies** (monthly):
   ```bash
   composer update
   ```

4. **Review Security Logs** (weekly):
   ```bash
   mysql -u root -p fleet_management -e "SELECT * FROM security_log ORDER BY created_at DESC LIMIT 20;"
   ```

---

## Support

For issues or questions:
- Check FINAL.md for complete documentation
- Review audit logs in the admin panel
- Check system logs

---

**Document Version:** 1.0
**Last Updated:** February 23, 2026
