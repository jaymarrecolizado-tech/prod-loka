<?php
/**
 * LOKA - Add Vehicle ID to Trip Tickets Migration
 *
 * Adds vehicle_id column to trip_tickets table for Type 2 tickets
 */

// Load environment variables from .env file
$envFile = __DIR__ . '/../.env';
$dbHost = 'localhost';
$dbName = 'loka_fleet';
$dbUser = 'root';
$dbPass = '';
$dbCharset = 'utf8mb4';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            switch ($name) {
                case 'DB_HOST':
                    $dbHost = $value;
                    break;
                case 'DB_NAME':
                    $dbName = $value;
                    break;
                case 'DB_USER':
                    $dbUser = $value;
                    break;
                case 'DB_PASSWORD':
                    $dbPass = $value;
                    break;
                case 'DB_CHARSET':
                    $dbCharset = $value;
                    break;
            }
        }
    }
}

echo "=== Migration: Add Vehicle ID to Trip Tickets ===\n\n";

try {
    $pdo = new PDO(
        sprintf("mysql:host=%s;dbname=%s;charset=%s", $dbHost, $dbName, $dbCharset),
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Connected to database: " . $dbName . "\n\n";

    // Add vehicle_id column
    $sql = "ALTER TABLE trip_tickets
    ADD COLUMN vehicle_id INT UNSIGNED DEFAULT NULL
    COMMENT 'Vehicle ID (required for Type 2 tickets)'
    AFTER request_id";

    try {
        $pdo->exec($sql);
        echo "✓ Added vehicle_id column to trip_tickets table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠ Column vehicle_id already exists\n";
        } else {
            throw $e;
        }
    }

    // Add foreign key
    $sql = "ALTER TABLE trip_tickets
    ADD CONSTRAINT fk_trip_tickets_vehicle
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL";

    try {
        $pdo->exec($sql);
        echo "✓ Added foreign key constraint for vehicle_id\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate foreign key constraint') !== false) {
            echo "⚠ Foreign key constraint already exists\n";
        } else {
            // Try adding without constraint name
            try {
                $sql = "ALTER TABLE trip_tickets
                ADD FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL";
                $pdo->exec($sql);
                echo "✓ Added foreign key constraint for vehicle_id\n";
            } catch (PDOException $e2) {
                echo "⚠ Could not add foreign key: " . $e2->getMessage() . "\n";
            }
        }
    }

    // Add index
    $sql = "ALTER TABLE trip_tickets
    ADD INDEX IF NOT EXISTS idx_vehicle_id (vehicle_id)";

    try {
        $pdo->exec($sql);
        echo "✓ Added index on vehicle_id\n";
    } catch (PDOException $e) {
        echo "⚠ Index may already exist\n";
    }

    // Backfill vehicle_id from request_id for Type 1 tickets
    $sql = "UPDATE trip_tickets tt
    JOIN requests r ON tt.request_id = r.id
    SET tt.vehicle_id = r.vehicle_id
    WHERE tt.ticket_type = 'type1' OR tt.vehicle_id IS NULL";

    $affected = $pdo->exec($sql);
    echo "✓ Backfilled vehicle_id for {$affected} existing tickets\n";

    echo "\n" . str_repeat('=', 50) . "\n";
    echo "MIGRATION COMPLETE\n";
    echo str_repeat('=', 50) . "\n\n";

} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
