# LOKA Fleet Management - Production Migration Summary

**Date Prepared:** March 16, 2026
**Target Server:** Hostinger KVM 2 VPS
**IP Address:** 187.77.150.203
**Domain:** https://lokafleet.dictr2.cloud

---

## Production Package Location

```
C:\wamp64\www\Projects\loka2\prod2prod\
```

**Package Size:** 36 MB
**Total Files:** 1,510 files

---

## What Has Been Prepared

### 1. Production-Ready Application Files

✅ **Core Application**
- index.php - Main router
- health.php - Health check endpoint
- migrate.php - Database migration runner
- .htaccess - Production Apache configuration (security optimized)

✅ **Built Frontend Assets**
- assets/dist/ - Minified and optimized JavaScript/CSS
- Excluded: Source files, node_modules, development dependencies

✅ **Backend Components**
- api/ - REST API endpoints
- classes/ - Core PHP classes (Auth, Database, EmailQueue, Security, etc.)
- config/ - Configuration files
- cron/ - Cron jobs including email queue processor
- includes/ - PHP includes and helper functions
- libraries/ - TCPDF and other third-party libraries
- migrations/ - Database migration files
- pages/ - All page modules
- vendor/ - Composer dependencies

✅ **Logs Directory Structure**
- logs/audit/ - Audit trail logs
- logs/email_queue/ - Email processing logs
- logs/sessions/ - Session storage
- logs/*.log - Application log files

### 2. Configuration Files

✅ **Environment Templates**
- .env.example - Template with all available options
- .env.production - Production configuration with domain configured

✅ **Production Configuration**
- Domain: lokafleet.dictr2.cloud
- APP_ENV: production
- APP_DEBUG: false
- Database: fleet_management (placeholder)
- SMTP: Gmail template (update with actual credentials)

### 3. Deployment Documentation

✅ **README.md** (11,220 bytes)
- Complete package overview
- All deployment methods explained
- Server requirements
- Configuration instructions
- Troubleshooting guide
- Maintenance procedures

✅ **QUICK_START.md** (6,035 bytes)
- Fast-track deployment checklist
- Step-by-step instructions
- Pre-upload, upload, post-upload steps
- Quick verification guide

✅ **DEPLOYMENT_GUIDE.md** (12,282 bytes)
- Comprehensive deployment documentation
- Database setup with SQL commands
- SSL configuration (Let's Encrypt & Hostinger)
- Cron job setup
- Security checklist
- Performance optimization
- Backup strategies
- Troubleshooting section

### 4. Automation Scripts

✅ **create_archive.bat** (2,449 bytes)
- Windows batch script
- Creates tar.gz archive using 7-Zip or tar
- Checks for available tools
- Provides feedback and error handling

✅ **setup.sh** (7,081 bytes)
- Bash script for server-side setup
- Automates:
  - File permission setting
  - Environment file creation
  - Database and user creation
  - Schema import (optional)
  - Admin user creation
  - Cron job configuration
  - SSL setup (optional)
- Interactive with user prompts
- Color-coded output

---

## Deployment Steps Overview

### Step 1: Create Archive (On Local Machine)

**Windows:**
```cmd
cd C:\wamp64\www\Projects\loka2\prod2prod
create_archive.bat
```

**Linux/Mac/Git Bash:**
```bash
cd /c/wamp64/www/Projects/loka2/prod2prod
tar -czf loka-fleet.tar.gz .
```

### Step 2: Upload to Server

**Option A: Hostinger File Manager**
1. Login to hPanel.hostinger.com
2. Go to Files → File Manager
3. Navigate to public_html/
4. Upload loka-fleet.tar.gz
5. Extract the archive

**Option B: SFTP/SCP**
```bash
scp loka-fleet.tar.gz user@187.77.150.203:/tmp/
ssh user@187.77.150.203
cd /var/www/html
tar -xzf /tmp/loka-fleet.tar.gz
```

### Step 3: Run Setup Script

```bash
ssh user@187.77.150.203
cd /var/www/html
chmod +x setup.sh
./setup.sh
```

The setup script will:
- Set correct file permissions
- Create .env from template
- Prompt for database credentials
- Create database and user
- Import schema (if available)
- Create admin user
- Set up cron job for email queue
- Configure SSL (optional)

### Step 4: Manual Configuration

After running setup script:

1. **Update .env with final credentials:**
   - Database password
   - SMTP credentials (email + app password)

2. **Verify SSL certificate:**
   - Visit https://lokafleet.dictr2.cloud
   - Ensure no security warnings

3. **Test application:**
   - Health check: https://lokafleet.dictr2.cloud/health.php
   - Login: https://lokafleet.dictr2.cloud/
   - Change default admin password immediately

---

## Server Requirements

### Minimum Specifications
- **OS:** Linux (Ubuntu/Debian recommended)
- **PHP:** 8.0 or higher
- **MySQL/MariaDB:** 5.7 or higher
- **RAM:** 2GB (4GB recommended)
- **Disk:** 20GB minimum
- **Web Server:** Apache with mod_rewrite

### Required PHP Extensions
```
✓ pdo, pdo_mysql
✓ mbstring
✓ json
✓ openssl
✓ curl
✓ gd or imagick
✓ zip
✓ intl
```

### Verification Commands
```bash
php -v
php -m | grep -E "pdo|mysqli|mbstring|json"
mysql --version
```

---

## Security Features Included

✅ **Apache Security (.htaccess)**
- HTTPS enforcement
- DDoS protection settings
- Request size limits
- Security headers (X-Frame-Options, X-Content-Type-Options)
- Blocked access to sensitive directories (config, classes, includes, cron)
- PHP settings for security (execution time, file upload limits)
- Gzip compression enabled
- Browser caching configured

✅ **Application Security**
- CSRF protection on all POST requests
- SQL injection prevention (prepared statements)
- XSS prevention (output escaping)
- Rate limiting built-in
- Password hashing (bcrypt)
- Session timeout configuration
- Role-based access control

---

## Cron Jobs Required

### Essential: Email Queue Processor

```bash
*/2 * * * * php /var/www/html/cron/process_queue.php >> /var/www/html/logs/cron.log 2>&1
```

This cron job:
- Runs every 2 minutes
- Processes queued emails
- Prevents request blocking
- Required for notification emails to work

### Optional: Automated Backups

```bash
# Daily database backup at 2 AM
0 2 * * * mysqldump -u loka_db_user -p'PASSWORD' fleet_management | gzip > /backups/fleet_$(date +\%Y\%m\%d).sql.gz

