<?php
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
try {
    $pdo->exec('ALTER TABLE gas_vouchers ADD COLUMN other_qty DECIMAL(10,2) DEFAULT NULL AFTER other_items');
    echo "Added other_qty\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $pdo->exec('ALTER TABLE gas_vouchers ADD COLUMN other_unit VARCHAR(20) DEFAULT NULL AFTER other_qty');
    echo "Added other_unit\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }
echo "Done.\n";
