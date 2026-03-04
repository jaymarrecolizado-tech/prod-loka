# LOKA Fleet Management System - Agent Guide

This document provides essential information for AI agents working on the LOKA Fleet Management System.

## Project Overview

LOKA is a full-stack fleet management system built with Vanilla PHP (backend) and Vue 3 + TypeScript (frontend). It manages vehicle requests, approvals, drivers, maintenance, and reporting with role-based access control.

### Tech Stack

**Backend:**
- PHP 8.0+ (Vanilla, no framework)
- MySQL/MariaDB
- PDO for database operations
- TCPDF for PDF generation

**Frontend:**
- Vue 3 (Composition API)
- TypeScript
- Vite for build tooling
- TailwindCSS + DaisyUI for styling
- Pinia for state management
- Vue Router for routing
- Axios for HTTP requests

**Development Tools:**
- PHPUnit for PHP testing
- Vitest for unit tests
- Playwright for E2E tests
- ESLint + Prettier for linting
- PHPStan for static analysis
- Docker for containerization

---

## Essential Commands

### Frontend Development

```bash
# Navigate to public_html directory first
cd public_html

# Install dependencies
npm install

# Start development server (http://localhost:5173)
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview
```

### Backend Testing & Quality

```bash
cd public_html

# Run PHPUnit tests
composer test
# or
vendor/bin/phpunit

# Run tests with coverage
composer test:coverage

# Static analysis (PHPStan)
composer analyze

# PHP CodeSniffer check
composer cs-check

# Fix PHP CS issues
composer cs-fix

# Format all code with PHP CS Fixer
composer cs-fix:all

# Psalm analysis
composer psalm

# PHP metrics
composer metrics

# Copy/paste detection
composer cpd

# Mess detection
composer phpmd
```

### Frontend Testing & Quality

```bash
cd public_html

# Run unit tests (Vitest)
npm run test

# Run tests with UI
npm run test:ui

# Run tests with coverage
npm run test:coverage

# Run E2E tests (Playwright)
npm run test:e2e

# Lint code
npm run lint

# Fix lint issues
npm run lint:fix

# Format code (Prettier)
npm run format

# Check formatting
npm run format:check

# TypeScript type checking
npm run type-check

# Run all validation
npm run validate
```

### Database & Migrations

```bash
cd public_html

# Run all migrations
php migrate.php

# Run specific migration
php migrations/run_migration_014.php

# Setup database
php setup_database.php
```

### Docker

```bash
cd public_html

# Build containers
npm run docker:build
# or
docker-compose build

# Start containers
npm run docker:up
# or
docker-compose up -d

# Stop containers
npm run docker:down
# or
docker-compose down

# View logs
npm run docker:logs
```

---

## Project Structure

```
loka2/
├── public_html/                    # Main application directory
│   ├── api/                        # API endpoints
│   │   ├── requests.php
│   │   └── vehicle_types.php
│   ├── assets/
│   │   ├── js/                     # Frontend source
│   │   │   ├── api/                # API client modules
│   │   │   ├── components/         # Vue components
│   │   │   ├── composables/        # Vue composables
│   │   │   ├── router/             # Vue Router config
│   │   │   ├── stores/             # Pinia stores
│   │   │   ├── views/              # Vue page components
│   │   │   ├── App.vue
│   │   │   ├── admin.js
│   │   │   ├── app.js
│   │   │   └── main.js
│   │   ├── css/                    # Stylesheets
│   │   └── img/                    # Images
│   ├── classes/                    # Core PHP classes
│   │   ├── Auth.php
│   │   ├── Cache.php
│   │   ├── Database.php
│   │   ├── EmailQueue.php
│   │   ├── Mailer.php
│   │   ├── Migration.php
│   │   ├── NotificationService.php
│   │   └── Security.php
│   ├── config/                     # Configuration files
│   │   ├── bootstrap.php
│   │   ├── constants.php
│   │   ├── database.php
│   │   ├── mail.php
│   │   ├── notifications.php
│   │   ├── security.php
│   │   └── session.php
│   ├── cron/                       # Cron jobs
│   │   ├── process_queue.php       # Email queue processor
│   │   └── ...
│   ├── includes/                   # PHP includes
│   │   ├── footer.php
│   │   ├── functions.php           # Helper functions
│   │   ├── header.php
│   │   └── sidebar.php
│   ├── logs/                       # Application logs
│   │   └── sessions/
│   ├── migrations/                 # Database migrations
│   ├── pages/                      # Page files (organized by feature)
│   │   ├── admin/
│   │   ├── api/
│   │   ├── auth/
│   │   ├── approvals/
│   │   ├── dashboard/
│   │   ├── drivers/
│   │   ├── maintenance/
│   │   ├── notifications/
│   │   ├── reports/
│   │   ├── requests/
│   │   ├── schedule/
│   │   ├── vehicles/
│   │   └── ...
│   ├── .env.example                # Environment variables template
│   ├── composer.json               # PHP dependencies
│   ├── docker-compose.yml
│   ├── index.php                   # Main entry point / router
│   ├── package.json               # Node.js dependencies
│   ├── phpunit.xml
│   ├── vite.config.js
│   └── ...config files
```

