# Trip Tickets Access Summary

## Overview
Trip tickets are **NOT** exclusive to drivers. The following roles can **view and generate** trip tickets:

1. **Drivers** - Can view their own trip tickets via "My Trip Tickets" and generate tickets for their assigned trips
2. **Department Approvers (Approvers)** - Can view all trip tickets and generate tickets for any completed trip
3. **Motorpool Head** - Can view all trip tickets and generate tickets for any completed trip

## Changes Made to Emphasize Multi-Role Access

### 1. Sidebar Navigation (`includes/sidebar.php`)

**Before:** Had duplicate/confusing menu items
- "Trip Tickets" for approvers
- "Review Trip Tickets" for motorpool/admin

**After:** Consolidated into single menu item
- **"Trip Tickets"** - Shows for Drivers, Approvers, and Motorpool Head
- Displays pending count badge for approvers/motorpool

```php
<!-- Trip Tickets (Drivers, Approvers, and Motorpool Head) -->
<?php if (isDriver() || isApprover()): ?>
<li class="nav-item">
    <a class="nav-link <?= activeMenu('trip-tickets') ?>" href="<?= APP_URL ?>/?page=trip-tickets">
        <i class="bi bi-file-earmark-text"></i>
        <span>Trip Tickets</span>
        <!-- Pending count for approvers -->
    </a>
</li>
<?php endif; ?>
```

### 2. Trip Tickets Index Page (`pages/trip-tickets/index.php`)

**Updated Documentation:**
```php
/**
 * LOKA - Trip Tickets Page
 *
 * Dedicated page for drivers, department approvers, and motorpool head
 * to view and manage trip tickets for completed trips
 *
 * Access: Drivers, Department Approvers, Motorpool Head
 */
```

**Updated Page Header:**
```html
<h1>Trip Tickets</h1>
<p>View and manage trip tickets - accessible to Drivers, Department Approvers, and Motorpool Head</p>
```

**Updated Button Labels:**
- "Create Trip Ticket" → **"Generate Trip Ticket"**
- Modal title: "Create Trip Ticket" → **"Generate Trip Ticket"**
- Submit button: "Create Ticket" → **"Generate Trip Ticket"**

### 3. Trip Ticket Create Form (`pages/trip-tickets/create.php`)

**Updated Documentation:**
```php
/**
 * LOKA - Create Trip Ticket Form
 *
 * Pre-filled form for creating trip ticket after trip completion
 *
 * Access: Drivers, Department Approvers, Motorpool Head, Guards, Admins
 * - Drivers: Can only create tickets for their own assigned trips
 * - Approvers/Motorpool Head: Can create tickets for any completed trip
 */
```

**Updated Permission Check:**
```php
// OLD (WRONG):
if (!isDriver() && !isGuard() && !isAdmin()) { ... }

// NEW (CORRECT):
if (!isDriver() && !isGuard() && !isApprover()) { ... }
```

**Updated Page Header:**
```html
<h1>
    <?php echo isDriver() ? 'My Trip Ticket' : 'Generate Trip Ticket'; ?>
</h1>
<p>Document completed trip details - Accessible to Drivers, Department Approvers, and Motorpool Head</p>
```

**Updated Submit Button:**
```php
<button type="submit" class="btn btn-success">
    <?php echo isDriver() ? 'Submit Trip Ticket' : 'Generate Trip Ticket'; ?>
</button>
```

### 4. Bug Fixes

#### Fix 1: Permission Check in `create.php`
- **Issue:** Approvers were blocked from creating trip tickets
- **Fix:** Added `!isApprover()` to the allowed roles

#### Fix 2: Logic Error in `index.php` create action
- **Issue:** Trip tickets were only created when there WERE errors (inverted logic)
- **Fix:** Changed condition to `if (empty($errors))`

#### Fix 3: Missing driver_id in modal form
- **Issue:** The modal queried driver_id but didn't submit it
- **Fix:**
  - Added `data-driver-id` attribute to request options
  - Added hidden input `<input type="hidden" name="driver_id" id="driverIdInput">`
  - Added JavaScript function `populateRequestData()` to populate driver_id on selection

#### Fix 4: Redirect Logic
- **Issue:** Redirects only checked for drivers and defaulted to guard
- **Fix:** Updated all redirect statements to properly handle drivers, guards, and approvers

## Page Structure

| Page | Purpose | Access |
|------|---------|--------|
| `my-trip-tickets` | View own tickets | **Drivers only** |
| `trip-tickets` | View and manage all tickets, generate new ones | **Drivers, Approvers, Motorpool Head** |

## Workflow

### For Drivers:
1. Complete a trip
2. Guard records arrival → redirects to create trip ticket form
3. Driver fills out trip ticket details for their own trip
4. Driver submits trip ticket
5. View via "My Trip Tickets" menu

### For Approvers/Motorpool Head:
1. Navigate to "Trip Tickets" menu
2. View all tickets across all drivers
3. Click "Generate Trip Ticket" button
4. Select any completed trip without a ticket
5. Fill out trip ticket details
6. Submit trip ticket

## Key Points

1. ✅ **Trip tickets are NOT exclusive to drivers**
2. ✅ **Approvers and Motorpool Head can generate tickets for ANY completed trip**
3. ✅ **Drivers are restricted to their own trips only**
4. ✅ **All three roles can view trip tickets via the main Trip Tickets page**
5. ✅ **Drivers have an additional "My Trip Tickets" page for personal view**

---

**Last Updated:** March 10, 2026
