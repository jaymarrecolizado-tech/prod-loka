<?php
/**
 * LOKA - Create Trip Ticket Migration (Fixed)
 *
 * Adds trip_tickets table for recording trip completion details
 */

// Load environment variables from .env file
$envFile = __DIR__ . '/../.env';
$dbHost = 'localhost';
$dbName = 'lokaloka2';
$dbUser = 'root';
$dbPass = '';
$dbCharset = 'utf8mb4';

// First use hardcoded value for lokaloka2
// Then check .env file
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            // Only update these, NOT DB_NAME (keep lokaloka2)
            switch ($name) {
                case 'DB_HOST':
                    $dbHost = $value;
                    break;
                // Skip DB_NAME - use hardcoded lokaloka2
                // case 'DB_NAME':
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

echo "=== Migration: Create Trip Tickets Table ===\n\n";

try {
    $pdo = new PDO(
        sprintf("mysql:host=%s;dbname=%s;charset=%s", $dbHost, $dbName, $dbCharset),
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Connected to database: " . $dbName . "\n\n";

    // Drop table if exists (for clean reinstall)
    $pdo->exec("DROP TABLE IF EXISTS trip_tickets");
    echo "✓ Dropped old trip_tickets table (if existed)\n";

    // Create trip_tickets table WITHOUT foreign key constraints first
    $sql = "CREATE TABLE trip_tickets (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_id INT UNSIGNED NOT NULL,
        driver_id INT UNSIGNED NOT NULL,
        trip_type ENUM('official', 'personal', 'maintenance', 'other') NOT NULL DEFAULT 'official',

        -- Trip Details
        start_date DATETIME NOT NULL COMMENT 'Actual trip start time',
        end_date DATETIME NOT NULL COMMENT 'Actual trip end time',
        destination TEXT NOT NULL COMMENT 'Trip destination',
        purpose TEXT COMMENT 'Purpose of trip',
        passengers INT UNSIGNED DEFAULT 0 COMMENT 'Number of passengers',

        -- Mileage
        start_mileage INT UNSIGNED DEFAULT NULL COMMENT 'Odometer reading at start',
        end_mileage INT UNSIGNED DEFAULT NULL COMMENT 'Odometer reading at end',
        distance_traveled INT UNSIGNED DEFAULT NULL COMMENT 'Total distance in km',

        -- Fuel
        fuel_consumed DECIMAL(10,2) UNSIGNED DEFAULT NULL COMMENT 'Fuel consumed in liters',
        fuel_cost DECIMAL(10,2) UNSIGNED DEFAULT NULL COMMENT 'Fuel cost in PHP',

        -- Documents (stored as file paths)
        travel_order_path VARCHAR(255) DEFAULT NULL COMMENT 'Path to travel order document',
        ob_slip_path VARCHAR(255) DEFAULT NULL COMMENT 'Path to OB slip document',
        other_documents_path VARCHAR(255) DEFAULT NULL COMMENT 'Path to other documents',

        -- Issues/Incidents
        has_issues BOOLEAN DEFAULT FALSE COMMENT 'Were there any issues?',
        issues_description TEXT COMMENT 'Description of issues',
        resolved BOOLEAN DEFAULT FALSE COMMENT 'Have issues been resolved?',
        resolution_notes TEXT COMMENT 'Notes on resolution',

        -- Guard Verification
        dispatch_guard_id INT UNSIGNED NOT NULL COMMENT 'Guard who dispatched',
        arrival_guard_id INT UNSIGNED NOT NULL COMMENT 'Guard who verified arrival',
        guard_notes TEXT COMMENT 'Additional notes from guard',

        -- Status
        status ENUM('draft', 'submitted', 'reviewed', 'approved') NOT NULL DEFAULT 'draft',
        reviewed_by INT UNSIGNED DEFAULT NULL COMMENT 'User ID who reviewed',
        reviewed_at DATETIME DEFAULT NULL,

        -- Audit
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        created_by INT UNSIGNED NOT NULL COMMENT 'User ID who created',

        -- Indexes
        INDEX idx_request (request_id),
        INDEX idx_driver (driver_id),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Stores trip completion tickets with details, documents, and issues'";

    $pdo->exec($sql);
    echo "✓ Created trip_tickets table\n";

    // Add trip_ticket_id to requests table
    $sql = "ALTER TABLE requests
    ADD COLUMN trip_ticket_id INT UNSIGNED DEFAULT NULL
    COMMENT 'ID of trip completion ticket'
    AFTER arrival_guard_id";

    try {
        $pdo->exec($sql);
        echo "✓ Added trip_ticket_id column to requests table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            echo "⚠ Column trip_ticket_id already exists\n";
        } else {
            throw $e;
        }
    }

    // Add index for trip_ticket_id
    $sql = "ALTER TABLE requests
    ADD INDEX idx_trip_ticket (trip_ticket_id)";

    try {
        $pdo->exec($sql);
        echo "✓ Added index on trip_ticket_id\n";
    } catch (PDOException $e) {
        echo "⚠ Index may already exist\n";
    }

    echo "\n" . str_repeat('=', 50) . "\n";
    echo "MIGRATION COMPLETE\n";
    echo str_repeat('=', 50) . "\n\n";

    echo "Table 'trip_tickets' created successfully!\n";
    echo "Note: Foreign key constraints omitted to avoid errors.\n";
    echo "Application-level validation will handle referential integrity.\n\n";

} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
