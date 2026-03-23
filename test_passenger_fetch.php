<?php
require_once 'public_html/config/bootstrap.php';

echo "Testing passenger fetch for trip tickets...\n\n";

// Find a request with multiple passengers
$pdo = Database::getInstance()->getConnection();

$stmt = $pdo->query("SELECT r.id, r.destination, r.passenger_count,
       du.name as trip_driver_name
FROM requests r
LEFT JOIN drivers d ON r.driver_id = d.id
LEFT JOIN users du ON d.user_id = du.id
WHERE r.passenger_count > 0
ORDER BY r.id DESC
LIMIT 3");

$trips = $stmt->fetchAll(PDO::FETCH_OBJ);

foreach ($trips as $t) {
    echo "Request ID: {$t->id}\n";
    echo "Destination: {$t->destination}\n";
    echo "Passenger count: {$t->passenger_count}\n";
    echo "Driver: {$t->trip_driver_name}\n";

    // Fetch passengers
    $passenger_sql = "SELECT
            CASE
                WHEN rp.user_id IS NOT NULL THEN u.name
                ELSE rp.guest_name
            END as name,
            CASE
                WHEN rp.user_id IS NOT NULL THEN CONCAT(u.name, ' (Passenger)')
                ELSE CONCAT(rp.guest_name, ' (Guest)')
            END as display_name
        FROM request_passengers rp
        LEFT JOIN users u ON rp.user_id = u.id
        WHERE rp.request_id = ?";
    $pstmt = $pdo->prepare($passenger_sql);
    $pstmt->execute([$t->id]);
    $passengers_list = $pstmt->fetchAll(PDO::FETCH_OBJ);

    echo "People on trip:\n";
    $all_people = [];
    if ($t->trip_driver_name) {
        $all_people[] = ['name' => $t->trip_driver_name, 'role' => 'Driver'];
        echo "  1. {$t->trip_driver_name} (Driver)\n";
    }
    foreach ($passengers_list as $p) {
        $role = (strpos($p->display_name, '(Guest)') !== false) ? 'Guest' : 'Passenger';
        $all_people[] = ['name' => $p->name, 'role' => $role];
        echo "  " . (count($all_people)) . ". {$p->name} ({$role})\n";
    }
    echo "\n";
}
