<?php
/**
 * Show All Completed Trips
 */

$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT r.id,
    r.start_datetime,
    r.end_datetime,
    r.destination,
    r.purpose,
    r.status,
    r.actual_arrival_datetime,
    r.actual_dispatch_datetime,
    r.mileage_actual,
    r.mileage_start,
    r.passenger_count,
    u.name as requester_name,
    u.email as requester_email,
    d.name as department_name,
    v.plate_number,
    v.make,
    v.model,
    driver_user.name as driver_name,
    dispatch_guard.name as dispatch_guard_name,
    arrival_guard.name as arrival_guard_name
FROM requests r
JOIN users u ON r.user_id = u.id
JOIN departments d ON r.department_id = d.id
LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
LEFT JOIN drivers dr ON r.driver_id = dr.id AND dr.deleted_at IS NULL
LEFT JOIN users driver_user ON dr.user_id = driver_user.id
LEFT JOIN users dispatch_guard ON r.dispatch_guard_id = dispatch_guard.id
LEFT JOIN users arrival_guard ON r.arrival_guard_id = arrival_guard.id
WHERE r.status = 'completed'
AND r.actual_arrival_datetime IS NOT NULL
AND r.deleted_at IS NULL
ORDER BY r.actual_arrival_datetime DESC";

$stmt = $pdo->query($sql);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== ALL COMPLETED TRIPS ===\n\n";
echo "Total: " . count($trips) . " completed trips\n";
echo "==========================================\n\n";

if (empty($trips)) {
    echo "No completed trips found.\n";
    exit;
}

foreach ($trips as $t) {
    echo "┌─ TRIP #" . $t['id'] . " ─────────────────────────────\n";
    echo "│ Completed: " . $t['actual_arrival_datetime'] . "\n";
    echo "│ \n";
    echo "│ Vehicle: " . ($t['plate_number'] ?? 'N/A') . "\n";
    if ($t['plate_number']) {
        echo "│          " . ($t['make'] ?? '') . " " . ($t['model'] ?? '') . "\n";
    }
    echo "│ \n";
    echo "│ Driver: " . ($t['driver_name'] ?? 'N/A') . "\n";
    echo "│ \n";
    echo "│ Requester: " . $t['requester_name'] . "\n";
    echo "│             " . $t['department_name'] . "\n";
    echo "│ \n";
    echo "│ Destination: " . $t['destination'] . "\n";
    echo "│ Purpose: " . substr($t['purpose'], 0, 60) . (strlen($t['purpose']) > 60 ? '...' : '') . "\n";
    echo "│ \n";
    echo "│ Scheduled Start: " . $t['start_datetime'] . "\n";
    echo "│ Scheduled End:   " . $t['end_datetime'] . "\n";
    echo "│ \n";
    if ($t['actual_dispatch_datetime']) {
        echo "│ Dispatched: " . $t['actual_dispatch_datetime'] . " by " . ($t['dispatch_guard_name'] ?? 'N/A') . "\n";
    }
    echo "│ Arrived:    " . $t['actual_arrival_datetime'] . " by " . ($t['arrival_guard_name'] ?? 'N/A') . "\n";
    echo "│ \n";
    echo "│ Mileage: " . ($t['mileage_actual'] ?: $t['mileage_start'] ?: 'N/A') . " km\n";
    echo "│ Passengers: " . ($t['passenger_count'] ?? 0) . "\n";
    echo "└─────────────────────────────────────────\n\n";
}

// Summary statistics
echo "\n=== SUMMARY ===\n";
$totalMileage = 0;
$hasMileage = 0;
foreach ($trips as $t) {
    if ($t['mileage_actual']) {
        $totalMileage += $t['mileage_actual'];
        $hasMileage++;
    }
}
echo "Total trips: " . count($trips) . "\n";
echo "Trips with mileage: " . $hasMileage . "\n";
echo "Total mileage: " . number_format($totalMileage) . " km\n";
echo "Average per trip: " . ($hasMileage > 0 ? number_format($totalMileage / $hasMileage) : 0) . " km\n";

// Count by department
echo "\n=== BY DEPARTMENT ===\n";
$deptCounts = [];
foreach ($trips as $t) {
    $dept = $t['department_name'];
    if (!isset($deptCounts[$dept])) $deptCounts[$dept] = 0;
    $deptCounts[$dept]++;
}
arsort($deptCounts);
foreach ($deptCounts as $dept => $count) {
    echo "  $dept: $count trips\n";
}

// Count by vehicle
echo "\n=== BY VEHICLE (Top 10) ===\n";
$vehicleCounts = [];
foreach ($trips as $t) {
    $vehicle = $t['plate_number'] ?? 'No Vehicle';
    if (!isset($vehicleCounts[$vehicle])) $vehicleCounts[$vehicle] = 0;
    $vehicleCounts[$vehicle]++;
}
arsort($vehicleCounts);
$count = 0;
foreach ($vehicleCounts as $vehicle => $num) {
    if ($count++ >= 10) break;
    echo "  $vehicle: $num trips\n";
}
