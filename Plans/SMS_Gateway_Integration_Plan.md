# SMS Notifications Integration Plan
## LOKA Fleet Management — Android SMS Gateway + PHP App

**Branch:** `loka-smsnotifimplem7192026`  
**Date:** July 19, 2026  
**Status:** Implemented on branch `loka-smsnotifimplem7192026` (outbound queue + All Father UI)  
**Supersedes:** Original generic Node/React/PostgreSQL plan (kept ideas for gateway infra only)

### Locked decisions (so far)

| # | Decision |
|---|----------|
| 1 | MVP event allowlist (assigned, approved, dispatch, arrival/complete, reject/revision, cancel, gas voucher approve/reject) |
| 2 | **Queue** + cron (like email), not sync-send in the HTTP request |
| 3 | Recipients mirror email/in-app: requester, driver, **linked passenger users** (guests skipped) |
| 4 | Global enable (no per-user opt-in in v1) — controlled by **All Father** UI |
| 5 | **Option A (dual path):** (1) **Local first** — Docker SMS gateway on this Windows/XAMPP machine + your Android phone for testing; (2) **Then production** — same gateway stack on the **same Hostinger VPS** as LOKA + office phone/SIM in server room |
| 6 | **Outbound only** — SMS exists solely to **notify travel participants** (requester, driver, linked passengers). No inbound/reply commands in scope |
| 7 | Dev test with real Android phone + local Docker; then Hostinger |

**Also locked:** All Father can **toggle SMS notifications** and edit **SMS settings** in-app (not `.env`-only).

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

**Outbound only:** LOKA → Gateway API → Phone → SMS to travel participants.  
**Inbound / reply SMS:** out of scope (not needed for notify-only).

---

## 4. Scope decisions (discuss before coding)

### 4.1 MVP vs later

| Item | MVP (recommended) | Later |
|------|-------------------|--------|
| Channel | **Outbound notify-only** (travel participants) | Inbound/replies (explicitly out of scope) |
| Trigger | Allowlisted `notify()` event types | Manual blast UI, templates editor |
| Recipients | Same as email for those events (requester / driver / linked passengers with phone) | Guests — skip |
| Delivery | **Queue** + `cron/process_sms_queue.php` | Richer retry / delivery receipts |
| Control UI | **All Father:** enable toggle + gateway settings | Per-user opt-in |
| Ops UI | SMS logs + test send + health (All Father; optional admin read-only later) | Full analytics dashboard |
| Infra | Local Docker (dev test) → then Hostinger VPS Docker (prod) + office Android phone | Second phone / multi-SIM |

### 4.2 Which events should SMS? (MVP — locked)

SMS only when the event type is allowlisted **and** SMS is enabled by All Father. Who receives = whoever already gets `notify()` / `notifyDriver()` / `notifyPassengersBatch()` for that event (must have `users.phone`).

| Event types (examples) | Typical recipients |
|------------------------|--------------------|
| `driver_assigned`, `driver_requested` | Driver (+ others if notify() already targets them) |
| `request_fully_approved`, `trip_fully_approved` | Requester, passengers, driver as email does today |
| `vehicle_dispatched`, `trip_started` | Requester, driver, passengers as email does |
| `vehicle_arrived`, `trip_completed` | Same |
| `request_rejected`, `request_revision`, `trip_rejected`, `trip_revision` | Same |
| `request_cancelled`, `trip_cancelled_driver`, `trip_cancelled` | Same |
| `gas_voucher_approved`, `gas_voucher_rejected` | Voucher requester |

**Defer / out of scope:** “submitted to motorpool” noise, damage alerts, trip ticket review, **any inbound SMS / reply commands**.

### 4.3 Message style

- Max ~160 chars when possible (1 segment); allow up to ~320 for critical ones.
- Plain text, PH English/Taglish OK.
- Include request id + short deep link: `{APP_URL}/?page=requests&action=view&id={id}` (or my-trips for drivers).
- Normalize PH numbers: `09XXXXXXXXX` → `+639XXXXXXXXX`.

### 4.4 Fail-soft rules

- Missing/invalid phone → skip SMS, still create in-app + email.
- Gateway down → log failure, **do not** block approve/dispatch.
- SMS disabled (All Father toggle / missing settings) → no gateway calls (safe XAMPP default).

