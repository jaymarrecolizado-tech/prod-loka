<?php
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');
$stmt = $pdo->query('SELECT * FROM drivers LIMIT 1');
if ($stmt->rowCount() > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Driver table has: " . implode(', ', array_keys($row)) . PHP_EOL;
}
$pdo = null;
