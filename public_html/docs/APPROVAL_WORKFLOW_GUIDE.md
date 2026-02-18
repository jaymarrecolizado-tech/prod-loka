# Approval Workflow - Complete Guide
**Date:** January 28, 2026

## Two-Stage Approval Workflow

### Overview

The LOKA system uses a **two-stage approval process** where:

1. **Requester** selects ANY two people:
   - **Approver** - First stage reviewer (can be anyone, not department-restricted)
   - **Motorpool Head** - Second stage reviewer (can be anyone, not department-restricted)

2. **Approver** (Stage 1):
   - Reviews request
   - Approves or rejects
   - Can be ANY user assigned, regardless of department

3. **Motorpool Head** (Stage 2):
   - Reviews ONLY after Approver approves
   - Assigns vehicle and driver
   - Gives final approval

### Status Flow

```
Request Created
    ↓
[STATUS: pending]
    └─ Only APPROVER can approve
    └─ Approver selected by requester (any user, any department)
    ↓
[When Approver Approves]
    ↓
[STATUS: pending_motorpool]
    └─ Only MOTORPOOL HEAD can approve
    └─ Motorpool head selected by requester (any user, any department)
    ↓
[When Motorpool Head Approves + Assigns Vehicle/Driver]
    ↓
[STATUS: approved]
    └─ Trip confirmed
    └─ Driver assigned
    └─ Vehicle assigned
```

## Current Implementation

### ✅ Workflow Correctly Implemented

**File:** `pages/approvals/process.php` (lines 5-8)
```php
/**
 * Two-stage approval workflow with race condition protection:
 * Stage 1: Assigned Approver reviews and approves (REGARDLESS of department)
 * Stage 2: Assigned Motorpool Head assigns vehicle/driver and gives final approval
 */
```

**File:** `pages/approvals/process.php` (lines 63-78)
```php
// Validate permissions - Only specifically assigned approver/motorpool head can process
if ($approvalType === 'motorpool' && $request->status === STATUS_PENDING_MOTORPOOL) {
    if ($request->motorpool_head_id == $currentUserId || isAdmin()) {
        $canProcess = true;
    }
} elseif ($approvalType === 'department' && $request->status === STATUS_PENDING) {
    if ($request->approver_id == $currentUserId || isAdmin()) {
        $canProcess = true;
    }
}
```

**Key Points:**
- ✅ Approver can be ANY user (not department-restricted)
- ✅ Motorpool Head can be ANY user (not department-restricted)
- ✅ Only ASSIGNED approver/motorpool head can approve
- ✅ Admin can override and approve at any stage

## Notification Flow

### When Request is Created

All 5 stakeholders receive **informational** notifications:

| Stakeholder | Notification Type | Message | Can Approve? |
|-------------|-------------------|----------|--------------|
| Requester | `request_confirmation` | "Your request has been submitted" | No |
| Approver | `request_submitted` | "New request awaiting your approval" | **Yes** |
| Motorpool Head | `request_submitted_motorpool` | "New request submitted" (informational) | No (not yet) |
| Passengers | `added_to_request` | "Added to vehicle request" | No |
| Requested Driver | `driver_requested` | "You have been requested as driver" | No |

**Motorpool Head gets notification for awareness, CANNOT approve yet.**

### When Approver Approves (Stage 1 → Stage 2)

3 stakeholders receive notifications:

| Stakeholder | Notification Type | Message | Action Required |
|-------------|-------------------|----------|-----------------|
| Requester | `department_approved` | "Request approved by approver" | Wait for motorpool |
| Motorpool Head | `pending_motorpool_approval` | "Request awaiting motorpool approval" | **Approve + Assign** |
| Requested Driver | `driver_status_update` | "Request progress update" | Wait for assignment |

**Motorpool Head NOW gets actionable notification to approve and assign vehicle/driver.**

### When Motorpool Head Approves (Stage 2 → Complete)

All parties receive final notification:

| Stakeholder | Notification Type | Message |
|-------------|-------------------|----------|
| Requester | `request_fully_approved` | "Request fully approved with vehicle and driver" |
| Passengers | `trip_fully_approved` | "Trip fully approved" |
| Assigned Driver | `driver_assigned` | "You have been assigned as driver" |

## Current State (Request #1)

| Field | Value |
|-------|--------|
| Request ID | 1 |
| Status | `pending` |
| Requester | Joyce Ann Urdillas |
| Approver | Shawn Tibo (can approve now) |
| Motorpool Head | Ronald Bariuan (notified, waiting for approver) |
| Destination | Malaysia |
| Start Date | 2026-01-29 12:00 |

**What happens next:**

1. ✅ **Shawn Tibo (Approver)** receives notification → Approves request
2. → Status changes to `pending_motorpool`
3. ✅ **Ronald Bariuan (Motorpool Head)** receives notification → Approves & assigns vehicle
4. → Status changes to `approved`
5. ✅ **All parties** receive final approval notifications

## Permission Logic

### Who Can Approve at Each Stage?

**Stage 1 (status = pending):**
- ONLY the selected `approver_id` user
- OR admin users
- NO department restrictions

**Stage 2 (status = pending_motorpool):**
- ONLY the selected `motorpool_head_id` user
- OR admin users
- NO department restrictions

**Code Location:** `pages/approvals/process.php` (lines 63-78)