---

## 5. App integration design (PHP)

### 5.1 Config: `.env` defaults + All Father overrides

**`.env` (defaults / secrets fallback — never commit real passwords):**

```env
SMS_ENABLED=false
SMS_GATEWAY_URL=
SMS_GATEWAY_USERNAME=
SMS_GATEWAY_PASSWORD=
SMS_DEFAULT_COUNTRY_CODE=63
SMS_TIMEOUT_SECONDS=15
SMS_MAX_MESSAGE_LENGTH=320
SMS_EVENT_ALLOWLIST=driver_assigned,driver_requested,request_fully_approved,trip_fully_approved,vehicle_dispatched,trip_started,vehicle_arrived,trip_completed,request_rejected,request_revision,trip_rejected,trip_revision,request_cancelled,trip_cancelled_driver,gas_voucher_approved,gas_voucher_rejected
```

**All Father UI (primary runtime control)** — e.g. `/?page=security&action=sms` or `/?page=settings&action=sms` gated by `isAllFather()`:

| Setting | Purpose |
|---------|---------|
| Enable SMS notifications | Master on/off (writes `settings` key `sms_enabled`) |
| Gateway URL | `https://sms.yourdomain.com` |
| Gateway username / password | Basic auth to capcom6 |
| Default country code | `63` |
| Event allowlist | Checkboxes or comma list of notify types |
| Test send | Send one SMS to a number + show result |

Resolution order: **DB settings (All Father) override `.env` defaults** when present. Password fields: show “unchanged” if blank on save. Audit-log all SMS setting changes.

### 5.2 New files (proposed)

| Path | Role |
|------|------|
| `public_html/classes/SmsGateway.php` | HTTP client to capcom6 `/message` |
| `public_html/classes/SmsQueue.php` | Queue rows like `EmailQueue` |
| `public_html/config/sms.php` | Load env + settings + template map |
| `public_html/includes/sms.php` | `smsEnabled()`, `smsNotifyUser()`, helpers |
| `database/migrations/0xx_sms_*.php` | `sms_logs` (+ settings seeds) |
| `public_html/pages/sms/settings.php` | All Father toggle + gateway settings |
| `public_html/pages/sms/index.php` | Logs / health / test send |
| `public_html/cron/process_sms_queue.php` | Drain queue to gateway |

### 5.3 Hook into `notify()`

Minimal change in `includes/functions.php` after email queue:

```php
// Pseudocode — enqueue only; cron sends
if (smsEnabled() && smsEventAllowed($type)) {
    SmsQueue::queueForUser($userId, $type, $title, $message, $link, $requestId);
}
```

`SmsQueue::queueForUser()`:
1. Load `users.phone`; skip if empty/invalid
2. Normalize to E.164 (+63…)
3. Render short SMS body from template map
4. Insert `sms_logs` with status `pending`
5. Cron later calls gateway and updates `sent` / `failed`

`notifyDriver()` / `notifyPassengersBatch()` already call `notify()` — one hook covers requester, driver, and linked passengers.

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

```

No `sms_incoming` table — inbound replies are out of scope.

Follow existing migration numbering under `database/migrations/`.

### 5.5 All Father + ops UI (LOKA patterns)

- **Settings (All Father only):** enable toggle, gateway credentials, allowlist, test SMS.
- **Logs:** recent `sms_logs`, status filters, gateway health.
- Sidebar: under Rate Limits / Administration area for All Father (same pattern as rate-limit tools).
- Not a React SPA.

### 5.6 Inbound webhook

**Not in scope.** SMS is notify-only for travel participants.

---

## 6. Infrastructure — local test first, then Hostinger

We support **both** environments with the same app code. Only All Father / `.env` gateway URL + credentials change.

### 6.1 Path A — This machine (dev / proof of concept)

| Piece | Where |
|-------|--------|
| LOKA | XAMPP (`localhost` / local `APP_URL`) |
| SMS Gateway | **Docker Desktop** on this Windows PC |
| Phone | Your Android + SIM, SMS Gateway app → Private Server |

**Typical local flow**
1. Install Docker Desktop on this PC.
2. Run `capcom6/sms-gateway` (compose file we’ll add under e.g. `devops/sms-gateway/`).
3. Point LOKA SMS settings at the local gateway (e.g. `http://host.docker.internal:3000` from container view, or `http://127.0.0.1:3000` from PHP on the host).
4. Phone must **reach** the gateway:
   - Same Wi‑Fi: use PC’s LAN IP, e.g. `http://192.168.x.x:3000` (HTTP OK for LAN tests), **or**
   - Use a tunnel (Cloudflare Tunnel / ngrok) for HTTPS if the app requires it.
