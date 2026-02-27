# LOKA System Improvements v1.0

**Document Version:** 1.0  
**Date:** 2026-02-27  
**Status:** Ready for Implementation  
**Database Impact:** Minimal (non-destructive) - tested on localhost without altering production data

---

## 1. Overview

This document outlines the implementation plan for the end-user requested improvements to the LOKA Fleet Management System. All changes are designed to be backward-compatible and deployable to localhost for testing before production deployment.

---

## 2. Requirements Summary

| # | Feature | Priority | Database Change | Impact |
|---|---------|----------|-----------------|--------|
| 1 | Driver Trip History View | High | No | Driver role |
| 2 | Mileage Tracking (Before/After Trip) | High | Yes - new columns | All roles |
| 3 | Guard Completed Trips View | Medium | No | Guard role |
| 4 | Guard Travel Order / OB Slip Checkbox | Medium | Yes - new columns | Guard role |
| 5 | Requester Cancel at Any Stage | High | No | Requester role |
| 6 | Approver Vehicle Display in Approval | Medium | No | Approver role |
| 7 | Mobile-First Dashboard | High | No | All roles |
| 8 | Vehicle Type CRUD Module | High | No | Admin role |

---

## 3. Database Schema Changes

### 3.1 New Columns for Mileage Tracking

Add to `requests` table:

```sql
ALTER TABLE requests 
ADD COLUMN mileage_start INT UNSIGNED NULL AFTER notes,
ADD COLUMN mileage_end INT UNSIGNED NULL AFTER mileage_start,
ADD COLUMN mileage_actual INT UNSIGNED NULL AFTER mileage_end;
```

**Description:**
- `mileage_start`: Odometer reading at trip start (input by Motorpool)
- `mileage_end`: Odometer reading at trip completion (input by Guard/Driver)
- `mileage_actual`: Calculated (mileage_end - mileage_start)

### 3.2 New Columns for Travel Order / OB Slip

Add to `requests` table:

```sql
ALTER TABLE requests 
ADD COLUMN has_travel_order TINYINT(1) NOT NULL DEFAULT 0 AFTER mileage_actual,
ADD COLUMN has_official_business_slip TINYINT(1) NOT NULL DEFAULT 0 AFTER has_travel_order,
ADD COLUMN travel_order_number VARCHAR(50) NULL AFTER has_official_business_slip,
ADD COLUMN ob_slip_number VARCHAR(50) NULL AFTER travel_order_number;
```

**Description:**
- `has_travel_order`: 1 if travel order present, 0 otherwise
- `has_official_business_slip`: 1 if OB slip present, 0 otherwise
- `travel_order_number`: Reference number for travel order
- `ob_slip_number`: Reference number for OB slip

### 3.3 Verify vehicle_types Table Exists

```sql
-- Ensure vehicle_types table exists
CREATE TABLE IF NOT EXISTS vehicle_types (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    passenger_capacity INT NOT NULL DEFAULT 4,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
);
```

---

## 4. Feature Implementation Details

### 4.1 Driver Trip History View

**Purpose:** Allow drivers to view their trip histories for trip ticket/manual form purposes.

**User Story:** "As a driver, I want to see my trip history so I can use it as basis for creating manual trip tickets."

**Implementation:**

**Page URL:** `?page=drivers&action=trips`

**Query Logic:**
```php
$driverId = getDriverIdFromUserId($_SESSION['user_id']);

$sql = "SELECT r.*, v.plate_number, v.make, v.model, d.name as department_name
        FROM requests r
        LEFT JOIN vehicles v ON r.vehicle_id = v.id
        LEFT JOIN departments d ON r.department_id = d.id
        WHERE (r.driver_id = ? OR r.requested_driver_id = ?)
        AND r.deleted_at IS NULL
        ORDER BY r.start_datetime DESC";
```

**Display Fields:**
| Field | Description |
|-------|-------------|
| Trip ID | Request ID |
| Date | Start datetime |
| Destination | Trip destination |
| Purpose | Trip purpose |
| Vehicle | Plate number, make, model |
| Mileage | Start - End (actual) |
| Status | Pending/Approved/Completed/Cancelled |

