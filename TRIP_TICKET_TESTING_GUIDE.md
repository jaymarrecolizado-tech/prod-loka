# 🧪 Trip Ticket Feature - Testing Guide

**Purpose:** Test the complete Trip Ticket system from driver's perspective
**Date:** 2025
**Prerequisites:** Database migration completed

---

## 📋 Test Checklist

Before starting testing, ensure:

- ✅ Database migration run (`php migrations/017_create_trip_tickets.php`)
- ✅ `trip_tickets` table exists in database
- ✅ `trip_ticket_id` column exists in `requests` table
- ✅ Driver account exists with assigned vehicle
- ✅ Guard account exists
- ✅ Motorpool account exists (for approving tickets)

---

## 🎯 Testing Scenarios

### Scenario 1: Driver Views My Trips (Trip Ticket Integration)

**Objective:** Verify trip ticket status is visible in driver's trip list

**Steps:**
1. **Login as Driver**
   - Go to: `http://localhost:8080/?page=auth&action=login`
   - Enter driver credentials
   - Click "Login"

2. **Navigate to My Trips**
   - Click "My Trips" in sidebar menu
   - Verify page loads: `/?page=my-trips`

3. **Check Statistics Cards**
   - Verify "Trip Tickets" card is visible (4th card)
   - Should show approved tickets count
   - Should show pending tickets count (if any)

4. **Review Table Columns**
   - Verify "Trip Ticket" column exists in table
   - Should be between "Role" and "Actions" columns

5. **Filter by Past Trips**
   - Click "Past Trips" tab
   - Should show completed trips only
   - Past trips should have trip ticket status or button

**Expected Results:**
- ✅ My Trips page loads successfully
- ✅ Statistics show trip ticket counts
- ✅ Trip Ticket column visible in table
- ✅ Past trips filter works

---

### Scenario 2: Driver Views Completed Trips Without Ticket

**Objective:** Verify "Create Ticket" button appears for completed trips

**Steps:**
1. **Login as Driver**
2. **Navigate to My Trips**
3. **Filter to "Past Trips"**
   - Click "Past Trips" tab
4. **Find a completed trip**
   - Look for trips with "Completed" status badge
5. **Check Trip Ticket column**
   - Should see green "Create Ticket" button for completed trips
   - Button icon: 📄 "Create Ticket"

