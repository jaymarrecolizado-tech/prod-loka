# Trip Ticket PDF Text Wrapping Fix

**Date:** March 22, 2026
**Files Modified:**
- `public_html/pages/trip-tickets/export-pdf.php`
- `prod2prod/pages/trip-tickets/export-pdf.php`

---

## Problem

The original PDF layout was using TCPDF's `Cell()` function which has limitations:
- **Fixed cell heights** - cells don't expand when text wraps
- **Text overflow** - long destinations, driver names, etc. were cut off
- **Multi-line dates** - stacked dates in multi-day trips didn't display properly
- **Signature space** - insufficient height for signatures

### Examples of Issues

| Issue | Before | After |
|-------|--------|-------|
| Long destination | "Municipality of Solano..." cut off | Fully visible with wrapping |
| Driver name | "Juan Dela Cruz Jr." cut off | Fully visible |
| Multi-day dates | Only start date shown | Both dates stacked |
| Signatures | 6px height too small | 8px height adequate |

---

## Solution

Replaced `Cell()` with `WriteHTMLCell()` for all fields that may contain long text:

### TCPDF Functions Comparison

| Function | Auto-Expand | Text Wrapping | HTML Support |
|----------|-------------|---------------|--------------|
| `Cell()` | ❌ No | ⚠️ Limited | ❌ No |
| `MultiCell()` | ✅ Yes | ✅ Yes | ⚠️ Limited |
| `WriteHTMLCell()` | ✅ Yes | ✅ Yes | ✅ Full |

**WriteHTMLCell() was chosen because:**
1. Automatically expands cell height for wrapped text
2. Properly handles newlines (`\n`) for stacked dates
3. Maintains proper alignment
4. Clean integration with existing layout

---

## Code Changes

### Before (Using Cell)

```php
// Row 1: Date of Trip & Destination
$pdf->Cell(25, 7, 'Date of Trip:', 0, 0);
$pdf->Cell(95, 7, $startDate, 'B', 0);
$pdf->Cell(20, 7, 'Destination:', 0, 0);
$pdf->Cell(0, 7, $ticket->destination, 'B', 1, 'L', false, false, 1, false, '', 'T');
```

**Issues:**
- Fixed height of 7 units
- Long destination truncated
- Stacked dates don't work

### After (Using WriteHTMLCell)

```php
// Row 1: Date of Trip & Destination
$pdf->Cell(25, 6, 'Date of Trip:', 0, 0);
$pdf->WriteHTMLCell(85, 6, '', '', $dateText, 'B', 0, 0, true, 'L', true);
$pdf->Cell(22, 6, 'Destination:', 0, 0);
$pdf->WriteHTMLCell(0, 6, '', '', $ticket->destination, 'B', 1, 0, true, 'L', true);
```

**Benefits:**
- Height auto-expands as needed
- Long destinations wrap properly
- Stacked dates work correctly

---

## Detailed Changes by Section

### Section I: Particulars of Trip

| Field | Old Function | New Function | Width | Notes |
|-------|-------------|-------------|--------|-------|
| Date of Trip | Cell | WriteHTMLCell | 85 | Handles stacked dates |
| Destination | Cell | WriteHTMLCell | 0 (flex) | Long destinations wrap |
| Type of Trip | Cell | WriteHTMLCell | 85 | Long trip types wrap |

**Layout Pattern:**
```
Label (25) | Value (85) | Label (22) | Value (flex)
```

### Section II: Vehicle & Driver Information

| Field | Old Function | New Function | Width | Notes |
|-------|-------------|-------------|--------|-------|
| Plate Number | Cell | WriteHTMLCell | 85 | Standard format |
| Driver | Cell | WriteHTMLCell | 0 (flex) | Long names wrap |
| Make / Model | Cell | WriteHTMLCell | 85 | Vehicle details wrap |
| License No. | Cell | WriteHTMLCell | 0 (flex) | Long licenses wrap |
| Fuel Type | Cell | WriteHTMLCell | 85 | - |
| Color | Cell | WriteHTMLCell | 0 (flex) | - |

**Layout Pattern:**
```
Label (25) | Value (85) | Label (22) | Value (flex)
```

### Section III: Passengers

| Field | Old Function | New Function | Width | Notes |
|-------|-------------|-------------|--------|-------|
| Passenger Name | Cell | WriteHTMLCell | 85 | Long names wrap |

**Layout Pattern:**
```
No. (12) | Name (85) | No. (12) | Name (85)
```

### Section VI: Driver Certification

| Field | Old Function | New Function | Height | Notes |
|-------|-------------|-------------|--------|-------|
| Name | Cell | WriteHTMLCell | 8 | Increased from 6 |
| Date | Cell | Cell | 8 | Increased from 6 |
| Signature | Cell | Cell | 8 | Increased from 6 |