**Features:**
- Filter by date range
- Filter by status
- Print/Export to PDF for manual trip ticket
- Show all trip statuses

**Files to Create/Modify:**
- `pages/my-trips/index.php` - Already exists, enhance with print option
- Create new if not exists: `pages/drivers/trips.php`
- `api/drivers.php` - add endpoint for driver trips

---

### 4.2 Mileage Tracking (Before/After Trip)

**Purpose:** Track vehicle mileage for each trip to monitor vehicle wear and fuel efficiency.

**User Story:** "As a motorpool head, I want to track the mileage before and after each trip to monitor vehicle condition."

**Workflow:**

| Stage | Actor | Action | Field | Visibility |
|-------|-------|--------|-------|------------|
| 1. Assignment | Motorpool | Input starting mileage | `mileage_start` | Editable |
| 2. Checkout | Guard | Verify/confirm mileage | `mileage_start` | Read-only |
| 3. Checkin | Guard | Input ending mileage | `mileage_end` | Editable |
| 4. Complete | System | Auto-calculate | `mileage_actual` | Auto |
| 5. Update | System | Update vehicle mileage | `vehicles.mileage` | Auto |

**Validation Rules:**
```php
// When setting mileage_start
if ($mileage_start < $vehicle->mileage) {
    throw new Exception('Starting mileage cannot be less than vehicle current mileage');
}

// When setting mileage_end
if ($mileage_end < $mileage_start) {
    throw new Exception('Ending mileage must be greater than or equal to starting mileage');
}

// Auto-calculate
$mileage_actual = $mileage_end - $mileage_start;

// After trip completion, update vehicle mileage
$vehicleModel->update($vehicle_id, ['mileage' => $mileage_end]);
```

**UI Implementation:**

**In Request View (Motorpool Stage):**
```html
<div class="row">
    <div class="col-md-4">
        <label class="form-label">Starting Mileage</label>
        <input type="number" name="mileage_start" class="form-control" 
               value="<?= $request->mileage_start ?? '' ?>" 
               min="<?= $vehicle->mileage ?>" required>
        <small class="text-muted">Current: <?= $vehicle->mileage ?> km</small>
    </div>
</div>
```

**In Request View (Guard Checkin):**
```html
<div class="row">
    <div class="col-md-4">
        <label class="form-label">Ending Mileage</label>
        <input type="number" name="mileage_end" class="form-control" 
               value="<?= $request->mileage_end ?? '' ?>" 
               min="<?= $request->mileage_start ?>" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Actual Distance</label>
        <input type="text" class="form-control" 
               value="<?= $request->mileage_actual ? $request->mileage_actual . ' km' : 'Pending' ?>" 
               readonly>
    </div>
</div>
```

**Files to Modify:**
- `pages/requests/view.php` - add mileage fields based on stage
- `pages/approvals/process.php` - add mileage input for motorpool
- `pages/guard/actions.php` - add mileage input for checkin
- `api/requests.php` - add mileage update endpoint
- `classes/Request.php` - add mileage methods

---

### 4.3 Guard Completed Trips View

**Purpose:** Allow guards to view completed trips for reference and audit.

**User Story:** "As a guard, I want to see all completed trips for reference during vehicle check-in/check-out."

**Page URL:** `?page=guards&action=completed`

**Query:**
```php
$sql = "SELECT r.*, v.plate_number, v.make, v.model, 
               u.name as requester_name, d.name as department_name,
               dr.user_id as driver_user_id
        FROM requests r
        LEFT JOIN vehicles v ON r.vehicle_id = v.id
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN departments d ON r.department_id = d.id
        LEFT JOIN drivers dr ON r.driver_id = dr.id
        WHERE r.status = 'completed'
        AND r.deleted_at IS NULL
        ORDER BY r.end_datetime DESC";
```

**Display Fields:**
| Field | Description |
|-------|-------------|
| Trip ID | Request ID |
| Date | Trip date/time |
| Requester | Name + Department |
| Driver | Assigned driver |
| Vehicle | Plate number |
| Destination | Trip destination |
| Mileage | Actual distance traveled |
| Documents | Travel Order / OB Slip status |

