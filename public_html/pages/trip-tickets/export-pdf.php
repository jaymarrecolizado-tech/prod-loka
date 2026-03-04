<?php
/**
 * LOKA - Export Trip Ticket to PDF
 *
 * Generates a printable trip ticket matching the official DICT format
 * Landscape, 1 page, proper signatory order, with passengers from VRF
 */

require_once BASE_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';

$ticketId = (int) get('id', 0);
if (!$ticketId) {
    die('Invalid ticket ID.');
}

// Get ticket with full details
$ticket = db()->fetch(
    "SELECT tt.*,
            r.id as request_id, r.destination as trip_destination, r.purpose as trip_purpose,
            r.actual_dispatch_datetime, r.actual_arrival_datetime,
            d.license_number as driver_license, du.name as driver_name, du.phone as driver_phone,
            u_req.name as requester_name, u_req.email as requester_email, u_req.phone as requester_phone,
            dg.name as dispatch_guard, dg.phone as dispatch_guard_phone,
            ag.name as arrival_guard, ag.phone as arrival_guard_phone,
            u_rev.name as reviewed_by_name, u_rev.email as reviewed_by_email,
            v.plate_number, v.make, v.model as vehicle_model, v.color,
            dept.name as department_name,
            mph.name as motorpool_head_name
     FROM trip_tickets tt
     JOIN requests r ON tt.request_id = r.id
     LEFT JOIN drivers d ON tt.driver_id = d.id
     LEFT JOIN users du ON d.user_id = du.id
     LEFT JOIN users u_req ON r.user_id = u_req.id
     LEFT JOIN departments dept ON u_req.department_id = dept.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN users dg ON tt.dispatch_guard_id = dg.id
     LEFT JOIN users ag ON tt.arrival_guard_id = ag.id
     LEFT JOIN users u_rev ON tt.reviewed_by = u_rev.id
     LEFT JOIN users mph ON r.motorpool_head_id = mph.id
     WHERE tt.id = ? AND tt.deleted_at IS NULL",
    [$ticketId]
);

if (!$ticket) {
    die('Ticket not found.');
}

// Check permission
if (isDriver()) {
    $currentDriverId = db()->fetchColumn(
        "SELECT id FROM drivers WHERE user_id = ? AND deleted_at IS NULL",
        [userId()]
    );
    if ($ticket->driver_id != $currentDriverId) {
        die('You can only export your own trip tickets.');
    }
}

// Get passengers from the original VRF (request)
$passengers = db()->fetchAll(
    "SELECT rp.id,
            CASE
                WHEN rp.user_id IS NOT NULL THEN u.name
                ELSE rp.guest_name
            END as passenger_name,
            u.phone as passenger_phone
     FROM request_passengers rp
     LEFT JOIN users u ON rp.user_id = u.id
     WHERE rp.request_id = ?
     ORDER BY rp.id ASC",
    [$ticket->request_id]
);

// Trip type labels
$tripTypeLabels = [
    'official' => 'Official Business',
    'personal' => 'Personal',
    'maintenance' => 'Maintenance',
    'travel_order' => 'Travel Order',
    'other' => 'Other'
];
$tripTypeInfo = $tripTypeLabels[$ticket->trip_type] ?? 'Official Business';
if ($ticket->trip_type === 'other' && !empty($ticket->trip_type_other)) {
    $tripTypeInfo = $ticket->trip_type_other;
}

// Create PDF - LANDSCAPE LEGAL
$pdf = new TCPDF('L', PDF_UNIT, 'LEGAL', true, 'UTF-8', false);
$pdf->SetCreator('LOKA Fleet Management System');
$pdf->SetAuthor('DICT - Region II');
$pdf->SetTitle('Trip Ticket TT-' . $ticket->request_id);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 8, 10);
$pdf->SetAutoPageBreak(FALSE, 0);
$pdf->AddPage();

// ===== HEADER SECTION =====
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 4, 'Republic of the Philippines', 0, 1, 'C');

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 5, 'DEPARTMENT OF INFORMATION AND COMMUNICATIONS TECHNOLOGY', 0, 1, 'C');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 4, 'REGION II - CAGAYAN VALLEY', 0, 1, 'C');

$pdf->Ln(2);

// ===== TITLE SECTION =====
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetFillColor(13, 110, 253);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, '  T R I P   T I C K E T ', 0, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

// ===== TICKET INFO =====
$pdf->SetFont('helvetica', '', 9);
$vrfNo = 'VRF-' . $ticket->request_id;
$ttNo = 'TT-' . $ticket->request_id;

$pdf->Cell(35, 5, 'Ticket No.:', 0, 0);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(45, 5, $ttNo . ' (Ref: ' . $vrfNo . ')', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(18, 5, 'Date:', 0, 0);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, date('F j, Y'), 0, 1);
$pdf->Ln(2);

