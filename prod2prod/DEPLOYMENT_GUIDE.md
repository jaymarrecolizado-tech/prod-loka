# LOKA Fleet Management System - Production Deployment Guide

## For Hostinger KVM2 VPS

**Domain:** https://lokafleet.dictr2.online/  
**Version:** 2.5.1  
**Server Requirements:** PHP 8.1+, MySQL 8.0+, Apache/Nginx

---

## Quick Start

### 1. Upload Files to VPS

Upload the contents of `public_html/` to your VPS at `/var/www/lokafleet/`:

```bash
# Using SCP (from your local machine)
scp -r public_html/* user@your-vps-ip:/var/www/lokafleet/

# Or using rsync
rsync -avz --progress public_html/ user@your-vps-ip:/var/www/lokafleet/
```

### 2. Set Permissions

```bash
ssh user@your-vps-ip

# Navigate to web root
cd /var/www/lokafleet

# Set ownership (www-data for Ubuntu/Debian, apache for CentOS)
sudo chown -R www-data:www-data .

# Set directory permissions
sudo find . -type d -exec chmod 755 {} \;

# Set file permissions
sudo find . -type f -exec chmod 644 {} \;

# Make sure these are writable by web server
sudo chmod -R 775 cache/
sudo chmod -R 775 logs/

# Protect sensitive files
sudo chmod 600 .env
```

### 3. Create Database

```bash
# Login to MySQL
mysql -u root -p

# Create database
CREATE DATABASE lokafleet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create user
CREATE USER 'loka_user'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';

# Grant privileges
GRANT ALL PRIVILEGES ON lokafleet.* TO 'loka_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Import Database Schema

```bash
# Import schema
cd /var/www/lokafleet
cat database/01_schema.sql | mysql -u loka_user -p lokafleet

# Import seed data
cat database/02_seed_data.sql | mysql -u loka_user -p lokafleet
```

Or using the web installer (after setting up files):
- Visit: `https://lokafleet.dictr2.online/setup_database.php`

### 5. Configure Environment

```bash
# Copy production environment file
cd /var/www/lokafleet
cp .env.production .env

# Edit with your settings
nano .env
```

**Required .env changes:**
```bash
APP_URL=https://lokafleet.dictr2.online
SITE_URL=https://lokafleet.dictr2.online

DB_HOST=localhost
DB_DATABASE=lokafleet
DB_USERNAME=loka_user
DB_PASSWORD=YOUR_STRONG_PASSWORD

SMTP_USER=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM_EMAIL=your-email@gmail.com
```

### 6. Configure Apache

Create `/etc/apache2/sites-available/lokafleet.conf`:

```apache
<VirtualHost *:80>
    ServerName lokafleet.dictr2.online
    DocumentRoot /var/www/lokafleet
    
    <Directory /var/www/lokafleet>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/lokafleet-error.log
    CustomLog ${APACHE_LOG_DIR}/lokafleet-access.log combined
</VirtualHost>
```

Enable site and SSL:
```bash
# Enable site
sudo a2ensite lokafleet

# Enable required modules
sudo a2enmod rewrite headers ssl deflate expires

# Restart Apache
sudo systemctl restart apache2

# Install Certbot for SSL (optional but recommended)
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d lokafleet.dictr2.online
```

### 7. Setup Cron Jobs

```bash
# Edit crontab
sudo crontab -e

# Add this line for email queue processing every 2 minutes
*/2 * * * * php /var/www/lokafleet/cron/process_queue.php >> /var/www/lokafleet/logs/cron.log 2>&1

# Add this for daily maintenance (optional)
0 0 * * * php /var/www/lokafleet/cron/daily_maintenance.php >> /var/www/lokafleet/logs/maintenance.log 2>&1
```

---

## Default Login Credentials

After installation, use these credentials to login:

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@dictr2.online | password123 |
| Motorpool Head | motorpool@dictr2.online | password123 |
| Guard | guard@dictr2.online | password123 |
| Approver (ORD) | ord@dictr2.online | password123 |
| Approver (AD) | ad@dictr2.online | password123 |
| Approver (FD) | fd@dictr2.online | password123 |

**⚠️ IMPORTANT:** Change all default passwords immediately after first login!

---

## Post-Deployment Verification

### 1. Test Website Access
- [ ] Homepage loads: `https://lokafleet.dictr2.online`
- [ ] Login page works
- [ ] Can log in with admin credentials
- [ ] No PHP errors visible

### 2. Test Database
- [ ] No database connection errors
- [ ] Login/authentication works
- [ ] Data saves correctly (create a test request)

### 3. Test Email System
- [ ] Test email sending works (Settings > Email Test)
- [ ] Email queue processes (check cron logs)

### 4. Security Checks
- [ ] Direct access to `/config/`, `/classes/` returns 403
- [ ] `.env` file not accessible via browser
- [ ] HTTPS is enforced
- [ ] Security headers present

---

## Troubleshooting

### Database Connection Error
```bash
# Test database connection
mysql -u loka_user -p -e "USE lokafleet; SHOW TABLES;"

# Check .env file has correct credentials
cat /var/www/lokafleet/.env | grep DB_
```

### Permission Denied Errors
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/lokafleet
sudo chmod -R 755 /var/www/lokafleet
sudo chmod -R 775 /var/www/lokafleet/cache
sudo chmod -R 775 /var/www/lokafleet/logs
```

### 500 Internal Server Error
```bash
# Check Apache error logs
sudo tail -f /var/log/apache2/lokafleet-error.log

# Enable PHP error display temporarily
# Edit index.php and set: error_reporting(E_ALL); ini_set('display_errors', 1);
```

### Email Not Sending
```bash
# Check email queue
mysql -u loka_user -p -e "SELECT status, COUNT(*) FROM lokafleet.email_queue GROUP BY status;"

# Check cron logs
tail -f /var/www/lokafleet/logs/cron.log

# Test SMTP manually
php /var/www/lokafleet/cron/process_queue.php
```

---

## Directory Structure

```
/var/www/lokafleet/
├── api/                    # API endpoints
├── assets/                 # CSS, JS, images
├── classes/               # PHP classes
├── config/                # Configuration files
├── cron/                  # Cron job scripts
├── database/              # Database migrations (dev only)
├── includes/              # Shared includes
├── libraries/             # Third-party libraries
├── logs/                  # Application logs
├── migrations/            # Database migrations
├── pages/                 # Page controllers
├── vendor/                # Composer dependencies
├── .env                   # Environment configuration
├── .htaccess              # Apache rewrite rules
├── composer.json          # PHP dependencies
├── index.php              # Main entry point
└── health.php             # Health check endpoint
```

---

## Support

For issues or questions:
1. Check the logs in `/var/www/lokafleet/logs/`
2. Review this deployment guide
3. Contact the development team

---

**Deployment Date:** 2026-03-01  
**Deployed By:** System Administrator  
**Version:** 2.5.1