# Weekly log cleanup
0 3 * * 0 find /var/www/html/logs -name "*.log" -type f -mtime +7 -delete
```

---

## SSL Certificate Options

### Option 1: Hostinger Free SSL (Recommended)
1. Login to Hostinger hPanel
2. Go to Domains → lokafleet.dictr2.cloud
3. Click Manage → SSL
4. SSL is automatically enabled

### Option 2: Let's Encrypt (Certbot)
```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d lokafleet.dictr2.cloud
```

---

## Default Credentials

⚠️ **CHANGE IMMEDIATELY AFTER FIRST LOGIN!**

```
Admin Username: admin
Admin Password: password
```

### Reset Admin Password

```bash
ssh user@187.77.150.203
cd /var/www/html
php reset_admin_password.php
```

---

## Important URLs After Deployment

| Service | URL |
|---------|-----|
| Application | https://lokafleet.dictr2.cloud/ |
| Health Check | https://lokafleet.dictr2.cloud/health.php |
| Admin Panel | https://lokafleet.dictr2.cloud/?page=admin |
| Dashboard | https://lokafleet.dictr2.cloud/?page=dashboard |

---

## File Permissions Summary

```bash
# Directories
find /var/www/html -type d -exec chmod 755 {} \;

# Files
find /var/www/html -type f -exec chmod 644 {} \;

