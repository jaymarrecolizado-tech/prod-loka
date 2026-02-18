# Email Configuration Guide

## Issue Identified

The email notification system is working correctly - system notifications are being created and emails are being queued. However, **emails are not being sent** because:

1. **MAIL_PASSWORD is empty** in `config/mail.php`
2. The email queue processor needs to be run manually (or via cron job)

## Solution

### Step 1: Generate Gmail App Password

You need to create an App Password for sending emails. Here's how:

1. **Enable 2-Factor Authentication** (if not already enabled):
   - Go to https://myaccount.google.com/security
   - Sign in with your Gmail account
   - Find "2-Step Verification" and turn it ON

2. **Generate App Password**:
   - Go to https://myaccount.google.com/apppasswords
   - Sign in again if prompted
   - Under "Select app", choose **Mail**
   - Under "Select device", choose **Other (Custom name)**
   - Enter a name like **"LOKA Fleet Management"**
   - Click **GENERATE**
   - Copy the 16-character password (example: `abcd efgh ijkl mnop`)

### Step 2: Update Mail Configuration

Edit `config/mail.php` and update line 19:

```php
define('MAIL_PASSWORD', getenv('SMTP_PASSWORD') ?: ($isProduction ? die('ERROR: SMTP_PASSWORD environment variable required in production') : 'YOUR_16_CHAR_APP_PASSWORD_HERE'));
```

Replace `YOUR_16_CHAR_APP_PASSWORD_HERE` with the password you generated (include spaces).

**Example:**
```php
define('MAIL_PASSWORD', getenv('SMTP_PASSWORD') ?: ($isProduction ? die('ERROR: SMTP_PASSWORD environment variable required in production') : 'abcd efgh ijkl mnop'));
```

### Step 3: Test Email Sending

Run the email queue processor manually to send pending emails:

```bash
php cron/process_queue.php
```

### Step 4: Set Up Automatic Email Processing

**For Local Development (Windows Task Scheduler):**

1. Open Task Scheduler
2. Create Basic Task
3. Name: "LOKA Email Queue Processor"
4. Trigger: Daily - Repeat every 2 minutes
5. Action: Start a program
   - Program: `C:\wamp64\bin\php\php8.x.x\php.exe`
   - Arguments: `C:\wamp64\www\fleetManagement\LOKA\cron\process_queue.php`
6. Finish

**For Production (Linux Cron):**

```bash
# Edit crontab
crontab -e

# Add this line (runs every 2 minutes)
*/2 * * * * /usr/bin/php /path/to/LOKA/cron/process_queue.php >> /var/log/email_queue.log 2>&1
```

## Verification

After configuration, verify emails are working:

1. Create a new vehicle request
2. Check system notifications in dashboard
3. Check email queue:
   ```bash
   php -r "require_once 'config/database.php'; require_once 'classes/Database.php'; \$db = Database::getInstance(); \$stats = \$db->fetchAll(\"SELECT status, COUNT(*) as count FROM email_queue GROUP BY status\"); print_r(\$stats);"
   ```
4. Check email queue logs in `logs/` directory

## Troubleshooting

**Emails still not sending?**

1. Check error logs: `C:/wamp64/www/fleetManagement/LOKA/logs/`
2. Verify SMTP settings in `config/mail.php`
3. Test with a different Gmail account
4. Check if Gmail is blocking the app password
5. Verify the cron job is running

**Common Gmail Issues:**

- "Less secure apps" setting no longer applies - you MUST use App Password
- Some organizations disable App Passwords - contact IT admin
- Make sure 2FA is enabled first
- App Passwords only work with the account that created them

## Current Email Queue Status

To check pending emails:

```bash
php cron/process_queue.php
```

This will process all pending emails in the queue and report results.

---

**Configuration File:** `config/mail.php`
**Processor Script:** `cron/process_queue.php`
**Documentation Date:** January 28, 2026