**Features:**
- Filter by date range
- Search by plate number, requester, driver
- View trip details
- Export to CSV

**Files to Create/Modify:**
- `pages/guard/completed.php` - new file
- `includes/sidebar.php` - add menu item for Guard role

---

### 4.4 Guard Travel Order / OB Slip Checkbox

**Purpose:** Allow guards to indicate presence of Approved Travel Order or Official Business Slip during checkout.

**User Story:** "As a guard, I want to tick a checkbox to indicate if there's an Approved Travel Order or OB Slip for documentation purposes."

**Workflow:**

**Stage 1: Motorpool Approval (Optional)**
- Approver can mark if travel documents are required
- These fields can be pre-checked or left for guard to determine

**Stage 2: Guard Checkout**
- Guard sees checkboxes for Travel Order and OB Slip
- Guard can check/uncheck based on actual documents presented
- Optional: Input document reference numbers

**UI Implementation:**

```html
<div class="card mt-3">
    <div class="card-header">
        <h6 class="mb-0">Travel Documents</h6>
    </div>
    <div class="card-body">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="has_travel_order" 
                   id="has_travel_order" value="1"
                   <?= $request->has_travel_order ? 'checked' : '' ?>>
            <label class="form-check-label" for="has_travel_order">
                Approved Travel Order Present
            </label>
            <input type="text" name="travel_order_number" class="form-control form-control-sm mt-1" 
                   placeholder="Travel Order No." value="<?= $request->travel_order_number ?>">
        </div>
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="has_official_business_slip" 
                   id="has_official_business_slip" value="1"
                   <?= $request->has_official_business_slip ? 'checked' : '' ?>>
            <label class="form-check-label" for="has_official_business_slip">
                Approved Official Business Slip Present
            </label>
            <input type="text" name="ob_slip_number" class="form-control form-control-sm mt-1" 
                   placeholder="OB Slip No." value="<?= $request->ob_slip_number ?>">
        </div>
    </div>
</div>
```

**Files to Modify:**
- `pages/requests/view.php` - add document section
- `pages/guard/checkout.php` - add checkbox controls
- `pages/approvals/process.php` - allow motorpool to set requirement
- `api/requests.php` - add document update endpoint

---

### 4.5 Requester Cancel at Any Stage

**Purpose:** Allow requesters to cancel their request at any approval stage, freeing up the vehicle for others.

**User Story:** "As a requester, I want to cancel my request at any time so the vehicle can be used by others."

**Implementation:**

**UI - Cancel Button:**
```html
<!-- Only show for request owner, not completed/cancelled -->
<?php if (isRequestOwner($request->id, $_SESSION['user_id']) 
    && !in_array($request->status, ['completed', 'cancelled'])): ?>
<button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelRequestModal">
    <i class="bi bi-x-circle"></i> Cancel Request
</button>
<?php endif; ?>
```

**Confirmation Modal:**
```html
<div class="modal fade" id="cancelRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Request?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this request?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    This action cannot be undone. The vehicle will be freed for others to use.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Request</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">Yes, Cancel</button>
            </div>
        </div>
    </div>
</div>
```

**JavaScript (AJAX Cancel):**
```javascript
document.getElementById('confirmCancelBtn').addEventListener('click', function() {
    const requestId = <?= $request->id ?>;
    
    fetch(APP_URL + '/?page=api&action=cancel_request&id=' + requestId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to cancel request');
        }
    });
});
```

**Backend Logic (API/Class):**
```php
public function cancelRequest($requestId, $userId) {
    // Get request
    $request = $this->getById($requestId);
    
    // Verify ownership
    if ($request->user_id != $userId) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }
    
    // Allow cancel at any stage except completed
    if ($request->status === 'completed') {
        return ['success' => false, 'message' => 'Cannot cancel completed trips'];
    }
    
    // Start transaction
    db()->beginTransaction();
    
    try {
        // Update request status
        db()->update('requests', [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$requestId]);
        
        // If vehicle was assigned, free it
        if ($request->vehicle_id) {
            db()->update('vehicles', [
                'status' => 'available',
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$request->vehicle_id]);
        }
        
        // If driver was assigned, free them
        if ($request->driver_id) {
            db()->update('drivers', [
                'status' => 'available',
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$request->driver_id]);
        }
        
        // Notify approvers
        $this->notifyApprovers($requestId, 'cancelled');
        
        db()->commit();
        
        return ['success' => true, 'message' => 'Request cancelled successfully'];
        
    } catch (Exception $e) {
        db()->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
```

