# LOKA Fleet Management System - Current Status
**Last Updated:** February 23, 2026

---

## 1. SYSTEM OVERVIEW

### Technology Stack
- **Backend:** PHP 8.3+ (Vanilla MVC)
- **Database:** MySQL/MariaDB
- **Frontend:** Bootstrap 5, jQuery, DataTables
- **PDF Generation:** TCPDF
- **Caching:** APCu (with file-based fallback)

### Database Configuration
- **Database Name:** `lokaloka2`
- **Config File:** `public_html/.env`

### Login Credentials (All Users: password123)
| Role | Email | Password |
|------|-------|----------|
| Admin | admin@fleet.local | password123 |
| Motorpool Head | jay.galil619@gmail.com | password123 |
| Guard | guard@fleet.local | password123 |
| Approver | shawntibo94@gmail.com | password123 |
| Requester | requester@fleet.local | password123 |

**All 101 users** in the database have the default password: `password123`

---

## 2. MODULES & FEATURES

### 2.1 Dashboard (`/page=dashboard`)
- Request statistics by status
- Recent activity feed
- Quick action buttons
- Vehicle availability overview
- Upcoming trips

### 2.2 Requests Module (`/page=requests`)
| Action | Description |
|--------|-------------|
| index | List all requests with filters |
| create | New request form with **multiple sequential destinations** |
| edit | Edit pending/revision requests |
| view | View request details |
| cancel | Cancel a request |
| print | Print request form with guard tracking and approval status |
| override | Motorpool can reassign vehicle/driver on approved requests |
| complete | Mark trip as completed |

**Request Workflow:**
```
pending → pending_motorpool → approved → completed
                 ↓                  ↓
             rejected           cancelled
                 ↓
             revision → (resubmit)
```

**Multiple Destinations Feature:**
- Users can add multiple locations in sequential order
- Locations displayed with numbered badges (1, 2, 3...)
- Stored as: `Location 1 → Location 2 → Location 3`
- Arrow separator clearly shows trip route

### 2.3 Approvals Module (`/page=approvals`)
- Department approvers see pending requests from their department
- Motorpool head approves after department approval
- Revision workflow for requesting updates
- Vehicle/driver assignment with conflict checking
- Shows revision requests in queue

### 2.4 Vehicles Module (`/page=vehicles`)
- CRUD operations for vehicles
- Vehicle types management
- Status tracking (available, in_use, maintenance, retired)
- Recent trips history

### 2.5 Drivers Module (`/page=drivers`)
- CRUD operations for drivers
- Link to user accounts
- License tracking
- Status management (active/inactive)
- Trip assignment history

### 2.6 Users Module (`/page=users`)
- CRUD operations (admin only)
- Role management (requester, approver, motorpool_head, guard, admin)
- Department assignment
- Status toggle (active/inactive)
- Bulk import via CSV

### 2.7 Departments Module (`/page=departments`)
- CRUD operations (motorpool/admin)
- View department members
- Request statistics per department

### 2.8 Maintenance Module (`/page=maintenance`)
- Create maintenance requests
- Track preventive/corrective/emergency maintenance
- Priority levels (low, medium, high, critical)
- Status workflow (pending → scheduled → in_progress → completed)
- Cost tracking (estimated vs actual)
- Odometer reading updates

### 2.9 Reports Module (`/page=reports`)

#### Main Reports Page
Card-based menu with 4 report types:
- **Vehicle History** - Trip history per vehicle with PDF/CSV export
- **Driver Report** - Trip history per driver with PDF/CSV export
- **Trip Requests** - All requests with filters and PDF/CSV export
- **Admin Reports** - Advanced data exports (admin only)

#### Vehicle History Report (`/page=reports&action=vehicle-history`)
- Select vehicle from dropdown
- Date range filter
- Vehicle information section (plate, make, model, year, type, color, mileage, fuel)
- Trip statistics (total trips, completed, total hours)
- Trip history table with all details
- **Export:** CSV and PDF

#### Driver Report (`/page=reports&action=driver`)
- Select driver from dropdown
- Date range filter
- Driver information section (name, phone, license, expiry, department, status, emergency contact)
- Trip statistics (total trips, completed, total hours, vehicles driven, passengers)
- Trip history table with all details
- **Export:** CSV and PDF

#### Trip Requests Report (`/page=reports&action=trips`)
- Date range filter
- Status filter
- Summary statistics cards
- Full requests table
- **Export:** CSV and PDF

