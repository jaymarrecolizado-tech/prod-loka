# Localhost Access Instructions

## Quick Access

Your LOKA Fleet Management System is working on localhost at:

### Primary Access (Development Version)
```
http://localhost/Projects/loka2/public_html/
```

✅ **This is the recommended URL for local development**

---

## Issue Explanation

You uploaded `prod2prod/` to your live server, which is correct. However, the `prod2prod/` folder contains production-specific configurations that prevent it from working on localhost:

1. **HTTPS Redirect** - Forces HTTPS which localhost doesn't have
2. **Production Database Config** - Points to production database
3. **Production Security Settings** - Strict settings for production

## Your Two Versions

| Folder | Purpose | Status |
|--------|---------|--------|
| `public_html/` | Development version | ✅ Working on localhost |
| `prod2prod/` | Production version | ❌ Won't work on localhost (by design) |

---

## How to Access

### Option 1: Use Development Version (Recommended)
Just open your browser and go to:
```
http://localhost/Projects/loka2/public_html/
```

This is your development environment. Continue developing here, then deploy to production when ready.

### Option 2: Make prod2prod Work Locally (Not Recommended)

If you really need to test prod2prod locally:

1. Backup the current .htaccess:
   ```bash
   cd C:/wamp64/www/Projects/loka2/prod2prod
   copy .htaccess .htaccess.production.backup
   ```

2. Use the localhost version:
   ```bash
   copy .htaccess.localhost .htaccess
   ```

3. Update database config in `.env` to point to local database

⚠️ **Warning:** This is not recommended as it mixes production and development configs.

---

## Default Login

For the development version (`public_html/`), you can use:

- **Email:** admin@fleet.local
- **Password:** (Check `reset_admin_password.php` or create a new one)

To reset the admin password:
```bash
cd C:/wamp64/www/Projects/loka2/public_html
php reset_admin_password.php
```

---

## Production Deployment

When deploying to production:

1. **Do NOT** upload `public_html/` - it contains development configs
2. **DO** upload `prod2prod/` contents
3. Update `.env` with production database credentials
4. Keep the production `.htaccess` (HTTPS enabled)

---

## Summary

✅ Your localhost is working fine!
✅ Use `http://localhost/Projects/loka2/public_html/` for development
✅ `prod2prod/` is for production only

❌ Don't try to access `prod2prod/` on localhost without modifying configs
❌ Don't upload `public_html/` to production

---

## Troubleshooting

### If public_html doesn't work:

1. Check WAMP is running (green icon)
2. Check MySQL is running
3. Try accessing: http://localhost/Projects/loka2/public_html/test_connection.php

### If you see a white page:

1. Check error logs: `C:/wamp64/www/Projects/loka2/public_html/logs/error.log`
2. Ensure database `lokaloka2` exists and is accessible

### Database Issues:

To check database connection:
```bash
cd C:/wamp64/www/Projects/loka2/public_html
php test_connection.php
```

To see database schema:
```bash
php check_schema.php
```

---

Last Updated: 2026-03-22