**Files to Modify:**
- `pages/requests/view.php` - add cancel button and modal
- `api/requests.php` - add cancel endpoint
- `classes/Request.php` - add cancel method

---

### 4.6 Approver Vehicle Display in Approval View

**Purpose:** Show the actually requested vehicle when checking approvals, not just in notification details.

**User Story:** "As an approver, I want to see the vehicle details directly in the approval list so I can make informed decisions."

**Implementation:**

**In Approval List (pages/approvals/index.php):**

Current display needs to add vehicle column:
```html
<th>Vehicle</th>
...
<td>
    <?php if ($request->vehicle_id): ?>
        <?= htmlspecialchars($request->vehicle_plate) ?>
    <?php else: ?>
        <span class="text-muted">Not assigned</span>
    <?php endif; ?>
</td>
```

**SQL Query Enhancement:**
```sql
SELECT r.*, 
       v.plate_number as vehicle_plate,
       v.make as vehicle_make,
       v.model as vehicle_model,
       vt.name as vehicle_type
FROM requests r
LEFT JOIN vehicles v ON r.vehicle_id = v.id
LEFT JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
WHERE ...
```

**In Approval Detail View (pages/approvals/view.php):**

Add vehicle info card:
```html
<div class="card mt-3">
    <div class="card-header">
        <h6 class="mb-0">Vehicle Information</h6>
    </div>
    <div class="card-body">
        <?php if ($request->vehicle_id): ?>
        <table class="table table-borderless">
            <tr>
                <th>Plate Number:</th>
                <td><?= htmlspecialchars($request->vehicle_plate) ?></td>
            </tr>
            <tr>
                <th>Vehicle:</th>
                <td><?= htmlspecialchars($request->vehicle_make . ' ' . $request->vehicle_model) ?></td>
            </tr>
            <tr>
                <th>Type:</th>
                <td><?= htmlspecialchars($request->vehicle_type ?? 'N/A') ?></td>
            </tr>
        </table>
        <?php else: ?>
        <p class="text-muted">Vehicle will be assigned by Motorpool Head</p>
        <?php endif; ?>
    </div>
</div>
```

**Files to Modify:**
- `pages/approvals/index.php` - add vehicle column to table
- `pages/approvals/view.php` - add vehicle details card

---

### 4.7 Mobile-First Dashboard

**Purpose:** Fix navigation issues on mobile devices and make dashboard responsive.

**User Story:** "As a user, I want the dashboard to work properly on my phone so I can manage requests on the go."

**Current Issues:**
- Sidebar navigation doesn't work on mobile
- Hide navigation button not visible on small screens
- Tables overflow horizontally
- Touch targets too small

**Implementation:**

**Step 1: Header/Navigation Update (includes/header.php)**

Add mobile toggler:
```html
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <button class="navbar-toggler me-2" type="button" id="sidebarToggle">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="?page=dashboard">LOKA</a>
        <!-- User menu dropdown -->
    </div>
</nav>
```

**Step 2: Sidebar Update (includes/sidebar.php)**

Make sidebar responsive:
```php
// Add mobile-close class for mobile toggle
$sidebarClass = 'sidebar collapse navbar-collapse show';
if (!isMobile()) {
    $sidebarClass = 'sidebar';
}
```

**Step 3: CSS Updates (assets/css/style.css)**

