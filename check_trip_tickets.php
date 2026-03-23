<?php
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');
$stmt = $pdo->query('SHOW TABLES LIKE "trip_tickets"');
if ($stmt->rowCount() > 0) {
    echo "trip_tickets table EXISTS in lokaloka2\n";
} else {
    echo "trip_tickets table DOES NOT EXIST in lokaloka2\n";
}
$pdo = null;