```php
if ($approvalType === 'motorpool' && $request->status === STATUS_PENDING_MOTORPOOL) {
    if ($request->motorpool_head_id == $currentUserId || isAdmin()) {
        $canProcess = true;
    }
} elseif ($approvalType === 'department' && $request->status === STATUS_PENDING) {
    if ($request->approver_id == $currentUserId || isAdmin()) {
        $canProcess = true;
    }
}
```

## Admin Override

Admin users can approve at **any stage**:

```php
if (isAdmin()) {
    $canProcess = true;
}
```

This allows admins to:
- Skip the approver and approve directly
- Skip the motorpool head and approve directly
- Resolve stuck requests
- Handle emergencies

## Notification Templates

### Email Templates (config/mail.php)

| Template Key | When Used | Subject |
|--------------|-------------|---------|
| `request_submitted` | Request created (approver) | "New Vehicle Request Submitted" |
| `request_submitted_motorpool` | Request created (motorpool head) | "New Vehicle Request Awaiting Review" |
| `pending_motorpool_approval` | Approver approved | "Request Awaiting Motorpool Approval" |
| `department_approved` | Approver approved | "Request Approved by Department" |
| `request_fully_approved` | Motorpool approved | "Request Fully Approved!" |

### System Notifications (notifications table)

All email templates have corresponding system notifications with same logic.

## Status Definitions

| Status | Meaning | Can Approve |
|--------|-----------|--------------|
| `pending` | Awaiting Approver (Stage 1) | Approver only |
| `pending_motorpool` | Awaiting Motorpool Head (Stage 2) | Motorpool Head only |
| `approved` | Fully approved | None (complete) |
| `rejected` | Rejected at any stage | Requester can resubmit |
| `revision` | Sent back for revision | Requester can edit |
| `cancelled` | Cancelled | Requester can create new |

## Example Workflow

### Scenario: Joyce Ann requests trip to Malaysia

**Step 1: Request Created**
- Joyce Ann selects:
  - Approver: Shawn Tibo (from different department)
  - Motorpool Head: Ronald Bariuan (from different department)
- Status: `pending`

**Notifications Sent:**
- ✅ Joyce Ann: "Request submitted successfully"
- ✅ Shawn Tibo: "New request awaiting your approval"
- ✅ Ronald Bariuan: "New request submitted" (informational)
- ✅ Passengers: "Added to vehicle request"
- ✅ Driver: "You have been requested as driver"

**Step 2: Approver Action**
- Shawn Tibo logs in
- Sees request in Approvals list
- Clicks "Approve"
- Status changes to: `pending_motorpool`

**Notifications Sent:**
- ✅ Joyce Ann: "Request approved by approver"
- ✅ Ronald Bariuan: "Request awaiting motorpool approval" (actionable now!)
- ✅ Driver: "Request progress update"

**Step 3: Motorpool Head Action**
- Ronald Bariuan logs in
- Sees request in Approvals list (status = pending_motorpool)
- Selects vehicle and driver
- Clicks "Approve & Assign"
- Status changes to: `approved`

**Notifications Sent:**
- ✅ Joyce Ann: "Request fully approved with vehicle and driver"
- ✅ Passengers: "Trip fully approved"
- ✅ Assigned Driver: "You have been assigned as driver"

## Key Features

### No Department Restrictions ✅

Requester can select:
- ANY user as Approver (not from same department required)
- ANY user as Motorpool Head (not from same department required)

This allows flexibility:
- Cross-department requests
- Specialized approvers
- Different approval chains per request type

### Race Condition Protection ✅

The approval system uses:
- Row-level locking (`FOR UPDATE`)
- State machine validation
- Prevents concurrent approvals

**Code:** `pages/approvals/process.php` (lines 47-53, 204-235)

```php
// FOR UPDATE - prevents concurrent processing
$request = db()->fetch(
    "SELECT * FROM requests WHERE id = ? FOR UPDATE",
    [$requestId]
);

// State machine validation
$validTransitions = [
    STATUS_PENDING => [STATUS_PENDING_MOTORPOOL, STATUS_REJECTED, ...],
    STATUS_PENDING_MOTORPOOL => [STATUS_APPROVED, STATUS_REJECTED, ...],
];
```

### Audit Trail ✅

All approvals logged:
- Who approved
- When approved
- What action taken
- From what status to what status

## Troubleshooting

### Approver Cannot See Request to Approve

**Check:**
1. User is the `approver_id` assigned to request
2. Request status is `pending` (not `pending_motorpool`)
3. User has `approver` role
4. User status is `active`

### Motorpool Head Cannot See Request to Approve

**Check:**
1. Request status is `pending_motorpool` (approver already approved)
2. User is the `motorpool_head_id` assigned to request
3. User has `motorpool_head` role
4. User status is `active`

### Request Stuck in Pending

**Possible Causes:**
1. Approver hasn't logged in or approved
2. Approver was changed but request not updated
3. Approver account is inactive
4. Race condition (rare, system handles this)

**Solution:**
- Admin can approve at any stage
- Or contact assigned approver

## Files Involved

| File | Purpose |
|-------|----------|
| `pages/requests/create.php` | Request creation (selects approver/motorpool) |
| `pages/approvals/process.php` | Approval workflow (two-stage logic) |
| `pages/approvals/index.php` | Approval list view |
| `config/constants.php` | Status definitions |
| `config/mail.php` | Email templates |
| `includes/functions.php` | `notify()`, `notifyDriver()`, etc. |

---

**Workflow Status:** ✅ Correctly Implemented
**Two-Stage Approval:** ✅ Working
**Department Restrictions:** ✅ None (by design)
**Notifications:** ✅ All stakeholders notified correctly
