# LOKA Fleet Management System - Production Deployment Guide

**Server Details:**
- Domain: https://lokafleet.dictr2.cloud
- IP Address: 187.77.150.203
- Hosting: Hostinger KVM 2 VPS

---

## Table of Contents
1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Server Requirements](#server-requirements)
3. [Database Setup](#database-setup)
4. [File Upload](#file-upload)
5. [Configuration](#configuration)
6. [Post-Deployment Steps](#post-deployment-steps)
7. [SSL Certificate Setup](#ssl-certificate-setup)
8. [Cron Job Configuration](#cron-job-configuration)
9. [Troubleshooting](#troubleshooting)
10. [Security Checklist](#security-checklist)

---

## Pre-Deployment Checklist

Before uploading, verify:

- [ ] Production build files are ready in `prod2prod/` directory
- [ ] Database credentials are ready (create secure password)
- [ ] SSL certificate will be configured (or ready to configure)
- [ ] Email SMTP credentials are confirmed
- [ ] DNS records point to the VPS IP (187.77.150.203)

---

## Server Requirements

### Minimum Requirements
- PHP 8.0 or higher
- MySQL/MariaDB 5.7 or higher
- 2GB RAM minimum (4GB recommended)
- 20GB disk space
- Apache web server with mod_rewrite enabled
- Required PHP extensions:
  - pdo, pdo_mysql
  - mbstring
  - json
  - openssl
  - curl
  - gd or imagick
  - zip
  - intl

### Verify PHP Version
```bash
php -v
```

### Verify Required Extensions
```bash
php -m | grep -E "pdo|mysqli|mbstring|json|openssl|curl|gd|zip"
```

---

## Database Setup

### 1. Create Database and User

Login to MySQL:
```bash
mysql -u root -p
```

Execute the following SQL:
```sql
-- Create database
CREATE DATABASE IF NOT EXISTS fleet_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create database user (change the password!)
CREATE USER IF NOT EXISTS 'loka_db_user'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD_HERE';

-- Grant privileges
GRANT ALL PRIVILEGES ON fleet_management.* TO 'loka_db_user'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

### 2. Import Database Schema

Upload the database schema file and run:

```bash
mysql -u loka_db_user -p fleet_management < schema.sql
```

Or if you have a dump file from development:
```bash
mysql -u loka_db_user -p fleet_management < loka_fleet_dump.sql
```

### 3. Verify Database
```bash
mysql -u loka_db_user -p -e "USE fleet_management; SHOW TABLES;"
```

---

## File Upload

### Option 1: Using SFTP (Recommended)

```bash
# Create archive locally
cd /path/to/prod2prod
tar -czf loka-fleet.tar.gz .

# Upload via SFTP
sftp user@187.77.150.203
put loka-fleet.tar.gz
exit
```

### Option 2: Using SCP

```bash
# Create archive
cd /c/wamp64/www/Projects/loka2/prod2prod
tar -czf loka-fleet.tar.gz .

# Upload
scp loka-fleet.tar.gz user@187.77.150.203:/home/user/

# Extract on server
ssh user@187.77.150.203
cd /var/www/html/  # or your web root
tar -xzf ~/loka-fleet.tar.gz
rm ~/loka-fleet.tar.gz
```

### Option 3: Using File Manager (Hostinger Control Panel)

1. Log in to Hostinger control panel
2. Go to Files → File Manager
3. Navigate to the public_html folder
4. Upload all files from `prod2prod/` directory
5. Ensure the directory structure is preserved

---

## Configuration

### 1. Create Environment File

Copy the production template and configure:

```bash
cd /var/www/html  # or your web root
cp .env.production .env
nano .env
```

Update the following values:

```env
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=fleet_management
DB_USERNAME=loka_db_user
DB_PASSWORD=YOUR_SECURE_PASSWORD_HERE

# Application URL
APP_URL=https://lokafleet.dictr2.cloud
SITE_URL=https://lokafleet.dictr2.cloud

# Email Configuration (if using Gmail)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USER=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM_EMAIL=your-email@gmail.com
SMTP_FROM_NAME="LOKA Fleet Management"
```

### 2. Set File Permissions

```bash
# Set proper ownership (adjust user as needed)
sudo chown -R www-data:www-data /var/www/html

# Set directory permissions
sudo find /var/www/html -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/html -type f -exec chmod 644 {} \;

# Make logs directory writable
sudo chmod 777 /var/www/html/logs
sudo chmod 666 /var/www/html/logs/*.log

# Protect .env file
sudo chmod 600 /var/www/html/.env
```

### 3. Create Database Configuration

If the system uses `config/database.php`, verify it reads from `.env`:

```php
<?php
// config/database.php - should already be configured to read from .env
return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_DATABASE') ?: 'fleet_management',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
];
```

---

## Post-Deployment Steps

### 1. Run Database Migrations

If there are pending migrations:

```bash
cd /var/www/html
php migrate.php
```

### 2. Verify Installation

Test the following URLs:

- Health check: `https://lokafleet.dictr2.cloud/health.php`
- Homepage: `https://lokafleet.dictr2.cloud/`
- Should show the login page

### 3. Create Initial Admin User

If no admin user exists:

```bash
# Using the included script if available
php reset_admin_password.php
```

Or directly in the database:

```sql
INSERT INTO users (username, email, password_hash, role, created_at)
VALUES ('admin', 'admin@lokafleet.dictr2.cloud',
        '$2y$10$your_hashed_password_here', 'admin', NOW());
```

### 4. Test Email Configuration

Send a test email:

```bash
php cron/test_email_config.php
```

---

## SSL Certificate Setup

### Using Let's Encrypt (Certbot)

```bash
# Install Certbot
sudo apt update
sudo apt install certbot python3-certbot-apache

# Obtain SSL certificate
sudo certbot --apache -d lokafleet.dictr2.cloud

# Test auto-renewal
sudo certbot renew --dry-run
```

### Using Hostinger Free SSL

If using Hostinger, SSL is usually automatic:
1. Go to Domains in Hostinger panel
2. Find lokafleet.dictr2.cloud
3. Click "Manage" → "SSL"
4. Enable SSL certificate

### Force HTTPS

The `.htaccess` file already includes HTTPS redirection. Verify:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## Cron Job Configuration

### Email Queue Processor (Required)

Set up a cron job to process email queue every 2 minutes:

```bash
# Edit crontab
sudo crontab -e

# Add this line (adjust path as needed)
*/2 * * * * php /var/www/html/cron/process_queue.php >> /var/www/html/logs/cron.log 2>&1
```

### Additional Cron Jobs (Optional)

```bash
# Daily database backup at 2 AM
0 2 * * * mysqldump -u loka_db_user -p'PASSWORD' fleet_management > /backups/fleet_$(date +\%Y\%m\%d).sql

# Weekly log rotation
0 3 * * 0 find /var/www/html/logs -name "*.log" -type f -mtime +7 -delete
```

### Verify Cron Jobs

```bash
# List cron jobs
sudo crontab -l

# Check cron logs
tail -f /var/log/syslog | grep CRON
```

---

## Troubleshooting

### 1. 500 Internal Server Error

Check error logs:
```bash
tail -f /var/log/apache2/error.log
tail -f /var/www/html/logs/error.log
```

Common causes:
- Missing .env file
- Incorrect file permissions
- Missing PHP extensions
- Database connection failed

### 2. Database Connection Failed

Test connection:
```bash
php -r "new PDO('mysql:host=localhost;dbname=fleet_management', 'loka_db_user', 'password');"
```

Check MySQL is running:
```bash
sudo systemctl status mysql
```

### 3. CSS/JS Not Loading

Check that assets exist:
```bash
ls -la /var/www/html/assets/dist/
```

Verify .htaccess is working:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 4. Emails Not Sending

Test email config:
```bash
php -r "
\$to = 'test@example.com';
\$subject = 'Test Email';
\$message = 'This is a test.';
\$headers = 'From: noreply@lokafleet.dictr2.cloud';
mail(\$to, \$subject, \$message, \$headers);
"
```

Check email queue:
```bash
mysql -u loka_db_user -p fleet_management -e "SELECT * FROM email_queue WHERE status = 'pending' LIMIT 5;"
```

### 5. Session Issues

Check session directory permissions:
```bash
ls -la /var/www/html/logs/sessions/
sudo chmod 777 /var/www/html/logs/sessions/
```

### 6. Rate Limiting Issues

If users are blocked:
```bash
# Clear rate limits from database
mysql -u loka_db_user -p fleet_management -e "DELETE FROM rate_limits;"
```

---

## Security Checklist

### Immediate Actions
- [ ] Change default admin password
- [ ] Set strong database password
- [ ] Verify SSL is working (HTTPS only)
- [ ] Block direct access to sensitive files (.env, .sql, .log)
- [ ] Set up firewall (only allow necessary ports)
- [ ] Enable fail2ban for SSH protection

### Firewall Configuration (UFW)

```bash
# Enable firewall
sudo ufw enable

# Allow SSH
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow MySQL (only if remote access needed)
sudo ufw allow 3306/tcp

# Check status
sudo ufw status
```

### Fail2Ban Setup

```bash
# Install fail2ban
sudo apt install fail2ban

# Create local configuration
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local

# Enable SSH protection
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### Additional Security Measures

1. **Disable PHP error display in production** - Already set in .htaccess
2. **Disable directory browsing** - Already set in .htaccess
3. **Set up automatic backups** - Configure cron job
4. **Monitor logs regularly** - Check access and error logs
5. **Keep software updated** - Run updates regularly
6. **Use strong passwords** - Minimum 12 characters, mixed case, numbers, symbols

---

## Backup Strategy

### Database Backups

```bash
# Manual backup
mysqldump -u loka_db_user -p fleet_management > fleet_backup_$(date +%Y%m%d).sql

# Automated backup via cron
0 2 * * * mysqldump -u loka_db_user -p'PASSWORD' fleet_management | gzip > /backups/fleet_$(date +\%Y\%m\%d).sql.gz
```

### File Backups

```bash
# Backup application files
tar -czf loka_files_backup_$(date +%Y%m%d).tar.gz /var/www/html
```

---

## Performance Optimization

### 1. Enable OpCache

Edit `/etc/php/8.0/apache2/php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### 2. Enable Gzip Compression

Already configured in `.htaccess`.

### 3. Browser Caching

Already configured in `.htaccess`.

### 4. Database Indexing

Ensure proper indexes on frequently queried columns:
```sql
-- Check indexes
SHOW INDEX FROM requests;
SHOW INDEX FROM users;
```

---

## Monitoring

### Check Application Health

Visit: `https://lokafleet.dictr2.cloud/health.php`

Expected response:
```json
{
  "status": "healthy",
  "database": "connected",
  "timestamp": "2026-03-16T..."
}
```

### Monitor Logs

```bash
# Application logs
tail -f /var/www/html/logs/app.log

# Error logs
tail -f /var/www/html/logs/error.log

# Email queue logs
tail -f /var/www/html/logs/email_queue.log

# Apache logs
tail -f /var/log/apache2/access.log
tail -f /var/log/apache2/error.log
```

---

## Support & Contact

For issues or questions:
- Review AGENTS.md for development guidelines
- Check logs directory for detailed error messages
- Verify configuration files are correct

---

## Appendix: File Structure

```
prod2prod/
├── .htaccess              # Apache configuration (production)
├── .env.example           # Environment template
├── .env.production        # Production config (copy to .env)
├── health.php             # Health check endpoint
├── index.php              # Main router
├── migrate.php            # Database migrations
├── api/                   # API endpoints
├── assets/
│   └── dist/             # Built frontend (JS/CSS)
├── classes/              # Core PHP classes
├── config/               # Configuration files
├── cron/                 # Cron jobs
├── includes/             # PHP includes
├── libraries/            # External libraries (TCPDF, etc.)
├── logs/                 # Application logs (writable)
├── migrations/           # Database migrations
├── pages/                # Page files
└── vendor/               # Composer dependencies
```

---

**Deployment Date:** March 16, 2026
**Version:** 2.5.1
