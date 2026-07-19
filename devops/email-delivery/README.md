# Email delivery mode (no CLI cron)

All Father → **System Control → Email** (`/?page=security&action=email`).

## Modes

| Mode | When to use |
|------|-------------|
| **Immediate** (default) | VPS/local without reliable cron. SMTP runs in the HTTP request. |
| **Queued** | Fast pages; drain with Process now / HTTP cron / CLI. |
| **Hybrid** | Critical templates sync; others queue. |

Requires `MAIL_ENABLED=true` and working SMTP in `.env`.

## Quick test (Immediate, no cron)

1. Run migration: `php public_html/migrations/024_email_delivery_mode.php`
2. All Father → Email → confirm mode **Immediate**, `MAIL_ENABLED` warning absent
3. Submit a new trip request once
4. Expect: busy modal (“Submitting… Sending notifications”), one request created, email_queue rows `sent` (or `pending` if SMTP failed)
5. Double-click Submit: modal blocks re-submit; server also rejects same destination+start within 120s

## HTTP cron (Queued / Hybrid)

Copy the URL from the Email page, or:

```bash
curl -s "https://yourdomain/?page=cron&action=email&key=YOUR_CRON_SECRET"
```

Optional SMS drain: `action=sms` with the same key. Rotate the secret from the Email page after sharing.
