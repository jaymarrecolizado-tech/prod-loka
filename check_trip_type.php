<?php
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');
$stmt = $pdo->query("SHOW COLUMNS FROM trip_tickets LIKE 'trip_type'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Current ENUM values: " . $row['Type'] . "\n";
$pdo = null;
