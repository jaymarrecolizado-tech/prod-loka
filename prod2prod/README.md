# LOKA Fleet Management System - Production Package

**Version:** 2.5.1  
**Target Environment:** Hostinger KVM2 VPS  
**Domain:** https://lokafleet.dictr2.online/  
**Date:** March 1, 2026

---

## Package Contents

```
prod2prod/
├── database/
│   ├── 01_schema.sql          # Complete database schema
│   └── 02_seed_data.sql       # Default users, departments, vehicles
├── public_html/               # Application files (upload to /var/www/lokafleet/)
│   ├── api/                   # REST API endpoints
│   ├── assets/                # CSS, JS, images
│   ├── classes/               # PHP classes (Auth, Database, etc.)
│   ├── config/                # Configuration files
│   ├── cron/                  # Cron job scripts
│   ├── includes/              # Shared PHP includes
│   ├── libraries/             # Third-party libraries (TCPDF, etc.)
│   ├── migrations/            # Database migration files
│   ├── pages/                 # Page controllers
│   ├── vendor/                # Composer dependencies
│   ├── .env.example           # Environment configuration template
│   ├── .htaccess              # Apache rewrite rules (production)
│   ├── composer.json          # PHP dependencies
│   ├── health.php             # Health check endpoint
│   └── index.php              # Main entry point
└── DEPLOYMENT_GUIDE.md        # Detailed deployment instructions
```

---

## Quick Deployment Steps

### 1. Upload Files
```bash
# Upload public_html contents to VPS
scp -r public_html/* user@your-vps:/var/www/lokafleet/
```

### 2. Create Database
```bash
mysql -u root -p
CREATE DATABASE lokafleet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'loka_user'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD';
GRANT ALL PRIVILEGES ON lokafleet.* TO 'loka_user'@'localhost';
EXIT;
```

### 3. Import Database
```bash
mysql -u loka_user -p lokafleet < database/01_schema.sql
mysql -u loka_user -p lokafleet < database/02_seed_data.sql
```

### 4. Configure Environment
```bash
cd /var/www/lokafleet
cp .env.example .env
nano .env  # Edit with your settings
```

### 5. Set Permissions
```bash
sudo chown -R www-data:www-data /var/www/lokafleet
sudo chmod -R 755 /var/www/lokafleet
sudo chmod -R 775 /var/www/lokafleet/cache
sudo chmod -R 775 /var/www/lokafleet/logs
sudo chmod 600 /var/www/lokafleet/.env
```

### 6. Configure Apache & SSL
```bash
sudo a2ensite lokafleet
sudo a2enmod rewrite headers ssl deflate expires
sudo certbot --apache -d lokafleet.dictr2.online
sudo systemctl restart apache2
```

### 7. Setup Cron
```bash
sudo crontab -e
# Add: */2 * * * * php /var/www/lokafleet/cron/process_queue.php >> /var/www/lokafleet/logs/cron.log 2>&1
```

---

## Default Login Credentials

| Role | Email | Password |
|------|-------|----------|
| **Admin** | admin@dictr2.online | password123 |
| **Motorpool Head** | motorpool@dictr2.online | password123 |
| **Guard** | guard@dictr2.online | password123 |
| **Approver (ORD)** | ord@dictr2.online | password123 |

**⚠️ Change all passwords immediately after first login!**

---

## System Requirements

- **PHP:** 8.1 or higher
- **MySQL:** 8.0 or higher
- **Web Server:** Apache 2.4+ with mod_rewrite
- **SSL:** Required for production (Let's Encrypt recommended)
- **Memory:** 512MB RAM minimum
- **Storage:** 1GB minimum

### Required PHP Extensions
- mysqli / pdo_mysql
- mbstring
- json
- openssl
- session
- ctype
- filter
- hash

---

## Features Included (v2.5.1)

### Core Features
- Multi-role user system (Admin, Motorpool, Approver, Guard, Requester)
- Vehicle request and approval workflow
- Driver assignment and tracking
- Real-time availability calendar
- Maintenance scheduling and tracking

### Recent Updates
- Guard dashboard with accurate statistics
- Patch notes page with version history
- Mobile-responsive design
- Email notification system
- PDF export capabilities
- CSV export for reports
- DDoS protection and security headers

---

## Post-Deployment Checklist

- [ ] Website loads without errors
- [ ] Admin login works
- [ ] Database connection stable
- [ ] Email sending configured
- [ ] Cron job running
- [ ] SSL certificate active
- [ ] Default passwords changed
- [ ] Backup schedule configured

---

## Support

For issues or questions:
1. Check logs in `/var/www/lokafleet/logs/`
2. Review `DEPLOYMENT_GUIDE.md`
3. Check application health at `/health.php`

---

## Security Notes

1. **Change default passwords immediately**
2. **Keep .env file secure** (chmod 600)
3. **Regularly update SSL certificates**
4. **Monitor logs for suspicious activity**
5. **Backup database regularly**
6. **Keep PHP and MySQL updated**

---

**© 2026 DICT Region II - Cagayan Valley**  
Fleet Management System
