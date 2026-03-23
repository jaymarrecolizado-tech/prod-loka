<?php
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');
$stmt = $pdo->query('DESCRIBE requests');
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Passenger-related columns in requests table:\n";
foreach ($result as $col) {
    if (strpos($col['Field'], 'passenger') !== false) {
        echo $col['Field'] . ' - ' . $col['Type'] . ' - ' . $col['Comment'] . "\n";
    }
}

echo "\nSample passenger data:\n";
$stmt = $pdo->query('SELECT id, passenger_count, passenger_names FROM requests WHERE passenger_count > 0 LIMIT 5');
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($requests as $req) {
    echo "Request {$req['id']}: Count={$req['passenger_count']}, Names={$req['passenger_names']}\n";
}
$pdo = null;
