<?php
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Tables with 'passenger':\n";
foreach ($tables as $t) {
    if (strpos(strtolower($t), 'passenger') !== false) {
        echo "  - $t\n";
    }
}
echo "\nTables with 'request':\n";
foreach ($tables as $t) {
    if (strpos(strtolower($t), 'request') !== false) {
        echo "  - $t\n";
    }
}
$pdo = null;
