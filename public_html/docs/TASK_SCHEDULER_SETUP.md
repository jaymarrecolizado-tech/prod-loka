# Windows Task Scheduler Setup Guide for LOKA Email Queue

## Quick Setup (2 Minutes)

### Step 1: Create Task
1. Press `Win + R`, type `taskschd.msc`, press Enter
2. Click **Create Basic Task** (right side)
3. Name: `LOKA Email Queue Processor`
4. Description: `Processes email queue every 2 minutes`

### Step 2: Set Trigger
1. Click **Triggers** tab
2. Click **New...**
3. Settings:
   - Begin the task: **At startup**
   - Repeat task every: **2 minutes**
   - Duration: **Indefinitely**
4. Click **OK**

### Step 3: Set Action
1. Click **Actions** tab
2. Click **New...**
3. Action: **Start a program**
4. Program/script: `C:\wamp64\bin\php\php8.3.2\php.exe`
   - *Note: Adjust PHP version if different*
5. Add arguments: `C:\wamp64\www\fleetManagement\LOKA\cron\process_queue.php`
6. Click **OK**

### Step 4: Configure Settings (Optional)
1. Click **Conditions** tab
2. Uncheck **Start the task only if the computer is on AC power** (optional)
3. Click **Settings** tab
4. Check **Allow task to be run on demand**
5. Check **Run task as soon as possible after a scheduled start is missed**
6. Click **OK** to create task

### Step 5: Test
1. Find your task in the list
2. Right-click → **Run**
3. Should see window with processing results

## Quick Setup Using Batch File

If you want to use the batch file (`process_email_queue.bat`):

1. Create task with:
   - Program: `C:\wamp64\www\fleetManagement\LOKA\process_email_queue.bat`
   - No arguments needed
2. Repeat every 2 minutes

## Verify It's Working

After setup:

1. Create a test request
2. Wait 2-3 minutes
3. Check if emails were received
4. Or run manually:
   ```batch
   C:\wamp64\www\fleetManagement\LOKA\process_email_queue.bat
   ```

## Troubleshooting

### Task Not Running
- Check if WAMP is running (Apache & PHP)
- Check Task Scheduler → Task Properties → History
- Verify PHP path is correct

### Emails Still Not Sending
- Run `process_email_queue.bat` manually
- Check `config/mail.php` for MAIL_PASSWORD
- Check `logs/` directory for errors

### Wrong PHP Version
Check your PHP version:
```batch
C:\wamp64\bin\php\php8.3.2\php.exe -v
```

Adjust path in Task Scheduler if needed.

## Automation Alternative: Run on Demand

If you don't want automatic processing, you can:

1. Create task to run **at logon**
2. Set to repeat every 30 minutes (less frequent)
3. Or run manually after creating requests

## Advanced: Separate Logs

To keep logs:
1. In arguments, add: `>> C:\wamp64\www\fleetManagement\LOKA\logs\email_queue.log 2>&1`
2. This will create log file with all processing output

## Production Deployment

For production server (Linux):

```bash
# Crontab entry
*/2 * * * * /usr/bin/php /path/to/LOKA/cron/process_queue.php >> /var/log/loka_email.log 2>&1
```

---

**Created:** January 28, 2026
**Purpose:** Enable automatic email queue processing on Windows
**Result:** Emails will be sent every 2 minutes automatically
