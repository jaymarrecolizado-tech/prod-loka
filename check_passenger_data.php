<?php
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');

echo "Sample request_passengers data:\n";
$stmt = $pdo->query('SELECT * FROM request_passengers LIMIT 10');
$passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($passengers as $p) {
    echo "  Request ID: {$p['request_id']}, User ID: {$p['user_id']}, Guest: {$p['guest_name']}\n";
}

echo "\nExample request with multiple passengers:\n";
$stmt = $pdo->query('SELECT r.id, r.passenger_count FROM requests r WHERE r.passenger_count > 1 LIMIT 2');
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($requests as $r) {
    echo "\n  Request {$r['id']} ({$r['passenger_count']} passengers):\n";
    $pstmt = $pdo->prepare('
        SELECT rp.user_id, rp.guest_name, u.name as user_name
        FROM request_passengers rp
        LEFT JOIN users u ON rp.user_id = u.id
        WHERE rp.request_id = ?
    ');
    $pstmt->execute([$r['id']]);
    $pass_list = $pstmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pass_list as $p) {
        if ($p['user_id']) {
            echo "    - {$p['user_name']} (User ID: {$p['user_id']})\n";
        } else {
            echo "    - {$p['guest_name']} (Guest)\n";
        }
    }
}

echo "\n\nChecking how driver is stored:\n";
$stmt = $pdo->query('SELECT r.id, r.driver_id, d.user_id, u.name as driver_name FROM requests r LEFT JOIN drivers d ON r.driver_id = d.id LEFT JOIN users u ON d.user_id = u.id LIMIT 3');
$driver_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($driver_data as $d) {
    echo "  Request {$d['id']}: Driver ID {$d['driver_id']} -> User ID {$d['user_id']} -> {$d['driver_name']}\n";
}

$pdo = null;
