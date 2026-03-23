<?php
/**
 * LOKA - Database Setup & Migration Script
 *
 * This script helps set up the database and run migrations
 * It's designed to handle common connection issues and provide helpful feedback
 */

echo "==================================================\n";
echo "LOKA FLEET - Database Setup & Migration\n";
echo "==================================================\n\n";

// Load environment variables from .env file
$envFiles = [
    __DIR__ . '/.env',                 // In project root
    __DIR__ . '/public_html/.env',  // In public_html folder
    __DIR__ . '/prod2prod/public_html/.env' // In prod folder
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
                // Set in environment
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
    echo "  Checked locations:\n";
    foreach ($envFiles as $envFile) {
        echo "    - $envFile\n";
    }
    echo "\nPlease copy .env.example to .env and configure.\n";
    exit(1);
}

// Debug: Show what was loaded
echo "\n" . str_repeat('-', 50) . "\n";
echo "Environment Variables Loaded:\n";
echo str_repeat('-', 50) . "\n";
foreach ($envVars as $key => $value) {
    if (strpos($key, 'DB_') === 0 || strpos($key, 'APP_') === 0) {
        echo "$key = $value\n";
    }
}
echo str_repeat('-', 50) . "\n\n";

// Get database configuration - PRIORITY: .env values
$dbHost = isset($envVars['DB_HOST']) ? $envVars['DB_HOST'] : 'localhost';
$dbName = isset($envVars['DB_NAME']) ? $envVars['DB_NAME'] : 'loka_fleet';
$dbUser = isset($envVars['DB_USER']) ? $envVars['DB_USER'] : 'root';
$dbPass = isset($envVars['DB_PASSWORD']) ? $envVars['DB_PASSWORD'] : '';
$dbCharset = isset($envVars['DB_CHARSET']) ? $envVars['DB_CHARSET'] : 'utf8mb4';

echo "\n" . str_repeat('-', 50) . "\n";
echo "Database Configuration (from .env):\n";
echo str_repeat('-', 50) . "\n";
echo "Host:     $dbHost\n";
echo "Database: $dbName\n";
echo "User:     $dbUser\n";
echo "Password: " . ($dbPass ? '***' : '(empty)') . "\n";
echo "Charset:   $dbCharset\n";
echo str_repeat('-', 50) . "\n\n";

// Test MySQL connection first
echo "Testing MySQL connection...\n";

try {
    // Try connecting without database first (to check if MySQL is running)
    $pdo = new PDO(
        "mysql:host=$dbHost;charset=$dbCharset",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    echo "✓ MySQL connection successful!\n\n";

} catch (PDOException $e) {
    echo "\n✗ ERROR: MySQL connection failed!\n";
    echo "\nError Details:\n";
    echo "  " . $e->getMessage() . "\n\n";

    echo "\nPossible Solutions:\n";
    echo "1. Make sure MySQL server is running\n";
    echo "2. Check database credentials in .env file\n";
    echo "3. Verify MySQL user permissions\n";
    echo "4. Check if MySQL is listening on correct port (default: 3306)\n\n";

    echo "For WAMP/XAMPP users:\n";
    echo "- Open phpMyAdmin: http://localhost/phpmyadmin\n";
    echo "- Create database: '$dbName'\n";
    echo "- Create user: '$dbUser' with password: '***'\n";
    echo "- Grant all privileges on database to user\n\n";

    exit(1);
}

// Create database if it doesn't exist
echo "Checking if database '$dbName' exists...\n";

try {
    $databases = $pdo->query("SHOW DATABASES LIKE '$dbName'")->fetchAll();

    if (empty($databases)) {
        echo "  Database not found. Creating...\n";

        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "  ✓ Database '$dbName' created successfully!\n\n";
        } catch (PDOException $e) {
            echo "  ✗ Failed to create database: " . $e->getMessage() . "\n\n";
            exit(1);
        }
    } else {
        echo "  ✓ Database '$dbName' exists.\n\n";
    }

} catch (PDOException $e) {
    echo "  ✗ Error checking database: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Now connect to the specific database
echo "Connecting to database '$dbName'...\n";

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=$dbCharset",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    echo "  ✓ Connected to database!\n\n";

} catch (PDOException $e) {
    echo "  ✗ Failed to connect to database: " . $e->getMessage() . "\n\n";
    echo "Possible reasons:\n";
    echo "1. Database exists but user doesn't have access\n";
    echo "2. Password is incorrect\n";
    echo "3. Database name is wrong\n\n";
    exit(1);
}

// Run migration: Create Trip Tickets Table
echo str_repeat('=', 50) . "\n";
echo "Running Migration: Create Trip Tickets Table\n";
echo str_repeat('=', 50) . "\n\n";