**Expected Results:**
- ✅ "Create Ticket" button visible for completed trips
- ✅ Button is green and clickable
- ✅ No trip ticket link (ticket doesn't exist yet)

---

### Scenario 3: Driver Creates Trip Ticket

**Objective:** Verify trip ticket creation form loads and pre-fills correctly

**Steps:**
1. **From My Trips page**, find a completed trip
2. **Click "Create Ticket" button**
   - Should redirect to: `/?page=trip-tickets&action=create_form&request_id=X`
3. **Verify page loads**
   - Title: "Create Trip Ticket"
   - Reference card showing trip details

4. **Check Pre-filled Fields**
   - Start Date: Should be pre-filled from dispatch time
   - End Date: Should be pre-filled from arrival time
   - Destination: Should show trip destination
   - Requester: Should show requester name
   - Driver: Should show driver name & license
   - Vehicle: Should show plate number (if assigned)
   - Purpose: Should show original purpose
   - Passengers: Should be pre-filled from passenger count

5. **Fill in Additional Fields**
   - Select "Trip Type" (e.g., "Official Business")
   - Verify "Start Odometer" is pre-filled (if recorded)
   - Enter "End Odometer" (required)
   - Optionally enter "Fuel Consumed" (liters)
   - Optionally enter "Fuel Cost" (PHP)

6. **Upload Documents (Optional)**
   - Click "Choose File" for Travel Order
   - Upload TO (PDF or Image)
   - Click "Choose File" for OB Slip
   - Upload OB slip (PDF or Image)
   - Optionally upload "Other Documents"

7. **Report Issues (Optional)**
   - Check "Any issues or incidents?" toggle
   - If checked, fill in issues description
   - Select "Resolved?" (Yes/No)
   - Add resolution notes if resolved

8. **Add Guard Notes (Optional)**
   - Fill in additional observations

9. **Click "Create Trip Ticket"**
   - Should redirect to ticket view page
   - Should show success message

**Expected Results:**
- ✅ Redirects to trip ticket creation form
- ✅ All request data pre-filled correctly
- ✅ Form validation works (required fields)
- ✅ Trip ticket created successfully
- ✅ Redirects to ticket view page
- ✅ Success message displayed

---

### Scenario 4: Driver Views Created Trip Ticket

**Objective:** Verify trip ticket is visible and shows correct details

**Steps:**
1. **After creating ticket**, you should be on ticket view page
   - URL: `/?page=trip-tickets&action=view&id=X`
2. **Verify Ticket Details**
   - Ticket ID displayed
   - Request ID linked correctly
   - Trip type shown
   - All trip details displayed correctly
   - Mileage information shown
   - Fuel information shown
   - Documents shown (if uploaded)
   - Issues section shown (if reported)
   - Guard verification details shown

3. **Check Status**
   - Status should be "submitted" (yellow badge)
   - Wait for motorpool to review

**Expected Results:**
- ✅ Ticket view page loads
- ✅ All details displayed correctly
- ✅ Status shows "submitted"
- ✅ Documents visible (if uploaded)
- ✅ No errors or missing data

---

### Scenario 5: Driver Checks Trip Ticket Status

**Objective:** Verify driver can see ticket status from My Trips

**Steps:**
1. **Navigate to My Trips**
   - Should still be on: `/?page=my-trips`
2. **Find the trip with ticket**
   - Look for the same request
3. **Check Trip Ticket column**
   - Should now show a link instead of "Create Ticket" button
   - Badge shows status (e.g., "submitted" = yellow)

4. **Click ticket link**
   - Should open ticket view page
   - Verify details match what you entered

**Expected Results:**
- ✅ Trip ticket link visible
- ✅ Status badge shows correct status
- ✅ Click opens ticket view page
- ✅ Details match what was entered

---

### Scenario 6: Driver Views Trip Ticket After Approval

**Objective:** Verify driver sees when ticket is approved

**Steps:**
1. **Logout as Driver** (optional)
2. **Login as Motorpool**
   - Navigate to Trip Tickets
   - Find pending ticket
   - Click "View"
   - Click green checkmark button to approve
3. **Logout as Motorpool**

4. **Login as Driver** again
5. **Navigate to My Trips**
6. **Find the trip**
7. **Check Trip Ticket column**
   - Should now show green "approved" badge
   - Badge has check icon: ✓

8. **Click ticket link**
   - Verify status changed to "approved"
   - Verify "Reviewed by" section shows motorpool reviewer

**Expected Results:**
- ✅ Driver can see approval status change
- ✅ Badge shows "approved" (green)
- ✅ Ticket shows reviewer details
- ✅ No action required from driver

---

### Scenario 7: Driver Sees Only Their Own Trips

**Objective:** Verify security - drivers only see their own trips

**Steps:**
1. **Create another driver account** (if not exists)
2. **Assign some trips to Driver A**
3. **Assign some trips to Driver B**

4. **Login as Driver A**
5. **Navigate to My Trips**
   - Should only see Driver A's trips
   - Should NOT see Driver B's trips

6. **Check statistics**
   - Should show count for Driver A's trips only

7. **Logout**

8. **Login as Driver B**
9. **Navigate to My Trips**
   - Should only see Driver B's trips
   - Should NOT see Driver A's trips

**Expected Results:**
- ✅ Driver A only sees Driver A's trips
- ✅ Driver B only sees Driver B's trips
- ✅ No cross-trip visibility (security working)
- ✅ Statistics accurate per driver

---

### Scenario 8: Guard Workflow Integration

**Objective:** Verify trip ticket creation flow after trip completion

**Steps:**
1. **Login as Motorpool**
2. **Create and approve a vehicle request**
3. **Assign a driver to the trip**
4. **Logout**

5. **Login as Guard**
6. **Navigate to Guard Dashboard**
   - URL: `/?page=guard`
7. **Find dispatched vehicle**
8. **Click "Record Arrival"**
   - Fill in arrival time
   - Optionally fill in ending mileage
   - Add guard notes
   - Click "Record Arrival"

9. **Verify redirect**
   - Should automatically redirect to: `/?page=trip-tickets&action=create_form&request_id=X`
   - Success message: "Arrival recorded. Please create a trip ticket."

10. **Verify pre-filled data**
    - Start date: Should match dispatch time
    - End date: Should match arrival time
    - All trip details from request
    - Driver information pre-filled

11. **Complete trip ticket creation**
    - Fill in remaining fields
    - Submit for review

**Expected Results:**
- ✅ Guard can record arrival
- ✅ Trip marked as completed automatically
- ✅ Redirected to trip ticket creation
- ✅ All data pre-filled correctly
- ✅ Trip ticket created successfully
- ✅ Driver and vehicle status back to "available"

---

## 📊 Database Verification

After testing, verify database has correct data:

```sql
-- Check trip_tickets table exists
SHOW TABLES LIKE 'trip_tickets';

-- Verify table structure
DESCRIBE trip_tickets;

-- Check for trip tickets
SELECT * FROM trip_tickets ORDER BY created_at DESC LIMIT 5;

-- Check request linkage
SELECT r.id, r.destination, tt.id as ticket_id, tt.status as ticket_status
FROM requests r
LEFT JOIN trip_tickets tt ON r.id = tt.request_id
WHERE r.status = 'completed'
ORDER BY r.actual_arrival_datetime DESC;

-- Check driver-specific tickets (Driver A)
SELECT r.id, r.destination, tt.id as ticket_id, tt.status
FROM requests r
LEFT JOIN trip_tickets tt ON r.id = tt.request_id
JOIN drivers d ON r.driver_id = d.id
WHERE d.user_id = [driver_a_user_id]
ORDER BY r.actual_arrival_datetime DESC;
```

---

## 🚨 Common Issues & Solutions

### Issue: "Trip Ticket column not visible"
**Solution:**
- Refresh browser (Ctrl+F5)
- Check if page is `/?page=my-trips` (not `/?page=trip-tickets`)
- Verify table header includes "Trip Ticket" column

### Issue: "No trips showing for driver"
**Solution:**
- Verify driver account has `driver_id` in `drivers` table
- Verify driver has assigned trips in `requests` table
- Check SQL query in `pages/my-trips/index.php`
- Ensure `driver_id = ?` condition is working

### Issue: "Cannot create trip ticket - page not found"
**Solution:**
- Verify routing in `index.php`
- Check `case 'trip-tickets':` exists
- Check `action === 'create_form'` case exists
- Verify file exists: `pages/trip-tickets/create.php`

### Issue: "Data not pre-filled in form"
**Solution:**
- Verify `request_id` is passed in URL
- Check SQL query in `pages/trip-tickets/create.php`
- Ensure request data is fetched correctly
- Verify request is completed (`status = 'completed'`)

### Issue: "Trip ticket status not updating in My Trips"
**Solution:**
- Verify `trip_ticket_id` is set in `requests` table
- Check SQL JOIN with `trip_tickets` table
- Verify `trip_ticket_status` column is fetched
- Refresh page (Ctrl+F5)

### Issue: "Driver can see other drivers' trips"
**Solution:**
- Verify `WHERE (r.driver_id = ? OR r.requested_driver_id = ?)` condition
- Check if `$driver->id` is correct (from logged-in user)
- Verify `driver_id` in `drivers` table matches user_id
- Clear browser cache and cookies

### Issue: "Documents not uploading"
**Solution:**
- Verify uploads directory exists: `public_html/uploads/trip_tickets/`
- Check directory permissions: `chmod 755`
- Check form has `enctype="multipart/form-data"`
- Verify PHP upload settings in `php.ini`:
  - `upload_max_filesize = 10M`
  - `post_max_size = 10M`

---

## ✅ Success Criteria

The trip ticket feature is working correctly when:

1. **Driver can view My Trips**
   - ✅ Page loads successfully
   - ✅ Shows only their own trips
   - ✅ Statistics are accurate

2. **Trip Ticket Column Visible**
   - ✅ "Trip Ticket" column exists in table
   - ✅ Shows status or "Create" button appropriately
   - ✅ Links to ticket view page

3. **Ticket Creation Works**
   - ✅ "Create Ticket" button redirects to form
   - ✅ Form is pre-filled with request data
   - ✅ All required fields work
   - ✅ Documents can be uploaded (optional)
   - ✅ Ticket creates successfully

4. **Ticket Status Updates**
   - ✅ Driver can see ticket status change
   - ✅ Status badge reflects current state
   - ✅ Link to view ticket works

5. **Approval Workflow**
   - ✅ Motorpool can review tickets
   - ✅ Driver sees when ticket is approved
   - ✅ No manual intervention needed

6. **Security Working**
   - ✅ Drivers only see their own trips
   - ✅ No cross-driver visibility
   - ✅ Trip tickets link to correct requests

---

## 📱 Browser Testing Checklist

Test in multiple browsers:

- ✅ Google Chrome (latest)
- ✅ Mozilla Firefox (latest)
- ✅ Microsoft Edge (latest)
- ✅ Safari (if applicable)

**Key tests:**
- Form submission works
- File uploads work
- JavaScript validation works
- Page redirects work
- CSS renders correctly

---

## 🎯 Post-Testing Actions

After successful testing:

1. **Document Results**
   - Screenshot My Trips page with ticket status
   - Screenshot trip ticket creation form
   - Screenshot ticket view page
   - Note any issues encountered

2. **Report Bugs (if any)**
   - Describe issue clearly
   - Include steps to reproduce
   - Include browser and version
   - Include error messages (if any)

3. **Acceptance Criteria**
   - All scenarios pass ✅
   - No critical bugs found
   - Security working correctly
   - Performance acceptable (< 2s page load)

---

## 📝 Test Log Template

Use this template to track testing progress:

| Scenario | Tester | Date | Result | Notes |
|----------|--------|------|--------|
| Scenario 1 | Name | ✅ Pass | - |
| Scenario 2 | Name | ✅ Pass | - |
| Scenario 3 | Name | ✅ Pass | - |
| Scenario 4 | Name | ✅ Pass | - |
| Scenario 5 | Name | ✅ Pass | - |
| Scenario 6 | Name | ✅ Pass | - |
| Scenario 7 | Name | ✅ Pass | - |
| Scenario 8 | Name | ✅ Pass | - |

**Overall Result:** [ ] All Scenarios Passed

---

## 🎉 Ready for Production

Once all scenarios pass:

1. **Pull latest changes**
   ```bash
   git checkout main
   git pull origin main
   git merge trip_ticket
   ```

2. **Run production migration**
   ```bash
   cd public_html
   php migrations/017_create_trip_tickets.php
   ```

3. **Verify in production**
   - Test with production database
   - Verify all features work
   - Document any production-specific issues

4. **Deploy**
   - Push to production branch
   - Deploy to production server
   - Monitor for issues

---

**Generated by:** AI Agent (GLM-4.7 via Crush)
**Last Updated:** 2025
**Branch:** trip_ticket
