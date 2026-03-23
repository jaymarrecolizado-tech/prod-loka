<?php
/**
 * Add trip_type_other column to trip_tickets table
 */
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');

echo "=== Adding trip_type_other column ===\n\n";

try {
    // Add column if it doesn't exist
    $sql = "ALTER TABLE trip_tickets
    ADD COLUMN trip_type_other VARCHAR(100) DEFAULT NULL
    COMMENT 'Custom trip type description when trip_type is other'
    AFTER trip_type";

    $pdo->exec($sql);
    echo "✓ Added trip_type_other column\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "⚠ Column trip_type_other already exists\n";
    } else {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
    }
}

$pdo = null;