---

## Code Patterns & Conventions

### PHP Backend

#### Class Structure
All core classes use a singleton pattern or dependency injection via constructor:

```php
class ClassName
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function methodName(): ReturnType
    {
        // Implementation
    }
}
```

#### Database Access
Use the Database singleton for all database operations:

```php
$db = Database::getInstance();

// Fetch single row
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);

// Fetch all rows
$requests = $db->fetchAll("SELECT * FROM requests WHERE user_id = ?", [$userId]);

// Insert
$id = $db->insert('requests', $data);

// Update
$affected = $db->update('requests', $data, 'id = ?', [$requestId]);

// Delete
$affected = $db->delete('requests', 'id = ?', [$requestId]);

// Transactions
$db->beginTransaction();
try {
    // Multiple operations
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}
```

#### Security Patterns
All inputs must be sanitized:

```php
// Use helper functions
$email = postSafe('email', '', 100);
$name = getSafe('name', '', 50);

// Or sanitize via Security class
$security = Security::getInstance();
$email = $security->sanitizeEmail($email);
```

CSRF protection on all forms:

```php
requireCsrf();
```

Output escaping:

```php
echo e($variable);  // htmlspecialchars wrapper
```

#### Page Structure
Pages follow this pattern:

```php
<?php
$pageTitle = 'Page Title';
$errors = [];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // Validate and process
    // ...

    redirectWith('/?page=some-page', 'success', 'Message');
}

// GET request - render page
require_once INCLUDES_PATH . '/header.php';
?>
<!-- HTML content -->
<?php require_once INCLUDES_PATH . '/footer.php'; ?>
```

#### Helper Functions
Use helper functions from `includes/functions.php`:

```php
// Database shortcut
$db = db();

// User helpers
isLoggedIn()
userId()
userRole()
currentUser()
hasRole(ROLE_APPROVER)
isAdmin()

// Input helpers
post('key')              // Raw POST
postSafe('key', '', 50)  // Sanitized POST with length limit
get('key')               // Raw GET
getSafe('key', '', 50)   // Sanitized GET with length limit

// Redirects
redirect('/?page=some-page')
redirectWith('/?page=some-page', 'success', 'Message')

// Flash messages
setFlashMessage('Message', 'success')
$flash = getFlash()
```

### Frontend (Vue 3 + TypeScript)

#### Component Pattern
Use Composition API with `<script setup>`:

```vue
<template>
  <div class="component">
    <!-- Template content -->
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

// Props
interface Props {
  title: string
  count?: number
}
const props = withDefaults(defineProps<Props>(), {
  count: 0
})

// Emits
const emit = defineEmits<{
  update: [value: string]
  delete: [id: number]
}>()

// State
const isLoading = ref(false)
const items = ref<Item[]>([])

// Computed
const total = computed(() => items.value.length)

// Methods
const fetchData = async () => {
  isLoading.value = true
  try {
    // API call
  } finally {
    isLoading.value = false
  }
}

// Lifecycle
onMounted(() => {
  fetchData()
})
</script>

<style scoped>
.component {
  /* Scoped styles */
}
</style>
```

#### API Calls
Use the centralized API client:

```javascript
import api from '@/api'

// GET request
const data = await api.get('/endpoint')

// POST request
const result = await api.post('/endpoint', { data })

// PUT request
const result = await api.put('/endpoint/123', { data })

// DELETE request
await api.delete('/endpoint/123')
```

Use composables for reusable logic:

```javascript
import { useApi } from '@/composables/useApi'

const { isLoading, error, execute } = useApi()

const fetchData = async () => {
  await execute(api.getSomeData, param1, param2)
}
```

#### State Management (Pinia)
Use Pinia stores for global state:

```javascript
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useFeatureStore = defineStore('feature', () => {
  // State
  const items = ref([])
  const isLoading = ref(false)

  // Computed
  const count = computed(() => items.value.length)

  // Actions
  const fetchItems = async () => {
    isLoading.value = true
    try {
      items.value = await api.getItems()
    } finally {
      isLoading.value = false
    }
  }

  return {
    items,
    isLoading,
    count,
    fetchItems,
  }
})
```

#### Styling
Use TailwindCSS utility classes:

```vue
<template>
  <div class="bg-white rounded-lg shadow p-6">
    <button class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700">
      Button
    </button>
  </div>
</template>
```

Use DaisyUI components where appropriate (configured in `tailwind.config.js`).

---

## Configuration

### Environment Variables
Copy `.env.example` to `.env` and configure:

```bash
# Application
APP_ENV=development|production
APP_DEBUG=true|false
APP_URL=http://localhost:8080

# Database
DB_HOST=localhost
DB_NAME=loka_fleet
DB_USER=loka_user
DB_PASSWORD=secret

# Mail
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
```

**Critical:** In production, all sensitive values MUST be environment variables. The application will die if required env vars are missing in production mode.

### Database Configuration
Database credentials are loaded from environment variables in `config/database.php`.

Supported variable names (checked in order):
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- OR `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### URL Configuration
Set `APP_URL` in `.env` for the base URL:
- Development: `http://localhost:8080` or `/projects/LOKA`
- Production: Full domain URL (e.g., `https://example.com`)

---

## Important Gotchas

### PHP
1. **Always use Database singleton** - Never create new PDO instances directly
2. **Validate table names** - The Database class has a whitelist in `ALLOWED_TABLES` constant. Only these tables can be accessed via `insert()`, `update()`, `delete()`
3. **CSRF on all POST** - Call `requireCsrf()` at the top of every POST handler
4. **Sanitize all inputs** - Use `postSafe()` and `getSafe()` helpers, never use raw `$_POST` or `$_GET`
5. **Transaction safety** - Always wrap multi-step operations in transactions with proper try/catch/rollback
6. **Password hashing** - Use `$auth->hashPassword()` method, never plain `password_hash()`
7. **Session handling** - Use helper functions, never manipulate `$_SESSION` directly
8. **Date formatting** - Use `date(DATETIME_FORMAT)` for database timestamps
9. **Role-based access** - Always check permissions with `requireRole()`, `hasRole()`, or similar helpers
10. **Audit logging** - Call `auditLog('action', 'entity', $id)` for important operations

### Frontend
1. **Use Composition API** - All components must use `<script setup>` syntax
2. **TypeScript** - All new code should be typed with TypeScript interfaces
3. **Single file components** - Keep all component logic in the `.vue` file
4. **Component naming** - Use PascalCase for component filenames (e.g., `UserForm.vue`)
5. **Props validation** - Always define props with types and validation
6. **Error handling** - Use try/catch with error state in composables
7. **API calls** - Use the centralized api client, not axios directly
8. **Loading states** - Always show loading states during async operations
9. **Responsive design** - Use Tailwind's responsive prefixes (`md:`, `lg:`) for mobile-first design
10. **Avoid $refs** - Use reactive state instead of template refs when possible

### Routing
1. **Backend routing** - All routing goes through `index.php` with `?page=...` query parameter
2. **Frontend routing** - Vue Router handles client-side routing with history mode
3. **Public pages** - Add to `$publicPages` array in `index.php` for no-auth routes
4. **Page files** - Follow pattern: `pages/{module}/{action}.php`

