<?php
/**
 * Test page for Travel Order template
 * No authentication required - uses sample data
 */

// Simple test configuration
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');

// Load minimal dependencies
require_once BASE_PATH . '/config/bootstrap.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/classes/Database.php';
require_once BASE_PATH . '/classes/Auth.php';
require_once BASE_PATH . '/includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current user or use test values
$generatorName = currentUser() ? currentUser()->name : 'Juan P. Dela Cruz';
$isPrint = get('print') == '1';
$templateType = get('template', 'travelorder');

// Sample vehicle info
$vInfo = (object)[
    'plate_number' => 'ABC 1234',
    'make' => 'Toyota',
    'model' => 'Fortuner',
    'fuel_type' => 'Diesel',
    'color' => 'Black'
];

// Sample trips
$trips = [
    (object)[
        'req_id' => 1001,
        'destination' => 'Cagayan State University, Tuguegarao City',
        'purpose' => 'Meeting with university IT department regarding network infrastructure assessment and proposed collaboration on digital literacy program.',
        'start_date' => date('Y-m-d') . ' 08:00:00',
        'end_date' => date('Y-m-d') . ' 17:00:00',
        'start_mileage' => 15420,
        'end_mileage' => 15520,
        'distance_traveled' => 100,
        'fuel_consumed' => 12.5,
        'fuel_cost' => 937.50,
        'all_people' => [
            ['name' => 'Juan P. Dela Cruz', 'role' => 'Driver'],
            ['name' => 'Maria S. Santos', 'role' => 'Passenger'],
            ['name' => 'Pedro M. Reyes', 'role' => 'Passenger'],
        ]
    ],
    (object)[
        'req_id' => 1002,
        'destination' => 'DPWH Regional Office, Tuguegarao',
        'purpose' => 'Coordination meeting for ICT project implementation and budget coordination.',
        'start_date' => date('Y-m-d', strtotime('+1 day')) . ' 09:00:00',
        'end_date' => date('Y-m-d', strtotime('+1 day')) . ' 12:00:00',
        'start_mileage' => 15520,
        'end_mileage' => 15545,
        'distance_traveled' => 25,
        'fuel_consumed' => 3.5,
        'fuel_cost' => 262.50,
        'all_people' => [
            ['name' => 'Juan P. Dela Cruz', 'role' => 'Driver'],
            ['name' => 'Ana L. Garcia', 'role' => 'Passenger'],
        ]
    ]
];

// Calculate totals
$totalDist = 0;
$totalFuel = 0;
$totalCost = 0;
foreach ($trips as $t) {
    $totalDist += $t->distance_traveled;
    $totalFuel += $t->fuel_consumed;
    $totalCost += $t->fuel_cost;
}

// Trip ticket number
$dateFrom = date('Y-m-01');
$dateTo = date('Y-m-t');
$tripTicketNumber = date('Y') . '-ABC1234-' . date('m') . '01';

// Sample fuel entries
$fuelEntries = [
    ['date' => date('Y-m-d'), 'qty' => 15.0, 'amt' => 1125.00, 'items' => '', 'remarks' => 'GAS Voucher #12345']
];

// Sample guards and drivers for dropdowns
$guards = [(object)['name' => 'Guard A. Test']];
$drivers = [(object)['name' => 'Juan P. Dela Cruz']];

if ($isPrint) {
    if ($templateType === 'travelorder') {
        require_once __DIR__ . '/summary-print-travelorder.php';
    } else {
        require_once __DIR__ . '/summary-print.php';
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Travel Order Template</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding: 20px; }
    </style>
</head>
<body>
    <div class="max-w-[600px] mx-auto mb-5">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <div class="bg-primary text-primary-content rounded-t-lg px-4 py-3">
                    <h5 class="font-bold"><i class="bi bi-printer me-2"></i>Test Travel Order Template</h5>
                </div>
                <div class="p-4">
                    <p class="text-base-content/60 mb-3">This is a test page using sample data to preview the template.</p>
                    
                    <div class="mb-3">
                        <label class="label"><span class="label-text font-medium">Select Template:</span></label>
                        <div class="grid grid-cols-1 gap-2">
                            <a href="?template=travelorder&print=1" target="_blank" class="loka-btn-outline-primary">
                                <i class="bi bi-file-text me-2"></i>Preview Travel Order Template
                            </a>
                            <a href="?template=vehicle&print=1" target="_blank" class="loka-btn-secondary">
                                <i class="bi bi-car-front me-2"></i>Preview Vehicle Trip Ticket Template
                            </a>
                        </div>
                    </div>

                    <hr class="my-4 border-base-300">

                    <h6 class="font-semibold">Current Test Data:</h6>
                    <ul class="text-sm text-base-content/60 space-y-1 mt-2">
                        <li><strong>Vehicle:</strong> <?= $vInfo->plate_number ?> - <?= $vInfo->make ?> <?= $vInfo->model ?></li>
                        <li><strong>Driver:</strong> <?= $generatorName ?></li>
                        <li><strong>Date Range:</strong> <?= date('M d, Y', strtotime($dateFrom)) ?> - <?= date('M d, Y', strtotime($dateTo)) ?></li>
                        <li><strong>Total Distance:</strong> <?= number_format($totalDist) ?> km</li>
                        <li><strong>Total Fuel:</strong> <?= number_format($totalFuel, 2) ?> L</li>
                        <li><strong>No. of Trips:</strong> <?= count($trips) ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>