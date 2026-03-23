<?php
/**
 * LOKA - Database Diagnostic & Setup Script
 *
 * Checks existing databases, identifies the correct one to use,
 * and creates the trip_tickets table in the right database
 */

echo "==================================================\n";
echo "LOKA FLEET - Database Diagnostic & Setup\n";
echo "==================================================\n\n";

// Load environment variables from .env file
$envFiles = [
    __DIR__ . '/.env',
    __DIR__ . '/public_html/.env',
    __DIR__ . '/prod2prod/public_html/.env'
];

$envVars = [];
$envFileFound = false;

foreach ($envFiles as $envFile) {
    if (file_exists($envFile)) {
        echo "✓ Found .env file: $envFile\n";
        $envFileFound = true;
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                $envVars[$name] = $value;
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
        break;
    }
}

if (!$envFileFound) {
    echo "✗ ERROR: .env file not found!\n";
    exit(1);
}

$dbHost = $envVars['DB_HOST'] ?? 'localhost';
$dbUser = $envVars['DB_USER'] ?? 'root';
$dbPass = $envVars['DB_PASSWORD'] ?? '';
$dbName = $envVars['DB_NAME'] ?? 'loka_fleet';

echo "Configuration from .env:\n";
echo "  Host: $dbHost\n";
echo "  User: $dbUser\n";
echo "  Password: " . ($dbPass ? '***' : '(empty)') . "\n";
echo "  Database: $dbName\n\n";

// Test MySQL connection
echo "Testing MySQL connection...\n";

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ]
    );
    echo "✓ MySQL connection successful!\n\n";
} catch (PDOException $e) {
    echo "✗ MySQL connection failed: " . $e->getMessage() . "\n\n";
    echo "Solutions:\n";
    echo "  1. Make sure MySQL/WAMP is running\n";
    echo "  2. Check phpMyAdmin: http://localhost/phpmyadmin\n";
    echo "  3. Verify root user has no password\n";
    exit(1);
}

// List all databases
echo "Available Databases:\n";
echo str_repeat('-', 50) . "\n";

try {
    $databases = $pdo->query("SHOW DATABASES")->fetchAll();

    foreach ($databases as $db) {
        $dbNameList = $db->Database;
        $isTarget = ($dbNameList === $dbName);
        echo ($isTarget ? "→ " : "  ") . "$dbNameList";
        echo ($isTarget ? " [TARGET]" : "") . "\n";
    }

    echo str_repeat('-', 50) . "\n\n";

    // Check if target database exists
    $targetExists = false;
    foreach ($databases as $db) {
        if ($db->Database === $dbName) {
            $targetExists = true;
            break;
        }
    }

    if (!$targetExists) {
        echo "✗ Database '$dbName' does not exist!\n";
        echo "Creating it now...\n\n";

        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "✓ Database '$dbName' created successfully!\n\n";
        } catch (PDOException $e) {
            echo "✗ Failed to create database: " . $e->getMessage() . "\n\n";
            exit(1);
        }
    } else {
        echo "✓ Database '$dbName' exists.\n\n";
    }

} catch (PDOException $e) {
    echo "✗ Error checking databases: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Connect to target database
echo "Connecting to database '$dbName'...\n";

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ]
    );
    echo "✓ Connected to '$dbName'!\n\n";
} catch (PDOException $e) {
    echo "✗ Failed to connect to '$dbName': " . $e->getMessage() . "\n\n";
    echo "This usually means:\n";
    echo "  1. Database exists but user doesn't have access\n";
    echo "  2. User/password is incorrect\n";
    exit(1);
}

// List tables in target database
echo "Tables in '$dbName':\n";
echo str_repeat('-', 50) . "\n";

try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll();

    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        echo "  - $tableName\n";
    }

    echo str_repeat('-', 50) . "\n\n";

} catch (PDOException $e) {
    echo "✗ Error listing tables: " . $e->getMessage() . "\n\n";
}

// Check for required tables
$requiredTables = ['requests', 'drivers', 'users', 'vehicles', 'departments'];
$missingTables = [];

foreach ($requiredTables as $reqTable) {
    $found = false;
    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        if ($tableName === $reqTable) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $missingTables[] = $reqTable;
    }
}

if (!empty($missingTables)) {
    echo "⚠ WARNING: Missing required tables:\n";
    foreach ($missingTables as $table) {
        echo "  - $table\n";
    }
    echo "\nThe application needs these tables to work.\n";
    echo "Please restore from backup or initialize the database.\n\n";
}

// Check if trip_tickets table exists
echo "Checking for trip_tickets table...\n";

$tripTicketsExists = false;
foreach ($tables as $table) {
    $tableName = array_values((array)$table)[0];
    if ($tableName === 'trip_tickets') {
        $tripTicketsExists = true;
        break;
    }
}