try {
    // Check if table already exists
    $tables = $pdo->query("SHOW TABLES LIKE 'trip_tickets'")->fetchAll();

    if (!empty($tables)) {
        echo "⚠ 'trip_tickets' table already exists.\n";
        echo "  Dropping and recreating...\n";

        try {
            $pdo->exec("DROP TABLE IF EXISTS trip_tickets");
            echo "  ✓ Old table dropped.\n";
        } catch (PDOException $e) {
            echo "  ⚠ Warning: " . $e->getMessage() . "\n";
        }
    }

    // Create trip_tickets table
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
        has_issues BOOLEAN DEFAULT 0 COMMENT 'Were there any issues?',
        issues_description TEXT COMMENT 'Description of issues',
        resolved BOOLEAN DEFAULT 0 COMMENT 'Have issues been resolved?',
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

        -- Foreign Keys (will be added separately)
        INDEX idx_request (request_id),
        INDEX idx_driver (driver_id),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Stores trip completion tickets with details, documents, and issues'";

    $pdo->exec($sql);
    echo "✓ Created trip_tickets table\n\n";

    // Add foreign keys (done separately to handle potential dependency issues)
    echo "Adding foreign key constraints...\n";

    try {
        $pdo->exec("ALTER TABLE trip_tickets
            ADD CONSTRAINT fk_trip_tickets_request
            FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE");
        echo "  ✓ Foreign key: request_id\n";
    } catch (PDOException $e) {
        echo "  ⚠ Foreign key (request_id) may already exist or requests table not found\n";
    }

    try {
        $pdo->exec("ALTER TABLE trip_tickets
            ADD CONSTRAINT fk_trip_tickets_driver
            FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE");
        echo "  ✓ Foreign key: driver_id\n";
    } catch (PDOException $e) {
        echo "  ⚠ Foreign key (driver_id) may already exist or drivers table not found\n";
    }

    try {
        $pdo->exec("ALTER TABLE trip_tickets
            ADD CONSTRAINT fk_trip_tickets_dispatch_guard
            FOREIGN KEY (dispatch_guard_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "  ✓ Foreign key: dispatch_guard_id\n";
    } catch (PDOException $e) {
        echo "  ⚠ Foreign key (dispatch_guard_id) may already exist or users table not found\n";
    }

    try {
        $pdo->exec("ALTER TABLE trip_tickets
            ADD CONSTRAINT fk_trip_tickets_arrival_guard
            FOREIGN KEY (arrival_guard_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "  ✓ Foreign key: arrival_guard_id\n";
    } catch (PDOException $e) {
        echo "  ⚠ Foreign key (arrival_guard_id) may already exist or users table not found\n";
    }

    try {
        $pdo->exec("ALTER TABLE trip_tickets
            ADD CONSTRAINT fk_trip_tickets_reviewed_by
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "  ✓ Foreign key: reviewed_by\n";
    } catch (PDOException $e) {
        echo "  ⚠ Foreign key (reviewed_by) may already exist or users table not found\n";
    }

    try {
        $pdo->exec("ALTER TABLE trip_tickets
            ADD CONSTRAINT fk_trip_tickets_created_by
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE");
        echo "  ✓ Foreign key: created_by\n";
    } catch (PDOException $e) {
        echo "  ⚠ Foreign key (created_by) may already exist or users table not found\n";
    }

    echo "\n";

    // Add trip_ticket_id to requests table
    echo "Adding trip_ticket_id column to requests table...\n";

    try {
        $columns = $pdo->query("SHOW COLUMNS FROM requests LIKE 'trip_ticket_id'")->fetchAll();

        if (empty($columns)) {
            $pdo->exec("ALTER TABLE requests
                ADD COLUMN trip_ticket_id INT UNSIGNED DEFAULT NULL
                COMMENT 'ID of trip completion ticket'
                AFTER arrival_guard_id");
            echo "  ✓ Column trip_ticket_id added to requests table\n";
        } else {
            echo "  ⚠ Column trip_ticket_id already exists in requests table\n";
        }
    } catch (PDOException $e) {
        echo "  ⚠ Warning: " . $e->getMessage() . "\n";
    }

    // Add index
    try {
        $pdo->exec("ALTER TABLE requests ADD INDEX idx_trip_ticket (trip_ticket_id)");
        echo "  ✓ Index idx_trip_ticket added to requests table\n";
    } catch (PDOException $e) {
        echo "  ⚠ Index may already exist\n";
    }

    echo "\n";

} catch (PDOException $e) {
    echo "\n✗ ERROR: Migration failed!\n";
    echo "\nError Details:\n";
    echo "  " . $e->getMessage() . "\n\n";

    echo "Possible Solutions:\n";
    echo "1. Check database user has CREATE TABLE permissions\n";
    echo "2. Verify requests and drivers tables exist\n";
    echo "3. Check if there are any existing foreign key constraints blocking\n\n";

    exit(1);
}

// Verify migration
echo str_repeat('=', 50) . "\n";
echo "Verifying Migration...\n";
echo str_repeat('=', 50) . "\n\n";

try {
    $count = $pdo->query("SELECT COUNT(*) as count FROM trip_tickets")->fetch();
    echo "✓ trip_tickets table exists\n";
    echo "  Current records: " . $count->count . "\n";

    $hasColumn = $pdo->query("SHOW COLUMNS FROM requests LIKE 'trip_ticket_id'")->fetchAll();
    if (!empty($hasColumn)) {
        echo "✓ trip_ticket_id column exists in requests table\n";
    } else {
        echo "⚠ Warning: trip_ticket_id column may not exist in requests table\n";
    }

} catch (PDOException $e) {
    echo "✗ Verification failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "MIGRATION COMPLETE!\n";
echo str_repeat('=', 50) . "\n\n";

echo "Next Steps:\n";
echo "1. Refresh the application\n";
echo "2. Login as a driver with completed trips\n";
echo "3. Navigate to My Trips page\n";
echo "4. Look for 'Trip Ticket' column in the trips table\n";
echo "5. Click 'Create Ticket' button for a completed trip\n";
echo "6. Fill in trip details and submit\n";
echo "7. Check that trip ticket status is visible\n\n";

echo "Access the application:\n";
echo "  URL: http://localhost:8080/\n";
echo "  Or: http://" . $dbHost . ":8080/\n\n";

echo str_repeat('=', 50) . "\n";
echo "All done! The Trip Ticket feature is ready to use.\n";
echo str_repeat('=', 50) . "\n";

exit(0);