### Security
1. **Never expose secrets** - Never commit `.env` file, passwords, or API keys
2. **SQL injection prevention** - Always use prepared statements with parameter binding
3. **XSS prevention** - Always use `e()` function for output in templates
4. **Rate limiting** - The system has built-in rate limiting - don't disable it
5. **Password policy** - Passwords must meet requirements defined in `config/security.php`
6. **HTTPS required in production** - Application will die if not HTTPS in production mode

### Database
1. **Table whitelist** - Only tables in `Database::ALLOWED_TABLES` can be modified via ORM methods
2. **Soft deletes** - Use `db()->softDelete()` instead of hard deletes when preserving data
3. **Datetime format** - Always use `DATETIME_FORMAT` constant for timestamps
4. **Foreign keys** - Respect foreign key constraints in database schema
5. **Indexing** - Queries with `ORDER BY`, `WHERE`, or JOIN should have proper indexes

### Email System
1. **Always use EmailQueue** - Never send emails directly in request handlers
2. **Queue critical emails** - Use `EmailQueue::queue()` for all emails
3. **Template system** - Use `queueTemplate()` method with template keys from `MAIL_TEMPLATES`
4. **Cron job required** - Email queue processor must run every 2 minutes via cron
5. **Control No. in subject** - Pass `requestId` to include "Control No. XXX:" in subject

### Testing
1. **PHPUnit tests** - Located in `tests/Unit` and `tests/Feature` directories
2. **Vitest tests** - Frontend unit tests alongside components
3. **Playwright E2E** - Browser automation tests in `e2e/` directory
4. **Test coverage** - Run `composer test:coverage` or `npm run test:coverage`
5. **Always test after changes** - Run relevant tests before committing

---

## User Roles & Permissions

### Role Hierarchy (from least to most privileged)
1. `requester` - Can create requests, view own data
2. `guard` - Can record vehicle dispatch/arrival
3. `approver` - Can approve department requests, manage vehicles/drivers
4. `motorpool_head` - Final approval, assign vehicles/drivers
5. `admin` - Full system access

### Permission Checking
```php
// Check minimum role level
if (hasRole(ROLE_APPROVER)) {
    // User is approver or higher
}

// Check exact role
if (isAdmin()) {
    // User is admin
}

// Require specific role (redirects if not)
requireRole(ROLE_ADMIN);

// Allow multiple roles
requireRole([ROLE_APPROVER, ROLE_ADMIN]);
```

### Frontend Permission Checking
```javascript
const authStore = useAuthStore()

// Check role
if (authStore.hasRole('admin')) {
  // Show admin UI
}

// Check multiple roles
if (authStore.hasAnyRole(['admin', 'motorpool_head'])) {
  // Show UI
}
```

---

## Common Patterns

### CRUD Operations Pattern

#### PHP Backend (Create/Update Form)
```php
<?php
$pageTitle = $action === 'edit' ? 'Edit Item' : 'Create Item';
$errors = [];
$item = null;

// Get item for edit
if ($action === 'edit') {
    $id = (int) get('id');
    $item = db()->fetch("SELECT * FROM table WHERE id = ?", [$id]);
    if (!$item) {
        redirectWith('/?page=items', 'danger', 'Item not found.');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $name = postSafe('name', '', 100);
    $description = postSafe('description', '', 500);

    // Validation
    if (empty($name)) {
        $errors[] = 'Name is required';
    }

    if (empty($errors)) {
        $data = [
            'name' => $name,
            'description' => $description,
            'updated_at' => date(DATETIME_FORMAT)
        ];

        try {
            if ($action === 'create') {
                $data['created_at'] = date(DATETIME_FORMAT);
                db()->insert('table', $data);
                redirectWith('/?page=items', 'success', 'Item created successfully.');
            } else {
                db()->update('table', $data, 'id = ?', [$id]);
                redirectWith('/?page=items', 'success', 'Item updated successfully.');
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
```

#### PHP Backend (Delete)
```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $id = (int) post('id');

    $item = db()->fetch("SELECT * FROM table WHERE id = ?", [$id]);
    if (!$item) {
        jsonResponse(false, ['error' => 'Item not found'], 'Item not found', 404);
    }

    try {
        db()->delete('table', 'id = ?', [$id]);
        auditLog('delete', 'table', $id);
        jsonResponse(true, ['message' => 'Item deleted successfully']);
    } catch (Exception $e) {
        jsonResponse(false, ['error' => $e->getMessage()], 'Failed to delete item', 500);
    }
}
```

