<?php
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');

echo "=== Requests Table (VRF) ===\n";
$stmt = $pdo->query('SELECT id, destination, passenger_count FROM requests ORDER BY id DESC LIMIT 3');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "VRF-{$row['id']} - {$row['destination']} - Passengers: {$row['passenger_count']}\n";
}

echo "\n=== Request Passengers Table Structure ===\n";
$stmt = $pdo->query('DESCRIBE request_passengers');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\n=== Recent Request Passengers ===\n";
$stmt = $pdo->query('SELECT * FROM request_passengers LIMIT 5');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

$pdo = null;
