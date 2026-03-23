<?php
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');
$stmt = $pdo->query('SELECT COUNT(*) as count FROM trip_tickets');
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo 'Trip tickets count: ' . $result['count'] . PHP_EOL;

if ($result['count'] > 0) {
    $stmt = $pdo->query('SELECT id, request_id, status, created_at FROM trip_tickets ORDER BY created_at DESC LIMIT 5');
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nRecent tickets:\n";
    foreach ($tickets as $ticket) {
        echo "  ID {$ticket['id']}: Request {$ticket['request_id']} - {$ticket['status']} - {$ticket['created_at']}\n";
    }
}
$pdo = null;
