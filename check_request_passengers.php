<?php
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');
$stmt = $pdo->query('DESCRIBE request_passengers');
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "request_passengers table structure:\n";
foreach ($result as $col) {
    echo "  {$col['Field']} - {$col['Type']}\n";
}

echo "\nSample data:\n";
$stmt = $pdo->query('SELECT * FROM request_passengers LIMIT 5');
$passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($passengers as $p) {
    echo "  Request ID: {$p['request_id']}, Name: {$p['passenger_name']}\n";
}

echo "\nExample request with multiple passengers:\n";
$stmt = $pdo->query('SELECT r.id, r.passenger_count FROM requests r WHERE r.passenger_count > 1 LIMIT 3');
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($requests as $r) {
    echo "\n  Request {$r['id']} ({$r['passenger_count']} passengers):\n";
    $pstmt = $pdo->prepare('SELECT passenger_name FROM request_passengers WHERE request_id = ?');
    $pstmt->execute([$r['id']]);
    $pass_list = $pstmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pass_list as $p) {
        echo "    - {$p['passenger_name']}\n";
    }
}
$pdo = null;