```css
/* Mobile-first base styles */

/* Navigation toggler - always visible on mobile */
.navbar-toggler {
    display: flex !important;
}

/* Sidebar - slide-in on mobile */
@media (max-width: 991.98px) {
    .sidebar {
        position: fixed;
        top: 56px; /* Below navbar */
        left: 0;
        bottom: 0;
        width: 280px;
        z-index: 1050;
        transform: translateX(-100%);
        transition: transform 0.3s ease-in-out;
        overflow-y: auto;
        background: #212529;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    /* Overlay when sidebar is open */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1040;
    }
    
    .sidebar-overlay.show {
        display: block;
    }
}

/* Main content - adjust for fixed header */
@media (max-width: 991.98px) {
    body {
        padding-top: 56px;
    }
    
    .main-content {
        margin-left: 0 !important;
        padding: 1rem;
    }
}

/* Touch-friendly buttons */
@media (max-width: 768px) {
    .btn {
        min-height: 44px;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
    
    .btn-sm {
        min-height: 36px;
    }
    
    /* Form controls - larger touch targets */
    .form-control, .form-select {
        min-height: 44px;
    }
    
    /* Cards - full width on mobile */
    .dashboard-card, .card {
        margin-bottom: 1rem;
    }
    
    /* Stats cards - stack vertically */
    .stats-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    /* Table responsive wrapper */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border: 0;
    }
    
    /* Table font size */
    .table {
        font-size: 0.85rem;
    }
}

/* Hide navbar collapse on desktop */
@media (min-width: 992px) {
    .navbar-toggler {
        display: none !important;
    }
    
    .sidebar {
        transform: none !important;
    }
}
```

**Step 4: JavaScript Updates (assets/js/app.js)**

```javascript
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('show');
            
            // Create or toggle overlay
            let overlay = document.querySelector('.sidebar-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'sidebar-overlay';
                document.body.appendChild(overlay);
            }
            overlay.classList.toggle('show');
        });
        
        // Close sidebar when clicking overlay
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('sidebar-overlay')) {
                sidebar.classList.remove('show');
                e.target.classList.remove('show');
            }
        });
    }
    
    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            if (sidebar) sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        }
    });
});
```

**Files to Modify:**
- `includes/header.php` - mobile navigation HTML, toggler button
- `includes/sidebar.php` - responsive wrapper
- `assets/css/style.css` - mobile-first styles
- `assets/js/app.js` - mobile navigation JS

---

### 4.8 Vehicle Type CRUD Module

**Purpose:** Add a dedicated management module for vehicle types (e.g., Sedan, SUV, Van, Truck, etc.) which currently has no UI management interface.

**User Story:** "As an admin, I want to manage vehicle types through the UI so I don't need to edit the database directly."

**Current State:**
- `vehicle_types` table exists in database
- No admin UI to manage vehicle types
- Admins must directly edit database to add/modify vehicle types

**Implementation:**

**Page: List (pages/vehicle_types/index.php)**
```php
// Check admin role
requireRole('admin');

$vehicleTypes = db()->fetchAll("
    SELECT vt.*, 
           (SELECT COUNT(*) FROM vehicles v WHERE v.vehicle_type_id = vt.id AND v.deleted_at IS NULL) as vehicle_count
    FROM vehicle_types vt
    WHERE vt.deleted_at IS NULL
    ORDER BY vt.name
");
```

**Display Table:**
| Name | Description | Passenger Capacity | Vehicles Using | Actions |
|------|-------------|-------------------|----------------|---------|
| Sedan | 4-door sedan | 4 | 5 | Edit / Delete |

**Page: Create (pages/vehicle_types/create.php)**
```html
<form method="POST" action="?page=vehicle_types&action=store">
    <div class="mb-3">
        <label class="form-label">Name *</label>
        <input type="text" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">Passenger Capacity *</label>
        <input type="number" name="passenger_capacity" class="form-control" 
               min="1" max="50" value="4" required>
    </div>
    <button type="submit" class="btn btn-primary">Create Vehicle Type</button>
</form>
```

**Page: Edit (pages/vehicle_types/edit.php)**
```php
// GET: Display edit form
$id = getInt('id');
$vehicleType = db()->fetch("SELECT * FROM vehicle_types WHERE id = ?", [$id]);

// POST: Update
if (isPost()) {
    db()->update('vehicle_types', [
        'name' => post('name'),
        'description' => post('description'),
        'passenger_capacity' => postInt('passenger_capacity'),
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$id]);
    
    setFlashMessage('Vehicle type updated successfully', 'success');
    redirect('?page=vehicle_types');
}
```

