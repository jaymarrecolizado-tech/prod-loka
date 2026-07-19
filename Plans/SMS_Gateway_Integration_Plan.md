# SMS Notifications Integration Plan
## LOKA Fleet Management — Android SMS Gateway + PHP App

**Branch:** `loka-smsnotifimplem7192026`  
**Date:** July 19, 2026  
**Status:** Discussion draft (aligned to current LOKA codebase)  
**Supersedes:** Original generic Node/React/PostgreSQL plan (kept ideas for gateway infra only)

---

## 1. Why this plan was rewritten

The original plan assumed a **Node.js + Express + React + PostgreSQL** fleet app with delivery/OB Pass Slip features. LOKA is different:

| Assumed in old plan | Actual LOKA stack |
|---------------------|-------------------|
| Node.js / Express API | PHP pages + `index.php` router |
| React SPA dashboard | Server-rendered HTML + Vite/Tailwind/DaisyUI |
| PostgreSQL | MySQL (`fleetdb`) |
| Separate SMS REST routes | Extend existing `notify()` pipeline |
| Delivery / OB Pass Slip / geofence / speeding | Trip requests, approvals, guard dispatch/arrival, gas vouchers, trip tickets |
| Customers as SMS recipients | Requesters, drivers, passengers, approvers (users with phone) |

**Keep from the old plan:** capcom6 Android SMS Gateway (Private Server) + old phone + optional Hostinger Docker/Nginx setup.  
**Replace:** all app-side Node/React/Postgres code with PHP/MySQL that hooks into LOKA’s existing notification path.

---

## 2. Current notification architecture (what we build on)

```
Workflow page (approve / assign / dispatch / etc.)
        │
        ▼
 notify() / notifyDriver() / notifyPassengersBatch()
   includes/functions.php
        ├─► INSERT notifications     (in-app bell)
        └─► EmailQueue::queueTemplate() → email_queue → SMTP cron
```

**Facts today**
- Phones live on `users.phone` (and `drivers.emergency_contact_phone` — emergency only, not for SMS).
- No SMS env vars, tables, or gateway code.
- `NotificationService` + `config/notifications.php` are **legacy / unused**.
- Changelog already lists SMS as a future item.

**SMS should plug in next to email**, not replace the in-app bell:

```
 notify(...)
        ├─► notifications (unchanged)
        ├─► EmailQueue (unchanged)
        └─► SmsQueue / SmsGateway  (NEW — if SMS_ENABLED + user has phone)
```

---

## 3. Proposed architecture (LOKA-shaped)

```
                    ┌─────────────────────────────┐
                    │  LOKA PHP (Hostinger / XAMPP) │
                    │  notify() → SmsService       │
                    └──────────────┬──────────────┘
                                   │ HTTPS Basic Auth
                                   ▼
                    ┌─────────────────────────────┐
                    │  SMS Gateway Server (Docker) │
                    │  sms.yourdomain.com :443     │
                    │  capcom6/sms-gateway         │
                    └──────────────┬──────────────┘
                                   │ push / websocket
                                   ▼
                    ┌─────────────────────────────┐
                    │  Old Android phone + SIM     │
                    │  SMS Gateway app (Private)   │
                    └─────────────────────────────┘
```

**Outbound (MVP):** LOKA → Gateway API → Phone → SMS to user.  
**Inbound (Phase 2, optional):** reply SMS → webhook → log only first; commands later if needed.

---

## 4. Scope decisions (discuss before coding)

### 4.1 MVP vs later

| Item | MVP (recommended) | Later |
|------|-------------------|--------|
| Channel | Outbound SMS only | Two-way reply commands |
| Trigger | Mirror selected `notify()` events | Manual blast UI, templates editor |
| Recipients | Users with non-empty `users.phone` | Guests (no account) — skip |
| Delivery | Sync or short queue like email | Retry worker / cron |
| Admin UI | Simple logs page (admin/motorpool) | Full React-style dashboard |
| Preferences | Global on/off + per-event flags in `.env` or `settings` | Per-user SMS opt-in in profile |
| Environments | Off in local unless `SMS_ENABLED=true` | Staging gateway phone |

### 4.2 Which events should SMS? (proposed MVP set)

Keep SMS **short and high-value** (cost + noise). In-app + email stay for everything else.

