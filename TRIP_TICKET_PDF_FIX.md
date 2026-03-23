# Trip Ticket PDF Layout Improvements

## Date: March 22, 2026

## Changes Made

### Files Modified:
1. `public_html/pages/trip-tickets/export-pdf.php`
2. `prod2prod/pages/trip-tickets/export-pdf.php`

---

## Improvements Implemented

### 1. SQL Query Enhancement
Added `fuel_type` to the vehicle data query:
```sql
v.plate_number, v.make, v.model as vehicle_model, v.color, v.fuel_type
```

### 2. Date Prepared Section
**Before:**
```php
$pdf->Cell(30, 5, 'Date:', 0, 0);
$pdf->Cell(0, 5, date('F j, Y'), 0, 1);
```

**After:**
```php
$pdf->Cell(30, 6, 'Date Prepared:', 0, 0);
$pdf->Cell(0, 6, date('F j, Y'), 'B', 1);
```
- Changed label from "Date" to "Date Prepared"
- Added bottom border for clarity
- Increased cell height from 5 to 6

### 3. Date of Trip Section (Section I - Particulars of Trip)
**Before:**
```php
$pdf->Cell(35, 6, 'Date of Trip:', 0, 0);
$pdf->Cell(80, 6, date('F j, Y', strtotime($ticket->start_date)), 'B', 0);
```

**After:**
```php
$pdf->Cell(45, 8, 'Date of Trip:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(70, 8, date('F j, Y', strtotime($ticket->start_date)), 'B', 0);
```
- Increased label width from 35 to 45
- Increased value cell width from 80 to 70
- Increased cell height from 6 to 8 for better visibility
- Made date font bold for emphasis
- Added text wrapping with `'T'` border mode

### 4. Vehicle & Driver Information Section (Section II)
**Before:**
```php
$pdf->Cell(35, 6, 'Vehicle No.:', 0, 0);
$pdf->Cell(80, 6, $ticket->plate_number ?: 'N/A', 'B', 0);
$pdf->Cell(25, 6, 'Driver:', 0, 0);
$pdf->Cell(0, 6, $ticket->driver_name, 'B', 1);

$pdf->Cell(35, 6, 'Make/Model:', 0, 0);
$pdf->Cell(80, 6, ($ticket->make ?: 'N/A') . ' ' . ($ticket->vehicle_model ?: ''), 'B', 0);
$pdf->Cell(25, 6, 'License No.:', 0, 0);
$pdf->Cell(0, 6, $ticket->driver_license ?: 'N/A', 'B', 1);
```

**After:**
```php
// Row 1: Plate Number & Driver
$pdf->Cell(40, 7, 'Plate Number:', 0, 0);
$pdf->Cell(75, 7, ($ticket->plate_number ?: 'N/A'), 'B', 0, 'L', false, false, 1, false, '', 'T');
$pdf->Cell(30, 7, 'Driver:', 0, 0);
$pdf->Cell(0, 7, ($ticket->driver_name ?: 'N/A'), 'B', 1, 'L', false, false, 1, false, '', 'T');

// Row 2: Make / Model & License
$pdf->Cell(40, 7, 'Make / Model:', 0, 0);
$pdf->Cell(75, 7, trim((($ticket->make ?: 'N/A') . ' ' . ($ticket->vehicle_model ?: 'N/A'))), 'B', 0, 'L', false, false, 1, false, '', 'T');
$pdf->Cell(30, 7, 'License No.:', 0, 0);
$pdf->Cell(0, 7, ($ticket->driver_license ?: 'N/A'), 'B', 1, 'L', false, false, 1, false, '', 'T');

// Row 3: Fuel Type (if available) & Color
$pdf->Cell(40, 7, 'Fuel Type:', 0, 0);
$fuelType = $ticket->fuel_type ?? 'N/A';
$pdf->Cell(75, 7, ucfirst($fuelType), 'B', 0, 'L', false, false, 1, false, '', 'T');
$pdf->Cell(30, 7, 'Color:', 0, 0);
$color = $ticket->color ?? 'N/A';
$pdf->Cell(0, 7, ucfirst($color), 'B', 1, 'L', false, false, 1, false, '', 'T');
```

### Key Improvements in Section II:

1. **Better Cell Dimensions:**
   - Label width: 35 → 40
   - Value width: 80 → 75
   - Cell height: 6 → 7

2. **Improved Labels:**
   - "Vehicle No." → "Plate Number" (more descriptive)

3. **Text Wrapping Enabled:**
   - Added `'T'` border mode to all value cells for text wrapping
   - Allows long text to wrap within cell boundaries

4. **Added New Fields:**
   - **Fuel Type:** Displays vehicle fuel type (Diesel, Gasoline, Electric, Hybrid)
   - **Color:** Displays vehicle color (replaces Location/Base since that column doesn't exist in database)

5. **Better Content Handling:**
   - Added `trim()` to Make/Model to remove extra spaces
   - Fallback values for missing data using null coalescing operator (`??`)

---

## Cell Parameters Explanation

TCPDF `Cell()` parameters used:
```php
Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $stretch, $ignore_min_height, $calign, $valign)
```

Key parameters for text wrapping:
- `$border = 'T'` - Top border only (prevents border duplication with background)
- `$stretch = 1` - Allow stretching to fill cell
- `$calign = 'T'` - Center align vertically from top (helps with wrapped text)

---

## Testing Instructions

1. **On Localhost:**
   ```
   http://localhost/Projects/loka2/public_html/?page=trip-tickets
   ```

2. **Steps to Test:**
   - Navigate to Trip Tickets page
   - Click on any trip ticket to view
   - Click "Export PDF" or "Download PDF" button
   - Verify the PDF displays with:
     - Properly sized cells
     - Text wrapping for long content
     - All new fields (Fuel Type, Location/Base)
     - Better formatted Date of Trip and Date Prepared

3. **Verify Vehicle Information Section:**
   - Plate Number should be clearly visible
   - Make/Model should wrap if too long
   - Fuel Type should display correctly (Diesel, Gasoline, Electric, or Hybrid)
   - Color should display correctly
   - Driver and License No. should be properly aligned

---

## Production Deployment

The same changes have been applied to both:
- `public_html/pages/trip-tickets/export-pdf.php` (development)
- `prod2prod/pages/trip-tickets/export-pdf.php` (production)

When deploying to production:
1. Upload the modified `prod2prod/pages/trip-tickets/export-pdf.php`
2. Verify the PDF generation works correctly
3. Test with various ticket types and data

---

## Known Limitations

1. **Fuel Type:**
   - Requires the `vehicles` table to have `fuel_type` column
   - If column doesn't exist, values will default to "N/A"
   - Valid values: diesel, gasoline, electric, hybrid (from database ENUM)

2. **Color:**
   - Requires the `vehicles` table to have `color` column
   - If column doesn't exist, values will default to "N/A"

2. **Text Wrapping:**
   - TCPDF text wrapping is basic; very long text may not wrap perfectly
   - Consider using `MultiCell()` for complex multi-line text if needed

---

## Future Enhancements

1. Consider using `MultiCell()` for fields that may contain longer text
2. Add unit tests for PDF generation
3. Implement PDF preview before download
4. Add customizable PDF templates
5. Support for multiple PDF layouts/styles

---

**End of Document**
