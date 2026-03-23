<?php
/**
 * Add deleted_at column to trip_tickets table
 */
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');

echo "=== Add deleted_at column to trip_tickets ===\n\n";

try {
    // Add deleted_at column
    $sql = "ALTER TABLE trip_tickets
    ADD COLUMN deleted_at DATETIME DEFAULT NULL
    AFTER updated_at";

    $pdo->exec($sql);
    echo "✓ Added deleted_at column to trip_tickets\n";

    // Add index for deleted_at
    $sql = "ALTER TABLE trip_tickets
    ADD INDEX idx_deleted_at (deleted_at)";

    $pdo->exec($sql);
    echo "✓ Added index on deleted_at\n";

    echo "\nMigration complete!\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "⚠ Column deleted_at already exists\n";
    } else {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
    }
}

$pdo = null;