| Event | Who gets SMS | Why |
|-------|--------------|-----|
| Driver assigned / requested | Driver | Must know they have a trip |
| Trip fully approved | Requester (+ optional driver) | Trip is green-lit |
| Guard dispatch | Requester + driver | Vehicle left |
| Guard arrival / trip completed | Requester + driver | Trip done |
| Request rejected / revision | Requester | Action needed |
| Request cancelled | Driver (if assigned) + requester | Stop showing up |
| Gas voucher approved / rejected | Requester | Finance-critical |

**Defer SMS for:** every passenger fan-out, “submitted to motorpool”, damage alerts (email/in-app enough unless you want motorpool on SMS), trip ticket review.

### 4.3 Message style

- Max ~160 chars when possible (1 segment); allow up to ~320 for critical ones.
- Plain text, PH English/Taglish OK.
- Include request id + short deep link: `{APP_URL}/?page=requests&action=view&id={id}` (or my-trips for drivers).
- Normalize PH numbers: `09XXXXXXXXX` → `+639XXXXXXXXX`.

### 4.4 Fail-soft rules

- Missing/invalid phone → skip SMS, still create in-app + email.
- Gateway down → log failure, **do not** block approve/dispatch.
- `SMS_ENABLED=false` → no network calls (local XAMPP default).

---

## 5. App integration design (PHP)

### 5.1 Config (`.env` — same pattern as mail)

```env
SMS_ENABLED=false
SMS_GATEWAY_URL=https://sms.yourdomain.com
SMS_GATEWAY_USERNAME=
SMS_GATEWAY_PASSWORD=
SMS_WEBHOOK_SECRET=
SMS_DEFAULT_COUNTRY_CODE=63
SMS_TIMEOUT_SECONDS=15
SMS_MAX_MESSAGE_LENGTH=320
# Comma-separated notify() type keys that may also SMS
SMS_EVENT_ALLOWLIST=driver_assigned,driver_requested,request_fully_approved,vehicle_dispatched,trip_started,vehicle_arrived,trip_completed,request_rejected,request_revision,request_cancelled,trip_cancelled_driver,gas_voucher_approved,gas_voucher_rejected
```

Never commit real passwords. Do not overwrite existing `.env` without asking.

### 5.2 New files (proposed)

| Path | Role |
|------|------|
| `public_html/includes/sms.php` or `classes/SmsGateway.php` | HTTP client to capcom6 `/message` |
| `public_html/classes/SmsQueue.php` (optional) | Queue rows like `EmailQueue` |
| `public_html/config/sms.php` | Load env + template map |
| `database/migrations/0xx_sms_logs.php` | MySQL tables |
| `public_html/pages/sms/index.php` | Admin log viewer (phase 1.5) |
| `public_html/pages/webhooks/sms.php` | Inbound webhook (phase 2) |
| `cron/process_sms_queue.php` | If we queue instead of sync-send |

### 5.3 Hook into `notify()`

Minimal change in `includes/functions.php` after email queue:

```php
// Pseudocode
if (smsEnabled() && smsEventAllowed($type)) {
    smsNotifyUser($userId, $type, $title, $message, $link, $requestId);
}
```

`smsNotifyUser()`:
1. Load `users.phone`
2. Normalize to E.164 (+63…)
3. Render short SMS body from template map (not full email HTML)
4. Insert `sms_logs` row (`pending` / `sent` / `failed`)
5. Call gateway; update log

`notifyDriver()` already resolves to a user — no special path needed if the hook is inside `notify()`.

### 5.4 MySQL schema (not PostgreSQL)

