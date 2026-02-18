# LOKA Fleet Management System - Hostinger VPS Deployment Guide

A comprehensive guide for deploying the LOKA Fleet Management System on a Hostinger VPS using Nginx, PHP-FPM, and MySQL/MariaDB.

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Server Setup](#2-server-setup)
3. [Nginx Configuration](#3-nginx-configuration)
4. [Application Setup](#4-application-setup)
5. [Cron Job Configuration](#5-cron-job-configuration)
6. [Security Configuration](#6-security-configuration)
7. [Post-Deployment Verification](#7-post-deployment-verification)
8. [Troubleshooting](#8-troubleshooting)

---

## 1. Prerequisites

### 1.1 Server Requirements

| Component | Minimum Version | Recommended |
|-----------|----------------|-------------|
| PHP | 8.0 | 8.2+ |
| MySQL | 5.7 | 8.0+ |
| MariaDB | 10.3 | 10.6+ |
| Nginx | 1.18 | 1.24+ |
| Composer | 2.0 | Latest |

### 1.2 Required PHP Extensions

```bash
# Core extensions
php8.2-fpm
php8.2-mysql
php8.2-pdo
php8.2-mbstring
php8.2-xml
php8.2-curl
php8.2-zip
php8.2-gd
php8.2-intl
php8.2-bcmath
php8.2-json
php8.2-fileinfo
php8.2-opcache
php8.2-tokenizer
```

### 1.3 Hostinger VPS Specifications

- **Plan**: VPS 1 (or higher)
- **OS**: Ubuntu 22.04 LTS (recommended)
- **RAM**: 1GB minimum (2GB+ recommended)
- **Storage**: 20GB SSD minimum
- **Bandwidth**: Sufficient for your user base

---

## 2. Server Setup

### 2.1 Initial Server Configuration

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install essential tools
sudo apt install -y curl wget git unzip nano software-properties-common

# Set timezone (adjust to your location)
sudo timedatectl set-timezone Asia/Manila
```

### 2.2 Install Nginx

```bash
# Install Nginx
sudo apt install -y nginx

# Start and enable Nginx
sudo systemctl start nginx
sudo systemctl enable nginx

# Verify installation
nginx -v
```

### 2.3 Install PHP 8.2 with FPM

```bash
# Add PHP repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.2 and required extensions
sudo apt install -y php8.2-fpm php8.2-mysql php8.2-pdo php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath \
    php8.2-json php8.2-fileinfo php8.2-opcache php8.2-tokenizer

# Start and enable PHP-FPM
sudo systemctl start php8.2-fpm
sudo systemctl enable php8.2-fpm

# Verify PHP-FPM is running
sudo systemctl status php8.2-fpm
```

### 2.4 Install MySQL/MariaDB

```bash
# Install MariaDB (recommended)
sudo apt install -y mariadb-server mariadb-client

# Start and enable MariaDB
sudo systemctl start mariadb
sudo systemctl enable mariadb

# Secure MySQL installation
sudo mysql_secure_installation

# Follow the prompts:
# - Set root password: YES
# - Remove anonymous users: YES
# - Disallow root login remotely: YES
# - Remove test database: YES
# - Reload privilege tables: YES
```

### 2.5 Install Composer

```bash
# Download and install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verify installation
composer --version
```

---

## 3. Nginx Configuration

### 3.1 PHP-FPM Configuration

Edit the PHP-FPM pool configuration:

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

Update the following settings:

```ini
; User and group (use your deployment user)
user = www-data
group = www-data

; Listen configuration
listen = /run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Performance settings
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

; Environment variables
env[APP_ENV] = production
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.2-fpm
```

### 3.2 PHP Configuration

Edit the main PHP configuration:

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

Update the following settings for production:

```ini
; File Uploads
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20

; Execution Limits
max_execution_time = 300
max_input_time = 300
memory_limit = 256M

; Error Handling (Production)
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Security
expose_php = Off
allow_url_fopen = On
allow_url_include = Off

; Session
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
session.gc_maxlifetime = 7200

; OPcache (Enable for production)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 60
opcache.fast_shutdown = 1
```

### 3.3 Nginx Server Block Configuration

Create the Nginx configuration file for LOKA:

```bash
sudo nano /etc/nginx/sites-available/loka
```

Paste the following configuration:

```nginx
# LOKA Fleet Management - Nginx Configuration
# Hostinger VPS Deployment

server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/loka;
    index index.php;

    # Security: Hide Nginx version
    server_tokens off;

    # Security: Limit request body size
    client_max_body_size 10M;

    # Security: Timeout settings
    client_body_timeout 12;
    client_header_timeout 12;
    keepalive_timeout 15;
    send_timeout 10;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml+rss
        application/rss+xml
        font/truetype
        font/opentype
        application/vnd.ms-fontobject
        image/svg+xml;

    # Security Headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

    # Static file caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header X-Content-Type-Options "nosniff" always;
        access_log off;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Deny access to sensitive directories
    location ~ ^/(config|classes|migrations|logs|cron|includes)/ {
        deny all;
        return 403;
    }

    # Deny access to sensitive file extensions
    location ~* \.(log|sql|md|example|git|svn|hg)$ {
        deny all;
        return 403;
    }

    # Protect .env file
    location = /.env {
        deny all;
        return 403;
    }

    # PHP handling
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        
        # Security: Limit buffer sizes
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        
        # Security: Hide PHP version
        fastcgi_hide_header X-Powered-By;
    }

    # Main rewrite rule - Route all requests through index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Error pages
    error_page 404 /index.php;
    error_page 403 /index.php;
    
    # Logging
    access_log /var/log/nginx/loka-access.log;
    error_log /var/log/nginx/loka-error.log;
}
```

**Note:** Replace `your-domain.com` with your actual domain name and `/var/www/loka` with your actual deployment path.

### 3.4 Enable the Site

```bash
# Create symbolic link to enable the site
sudo ln -s /etc/nginx/sites-available/loka /etc/nginx/sites-enabled/

# Remove default site (optional)
sudo rm /etc/nginx/sites-enabled/default

# Test Nginx configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

---

## 4. Application Setup

### 4.1 Create Database

```bash
# Login to MySQL
sudo mysql -u root -p

# Create database
CREATE DATABASE loka_fleet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create database user
CREATE USER 'loka_user'@'localhost' IDENTIFIED BY 'your_secure_password_here';

# Grant privileges
GRANT ALL PRIVILEGES ON loka_fleet.* TO 'loka_user'@'localhost';

# Flush privileges
FLUSH PRIVILEGES;

# Exit
EXIT;
```

### 4.2 Upload Application Files

```bash
# Create application directory
sudo mkdir -p /var/www/loka
sudo chown -R $USER:$USER /var/www/loka

# Upload files using SCP or SFTP
# Example using SCP from local machine:
# scp -r /local/path/to/LOKA/* user@your-vps-ip:/var/www/loka/

# Or clone from Git repository:
# git clone https://github.com/your-repo/loka.git /var/www/loka
```

### 4.3 Set Directory Permissions

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/loka

# Set directory permissions
sudo find /var/www/loka -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/loka -type f -exec chmod 644 {} \;

# Set special permissions for logs directory
sudo chmod 775 /var/www/loka/logs
sudo chown -R www-data:www-data /var/www/loka/logs

# Set permissions for cron directory (scripts need execute)
sudo chmod 755 /var/www/loka/cron
sudo chmod 644 /var/www/loka/cron/*.php
```

### 4.4 Environment Configuration

Create the `.env` file:

```bash
cd /var/www/loka
sudo nano .env
```

Add the following environment variables:

```bash
# Application Environment
APP_ENV=production
APP_URL=https://your-domain.com
SITE_URL=https://your-domain.com

# Database Configuration
DB_HOST=localhost
DB_NAME=loka_fleet
DB_USER=loka_user
DB_PASS=your_secure_password_here

# SMTP Configuration (Gmail Example)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USER=your-email@gmail.com
SMTP_PASSWORD=your_16_char_app_password
SMTP_FROM_EMAIL=your-email@gmail.com
SMTP_FROM_NAME="LOKA Fleet Management"
```

**Important:** Replace all placeholder values with your actual credentials.

Set secure permissions on the `.env` file:

```bash
sudo chmod 600 /var/www/loka/.env
sudo chown www-data:www-data /var/www/loka/.env
```

### 4.5 Install Dependencies

```bash
cd /var/www/loka

# Install Composer dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# If Composer is not available, manually upload vendor directory
```

### 4.6 Database Migration

```bash
cd /var/www/loka

# Run database setup script
sudo -u www-data php setup_database.php

# Or run migrations manually
sudo -u www-data php migrate.php
```

### 4.7 Create Required Directories

```bash
# Create logs directory if not exists
sudo mkdir -p /var/www/loka/logs
sudo chmod 775 /var/www/loka/logs
sudo chown -R www-data:www-data /var/www/loka/logs

# Create backups directory
sudo mkdir -p /var/www/loka/backups
sudo chmod 775 /var/www/loka/backups
sudo chown -R www-data:www-data /var/www/loka/backups
```

---

## 5. Cron Job Configuration

### 5.1 Email Queue Processing

The email queue processor must run every 2 minutes to send queued emails.

```bash
# Edit crontab for www-data user
sudo crontab -u www-data -e
```

Add the following cron jobs:

```bash
# Email queue processor - runs every 2 minutes
*/2 * * * * /usr/bin/php /var/www/loka/cron/process_queue.php >> /var/www/loka/logs/cron.log 2>&1

# Database backup - daily at 2 AM
0 2 * * * /usr/bin/php /var/www/loka/scripts/create_backup.php >> /var/www/loka/logs/backup.log 2>&1

# Log rotation - weekly
0 0 * * 0 /usr/bin/logrotate -f /etc/logrotate.d/loka
```

### 5.2 Cron Job Script Permissions

```bash
# Ensure cron scripts are executable
sudo chmod 755 /var/www/loka/cron/process_queue.php
sudo chown www-data:www-data /var/www/loka/cron/process_queue.php
```

### 5.3 Log Rotation Configuration

Create log rotation configuration:

```bash
sudo nano /etc/logrotate.d/loka
```

Add the following:

```
/var/www/loka/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    sharedscripts
    postrotate
        /usr/bin/systemctl reload nginx > /dev/null 2>&1 || true
    endscript
}
```

---

## 6. Security Configuration

### 6.1 SSL/HTTPS Setup with Let's Encrypt

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Follow the prompts to complete SSL setup

# Test automatic renewal
sudo certbot renew --dry-run
```

### 6.2 Firewall Configuration (UFW)

```bash
# Install UFW if not present
sudo apt install -y ufw

# Default policies
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Allow SSH (adjust port if needed)
sudo ufw allow 22/tcp

# Allow HTTP and HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status verbose
```

### 6.3 Fail2Ban Installation

```bash
# Install Fail2Ban
sudo apt install -y fail2ban

# Create local configuration
sudo nano /etc/fail2ban/jail.local
```

Add the following:

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
port = http,https
logpath = /var/log/nginx/error.log

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
port = http,https
logpath = /var/log/nginx/error.log
```

Enable and start Fail2Ban:

```bash
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### 6.4 Secure File Permissions

```bash
# Set strict permissions on sensitive files
sudo chmod 600 /var/www/loka/.env
sudo chmod 600 /var/www/loka/config/*.php
sudo chmod 644 /var/www/loka/logs/.htaccess

# Remove write permissions from application files
sudo find /var/www/loka -type f -name "*.php" -exec chmod 644 {} \;

# Ensure only www-data can write to logs and backups
sudo chown -R www-data:www-data /var/www/loka/logs
sudo chown -R www-data:www-data /var/www/loka/backups
sudo chmod 755 /var/www/loka/logs
sudo chmod 755 /var/www/loka/backups
```

### 6.5 Database Security

```bash
# Login to MySQL
sudo mysql -u root -p

# Disable remote root access
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

# Remove anonymous users
DELETE FROM mysql.user WHERE User='';

# Remove test database
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';

# Flush privileges
FLUSH PRIVILEGES;
EXIT;
```

---

## 7. Post-Deployment Verification

### 7.1 Testing Checklist

- [ ] **Website Accessibility**
  - [ ] Homepage loads without errors
  - [ ] HTTPS is enforced (redirects from HTTP)
  - [ ] SSL certificate is valid

- [ ] **Authentication**
  - [ ] Login page loads
  - [ ] Can log in with valid credentials
  - [ ] Invalid credentials show appropriate error
  - [ ] Password reset functionality works

- [ ] **Database Connectivity**
  - [ ] No database connection errors in logs
  - [ ] Data is being read/written correctly
  - [ ] Migrations ran successfully

- [ ] **Email System**
  - [ ] Cron job is running (check `/var/www/loka/logs/cron.log`)
  - [ ] Test email can be sent
  - [ ] Email queue is being processed

- [ ] **File Uploads**
  - [ ] Can upload files within size limits
  - [ ] Uploaded files are accessible
  - [ ] Invalid file types are rejected

- [ ] **Security**
  - [ ] Direct access to `/config/`, `/classes/`, `/logs/` returns 403
  - [ ] `.env` file is not accessible via browser
  - [ ] Security headers are present (check with browser dev tools)
  - [ ] HTTPS is enforced

- [ ] **Performance**
  - [ ] Pages load within 3 seconds
  - [ ] Static assets are cached
  - [ ] Gzip compression is enabled

### 7.2 Verification Commands

```bash
# Check Nginx status
sudo systemctl status nginx

# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Check MySQL status
sudo systemctl status mariadb

# Check cron jobs
sudo crontab -u www-data -l

# Check recent cron execution
 tail -f /var/www/loka/logs/cron.log

# Check Nginx error logs
sudo tail -f /var/log/nginx/loka-error.log

# Check PHP error logs
sudo tail -f /var/log/php_errors.log

# Check application logs
sudo tail -f /var/www/loka/logs/error.log

# Test SSL configuration
openssl s_client -connect your-domain.com:443 -servername your-domain.com

# Check security headers
curl -I https://your-domain.com
```

---

## 8. Troubleshooting

### 8.1 Common Issues and Solutions

#### 502 Bad Gateway Error

**Cause:** PHP-FPM is not running or misconfigured

**Solution:**
```bash
# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Check PHP-FPM socket exists
ls -la /run/php/php8.2-fpm.sock

# Verify Nginx configuration
sudo nginx -t
```

#### 404 Not Found on All Pages

**Cause:** Rewrite rules not working

**Solution:**
```bash
# Check Nginx configuration includes try_files directive
grep -n "try_files" /etc/nginx/sites-available/loka

# Ensure index.php exists
ls -la /var/www/loka/index.php

# Check file permissions
sudo chown -R www-data:www-data /var/www/loka
```

#### Database Connection Errors

**Cause:** Incorrect database credentials or MySQL not running

**Solution:**
```bash
# Test MySQL connection
mysql -u loka_user -p loka_fleet

# Check MySQL status
sudo systemctl status mariadb

# Verify .env file has correct credentials
cat /var/www/loka/.env | grep DB_

# Check MySQL error logs
sudo tail -f /var/log/mysql/error.log
```

#### Emails Not Sending

**Cause:** Cron job not running or SMTP misconfiguration

**Solution:**
```bash
# Check if cron is running
sudo systemctl status cron

# Run email queue manually to test
sudo -u www-data php /var/www/loka/cron/process_queue.php

# Check cron logs
sudo tail -f /var/www/loka/logs/cron.log

# Verify SMTP settings in .env
cat /var/www/loka/.env | grep SMTP_

# Test email configuration
sudo -u www-data php /var/www/loka/cron/test_email_config.php
```

#### Permission Denied Errors

**Cause:** Incorrect file ownership or permissions

**Solution:**
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/loka

# Fix directory permissions
sudo find /var/www/loka -type d -exec chmod 755 {} \;

# Fix file permissions
sudo find /var/www/loka -type f -exec chmod 644 {} \;

# Fix special directories
sudo chmod 775 /var/www/loka/logs
sudo chmod 775 /var/www/loka/backups
```

#### White Screen of Death

**Cause:** PHP errors hidden in production

**Solution:**
```bash
# Temporarily enable error display for debugging
sudo nano /etc/php/8.2/fpm/php.ini
# Set: display_errors = On

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Check error logs
sudo tail -f /var/log/php_errors.log
sudo tail -f /var/www/loka/logs/error.log
```

### 8.2 Log File Locations

| Service | Log Location |
|---------|--------------|
| Nginx Access | `/var/log/nginx/loka-access.log` |
| Nginx Error | `/var/log/nginx/loka-error.log` |
| PHP Error | `/var/log/php_errors.log` |
| Application | `/var/www/loka/logs/error.log` |
| Cron Jobs | `/var/www/loka/logs/cron.log` |
| MySQL | `/var/log/mysql/error.log` |
| System | `/var/log/syslog` |

### 8.3 Performance Tuning

```bash
# Enable OPcache (already in php.ini)
# Monitor OPcache status
curl -s https://raw.githubusercontent.com/rlerdorf/opcache-status/master/opcache.php | php

# Nginx worker processes
# Edit: /etc/nginx/nginx.conf
worker_processes auto;
worker_connections 1024;

# MySQL performance tuning
# Edit: /etc/mysql/mariadb.conf.d/50-server.cnf
[mysqld]
innodb_buffer_pool_size = 256M
max_connections = 50
query_cache_size = 64M
```

---

## Appendix A: Complete Nginx Configuration Reference

```nginx
# /etc/nginx/nginx.conf - Main configuration

user www-data;
worker_processes auto;
pid /run/nginx.pid;
include /etc/nginx/modules-enabled/*.conf;

events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

http {
    # Basic Settings
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    server_tokens off;

    # MIME types
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    # SSL Settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256';

    # Logging
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml application/atom+xml image/svg+xml;

    # Virtual Host Configs
    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

---

## Appendix B: Environment Variables Reference

| Variable | Description | Required |
|----------|-------------|----------|
| `APP_ENV` | Application environment (production/development) | Yes |
| `APP_URL` | Application base URL | Yes |
| `SITE_URL` | Site URL (should match APP_URL) | Yes |
| `DB_HOST` | Database host (usually localhost) | Yes |
| `DB_NAME` | Database name | Yes |
| `DB_USER` | Database username | Yes |
| `DB_PASS` | Database password | Yes |
| `SMTP_HOST` | SMTP server hostname | Yes |
| `SMTP_PORT` | SMTP server port (587 for TLS) | Yes |
| `SMTP_ENCRYPTION` | Encryption type (tls/ssl) | Yes |
| `SMTP_USER` | SMTP username | Yes |
| `SMTP_PASSWORD` | SMTP password or app password | Yes |
| `SMTP_FROM_EMAIL` | From email address | Yes |
| `SMTP_FROM_NAME` | From name for emails | No |

---

## Appendix C: Backup and Recovery

### Automated Daily Backup Script

```bash
#!/bin/bash
# /var/www/loka/scripts/backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/www/loka/backups"
DB_NAME="loka_fleet"
DB_USER="loka_user"
DB_PASS="your_password"

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Files backup
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz -C /var/www/loka --exclude='logs/*' --exclude='backups/*' .

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
```

Make executable and add to cron:
```bash
chmod +x /var/www/loka/scripts/backup.sh
# Add to crontab: 0 2 * * * /var/www/loka/scripts/backup.sh
```

---

**Document Version:** 1.0  
**Last Updated:** January 29, 2026  
**Compatible with:** LOKA Fleet Management v1.0.0+