# Writable directories (logs)
chmod 777 /var/www/html/logs
chmod 666 /var/www/html/logs/*.log

# Protected files (.env)
chmod 600 /var/www/html/.env

# Executable scripts
chmod 755 /var/www/html/cron/*.php
chmod 755 /var/www/html/setup.sh
```

---

## Database Schema

If no schema file is included, you have two options:

1. **Run migrations:**
   ```bash
   php migrate.php
   ```

2. **Import from development:**
   ```bash
   mysqldump -u dev_user -p dev_database > schema.sql
   mysql -u loka_db_user -p fleet_management < schema.sql
   ```

---

## Verification Checklist

Before considering deployment complete:

- [ ] Archive created successfully
- [ ] Files uploaded to server
- [ ] setup.sh script executed
- [ ] Database created
- [ ] Database user created with strong password
- [ ] Schema imported or migrations run
- [ ] .env file configured with actual credentials
- [ ] File permissions set correctly
- [ ] Cron job for email queue configured
- [ ] SSL certificate active (HTTPS works)
- [ ] Health check endpoint responds correctly
- [ ] Login page loads
- [ ] Can log in as admin
- [ ] Default admin password changed
- [ ] Email sending tested
- [ ] Logs are being written
- [ ] Direct access to .env blocked

---

## Troubleshooting Reference

### Issue: 500 Internal Server Error
```bash
tail -50 /var/log/apache2/error.log
tail -50 /var/www/html/logs/error.log
```

### Issue: Database connection failed
```bash
mysql -u loka_db_user -p fleet_management
cat /var/www/html/.env | grep DB_
```

### Issue: CSS/JS not loading
```bash
ls -la /var/www/html/assets/dist/
cat /var/www/html/.htaccess
```

### Issue: Emails not sending
```bash
mysql -u loka_db_user -p fleet_management
SELECT * FROM email_queue WHERE status = 'pending';
tail -50 /var/www/html/logs/email_queue.log
crontab -l
```

---

## Backup Strategy

### Database Backup (Manual)
```bash
mysqldump -u loka_db_user -p fleet_management > fleet_backup_$(date +%Y%m%d).sql
```

### Database Backup (Automated - Cron)
```bash
0 2 * * * mysqldump -u loka_db_user -p'PASSWORD' fleet_management | gzip > /backups/fleet_$(date +\%Y\%m\%d).sql.gz
```

### Files Backup
```bash
tar -czf loka_files_backup_$(date +%Y%m%d).tar.gz /var/www/html
```

---

## Performance Optimization

### OpCache (Recommended)
Edit `/etc/php/8.0/apache2/php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### Database Indexes
Ensure proper indexes on frequently queried columns:
```sql
SHOW INDEX FROM requests;
SHOW INDEX FROM users;
SHOW INDEX FROM vehicles;
```

---

## Maintenance Commands

### Clear Cache
```bash
# Clear session files
rm -f /var/www/html/logs/sessions/*

# Clear old rate limits
mysql -u loka_db_user -p fleet_management
DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY);
```

### Check Logs
```bash
# Application log
tail -f /var/www/html/logs/app.log

# Error log
tail -f /var/www/html/logs/error.log

# Email queue log
tail -f /var/www/html/logs/email_queue.log

# Apache error log
tail -f /var/log/apache2/error.log
```

---

## Post-Deployment Security Checklist

- [ ] Changed default admin password
- [ ] Set strong database password
- [ ] HTTPS/SSL working (no warnings)
- [ ] .env file permissions: 600
- [ ] Logs directory writable: 777
- [ ] Firewall configured (allow 80, 443, 22 only)
- [ ] Fail2ban installed and enabled
- [ ] Disabled directory browsing (verified)
- [ ] Blocked direct access to .env (verified)
- [ ] PHP error display disabled (verified)
- [ ] Automated backups configured

---

## Support Resources

### Documentation Files (in prod2prod/)
1. **MIGRATION_SUMMARY.md** - This document
2. **README.md** - Complete package overview
3. **QUICK_START.md** - Fast deployment guide
4. **DEPLOYMENT_GUIDE.md** - Detailed documentation

### Original Documentation (in parent directory)
- **AGENTS.md** - Development guidelines and patterns
- **DEPLOYMENT_GUIDE.md** - Original deployment guide
- **README.md** - General application overview

---

## Important Notes

### What Was NOT Included
❌ node_modules/ - Development dependencies
❌ assets/js/src/ - Vue.js source files
❌ .git/ - Version control
❌ Development configuration files
❌ Test files and directories
❌ Local database dumps
❌ Temporary files

### What IS Ready for Production
✅ Minified frontend assets
✅ All PHP application code
✅ Vendor dependencies (Composer)
✅ Production .htaccess with security hardening
✅ Environment configuration templates
✅ Database migrations
✅ Cron jobs
✅ Complete documentation

---

## Final Checklist Before Upload

- [ ] Reviewed QUICK_START.md
- [ ] Created deployment archive
- [ ] Have SSH credentials for VPS
- [ ] Have database root password (if creating new DB)
- [ ] Have SMTP credentials (for email notifications)
- [ ] Confirmed DNS points to 187.77.150.203
- [ ] Read and understood DEPLOYMENT_GUIDE.md

---

## Contact & Support

If you encounter issues:

1. **Check logs:** `/var/www/html/logs/`
2. **Review documentation:** All .md files in prod2prod/
3. **Verify configuration:** .env file values
4. **Test connectivity:** Health check endpoint

---

**Package Prepared:** March 16, 2026
**Target Domain:** lokafleet.dictr2.cloud
**Server IP:** 187.77.150.203
**Package Version:** 2.5.1

---

**Next Step:** Follow QUICK_START.md to deploy!