#### Frontend (List with Table)
```vue
<template>
  <div class="page">
    <h1>{{ pageTitle }}</h1>

    <!-- DataTable component -->
    <DataTable
      :columns="columns"
      :data="items"
      :loading="isLoading"
      @sort="handleSort"
    >
      <template #actions="{ item }">
        <BaseButton
          variant="secondary"
          size="sm"
          @click="editItem(item.id)"
        >
          Edit
        </BaseButton>
        <BaseButton
          variant="danger"
          size="sm"
          @click="deleteItem(item.id)"
        >
          Delete
        </BaseButton>
      </template>
    </DataTable>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { DataTable, BaseButton } from '@/components'
import { useApi } from '@/composables/useApi'

const { isLoading, execute } = useApi()
const items = ref<Item[]>([])

const columns = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'name', label: 'Name', sortable: true },
  { key: 'created_at', label: 'Created', sortable: true },
  { key: 'actions', label: 'Actions' },
]

const fetchItems = async () => {
  await execute(api.getItems)
  items.value = data.value || []
}

const editItem = (id: number) => {
  router.push(`/?page=items&action=edit&id=${id}`)
}

const deleteItem = async (id: number) => {
  if (!confirm('Are you sure?')) return

  try {
    await api.deleteItem(id)
    fetchItems()
  } catch (error) {
    // Handle error
  }
}

onMounted(fetchItems)
</script>
```

---

## Email Queue System

### Architecture
Emails are queued asynchronously and processed by a cron job to prevent application blocking.

### Queueing Emails
```php
$queue = new EmailQueue();

// Simple queue
$queue->queue(
    $email,           // Recipient email
    $subject,         // Subject
    $body,            // HTML body
    $toName,          // Recipient name (optional)
    $template,        // Template key (optional)
    $priority,        // Priority 1-10 (lower = higher)
    $scheduledAt,     // Scheduled datetime (optional)
    $requestId        // Request ID for Control No. (optional)
);

// Using template
$queue->queueTemplate(
    'request_approved',  // Template key
    [
        'message' => 'Your request has been approved',
        'link' => '/?page=requests&action=view&id=123',
        'link_text' => 'View Request'
    ],
    $toName,
    5,                   // Priority
    123                  // Request ID
);
```

### Email Templates
Templates are defined in `config/notifications.php`:
```php
MAIL_TEMPLATES = [
    'request_approved' => [
        'subject' => 'Request Approved',
        'body' => '...'
    ],
    // ... more templates
];
```

### Cron Job Setup
Add to crontab:
```bash
*/2 * * * * php /path/to/public_html/cron/process_queue.php
```

---

## Approval Workflow

### Workflow States
1. `draft` - Request created, not submitted
2. `pending` - Awaiting department approval
3. `pending_motorpool` - Awaiting motorpool approval
4. `approved` - Fully approved, ready for assignment
5. `rejected` - Rejected by approver
6. `revision` - Sent back for revision
7. `cancelled` - Cancelled by requester
8. `completed` - Trip completed

### Processing Approvals
```php
// Approve request
public function approve(int $requestId, string $comments = ''): void
{
    db()->beginTransaction();
    try {
        // Update request status
        db()->update('requests', [
            'status' => STATUS_APPROVED,
            'approved_at' => date(DATETIME_FORMAT),
            'approver_comments' => $comments
        ], 'id = ?', [$requestId]);

        // Log approval
        db()->insert('approval_workflow', [
            'request_id' => $requestId,
            'approver_id' => userId(),
            'action' => APPROVAL_ACTION_APPROVE,
            'comments' => $comments,
            'created_at' => date(DATETIME_FORMAT)
        ]);

        // Send notification
        sendApprovalNotification($requestId);

        db()->commit();
    } catch (Exception $e) {
        db()->rollback();
        throw $e;
    }
}
```

---

## Audit Logging

All important actions must be logged for audit trail:

```php
auditLog('action', 'entity_type', $entity_id);

// Examples
auditLog('create', 'request', $requestId);
auditLog('update', 'user', $userId);
auditLog('delete', 'vehicle', $vehicleId);
auditLog('login', 'user', $userId);
auditLog('logout', 'user', $userId);
auditLog('approve', 'request', $requestId);
auditLog('password_reset', 'user', $userId);
```

Audit logs are stored in the `audit_logs` table with:
- User ID
- Action performed
- Entity type and ID
- IP address
- User agent
- Timestamp

---

## Deployment Notes

### File Structure
- `public_html/` is the web root
- All other files outside `public_html/` are outside web root for security

### Cron Jobs
Required cron job for email queue:
```bash
*/2 * * * * php /var/www/loka/cron/process_queue.php
```

### Permissions
Web server needs write access to:
- `logs/` directory
- `cache/` directory
- `sessions/` directory

### Environment Detection
Production is detected when:
- `APP_ENV=production` in environment
- OR HTTPS is enabled and not on localhost

### Build Artifacts
Frontend builds to `assets/dist/` directory. Always run `npm run build` before deploying.

---

## Troubleshooting

### Common Issues

**Database Connection Errors**
- Check `.env` file exists and is configured
- Verify database credentials are correct
- Ensure MySQL/MariaDB service is running

**Emails Not Sending**
- Verify cron job is running: `crontab -l`
- Check email queue table: `SELECT * FROM email_queue WHERE status = 'pending'`
- Test SMTP configuration: `php cron/test_email_config.php`
- Check logs: `tail -f logs/email_queue.log`

**403 Forbidden on Classes/Config**
- This is expected - `.htaccess` blocks direct access
- Files should only be included via PHP

**Session Issues**
- Check `sessions/` directory permissions
- Verify `session.php` is loaded after classes
- Check session timeout in `config/security.php`

**Rate Limiting Blocking**
- Check `rate_limits` table for blocked IPs/users
- Clear rate limits if needed during development
- Adjust limits in `config/security.php`

**Frontend Build Fails**
- Delete `node_modules/` and `package-lock.json`, run `npm install`
- Check Node.js version: `node --version` (should be >= 22)
- Check TypeScript errors: `npm run type-check`

---

## Additional Resources

### Documentation Files
- `README.md` - General overview and setup
- `DEPLOYMENT_CHECKLIST.md` - Deployment steps
- `docs/DATABASE.md` - Database schema
- `docs/SECURITY.md` - Security features
- `docs/APPROVAL_WORKFLOW_GUIDE.md` - Workflow details

### Configuration Files
- `config/constants.php` - All application constants
- `config/security.php` - Security settings
- `config/database.php` - Database configuration
- `config/mail.php` - Email configuration

### Key Classes
- `Database.php` - Database operations
- `Auth.php` - Authentication & authorization
- `Security.php` - Security functions & rate limiting
- `EmailQueue.php` - Email queue management
- `NotificationService.php` - In-app notifications

### Helper Files
- `includes/functions.php` - Global helper functions

---

## Development Workflow

1. **Make changes** to PHP or Vue files
2. **Test thoroughly**:
   - PHP: `composer test`
   - JS: `npm run test`
3. **Lint code**:
   - PHP: `composer cs-fix:all`
   - JS: `npm run lint:fix && npm run format`
4. **Type check**:
   - PHP: `composer analyze`
   - JS: `npm run type-check`
5. **Build frontend** (if changes): `npm run build`
6. **Run E2E tests** (if UI changes): `npm run test:e2e`

---

## Notes for AI Agents

- **Always read existing code** before making changes to understand patterns
- **Follow existing conventions** - don't introduce new patterns without reason
- **Use helper functions** - don't reinvent functionality that already exists
- **Security first** - never bypass security measures
- **Test everything** - run relevant tests after any change
- **Commit small, focused changes** - make code review easier
- **Document complex logic** - add comments explaining "why", not "what"
- **Keep it simple** - prefer straightforward solutions over clever ones
- **Handle errors gracefully** - never expose stack traces to users
- **Use transactions** for multi-step database operations

---

**Last Updated:** 2025
**Application Version:** 2.5.1