5. All Father: enable SMS, send **test SMS** to your number.
6. Exercise a real trip event (assign driver, etc.) and confirm queue → send.

**Local tips**
- Firewall: allow inbound TCP 3000 from LAN (or only the tunnel).
- Phone and PC on the same Wi‑Fi simplifies testing.
- When done testing, turn SMS **off** in All Father so XAMPP doesn’t keep trying to send.

### 6.2 Path B — Hostinger (production)

Same stack as before, after local works:

1. Docker on Hostinger VPS + Nginx + Let’s Encrypt → `https://sms.yourdomain.com`.
2. All Father: switch Gateway URL/credentials to production.
3. Office Android phone in server room → Private Server → production URL.
4. Cron on VPS for `process_sms_queue.php`.

### 6.3 Shared notes

- Verify image/API against [capcom6/android-sms-gateway](https://github.com/capcom6/android-sms-gateway) at implement time.
- SIM needs load; don’t use a personal SIM you care about for spam tests.
- Dev and prod should use **different** gateway passwords if possible.

---

## 7. Implementation phases (revised)

| Phase | Work | Outcome |
|-------|------|---------|
| **0 – Decide** | All scope questions locked | Ready to build |
| **1 – Infra (local)** | Docker on this PC + your phone | Test SMS works on XAMPP |
| **1b – Infra (prod)** | Hostinger Docker + office phone | Production SMS live |
| **2 – Core PHP** | Queue, migration, `notify()` hook, templates | Events enqueue when enabled |
| **3 – All Father UI** | Toggle + settings + test send + logs | Runtime control without editing `.env` |
| **4 – Cron** | `process_sms_queue.php` (local Task Scheduler / VPS cron) | Pending → sent/failed |
| **5 – Hardening** | Rate limits, audit log, retries, monitoring | Production-safe |

Estimate for phases 2–4 (app only, gateway already up): **1–2 days**, not 4–6 hours of full-stack Node work.

---

## 8. Security & ops checklist (LOKA)

- [ ] Gateway credentials in DB/settings + `.env` fallback; All Father only can edit
- [ ] Gateway bound to localhost on VPS; public via Nginx HTTPS only
- [ ] SMS settings / test send: **All Father only**; audit log changes
- [ ] Rate limit outbound (per user + global) to protect SIM
- [ ] Soft-fail: approve/dispatch never depends on SMS success
- [ ] Password fields not re-displayed in HTML after save

---

## 9. What we are explicitly not doing (from the old plan)

- No Node/Express SMS service or React `SMSDashboard.jsx`
- No PostgreSQL migrations / `TEXT[]` / JSONB
- No delivery tracking, geofence, speeding, OB Pass Slip SMS
- No rewriting LOKA’s notification system around `NotificationService`
- No two-way trip confirm-via-SMS in MVP (guard ops already handle dispatch/arrival in-app)

---

## 10. Discussion questions — all locked

1. MVP event allowlist  
2. Queue + cron  
3. Recipients = email audience (requester, driver, linked passengers)  
4. Global enable via All Father settings  
5. Local Docker + phone first → Hostinger VPS + office phone  
6. **Outbound notify-only** (no inbound)  
7. Real phone local testing, then production

---

## 11. Suggested first build step (after §10 done)

1. All Father SMS settings page + `settings` keys + migration `sms_logs`
2. `SmsQueue` + hook in `notify()` + short templates
3. Cron processor + gateway client
4. Hostinger Docker + phone connect guide (step-by-step for you)
5. Test send from All Father UI

No production secrets committed; confirm before changing live `.env`.

---

## 12. Summary

Self-hosted **Android SMS gateway on Hostinger VPS** + phone in the office server room; LOKA queues SMS beside email via `notify()`; **All Father** turns SMS on/off and configures the gateway in the UI.

**Branch:** `loka-smsnotifimplem7192026` — scope locked; ready to implement when you say go.
