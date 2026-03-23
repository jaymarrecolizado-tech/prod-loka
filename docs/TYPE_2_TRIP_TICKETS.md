# Type 2 Vehicle Trip Tickets - Implementation Summary

## Overview
Type 2 Vehicle Trip Tickets are **weekly summary tickets** that caretakers generate for each vehicle. They aggregate completed trip data and allow manual input for fuel refilling information.

## Key Features

### 1. Ticket Numbering Format
- **Format:** `YEAR-PLATE_NUMBER-MONTH+WEEK`
- **Example:** `2026-448SQB-0301` (Year 2026, Plate 448SQB, March, Week 1)
- Generated automatically using `generateType2TicketNumber()` helper function

### 2. Generation Frequency
- **Frequency:** Weekly
- Caretakers generate one ticket per vehicle per week
- Auto-aggregates data from completed trips for the week

### 3. Data Sources
- **Auto-aggregated:**
  - Completed trips (from requests table)
  - Trip dates, times, destinations, purposes
  - Driver and passenger information
  - Mileage (start/end)
  - Distance traveled
- **Manual input:**
  - Fuel refill data (quantity, amount, date, additional items, remarks)
  - Balance start/end

### 4. Approval Workflow
- **Primary Approver:** Department Approver
- **Backup Approver:** Motorpool Head (can approve if Department Approver unavailable)
- Approval type tracked in `approval_by` column (`dept_approver` or `motorpool_head`)

### 5. Document Attachments
- **Current:** None (as per requirements)
- Future: Can be added if needed

## Database Schema Changes

### New Columns in `trip_tickets` table (Migration 021):
```sql
ticket_type ENUM('type1', 'type2') DEFAULT 'type1'
ticket_number VARCHAR(50) -- Formatted ticket number
week_number INT UNSIGNED -- Week number within month
week_start DATE -- Week start date
week_end DATE -- Week end date
fuel_refill_data JSON -- Manual fuel refill entries
approval_by ENUM('dept_approver', 'motorpool_head')
```

### New Column (Migration 022):
```sql
vehicle_id INT UNSIGNED -- Direct link to vehicles table
```

## New Files Created

### 1. Migrations
- `migrations/021_add_trip_ticket_types.php` - Adds Type 2 columns
- `migrations/022_add_vehicle_id_to_trip_tickets.php` - Adds vehicle_id column

### 2. Helper Functions
- `includes/trip_ticket_helpers.php` - Helper functions for Type 2 tickets:
  - `generateType2TicketNumber()` - Generate formatted ticket number
  - `getWeekOfMonth()` - Get week number within a month
  - `getWeekDates()` - Get week start/end dates
  - `getExistingType2Ticket()` - Check for duplicate tickets
  - `fetchCompletedTripsForTicket()` - Fetch trips for a period
  - `canApproveType2Ticket()` - Check approval permissions
  - And more utility functions

### 3. Pages
- `pages/my-trip-tickets/generate-type2.php` - Generate/edit Type 2 tickets
- `pages/my-trip-tickets/type2-actions.php` - View/edit/submit Type 2 tickets
- `pages/my-trip-tickets/type2-print.php` - Print template for Type 2 tickets

### 4. Constants (Updated in `config/constants.php`)
```php
// Trip Ticket Types
TRIP_TICKET_TYPE_1 = 'type1'  // Per-trip ticket
TRIP_TICKET_TYPE_2 = 'type2'  // Weekly summary

// Trip Ticket Status
TRIP_TICKET_STATUS_DRAFT = 'draft'
TRIP_TICKET_STATUS_SUBMITTED = 'submitted'
TRIP_TICKET_STATUS_REVIEWED = 'reviewed'
TRIP_TICKET_STATUS_APPROVED = 'approved'
TRIP_TICKET_STATUS_REJECTED = 'rejected'

// Approval Types
APPROVAL_BY_DEPT_APPROVER = 'dept_approver'
APPROVAL_BY_MOTORPOOL_HEAD = 'motorpool_head'
```

## Updated Files

### 1. `pages/my-trip-tickets/index.php`
- Added Type 2 filter in the filters section
- Updated ticket list to show both Type 1 and Type 2 tickets
- Added action handlers for Type 2 (view, edit, submit, print)
- Different display for Type 1 (per-trip) vs Type 2 (weekly) tickets
- Stats now include Type 1 and Type 2 counts

### 2. `pages/trip-tickets/index.php`
- Updated approve action to handle both Type 1 and Type 2
- Type 1: Only Motorpool Head can approve
- Type 2: Department Approver (primary) or Motorpool Head (backup)
- Fixed query to handle Type 2 tickets (LEFT JOIN with requests)

## How to Use

### For Caretakers (Drivers/Motorpool):

1. **Generate Type 2 Ticket:**
   - Navigate to My Trip Tickets
   - Click "Type 2: Weekly Summary" button
   - Select vehicle and week dates
   - Review auto-aggregated trip data
   - Enter fuel refill data manually
   - Save as draft or submit for approval

2. **View/Edit Tickets:**
   - Filter by Type 2 in the tickets list
   - View ticket details, trips, and fuel data
   - Edit draft tickets as needed
   - Print ticket for physical records

3. **Submit for Approval:**
   - Click "Submit for Approval" on a draft ticket
   - Status changes to "submitted"
   - Department Approver (or Motorpool Head) will review

### For Approvers:

1. **Review Type 2 Tickets:**
   - Access from Trip Tickets list
   - Filter by Type 2 and "submitted" status
   - Review aggregated trip data
   - Verify fuel refill entries
   - Approve or return for revision

## Access Permissions

| Role | View Type 2 | Create Type 2 | Approve Type 2 |
|------|-------------|---------------|----------------|
| Driver | Own tickets | Own tickets | No |
| Motorpool | All tickets | All tickets | Yes (backup) |
| Department Approver | All tickets | No | Yes (primary) |
| Admin | All tickets | All tickets | Yes |

## Print Format

Type 2 tickets use a landscape A4 format similar to the existing summary print:
- Header with DICT branding and ticket number
- Vehicle information section
- Trip details table (auto-populated from completed trips)
- Fuel refill data section (manual entries)
- Summary statistics (trips, distance, fuel, cost)
- Signatories section (Prepared by, Reviewed by, Approved)

## Future Enhancements

1. **Document Attachments:** Add support for uploading receipts/documents
2. **Multiple Vehicle Selection:** Generate tickets for multiple vehicles at once
3. **Auto-Submit:** Option to auto-submit tickets after a deadline
4. **Notifications:** Email/in-app notifications for approvals
5. **Reports:** Weekly/monthly fuel consumption reports

## Notes

- Type 2 tickets are stored in the same `trip_tickets` table as Type 1
- Distinguished by `ticket_type` column
- `request_id` is 0 for Type 2 tickets (no single request)
- `vehicle_id` is required for Type 2 tickets
- `week_start`, `week_end`, `week_number` store the week information
- Fuel data stored as JSON in `fuel_refill_data` column
- Balance info temporarily stored in `issues_description` (as JSON)

## Testing Checklist

- [ ] Create a Type 2 ticket for a vehicle
- [ ] Verify auto-aggregated trip data
- [ ] Enter and save fuel refill data
- [ ] Submit ticket for approval
- [ ] Approve ticket as Department Approver
- [ ] Approve ticket as Motorpool Head (when Dept Approver unavailable)
- [ ] Print Type 2 ticket
- [ ] Filter tickets by type (Type 1 / Type 2)
- [ ] Verify ticket numbering format
- [ ] Check duplicate prevention (one ticket per vehicle per week)