#### Export Field List (30 fields)
| Category | Fields |
|----------|--------|
| Request | ID, Created, Scheduled Start/End, Purpose, Destination, Passengers, Status, Notes |
| Requester | Name, Email, Phone, Department |
| Vehicle | Plate, Make, Model, Year, Type, Color |
| Driver | Name, License |
| Approval | Approved By, Motorpool Head |
| Tracking | Actual Dispatch, Actual Arrival, Planned Duration, Actual Duration |
| Guard | Dispatched By, Received By, Guard Notes |

### 2.10 Guard Dashboard (`/page=guard`)
- View scheduled dispatches for today
- Record dispatch (time out)
- Record arrival (time in)
- Marks trip as completed on arrival
- Vehicle and driver status updates

### 2.11 My Trips (`/page=my-trips`)
- For users who are also drivers
- View assigned trips (upcoming and past)
- Trip statistics

### 2.12 Schedule Calendar (`/page=schedule&action=calendar`)
- Monthly calendar view
- Shows booked vehicles per day
- Visual indicators for availability

### 2.13 Notifications (`/page=notifications`)
- Real-time notification badge
- Mark as read (single/all)
- Archive functionality
- Delete functionality
- AJAX refresh for header

### 2.14 Profile (`/page=profile`)
- Edit name
- Edit **phone number**
- Edit **department**
- Change password with validation
- View account info

### 2.15 Audit Logs (`/page=audit`)
- Admin only
- View all system activity
- Filter by user, action, date

### 2.16 Settings (`/page=settings`)
- Admin only
- System name configuration
- Booking rules (max advance days, min notice)
- Email queue management

### 2.17 Admin Reports (`/page=admin-reports`)
- Admin only
- Export users, vehicles, departments, maintenance, audit logs
- CSV and PDF export options

---

## 3. SECURITY FEATURES

### 3.1 DDoS Protection
- IP-based rate limiting
- Automatic IP banning
- Configurable thresholds
- Ban duration tracking

### 3.2 Authentication & Authorization
- Password hashing (bcrypt, cost 12)
- Password policy enforcement
- Session management with fingerprint validation
- Role-based access control (RBAC)
- Remember me functionality

### 3.3 Input Validation
- CSRF token protection
- XSS prevention via `e()` helper
- SQL injection prevention (PDO prepared statements)
- Input sanitization helpers

### 3.4 Security Headers
- X-Content-Type-Options
- X-Frame-Options
- X-XSS-Protection
- Referrer-Policy
- Content-Security-Policy (production)

### 3.5 .htaccess Protection
- Block access to sensitive directories
- Block suspicious user agents
- Request size limits
- Directory listing disabled

---

## 4. DATABASE SCHEMA

### Core Tables
| Table | Description |
|-------|-------------|
| users | User accounts and profiles |
| departments | Department list |
| vehicles | Vehicle inventory |
| vehicle_types | Vehicle categories |
| drivers | Driver profiles |
| requests | Trip requests |
| request_passengers | Passenger list per request |
| approvals | Approval records (revision → approved tracking) |
| approval_workflow | Workflow state tracking |
| assignment_history | Vehicle/driver change history |

### Support Tables
| Table | Description |
|-------|-------------|
| notifications | User notifications |
| email_queue | Outgoing email queue |
| password_reset_tokens | Password reset tokens |
| maintenance_requests | Maintenance records |
| audit_log | System activity log |
| security_log | Security events log |
| rate_limits | Rate limiting tracking |
| settings | System configuration |
| migrations | Migration tracking |
| saved_workflows | User's saved approval workflows |

---

## 5. MIGRATIONS

All migrations applied:
```
000_migration_tracker.sql ✅
001_security_tables.sql ✅
002_email_queue.sql ✅
003_workflow_selection.sql ✅
004_notification_enhancements.sql ✅
005_performance_indexes.sql ✅
006_critical_indexes.sql ✅
007_viewed_at_column.php ✅
008_create_workflow_tables.php ✅
009_email_queue_request_id.php ✅
010_add_revision_status.php ✅
010_password_reset_tokens.sql ✅
011_guard_tracking_fields.sql ✅
012_maintenance_requests.sql ✅
013_assignment_history.sql ✅
014_additional_performance_indexes.sql ✅
```

### Run Migrations
```bash
cd public_html
php run-migrations.php
```

---

