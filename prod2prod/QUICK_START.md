# LOKA Fleet - Quick Deployment Checklist

**Server:** 187.77.150.203 (Hostinger KVM 2 VPS)
**Domain:** https://lokafleet.dictr2.cloud

---

## Pre-Upload (Local Machine)

### 1. Verify Production Build
```bash
cd C:\wamp64\www\Projects\loka2\prod2prod
dir
```

Verify these files/folders exist:
- [ ] .htaccess
- [ ] .env.example
- [ ] .env.production
- [ ] index.php
- [ ] health.php
- [ ] migrate.php
- [ ] api/
- [ ] assets/dist/
- [ ] classes/
- [ ] config/
- [ ] cron/
- [ ] includes/
- [ ] migrations/
- [ ] pages/
- [ ] vendor/
- [ ] logs/

### 2. Create Archive for Upload

**Using 7-Zip (Windows):**
1. Right-click `prod2prod` folder
2. Select 7-Zip → Add to archive
3. Choose "tar.gz" format
4. Create `loka-fleet.tar.gz`

**Using Git Bash:**
```bash
cd C:\wamp64\www\Projects\loka2\prod2prod
tar -czf loka-fleet.tar.gz .
```

---

## Upload to Server

### Option A: Hostinger File Manager (Easiest)

1. Log in to [Hostinger hPanel](https://hpanel.hostinger.com)
2. Go to **Files → File Manager**
3. Navigate to `public_html/` (or your domain folder)
4. Delete existing files if needed
5. Click **Upload** and select `loka-fleet.tar.gz`
6. Once uploaded, right-click → **Extract**
7. Verify files are extracted in correct location

### Option B: SFTP/SCP

```bash
# Upload archive
scp C:\wamp64\www\Projects\loka2\prod2prod\loka-fleet.tar.gz user@187.77.150.203:~/

# SSH to server
ssh user@187.77.150.203

# Navigate to web directory
cd /var/www/html  # or /home/user/public_html

# Backup existing files
tar -czf backup_$(date +%Y%m%d).tar.gz .

# Extract new files
tar -xzf ~/loka-fleet.tar.gz
rm ~/loka-fleet.tar.gz
```

---

## Post-Upload (On Server)

### 1. SSH to Server
```bash
ssh user@187.77.150.203
```

### 2. Set File Permissions
```bash
cd /var/www/html  # or your web root

# Make logs writable
chmod 777 logs
chmod 666 logs/*.log

# Make cron jobs executable
chmod 755 cron/*.php

# Protect .env file
chmod 600 .env
```

### 3. Create Environment File
```bash
cp .env.production .env
nano .env
```

**Update these values:**
```env
DB_PASSWORD=YOUR_SECURE_DATABASE_PASSWORD
SMTP_PASSWORD=YOUR_EMAIL_PASSWORD  # if using Gmail
```

Save: `Ctrl+O`, `Enter`, `Ctrl+X`

### 4. Set Up Database

```bash
# Login to MySQL
mysql -u root -p
```

Run these commands:
```sql
CREATE DATABASE IF NOT EXISTS fleet_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'loka_db_user'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD';
GRANT ALL PRIVILEGES ON fleet_management.* TO 'loka_db_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 5. Import Database Schema

If you have a schema file:
```bash
mysql -u loka_db_user -p fleet_management < schema.sql
```

Or run migrations:
```bash
php migrate.php
```

### 6. Test Installation

Open browser and visit:
- **Health Check:** https://lokafleet.dictr2.cloud/health.php
- **Main Site:** https://lokafleet.dictr2.cloud/

Should see login page.

### 7. Set Up Cron Job (Email Queue)

```bash
# Edit crontab
crontab -e

# Add this line (adjust path if needed)
*/2 * * * * php /var/www/html/cron/process_queue.php >> /var/www/html/logs/cron.log 2>&1

# Save and exit
```

---

## SSL Certificate

### Hostinger Free SSL

1. Go to hPanel → **Domains**
2. Find `lokafleet.dictr2.cloud`
3. Click **Manage** → **SSL**
4. SSL should be automatically enabled
5. Verify by visiting https://lokafleet.dictr2.cloud

### Manual SSL (Certbot)

```bash
# Install certbot
sudo apt update
sudo apt install certbot python3-certbot-apache

# Get certificate
sudo certbot --apache -d lokafleet.dictr2.cloud
```

---

## Create Admin User

### Option 1: Using Reset Script (if available)
```bash
php reset_admin_password.php
```

### Option 2: Direct SQL
```bash
mysql -u loka_db_user -p fleet_management
```

```sql
-- Replace 'YOUR_PASSWORD' with desired password
INSERT INTO users (username, email, password_hash, role, created_at, updated_at)
VALUES ('admin', 'admin@lokafleet.dictr2.cloud',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin',
        NOW(), NOW());
```

**Default password (with hash above):** `password`
**IMPORTANT:** Change immediately after first login!

---

## Verification Checklist

- [ ] HTTPS works (no security warnings)
- [ ] Homepage loads correctly
- [ ] Can access login page
- [ ] Can log in as admin
- [ ] Database connection works
- [ ] Email queue cron job is set
- [ ] Logs directory is writable
- [ ] .env file has correct credentials
- [ ] SSL certificate is valid

---

## Troubleshooting

### 500 Internal Server Error
```bash
# Check error logs
tail -50 /var/log/apache2/error.log
tail -50 /var/www/html/logs/error.log
```

### Database Connection Failed
```bash
# Test connection
mysql -u loka_db_user -p fleet_management

# Check if .env values match
cat /var/www/html/.env | grep DB_
```

### CSS/JS Not Loading
```bash
# Check if assets exist
ls -la /var/www/html/assets/dist/

# Check .htaccess
cat /var/www/html/.htaccess
```

### Permissions Issues
```bash
# Reset permissions
cd /var/www/html
chmod 755 .
chmod 644 *.php
chmod 777 logs
chmod 666 logs/*.log
```

---

## Post-Deployment Security

1. **Change default admin password** immediately
2. **Set strong database password** in .env
3. **Block direct access to .env** (already in .htaccess)
4. **Enable firewall** if not already
5. **Set up fail2ban** for SSH protection

---

## Important URLs After Deployment

- **Application:** https://lokafleet.dictr2.cloud/
- **Health Check:** https://lokafleet.dictr2.cloud/health.php
- **Admin Panel:** https://lokafleet.dictr2.cloud/?page=admin
- **Database:** localhost:3306 (via SSH tunnel only)
- **Hostinger Panel:** https://hpanel.hostinger.com

---

## Default Credentials (CHANGE IMMEDIATELY!)

```
Admin User: admin
Admin Password: password (from hash above)
Database: fleet_management
DB User: loka_db_user
DB Password: (set during setup)
```

---

## Support

For detailed instructions, see: **DEPLOYMENT_GUIDE.md**

---

**Last Updated:** March 16, 2026