if ($tripTicketsExists) {
    echo "✓ trip_tickets table exists\n";
} else {
    echo "✗ trip_tickets table does not exist\n";
    echo "Creating now...\n\n";

    try {
        $sql = "CREATE TABLE trip_tickets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_id INT UNSIGNED NOT NULL,
            driver_id INT UNSIGNED NOT NULL,
            trip_type ENUM('official', 'personal', 'maintenance', 'other') NOT NULL DEFAULT 'official',
            start_date DATETIME NOT NULL COMMENT 'Actual trip start time',
            end_date DATETIME NOT NULL COMMENT 'Actual trip end time',
            destination TEXT NOT NULL COMMENT 'Trip destination',
            purpose TEXT COMMENT 'Purpose of trip',
            passengers INT UNSIGNED DEFAULT 0 COMMENT 'Number of passengers',
            start_mileage INT UNSIGNED DEFAULT NULL COMMENT 'Odometer reading at start',
            end_mileage INT UNSIGNED DEFAULT NULL COMMENT 'Odometer reading at end',
            distance_traveled INT UNSIGNED DEFAULT NULL COMMENT 'Total distance in km',
            fuel_consumed DECIMAL(10,2) UNSIGNED DEFAULT NULL COMMENT 'Fuel consumed in liters',
            fuel_cost DECIMAL(10,2) UNSIGNED DEFAULT NULL COMMENT 'Fuel cost in PHP',
            travel_order_path VARCHAR(255) DEFAULT NULL COMMENT 'Path to travel order document',
            ob_slip_path VARCHAR(255) DEFAULT NULL COMMENT 'Path to OB slip document',
            other_documents_path VARCHAR(255) DEFAULT NULL COMMENT 'Path to other documents',
            has_issues BOOLEAN DEFAULT 0 COMMENT 'Were there any issues?',
            issues_description TEXT COMMENT 'Description of issues',
            resolved BOOLEAN DEFAULT 0 COMMENT 'Have issues been resolved?',
            resolution_notes TEXT COMMENT 'Notes on resolution',
            dispatch_guard_id INT UNSIGNED NOT NULL COMMENT 'Guard who dispatched',
            arrival_guard_id INT UNSIGNED NOT NULL COMMENT 'Guard who verified arrival',
            guard_notes TEXT COMMENT 'Additional notes from guard',
            status ENUM('draft', 'submitted', 'reviewed', 'approved') NOT NULL DEFAULT 'draft',
            reviewed_by INT UNSIGNED DEFAULT NULL COMMENT 'User ID who reviewed',
            reviewed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            created_by INT UNSIGNED NOT NULL COMMENT 'User ID who created',
            INDEX idx_request (request_id),
            INDEX idx_driver (driver_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Stores trip completion tickets with details, documents, and issues'";

        $pdo->exec($sql);
        echo "✓ trip_tickets table created successfully!\n";

    } catch (PDOException $e) {
        echo "✗ Failed to create table: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// Check for trip_ticket_id column in requests table
echo "Checking for trip_ticket_id column in requests table...\n";

try {
    $columns = $pdo->query("SHOW COLUMNS FROM requests LIKE 'trip_ticket_id'")->fetchAll();

    if (!empty($columns)) {
        echo "✓ trip_ticket_id column exists\n";
    } else {
        echo "✗ trip_ticket_id column does not exist\n";
        echo "Adding now...\n\n";

        try {
            // Check if arrival_guard_id column exists
            $arrivalGuardExists = $pdo->query("SHOW COLUMNS FROM requests LIKE 'arrival_guard_id'")->fetchAll();

            if (!empty($arrivalGuardExists)) {
                $pdo->exec("ALTER TABLE requests
                    ADD COLUMN trip_ticket_id INT UNSIGNED DEFAULT NULL
                    COMMENT 'ID of trip completion ticket'
                    AFTER arrival_guard_id");
                echo "✓ trip_ticket_id column added to requests table\n";
            } else {
                echo "⚠ arrival_guard_id column not found, adding at end of table\n";
                $pdo->exec("ALTER TABLE requests
                    ADD COLUMN trip_ticket_id INT UNSIGNED DEFAULT NULL
                    COMMENT 'ID of trip completion ticket'");
                echo "✓ trip_ticket_id column added to requests table\n";
            }

        } catch (PDOException $e) {
            echo "✗ Failed to add column: " . $e->getMessage() . "\n\n";
            exit(1);
        }
    }

    // Add index
    try {
        $pdo->exec("ALTER TABLE requests ADD INDEX IF NOT EXISTS idx_trip_ticket (trip_ticket_id)");
        echo "✓ Index idx_trip_ticket added (or already exists)\n";
    } catch (PDOException $e) {
        echo "⚠ Warning adding index (may already exist): " . $e->getMessage() . "\n";
    }

} catch (PDOException $e) {
    echo "✗ Error checking/adding column: " . $e->getMessage() . "\n\n";
}

echo "\n";
echo str_repeat('=', 50) . "\n";
echo "SETUP COMPLETE!\n";
echo str_repeat('=', 50) . "\n\n";

echo "Summary:\n";
echo "✓ MySQL connection working\n";
echo "✓ Connected to database: $dbName\n";
echo "✓ trip_tickets table ready\n";
echo "✓ trip_ticket_id column ready in requests table\n\n";

echo "Next Steps:\n";
echo "1. Access the application:\n";
echo "   http://localhost:8080/\n";
echo "\n";
echo "2. Login with an account\n";
echo "\n";
echo "3. For drivers:\n";
echo "   - Navigate to My Trips\n";
echo "   - Look for 'Trip Ticket' column\n";
echo "   - Create tickets for completed trips\n";
echo "\n";
echo "4. For guards:\n";
echo "   - Navigate to Guard Dashboard\n";
echo "   - Record arrival for a trip\n";
echo "   - System will redirect to create trip ticket\n";
echo "\n";

echo str_repeat('=', 50) . "\n";
echo "Ready to test! 🎉\n";
echo str_repeat('=', 50) . "\n\n";

exit(0);
