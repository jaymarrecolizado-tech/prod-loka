# LOKA Fleet Management - Deployment Checklist

## Pre-Deployment ✅
- [ ] Upload `public_html/` to VPS at `/var/www/loka`
- [ ] Create database `fleet_management` on VPS
- [ ] Create database user with privileges
- [ ] Copy `.env.production` to `.env` and update credentials
- [ ] Set file permissions (www-data ownership)

## Database Setup ✅ (You said complete)
- [ ] Run migrations: `php migrate.php` or `php setup_database.php`

## Cron Jobs ✅ (You said complete)
- [ ] Email queue processor: `*/2 * * * * php /var/www/loka/cron/process_queue.php`

## Post-Deployment Verification

### 1. Website Accessibility
- [ ] Homepage loads: `https://lokafleet.dictr2.online`
- [ ] Login page works
- [ ] Can log in with admin credentials

### 2. Database
- [ ] No database connection errors
- [ ] Login/authentication works
- [ ] Data saves correctly

### 3. Email System
- [ ] Test email sending works
- [ ] Email queue processes (check cron logs)

### 4. Security
- [ ] Direct access to `/classes/`, `/migrations/` returns 403
- [ ] `.env` file not accessible via browser
- [ ] Security headers present

### 5. Quick Test Commands
```bash
# Check site loads
curl -I https://lokafleet.dictr2.online

# Check security headers
curl -I https://lokafleet.dictr2.online | grep -i header

# Test email queue manually
php /var/www/loka/cron/process_queue.php

# Check cron logs
tail -f /var/www/loka/logs/cron.log
```

---

## Rollback Plan (If Issues)
1. Check error logs: `/var/log/nginx/loka-error.log`
2. Check PHP logs: `/var/log/php_errors.log`
3. Temporarily enable debug: Set `APP_DEBUG=true` in `.env`
4. Check file permissions: `ls -la /var/www/loka`

---

## Default Login (After Fresh Install)
- **URL:** `https://lokafleet.dictr2.online/login.php`
- Check database for admin user or run seed data
