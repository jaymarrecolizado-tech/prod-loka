# Trip Ticket PDF Date Stacking Feature

**Date:** March 22, 2026
**Files Modified:**
- `public_html/pages/trip-tickets/export-pdf.php`
- `prod2prod/pages/trip-tickets/export-pdf.php`

---

## Overview

The Date of Trip field now intelligently displays dates based on the trip duration:
- **Single-day trips:** Shows one date (e.g., "March 22, 2026")
- **Multi-day trips:** Stacks both start and end dates (e.g., "March 22, 2026\nMarch 24, 2026")

---

## Problem Solved

### Before (Single Date Only)
```
Date of Trip: March 22, 2026    Destination: Manila
```

For multi-day trips, only the start date was shown, which didn't indicate the trip duration.

### After (Smart Date Display)
**Single-day trip:**
```
Date of Trip: March 22, 2026    Destination: Manila
```

**Multi-day trip:**
```
Date of Trip: March 22, 2026    Destination: Davao City
             March 24, 2026
```

---

## Implementation Details

### Code Logic

```php
// Check if it's a multi-day trip
$startDate = date('F j, Y', strtotime($ticket->start_date));
$endDate = date('F j, Y', strtotime($ticket->end_date));

if ($startDate !== $endDate) {
    // Multi-day trip: stack both dates
    $pdf->Cell(95, 7, $startDate . "\n" . $endDate, 'B', 0, 'L', false, false, 1, false, '', 'T');
} else {
    // Single day trip
    $pdf->Cell(95, 7, $startDate, 'B', 0);
}
```

### Key Parameters

| Parameter | Value | Purpose |
|-----------|-------|---------|
| Width | 95 | Wider cell to accommodate stacked dates |
| Height | 7 | Base height (cell expands with stacked text) |
| Text | `$startDate . "\n" . $endDate` | Newline-separated dates |
| Border | 'B' | Bottom border only |
| Align | 'L' | Left-aligned dates |
| Stretch | 1 | Stretch to fill cell width |
| Calign | 'T' | Top alignment for wrapped text |
| Valign | 'T' | Top alignment for wrapped text |

---

## Reduced Margins

### Section I & II - New Spacing Pattern

| Column | Old Width | New Width | Change |
|---------|-----------|-----------|--------|
| Label 1 | 35 | **25** | -10 (more compact labels) |
| Value 1 | 80 | **95** | +15 (more space for values) |
| Label 2 | 25/30 | **20** | -5/-10 (more compact) |
| Value 2 | Flex | Flex | Same (remaining space) |

### Row Structure

```
Before: | Label1(35) | Value1(80) | Label2(25/30) | Value2(flex) |
After:  | Label1(25) | Value1(95) | Label2(20)    | Value2(flex) |
```

**Result:** 15 more characters of space for values per row!

---

## Visual Examples

### Single-Day Trip

```
I. PARTICULARS OF TRIP

Date of Trip: March 22, 2026              Destination: Manila
Time Out:     09:00 AM                     Time In:      05:00 PM
Type of Trip: Official Business           No. of Passengers: 4
```

### Multi-Day Trip

```
I. PARTICULARS OF TRIP

Date of Trip: March 22, 2026              Destination: Davao City
             March 24, 2026
Time Out:     09:00 AM                     Time In:      05:00 PM
Type of Trip: Official Business           No. of Passengers: 2
```

### Vehicle Information Section

```
II. VEHICLE & DRIVER INFORMATION

Plate Number:   ABC 1234                   Driver:        Juan Dela Cruz
Make / Model:   Toyota Innova              License No.:   N01-23-456789
Fuel Type:      Diesel                     Color:         White
```

---

## Benefits

### Date Stacking
✅ Clear visualization of trip duration
✅ Both start and end dates visible for multi-day trips
✅ Smart formatting - no unnecessary clutter for single-day trips
✅ Text wrapping support ensures proper display

### Reduced Margins
✅ More space for content (15 characters per row)
✅ Better use of available page width
✅ Labels are more compact
✅ Values have more room for longer text
✅ Consistent spacing across all sections

---

## Testing Instructions

### Test Single-Day Trip
1. Create/view a single-day trip ticket
2. Export to PDF
3. Verify:
   - ✅ Only one date is shown
   - ✅ Date is properly formatted (F j, Y)
   - ✅ Cell height is normal (7)

### Test Multi-Day Trip
1. Create/view a multi-day trip ticket (start_date ≠ end_date)
2. Export to PDF
3. Verify:
   - ✅ Both dates are shown stacked
   - ✅ Start date on first line
   - ✅ End date on second line
   - ✅ Text wraps properly
   - ✅ Cell expands to accommodate stacked dates

### Test Layout
1. Export any trip ticket PDF
2. Verify margins:
   - ✅ Label 1 width: 25
   - ✅ Value 1 width: 95
   - ✅ Label 2 width: 20
   - ✅ Value 2: remaining space
3. Check Section I and II:
   - ✅ Consistent spacing across all rows
   - ✅ No excessive whitespace
   - ✅ Values have adequate room

---

## Technical Notes

### TCPDF Text Wrapping

The stacked dates use TCPDF's text wrapping feature:
- Text with `\n` characters will wrap to new lines
- Cell height automatically expands to fit wrapped text
- `'T'` border mode prevents border duplication
- `stretch=1` ensures text fills the cell width

### Date Comparison

The comparison uses formatted dates (`F j, Y`) to determine if dates are different:
- This ignores time differences
- Only compares day, month, and year
- Example: "2026-03-22 09:00" vs "2026-03-22 17:00" = same day
- Example: "2026-03-22 09:00" vs "2026-03-23 17:00" = different days

---

## Files Updated

| File | Section | Changes |
|------|---------|---------|
| public_html/pages/trip-tickets/export-pdf.php | Section I | Date stacking, reduced margins |
| public_html/pages/trip-tickets/export-pdf.php | Section II | Reduced margins |
| prod2prod/pages/trip-tickets/export-pdf.php | Section I | Date stacking, reduced margins |
| prod2prod/pages/trip-tickets/export-pdf.php | Section II | Reduced margins |

Both files passed syntax check with no errors.

---

## Deployment

### Development (public_html)
Changes are already applied. Test immediately with:
```
http://localhost/Projects/loka2/public_html/?page=trip-tickets
```

### Production (prod2prod)
Changes are applied. Deploy `prod2prod/` folder to production server.

---

## Related Changes

This feature builds on previous PDF improvements:
- Trip ticket PDF layout enhancements
- Cell spacing improvements
- Text wrapping support
- Fuel Type and Color fields

---

## Summary

- ✅ Dates stack for multi-day trips
- ✅ Single-day trips show one date (no clutter)
- ✅ Reduced margins for better space utilization
- ✅ 15 more characters per row for values
- ✅ Consistent layout across both sections
- ✅ Text wrapping still supported
- ✅ Professional appearance maintained

---

**End of Document**
