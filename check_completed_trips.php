<?php
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');

echo "Completed requests with passengers:\n\n";

$stmt = $pdo->query("SELECT r.id, r.destination, r.status, r.passenger_count,
       v.plate_number,
       du.name as trip_driver_name,
       COALESCE(r.actual_dispatch_datetime, r.start_datetime) as start_date
FROM requests r
LEFT JOIN vehicles v ON r.vehicle_id = v.id
LEFT JOIN drivers d ON r.driver_id = d.id
LEFT JOIN users du ON d.user_id = du.id
WHERE r.status = 'completed' AND r.passenger_count > 0
ORDER BY r.id DESC
LIMIT 5");

$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($trips) === 0) {
    echo "No completed trips with passengers found.\n";
    exit;
}

foreach ($trips as $t) {
    echo "Request ID: {$t['id']}\n";
    echo "Vehicle: {$t['plate_number']}\n";
    echo "Destination: {$t['destination']}\n";
    echo "Date: {$t['start_date']}\n";
    echo "Driver: {$t['trip_driver_name']}\n";
    echo "Passenger count: {$t['passenger_count']}\n";

    // Fetch passengers
    $pstmt = $pdo->prepare("SELECT
            CASE
                WHEN rp.user_id IS NOT NULL THEN u.name
                ELSE rp.guest_name
            END as name
        FROM request_passengers rp
        LEFT JOIN users u ON rp.user_id = u.id
        WHERE rp.request_id = ?");
    $pstmt->execute([$t['id']]);
    $passengers = $pstmt->fetchAll(PDO::FETCH_COLUMN);

    echo "People:\n";
    echo "  - {$t['trip_driver_name']} (Driver)\n";
    foreach ($passengers as $p) {
        echo "  - $p (Passenger)\n";
    }
    echo "\n";
}
$pdo = null;