## 6. API ENDPOINTS

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/page=api&action=check_conflict` | GET | Check vehicle/driver conflicts |

---

## 7. CRON JOBS

| File | Purpose | Recommended Frequency |
|------|---------|----------------------|
| `cron/process_queue.php` | Process email queue | Every 5 minutes |
| `cron/process_queue_fast.php` | Fast email processing | Every minute |
| `cron/test_email_config.php` | Test email configuration | Manual |

---

## 8. ROLE PERMISSIONS MATRIX

| Feature | Requester | Approver | Motorpool | Guard | Admin |
|---------|:---------:|:--------:|:---------:|:-----:|:-----:|
| Create Requests | ✅ | ✅ | ✅ | ❌ | ✅ |
| View Own Requests | ✅ | ✅ | ✅ | ❌ | ✅ All |
| Edit Own Requests | ✅ | ✅ | ✅ | ❌ | ✅ |
| Department Approval | ❌ | ✅ | ✅ | ❌ | ✅ |
| Motorpool Approval | ❌ | ❌ | ✅ | ❌ | ✅ |
| Override Vehicle/Driver | ❌ | ❌ | ✅ | ❌ | ✅ |
| Guard Dashboard | ❌ | ❌ | ❌ | ✅ | ✅ |
| Record Dispatch/Arrival | ❌ | ❌ | ❌ | ✅ | ✅ |
| Vehicles/Drivers | ❌ | ✅ View | ✅ Full | ❌ | ✅ |
| Maintenance | ❌ | ✅ View | ✅ Full | ❌ | ✅ |
| Reports | ❌ | ✅ | ✅ | ❌ | ✅ |
| Users/Departments | ❌ | ❌ | ✅ View | ❌ | ✅ Full |
| Audit Logs | ❌ | ❌ | ❌ | ❌ | ✅ |
| Settings | ❌ | ❌ | ❌ | ❌ | ✅ |

---

## 9. CACHING STRATEGY

### Cache Implementation
- **Primary:** APCu (if available)
- **Fallback:** File-based cache (`cache/data/`)

### Cached Data (Returns Objects, Not Arrays)
| Data | TTL | Key Prefix |
|------|-----|------------|
| Employees list | 10 min | `user:employees:` |
| Approvers list | 10 min | `user:approvers:` |
| Available vehicles | 5 min | `vehicle:available:` |
| Active drivers | 10 min | `driver:active:` |
| Motorpool heads | 1 hour | `user:motorpool:` |

### Clear Cache
```bash
rm -rf public_html/cache/data/*.json
```

---

## 10. FILE STRUCTURE

```
public_html/
├── index.php              # Main router
├── .env                   # Environment config
├── config/
│   ├── bootstrap.php      # App bootstrap (classes before session)
│   ├── constants.php      # System constants
│   ├── database.php       # DB configuration
│   ├── mail.php           # Email settings
│   ├── security.php       # Security config
│   └── session.php        # Session config
├── classes/
│   ├── Auth.php           # Authentication
│   ├── Cache.php          # Caching layer (returns objects)
│   ├── Database.php       # DB wrapper (PDO, FETCH_OBJ)
│   ├── EmailQueue.php     # Email queue
│   ├── Mailer.php         # Email sending
│   ├── Migration.php      # Migration runner
│   └── Security.php       # Security utilities
├── includes/
│   ├── functions.php      # Helper functions
│   ├── header.php         # Page header
│   ├── footer.php         # Page footer
│   └── sidebar.php        # Navigation
├── pages/
│   ├── admin/
│   │   └── exports/       # PDF/CSV exports for admin
│   ├── approvals/         # Approval workflow
│   ├── auth/              # Login, password reset
│   ├── dashboard/         # Main dashboard
│   ├── departments/       # Department management
│   ├── drivers/           # Driver management
│   ├── guard/             # Guard dashboard
│   ├── maintenance/       # Maintenance module
│   ├── my-trips/          # Driver trips view
│   ├── notifications/     # Notifications
│   ├── profile/           # User profile (phone, dept editable)
│   ├── reports/           # Reports & exports
│   │   ├── index.php           # Main reports menu
│   │   ├── vehicle-history.php # Vehicle trip history
│   │   ├── driver.php          # Driver report
│   │   ├── trips.php           # All trip requests
│   │   ├── export.php          # CSV export (30 fields)
│   │   ├── export-pdf.php      # PDF export
│   │   ├── export-vehicle-history.php  # Vehicle PDF
│   │   ├── export-vehicle-csv.php      # Vehicle CSV
│   │   ├── export-driver.php    # Driver PDF
│   │   └── export-driver-csv.php # Driver CSV
│   ├── requests/          # Request management
│   ├── schedule/          # Calendar view
│   ├── settings/          # Admin settings
│   ├── users/             # User management
│   └── vehicles/          # Vehicle management
├── migrations/            # Database migrations
├── cron/                  # Scheduled jobs
├── cache/                 # Cache storage
├── logs/                  # System logs
│   └── sessions/          # Session files
└── vendor/                # Composer dependencies
```

---

## 11. RECENT FIXES (Feb 23, 2026)

### Authentication & Session Fixes
1. **Session Not Persisting** - Added `session_write_close()` before redirect
2. **Session Cookie Path** - Fixed cookie path configuration
3. **Class Load Order** - Classes now load before session.php (Security class fix)

### Cache Fixes
1. **Cache Returning Arrays** - Fixed `json_decode()` to return objects instead of arrays
2. **All cached data** now properly returns objects for consistent access

### Reports Enhancements
1. **Complete Field Exports** - CSV/PDF exports now include 30 fields
2. **Vehicle History Report** - Full vehicle info, trip statistics, all trip details
3. **Driver Report** - Complete driver info, trip statistics, all trip details
4. **CSV Exports** - Added for vehicle history and driver reports
5. **Removed Redundant Files** - Deleted placeholder utilization.php and department.php

### Request Features
1. **Multiple Destinations** - Users can add sequential locations with arrow separator
2. **Vehicle Validation** - Added required validation for vehicle selection

### Profile Enhancements
1. **Phone Number Editing** - Users can now edit their phone number
2. **Department Selection** - Users can change their department

### Print Form Fixes
1. **Approval Status Display** - Shows latest approval (revision → approved)
2. **Correct Date Labels** - "Approved on", "Disapproved on", "Revision requested on"

---

## 12. POTENTIAL ENHANCEMENTS

### High Priority
- [ ] Vehicle mileage/odometer history tracking
- [ ] Maintenance scheduling calendar view
- [ ] Email notifications for maintenance status changes
- [ ] Bulk actions for approvers (approve/reject multiple)

### Medium Priority
- [ ] Driver-specific portal enhancements
- [ ] Request templates for common trips
- [ ] Recurring trip scheduling
- [ ] Mobile-responsive improvements
- [ ] Dark mode theme

### Low Priority
- [ ] API for mobile app integration
- [ ] Real-time vehicle GPS tracking
- [ ] Fuel consumption tracking
- [ ] Cost analytics dashboard
- [ ] Multi-language support

---

## 13. TESTING CHECKLIST

### Core Workflows
- [x] Create new request → Department approval → Motorpool approval → Complete
- [x] Request revision flow (requester updates → reapprove)
- [x] Motorpool override of vehicle/driver
- [x] Guard dispatch and arrival recording
- [ ] Maintenance request full cycle
- [ ] Password reset via email
- [x] Profile update with password change

### Reports Testing
- [x] Vehicle History PDF export
- [x] Vehicle History CSV export
- [x] Driver Report PDF export
- [x] Driver Report CSV export
- [x] Trip Requests PDF export
- [x] Trip Requests CSV export

### Edge Cases
- [x] Cancel request at various stages
- [x] Edit request in revision status
- [x] Override with conflicting vehicle/driver
- [ ] Email queue failure handling
- [ ] Rate limiting and IP banning

---

## 14. QUICK REFERENCE

### URLs
```
Application:  http://localhost/projects/loka2/public_html/
Dashboard:    /?page=dashboard
Requests:     /?page=requests
Approvals:    /?page=approvals
Guard:        /?page=guard
Reports:      /?page=reports
Vehicle History: /?page=reports&action=vehicle-history
Driver Report:   /?page=reports&action=driver
Trip Requests:   /?page=reports&action=trips
Admin Reports:   /?page=admin-reports
```

### Status Constants
```php
STATUS_PENDING           = 'pending'
STATUS_PENDING_MOTORPOOL = 'pending_motorpool'
STATUS_APPROVED          = 'approved'
STATUS_REJECTED          = 'rejected'
STATUS_REVISION          = 'revision'
STATUS_CANCELLED         = 'cancelled'
STATUS_COMPLETED         = 'completed'
STATUS_MODIFIED          = 'modified'
```

### Role Constants
```php
ROLE_REQUESTER     = 'requester'
ROLE_APPROVER      = 'approver'
ROLE_MOTORPOOL     = 'motorpool_head'
ROLE_GUARD         = 'guard'
ROLE_ADMIN         = 'admin'
```

### Database Test
```bash
cd public_html
php db-test.php
```

### Clear All Test Data
```bash
# Truncates requests, notifications, approvals, etc. (keeps users, vehicles, drivers)
```

---

## 15. DEPLOYMENT NOTES

### Production Environment
1. Set `APP_ENV=production` in `.env`
2. Set `IS_PRODUCTION=true` in `.env`
3. Error reporting disabled automatically
4. Security headers enforced

### After Deployment
1. Run migrations: `php run-migrations.php`
2. Clear cache: `rm -rf cache/data/*.json`
3. Test login with admin credentials
4. Verify email queue processing

---

**End of Document**
