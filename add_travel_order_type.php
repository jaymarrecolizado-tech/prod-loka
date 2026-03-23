<?php
/**
 * Add 'travel_order' to trip_type ENUM
 */
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');

echo "=== Adding 'travel_order' to trip_type ENUM ===\n\n";

try {
    // Modify ENUM to include 'travel_order'
    $sql = "ALTER TABLE trip_tickets
    MODIFY COLUMN trip_type ENUM('official', 'personal', 'maintenance', 'travel_order', 'other')
    NOT NULL DEFAULT 'official'
    COMMENT 'Type of trip'";

    $pdo->exec($sql);
    echo "✓ Added 'travel_order' to trip_type ENUM\n";
    echo "\nNew ENUM values: official, personal, maintenance, travel_order, other\n";

} catch (PDOException $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

$pdo = null;