### Section VII: Signatory Clearance

| Field | Old Function | New Function | Height | Notes |
|-------|-------------|-------------|--------|-------|
| Name | Cell | Cell | 8 | Increased from 6 |
| Date | Cell | Cell | 8 | Increased from 6 |
| Signature | Cell | Cell | 8 | Increased from 6 |

### Footer

| Field | Old Function | New Function | Notes |
|-------|-------------|-------------|-------|
| Notes | Cell | WriteHTMLCell | Long text wraps |

---

## WriteHTMLCell Parameters

```php
WriteHTMLCell(
    $w,           // Cell width (0 = remaining)
    $h,           // Min cell height (auto-expands)
    $x,           // X position (empty = current)
    $y,           // Y position (empty = current)
    $html,        // HTML content or plain text
    $border,      // Border (0, 1, or 'B'/'L'/'R'/'T')
    $ln,          // Line position (0, 1, 2)
    $fill,        // Fill (0 or 1)
    $reseth,      // Reset last height (true/false)
    $align,       // Alignment ('L', 'C', 'R', 'J')
    $autopadding  // Auto padding (true/false)
)
```

**Key Parameters Used:**
- `$w`: 85 for fixed width, 0 for remaining space
- `$h`: 6 for data rows, 8 for signature rows
- `$html`: Plain text (works fine without HTML tags)
- `$border`: 'B' for bottom border only
- `$ln`: 0 for same row, 1 for next row
- `$align`: 'L' for left alignment, 'C' for center

---

## Height Adjustments

| Section | Old Height | New Height | Reason |
|---------|-----------|-----------|--------|
| Data rows | 7 | 6 | Tighter spacing |
| Signature rows | 6 | 8 | More space for signatures |
| Labels | 7 | 6 | Tighter spacing |

---

## Benefits

1. **No More Cut-off Text**
   - Long destinations fully visible
   - Driver names displayed completely
   - License numbers not truncated
   - Passenger names wrap properly

2. **Proper Date Display**
   - Single-day trips show one date
   - Multi-day trips show both dates stacked
   - Dates don't overflow

3. **Better Signature Space**
   - Increased height from 6 to 8 units
   - More room for handwritten signatures
   - Professional appearance

4. **Auto-Expanding Cells**
   - Cells grow to fit content
   - No manual height calculation needed
   - Maintains consistent layout

5. **Maintained Layout**
   - Same visual appearance
   - Consistent spacing
   - Professional output

---

## Testing

### Test 1: Long Destination
1. Create a trip with destination: "Municipality of Solano, Nueva Vizcaya"
2. Export PDF
3. Verify: Destination wraps and is fully visible

### Test 2: Long Driver Name
1. Assign driver with name: "Juan Dela Cruz Jr. III"
2. Export PDF
3. Verify: Full name displayed

### Test 3: Multi-Day Trip
1. Create trip from Jan 15 to Jan 17, 2026
2. Export PDF
3. Verify: Both dates stacked:
   ```
   January 15, 2026
   January 17, 2026
   ```

### Test 4: Long Passenger Names
1. Add passengers with long names
2. Export PDF
3. Verify: Names wrap properly

### Test 5: Signatures
1. Export PDF
2. Verify: Signature fields have adequate space (8 units height)

---

## Known Limitations

1. **Page Layout**
   - Very long text may push content beyond page bounds
   - Current code doesn't implement page breaks
   - Consider adding auto-page-break logic for extremely long content

2. **HTML Support**
   - WriteHTMLCell supports HTML, but we're using plain text
   - Could use `<br>` tags for manual breaks if needed

3. **Cell Alignment**
   - Cells with different heights may not align perfectly
   - Currently acceptable given the layout structure

---

## Files Updated

| File | Status | Syntax Check |
|------|--------|--------------|
| `public_html/pages/trip-tickets/export-pdf.php` | ✅ Updated | ✅ No errors |
| `prod2prod/pages/trip-tickets/export-pdf.php` | ✅ Updated | ✅ No errors |

---

## Deployment

### Development (public_html)
Changes are already applied. Test immediately:
```
http://localhost/Projects/loka2/public_html/?page=trip-tickets
```

### Production (prod2prod)
Changes are applied. Deploy `prod2prod/` folder to production server.

---

## Summary

- ✅ Replaced Cell() with WriteHTMLCell() for all value fields
- ✅ Auto-expanding cells for wrapped text
- ✅ Fixed multi-day date stacking
- ✅ Increased signature field heights (6 → 8)
- ✅ All content now fits properly
- ✅ No more cut-off text
- ✅ Professional appearance maintained

---

**End of Document**