**Page: Delete (pages/vehicle_types/delete.php)**
```php
// Check if vehicles are using this type
$vehicleCount = db()->fetch("
    SELECT COUNT(*) as cnt FROM vehicles 
    WHERE vehicle_type_id = ? AND deleted_at IS NULL
", [$id]);

if ($vehicleCount->cnt > 0) {
    setFlashMessage('Cannot delete: ' . $vehicleCount->cnt . ' vehicles are using this type', 'danger');
    redirect('?page=vehicle_types');
}

// Soft delete
db()->update('vehicle_types', [
    'deleted_at' => date('Y-m-d H:i:s')
], 'id = ?', [$id]);
```

**API Endpoints (api/vehicle_types.php):**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `?page=api&action=get_vehicle_types` | List all types |
| POST | `?page=api&action=create_vehicle_type` | Create new |
| POST | `?page=api&action=update_vehicle_type&id=` | Update |
| POST | `?page=api&action=delete_vehicle_type&id=` | Delete (soft) |

**Sidebar Menu Addition:**
```php
// In sidebar.php, add under Vehicles section
if (hasRole('admin')) {
    echo '<li><a href="?page=vehicle_types"><i class="bi bi-car-front"></i> Vehicle Types</a></li>';
}
```

**Files to Create:**
- `pages/vehicle_types/index.php` - List all vehicle types
- `pages/vehicle_types/create.php` - Add new type form
- `pages/vehicle_types/edit.php` - Edit type form
- `pages/vehicle_types/delete.php` - Delete action
- `api/vehicle_types.php` - CRUD API endpoints

**Files to Modify:**
- `includes/sidebar.php` - Add menu item

---

## 5. API Endpoint Specifications

### 5.1 Cancel Request

**Endpoint:** `POST /?page=api&action=cancel_request&id={request_id}`

**Response:**
```json
{
    "success": true,
    "message": "Request cancelled successfully"
}
```

### 5.2 Update Mileage

**Endpoint:** `POST /?page=api&action=update_mileage&id={request_id}`

**Request Body:**
```json
{
    "mileage_start": 15000,
    "mileage_end": 15150
}
```

### 5.3 Update Travel Documents

**Endpoint:** `POST /?page=api&action=update_documents&id={request_id}`

**Request Body:**
```json
{
    "has_travel_order": 1,
    "has_official_business_slip": 0,
    "travel_order_number": "TO-2026-001",
    "ob_slip_number": ""
}
```

### 5.4 Get Driver Trips

**Endpoint:** `GET /?page=api&action=get_driver_trips&driver_id={id}`

### 5.5 Vehicle Type CRUD

| Action | Method | Parameters |
|--------|--------|------------|
| List | GET | - |
| Create | POST | name, description, passenger_capacity |
| Update | POST | id, name, description, passenger_capacity |
| Delete | POST | id |

---

## 6. Testing Plan (Localhost)

### 6.1 Local Setup Instructions

1. **Copy production files to localhost:**
   ```bash
   cd C:\wamp64\www\Projects\loka2
   xcopy /E /I deploy-package\loka-deploy-20260223_220437\* public_html\
   ```

2. **Configure localhost database:**
   - Create database: `CREATE DATABASE fleet_management;`
   - Import production dump (already populated)
   - Update `.env` for localhost credentials:
     ```
     DB_HOST=localhost
     DB_NAME=fleet_management
     DB_USER=root
     DB_PASS=
     ```

3. **Run schema migration (for mileage and document fields):**
   ```sql
   ALTER TABLE requests 
   ADD COLUMN mileage_start INT UNSIGNED NULL,
   ADD COLUMN mileage_end INT UNSIGNED NULL,
   ADD COLUMN mileage_actual INT UNSIGNED NULL,
   ADD COLUMN has_travel_order TINYINT(1) NOT NULL DEFAULT 0,
   ADD COLUMN has_official_business_slip TINYINT(1) NOT NULL DEFAULT 0,
   ADD COLUMN travel_order_number VARCHAR(50) NULL,
   ADD COLUMN ob_slip_number VARCHAR(50) NULL;
   ```

4. **Test each feature:**
   - Use existing users from production
   - No database modifications needed for testing UI changes
   - Schema changes can be tested in separate test database

### 6.2 Feature Testing Checklist