// ===== MAIN CONTENT BOX =====
$contentY = $pdf->GetY();
$pdf->Rect(8, $contentY, 280, 170, 'D');

// ===== LEFT SIDE (Trip & Driver Info) =====
$leftX = 10;
$leftWidth = 130;

// TRIP INFORMATION
$pdf->SetXY($leftX, $contentY + 3);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell($leftWidth, 5, '  I. TRIP INFORMATION', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$tripY = $pdf->GetY() + 1;

$pdf->SetXY($leftX + 3, $tripY);
$pdf->Cell(35, 4, 'Date of Trip:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(50, 4, date('F j, Y', strtotime($ticket->start_date)), 0, 1);

$pdf->SetXY($leftX + 3, $tripY + 5);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(35, 4, 'Vehicle No.:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(50, 4, $ticket->plate_number ?: 'N/A', 0, 1);

$pdf->SetXY($leftX + 3, $tripY + 10);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(35, 4, 'Destination:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(60, 4, $ticket->destination, 0, 1);

$pdf->SetXY($leftX + 3, $tripY + 15);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(35, 4, 'Purpose:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(60, 4, truncate($ticket->purpose ?: 'N/A', 55), 0, 1);

$pdf->SetXY($leftX + 3, $tripY + 20);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(35, 4, 'Type of Trip:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(60, 4, $tripTypeInfo, 0, 1);

// DRIVER INFORMATION
$driverY = $tripY + 27;
$pdf->SetXY($leftX, $driverY);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell($leftWidth, 5, '  II. DRIVER INFORMATION', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$driverRowY = $pdf->GetY() + 1;

$pdf->SetXY($leftX + 3, $driverRowY);
$pdf->Cell(30, 4, 'Name:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(70, 4, $ticket->driver_name, 0, 1);

$pdf->SetXY($leftX + 3, $driverRowY + 5);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(30, 4, 'License No.:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(50, 4, $ticket->driver_license ?: 'N/A', 0, 1);

$pdf->SetXY($leftX + 3, $driverRowY + 10);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(30, 4, 'Department:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(70, 4, $ticket->department_name ?: 'N/A', 0, 1);

// PASSENGERS WITH SIGNATURES
$passengerY = $driverRowY + 17;
$pdf->SetXY($leftX, $passengerY);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell($leftWidth, 5, '  III. PASSENGERS', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($leftX + 3, $pdf->GetY() + 1);
$pdf->Cell(50, 4, 'No. of Passengers: ' . count($passengers), 0, 1);
$pdf->Ln(2);

if (!empty($passengers)) {
    foreach ($passengers as $index => $p) {
        $pY = $pdf->GetY();
        $num = $index + 1;

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(20, 4, "Passenger {$num}:", 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(80, 4, $p->passenger_name ?: '(Guest)', 0, 0);
        $pdf->Cell(30, 4, 'Date: _______', 0, 1);

        $sigY = $pdf->GetY() + 1;
        $pdf->SetX($leftX + 3);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(110, 4, 'Signature: _____________________________', 0, 1);

        // Make sure we don't exceed page
        if ($pdf->GetY() > 200) break;
    }
}

// ===== RIGHT SIDE (Odometer, Time, Fuel) =====
$rightX = 145;
$rightWidth = 60;

// ODOMETER
$pdf->SetXY($rightX, $driverY);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell($rightWidth, 5, '  IV. ODOMETER', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$odoY = $pdf->GetY() + 1;

$pdf->SetXY($rightX + 2, $odoY);
$pdf->Cell(25, 4, 'Start:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(28, 4, ($ticket->start_mileage ? number_format($ticket->start_mileage) : '______'), 0, 1);

$pdf->SetXY($rightX + 2, $odoY + 5);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(25, 4, 'End:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(28, 4, ($ticket->end_mileage ? number_format($ticket->end_mileage) : '______'), 0, 1);

$pdf->SetXY($rightX + 2, $odoY + 10);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(25, 4, 'Total:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(28, 4, ($ticket->distance_traveled ? number_format($ticket->distance_traveled) : '______'), 0, 1);

// TIME
$pdf->SetXY($rightX, $pdf->GetY() + 2);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell($rightWidth, 5, '  V. TIME RECORD', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$timeY = $pdf->GetY() + 1;

$pdf->SetXY($rightX + 2, $timeY);
$pdf->Cell(25, 4, 'Departed:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(30, 4, ($ticket->start_date ? date('h:i A', strtotime($ticket->start_date)) : '_____'), 0, 1);

$pdf->SetXY($rightX + 2, $timeY + 5);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(25, 4, 'Returned:', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(30, 4, ($ticket->end_date ? date('h:i A', strtotime($ticket->end_date)) : '_____'), 0, 1);

// FUEL & REFILLING
$pdf->SetXY($rightX, $pdf->GetY() + 2);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell($rightWidth, 5, '  VI. FUEL & REFILLING', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 7);
$fuelY = $pdf->GetY() + 1;

$pdf->SetXY($rightX + 2, $fuelY);
$pdf->Cell(25, 3, 'Consumed:', 0, 0);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell(30, 3, ($ticket->fuel_consumed ? number_format($ticket->fuel_consumed, 1) . ' L' : '______'), 0, 1);

$pdf->SetXY($rightX + 2, $fuelY + 4);
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell(25, 3, 'Amount:', 0, 0);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell(30, 3, ($ticket->fuel_cost ? 'P ' . number_format($ticket->fuel_cost, 2) : 'P_____'), 0, 1);

$pdf->SetXY($rightX + 2, $fuelY + 8);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->Cell(55, 3, 'Gas Station: ______________________', 0, 1);

$pdf->SetXY($rightX + 2, $fuelY + 11);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->Cell(55, 3, 'Amount Refilled: P ______________', 0, 1);

$pdf->SetXY($rightX + 2, $fuelY + 14);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->Cell(55, 3, 'Odo. Before Refill: ______ km', 0, 1);

$pdf->SetXY($rightX + 2, $fuelY + 17);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->Cell(55, 3, 'Odo. After Refill: ______ km', 0, 1);

// ===== DRIVER SIGNATURE (1st Signatory) =====
$driverSignY = $pdf->GetY() + 5;
$pdf->SetXY($leftX, $driverSignY);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell($leftWidth, 30, '', 0, 1, 'L', true);

$pdf->SetXY($leftX + 3, $driverSignY + 3);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 4, '1. DRIVER CERTIFICATION', 0, 1);

$pdf->SetFont('helvetica', '', 7);
$pdf->SetXY($leftX + 3, $driverSignY + 8);
$pdf->Cell(50, 4, 'Name: ' . $ticket->driver_name, 0, 1);

$pdf->SetXY($leftX + 3, $driverSignY + 12);
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell(50, 4, 'Date: __________________', 0, 1);

$pdf->SetXY($leftX + 3, $driverSignY + 16);
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell(70, 4, 'Signature: _____________________________', 0, 1);

// ===== SIGNATORY CLEARANCE =====
$clearanceY = $driverSignY + 28;
$pdf->SetXY($leftX, $clearanceY);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(13, 110, 253);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(270, 7, '  S I G N A T O R Y   C L E A R A N C E ', 0, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(1);

$signY = $pdf->GetY();
$signColWidth = 130;

// ===== 2. MOTORPOOL HEAD =====
$pdf->SetXY($leftX, $signY);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($signColWidth, 5, '2. MOTORPOOL HEAD', 0, 1);

$pdf->SetFont('helvetica', '', 7);
$pdf->SetX($leftX);
$pdf->MultiCell($signColWidth - 3, 3, 'Verifies trip details and vehicle condition.', 0, 'L');

$pdf->SetX($leftX);
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell(30, 4, 'Name: ___________________', 0, 0);
$pdf->Cell(25, 4, 'Date: _______', 0, 1);

$pdf->SetX($leftX);
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell(70, 4, 'Signature: _____________________________', 0, 1);

// ===== 3. ADMIN & FINANCE DIVISION CHIEF =====
$pdf->SetXY($leftX + $signColWidth, $signY);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($signColWidth, 5, '3. ADMIN & FINANCE DIVISION CHIEF', 0, 1);

$pdf->SetFont('helvetica', '', 7);
$pdf->SetX($leftX + $signColWidth);
$pdf->MultiCell($signColWidth - 3, 3, 'Reviews and approves the trip ticket. Certifies documents are complete and processes for payment.', 0, 'L');

$pdf->SetX($leftX + $signColWidth);
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell(30, 4, 'Name: ___________________', 0, 0);
$pdf->Cell(25, 4, 'Date: _______', 0, 1);

$pdf->SetX($leftX + $signColWidth);
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell(70, 4, 'Signature: _____________________________', 0, 1);

// ===== NOTES SECTION =====
$notesY = $signY + 20;
$pdf->SetXY($leftX, $notesY);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->MultiCell(270, 3, "NOTES: 1) This document must be signed by all parties. 2) Attach TO/OB Slip if applicable. 3) Submit to Finance for processing.", 0, 'L');

// ===== FOOTER =====
$pdf->SetY(-15);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 4, 'Generated by LOKA Fleet Management System | ' . date('F j, Y g:i A'), 0, 1, 'C', true);

$pdf->SetY(-10);
$pdf->SetFont('helvetica', '', 6);
$pdf->Cell(0, 4, 'DICT - Region II | ' . date('Y'), 0, 1, 'C');

// Output PDF
$filename = 'Trip_Ticket_TT-' . $ticket->request_id . '_VRF-' . $ticket->request_id . '_' . date('Y-m-d');
$pdf->Output($filename . '.pdf', 'D');
exit;