```sql
CREATE TABLE sms_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  request_id INT UNSIGNED NULL,
  phone VARCHAR(20) NOT NULL,
  event_type VARCHAR(64) NULL,
  message TEXT NOT NULL,
  status ENUM('pending','sent','failed','delivered') NOT NULL DEFAULT 'pending',
  gateway_message_id VARCHAR(100) NULL,
  gateway_response TEXT NULL,
  error_message TEXT NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  INDEX (status, created_at),
  INDEX (user_id),
  INDEX (request_id),
  INDEX (gateway_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phase 2 only
CREATE TABLE sms_incoming (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  message TEXT NOT NULL,
  raw_payload TEXT NULL,
  processed TINYINT(1) NOT NULL DEFAULT 0,
  received_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX (processed, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Follow existing migration numbering under `database/migrations/`.

### 5.5 Admin UI (keep LOKA patterns)

- New page: `/?page=sms` (admin / motorpool / All Father).
- Table of recent `sms_logs` + gateway health check button.
- Optional “Send test SMS” to a number (admin only).
- Sidebar under Administration — **not** a React SPA.

### 5.6 Webhook (phase 2)

- Route: `/?page=webhooks&action=sms` (no session; verify shared secret header).
- MVP: store in `sms_incoming` only.
- Commands like `OK` / `SOS` need careful product design against LOKA statuses (`requests`, guard actions) — **do not** invent a parallel `trips` table.

---

## 6. Infrastructure (largely unchanged from original)

Still valid for Hostinger KVM2 + old phone:

1. Docker run `capcom6/sms-gateway` (or current official image name — verify on GitHub before deploy).
2. Nginx + Let’s Encrypt for `sms.yourdomain.com`.
3. Android app in **Private Server** mode pointing at that URL.
4. SIM with enough SMS load (Globe/Smart/DITO).
5. Phone: stay awake, no battery optimization, always charging, solid Wi‑Fi.

**Local XAMPP:** do not require a phone. Keep `SMS_ENABLED=false` and unit-test with a mock/stub client, or point at a staging gateway when ready.

**Note:** Confirm exact API paths/auth against [capcom6/android-sms-gateway](https://github.com/capcom6/android-sms-gateway) docs at implementation time — image/config names change between releases.

---

## 7. Implementation phases (revised)

| Phase | Work | Outcome |
|-------|------|---------|
| **0 – Decide** | Agree MVP event list, sync vs queue, inbound yes/no | This discussion |
| **1 – Infra** | VPS gateway + phone connected + health OK | Can curl-send a test SMS |
| **2 – Core PHP** | Config, SmsGateway client, `sms_logs`, hook in `notify()` | Real events SMS when enabled |
| **3 – Templates** | Short bodies per allowlisted event type | Consistent, short messages |
| **4 – Admin logs** | `pages/sms` list + test send + health | Ops visibility |
| **5 – Hardening** | Rate limits, audit log, retries, monitoring scripts | Production-safe |
| **6 – Optional inbound** | Webhook + `sms_incoming` | Reply logging / future commands |

Estimate for phases 2–4 (app only, gateway already up): **1–2 days**, not 4–6 hours of full-stack Node work.

---

## 8. Security & ops checklist (LOKA)

- [ ] Gateway credentials only in `.env` / VPS secrets
- [ ] Gateway bound to localhost; public via Nginx HTTPS only
- [ ] Webhook secret required (phase 2)
- [ ] Admin-only SMS log / test send
- [ ] Rate limit outbound (per user + global) to protect SIM
- [ ] Never log full message bodies in PHP `error_log` in production if sensitive
- [ ] Soft-fail: approve/dispatch never depends on SMS success
- [ ] Separate DBs remain: no SMS tables in shared JSON files

---

## 9. What we are explicitly not doing (from the old plan)

- No Node/Express SMS service or React `SMSDashboard.jsx`
- No PostgreSQL migrations / `TEXT[]` / JSONB
- No delivery tracking, geofence, speeding, OB Pass Slip SMS
- No rewriting LOKA’s notification system around `NotificationService`
- No two-way trip confirm-via-SMS in MVP (guard ops already handle dispatch/arrival in-app)

---

## 10. Discussion questions (please decide)

1. **MVP event list** — OK with the table in §4.2, or add/remove events?
2. **Sync vs queue** — Send SMS inside `notify()` immediately, or insert `sms_logs` + cron like email? (Queue is safer under slow gateway.)
3. **Passenger SMS** — Never / only linked users / never guests?
4. **Opt-in** — Global flag only, or per-user “Receive SMS” on profile?
5. **Gateway hosting** — Same Hostinger VPS as LOKA, or separate small box + subdomain?
6. **Inbound replies** — Skip for v1, or log-only webhook from day one?
7. **Dev testing** — Mock client only on XAMPP, or a dedicated staging SIM/phone?

---

## 11. Suggested first build step (after decisions)

1. Keep infra docs in this plan; implement **Phase 2** in repo:
   - migration `sms_logs`
   - `SmsGateway` + `config/sms.php`
   - hook in `notify()` behind `SMS_ENABLED`
2. Manual test page or CLI script: send one SMS to your number.
3. Wire allowlisted events only.
4. Add admin logs page.

No production `.env` changes without your confirmation.

---

## 12. Summary

Use the **Android private SMS gateway** idea from the original plan, but integrate it into LOKA the same way email works: **PHP + MySQL + `notify()` + optional queue**, short templates for high-value trip events, soft-fail, admin logs. Drop Node/React/Postgres/delivery/OB assumptions.

**Branch:** `loka-smsnotifimplem7192026` — plan lives here; implementation starts after we lock §10 decisions.