| # | Feature | Test Case | Test User Role | Expected Result |
|---|---------|-----------|----------------|------------------|
| 1 | Driver Trip History | Login as driver, navigate to trips | Driver | See all assigned trips with details |
| 2 | Mileage Tracking | Complete approval workflow | Motorpool Head | Can input mileage_start and mileage_end |
| 3 | Guard Completed Trips | Login as guard | Guard | See completed trips list |
| 4 | Travel Order Checkbox | Guard checkout | Guard | Can check/uncheck checkbox |
| 5 | Cancel Request | Login as requester | Requester | Cancel at any stage works |
| 6 | Approver Vehicle | Login as approver | Approver | Vehicle shown in approval list |
| 7 | Mobile Dashboard | Open on phone | Any | Navigation works |
| 8 | Vehicle Type CRUD | Login as admin | Admin | Can add/edit/delete vehicle types |

### 6.3 Acceptance Criteria

**Feature 1 - Driver Trip History:**
- [ ] Driver can view list of all assigned trips
- [ ] Trips display date, destination, vehicle, mileage, status
- [ ] Filter by date range works
- [ ] Print/Export functionality works

**Feature 2 - Mileage Tracking:**
- [ ] Motorpool can input starting mileage
- [ ] System validates mileage >= vehicle current mileage
- [ ] Guard can input ending mileage
- [ ] System calculates actual mileage automatically
- [ ] Vehicle mileage updates after trip completion

**Feature 3 - Guard Completed Trips:**
- [ ] Guard can view completed trips
- [ ] Date range filter works
- [ ] Trip details are visible

**Feature 4 - Travel Documents:**
- [ ] Guard can check/uncheck Travel Order checkbox
- [ ] Guard can check/uncheck OB Slip checkbox
- [ ] Document reference numbers can be entered

**Feature 5 - Cancel Request:**
- [ ] Requester sees Cancel button on their requests
- [ ] Confirmation modal appears before cancel
- [ ] Cancelled request status updates to 'cancelled'
- [ ] Assigned vehicle is freed (status = 'available')
- [ ] Assigned driver is freed (status = 'available')
- [ ] Approvers are notified of cancellation

**Feature 6 - Approver Vehicle Display:**
- [ ] Vehicle column visible in approval list
- [ ] Vehicle details shown in approval detail view

**Feature 7 - Mobile Dashboard:**
- [ ] Navigation toggler visible on mobile
- [ ] Sidebar slides in/out on mobile
- [ ] Tables scroll horizontally on mobile
- [ ] Touch targets are adequately sized

**Feature 8 - Vehicle Type CRUD:**
- [ ] Admin can view list of vehicle types
- [ ] Admin can create new vehicle type
- [ ] Admin can edit existing vehicle type
- [ ] Admin cannot delete type if vehicles exist
- [ ] Menu item appears for admin role

---

## 7. File Changes Summary

### 7.1 New Files to Create

| File | Purpose | Feature |
|------|---------|---------|
| `pages/drivers/trips.php` | Driver trip history view | #1 |
| `pages/guards/completed.php` | Guard completed trips view | #3 |
| `pages/vehicle_types/index.php` | Vehicle type list | #8 |
| `pages/vehicle_types/create.php` | Add new vehicle type | #8 |
| `pages/vehicle_types/edit.php` | Edit vehicle type | #8 |
| `pages/vehicle_types/delete.php` | Delete vehicle type | #8 |
| `api/vehicle_types.php` | Vehicle type CRUD API | #8 |

### 7.2 Files to Modify

| File | Changes | Feature |
|------|---------|---------|
| `includes/header.php` | Mobile navigation toggler | #7 |
| `includes/sidebar.php` | Responsive menu, vehicle types menu | #7, #8 |
| `pages/requests/view.php` | Mileage fields, cancel button | #2, #5 |
| `pages/approvals/index.php` | Vehicle column in list | #6 |
| `pages/approvals/view.php` | Vehicle details | #6 |
| `pages/approvals/process.php` | Mileage input for motorpool | #2 |
| `pages/guard/actions.php` | Mileage input for checkin | #2 |
| `pages/guard/checkout.php` | Document checkboxes | #4 |
| `assets/css/style.css` | Mobile-first styles | #7 |
| `assets/js/app.js` | Mobile nav, cancel modal | #5, #7 |
| `api/requests.php` | Cancel, mileage, document endpoints | #2, #4, #5 |
| `classes/Request.php` | Cancel method, mileage methods | #2, #5 |

---

## 8. Deployment Steps

### 8.1 Localhost Testing

1. Copy files to localhost (from deploy-package)
2. Configure `.env` for localhost
3. Run schema migration:
   ```sql
   ALTER TABLE requests 
   ADD COLUMN mileage_start INT UNSIGNED NULL,
   ADD COLUMN mileage_end INT UNSIGNED NULL,
   ADD COLUMN mileage_actual INT UNSIGNED NULL,
   ADD COLUMN has_travel_order TINYINT(1) NOT NULL DEFAULT 0,
   ADD COLUMN has_official_business_slip TINYINT(1) NOT NULL DEFAULT 0,
   ADD COLUMN travel_order_number VARCHAR(50) NULL,
   ADD COLUMN ob_slip_number VARCHAR(50) NULL;
   ```
4. Test all features per checklist
5. Verify no breaking changes

### 8.2 Production Deployment

1. **Backup production database**
   ```bash
   mysqldump -u root -p fleet_management > backup_$(date +%Y%m%d).sql
   ```

2. **Run schema migration on production:**
   ```sql
   ALTER TABLE requests 
   ADD COLUMN mileage_start INT UNSIGNED NULL,
   ADD COLUMN mileage_end INT UNSIGNED NULL,
   ADD COLUMN mileage_actual INT UNSIGNED NULL,
   ADD COLUMN has_travel_order TINYINT(1) NOT NULL DEFAULT 0,
   ADD COLUMN has_official_business_slip TINYINT(1) NOT NULL DEFAULT 0,
   ADD COLUMN travel_order_number VARCHAR(50) NULL,
   ADD COLUMN ob_slip_number VARCHAR(50) NULL;
   ```

3. **Deploy modified files via FTP/SFTP**

4. **Verify functionality**
   - Test login
   - Test each feature
   - Check mobile responsiveness

### 8.3 Rollback Plan

If issues occur:

1. **Database Rollback:**
   ```sql
   ALTER TABLE requests 
   DROP COLUMN mileage_start,
   DROP COLUMN mileage_end,
   DROP COLUMN mileage_actual,
   DROP COLUMN has_travel_order,
   DROP COLUMN has_official_business_slip,
   DROP COLUMN travel_order_number,
   DROP COLUMN ob_slip_number;
   ```

2. **File Rollback:**
   - Restore previous version from backup/deploy-package

---

## 9. Backward Compatibility

All changes are backward compatible:
- Existing requests without mileage data will show empty/null
- Document checkboxes default to unchecked (0)
- Cancel button only appears for request owners
- Mobile navigation works alongside desktop view
- Vehicle types without UI still work (existing data preserved)

---

## 10. Timeline Estimate

| Feature | Estimated Hours |
|---------|-----------------|
| Driver Trip History | 4 hours |
| Mileage Tracking | 8 hours |
| Guard Completed Trips | 4 hours |
| Travel Order Checkbox | 4 hours |
| Cancel at Any Stage | 6 hours |
| Approver Vehicle Display | 2 hours |
| Mobile-First Dashboard | 8 hours |
| Vehicle Type CRUD | 6 hours |
| **Total** | **42 hours** |

---

## 11. Open Questions

1. Should mileage be required or optional?
2. Should travel order numbers be required when checkbox is ticked?
3. Should guards be able to override motorpool's travel order requirement?
4. Should cancelled requests be visible in driver's trip history?
5. Should vehicle mileage update be automatic or manual after trip completion?
6. Do you want email notifications to be sent when request is cancelled?

---

## 12. Next Steps

1. **Approve this plan** - Review and confirm implementation
2. **Set up localhost** - Copy files and configure
3. **Start implementation** - Begin with high-priority features
4. **Test on localhost** - Verify all features work
5. **Deploy to staging** - If available
6. **Deploy to production** - After testing

---

**End of Document**
