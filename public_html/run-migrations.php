<?php
/**
 * LOKA - Migration Runner
 * Run pending database migrations
 */

echo "=== LOKA Migration Runner ===\n\n";

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

// Use mysqli instead of PDO to avoid multi-statement issues
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("❌ Database connection failed: " . $mysqli->connect_error . "\n");
}

$mysqli->set_charset(DB_CHARSET);
echo "✅ Connected to database: " . DB_NAME . "\n\n";

// Check if migrations table exists
$result = $mysqli->query("SHOW TABLES LIKE 'schema_migrations'");
$migrationsTable = $result && $result->num_rows > 0;
$result?->free();

if (!$migrationsTable) {
    echo "Creating migrations tracker table...\n";
    $mysqli->query("
        CREATE TABLE schema_migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created schema_migrations table\n\n";
}

// Get executed migrations
$executed = [];
$result = $mysqli->query("SELECT migration FROM schema_migrations");
if ($result) {
    while ($row = $result->fetch_row()) {
        $executed[] = $row[0];
    }
    $result->free();
}
echo "Already executed migrations: " . count($executed) . "\n\n";

// Get migration files
$migrationDir = __DIR__ . '/migrations';
$files = glob($migrationDir . '/*.sql');
sort($files);

$pending = [];
foreach ($files as $file) {
    $name = basename($file);
    if (!in_array($name, $executed)) {
        $pending[] = $file;
    }
}

if (empty($pending)) {
    echo "✅ No pending migrations. Database is up to date!\n";
    $result = $mysqli->query("SHOW TABLES");
    echo "Total tables: " . ($result ? $result->num_rows : 0) . "\n";
    exit(0);
}

echo "Found " . count($pending) . " pending migrations:\n";
foreach ($pending as $file) {
    echo "  - " . basename($file) . "\n";
}
echo "\n";

// Execute pending migrations
foreach ($pending as $file) {
    $name = basename($file);
    echo "Executing: $name\n";
    
    $sql = file_get_contents($file);
    $hasError = false;
    $errorMsg = '';
    
    // Disable foreign key checks
    $mysqli->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Execute multi-statement SQL with error handling
    try {
        if ($mysqli->multi_query($sql)) {
            do {
                if ($result = $mysqli->store_result()) {
                    $result->free();
                }
                if ($mysqli->error && strpos($mysqli->error, 'Duplicate') === false && strpos($mysqli->error, 'already exists') === false) {
                    $hasError = true;
                    $errorMsg = $mysqli->error;
                }
            } while ($mysqli->next_result());
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, 'Duplicate') !== false || strpos($errorMsg, 'already exists') !== false) {
            $hasError = false;
        } else {
            $hasError = true;
        }
    }
    
    // Re-enable foreign key checks
    $mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
    
    // Clear any pending results
    while ($mysqli->next_result()) {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    }
    
    if ($hasError) {
        echo "  ❌ Error: $errorMsg\n";
    } elseif (strpos($errorMsg, 'Duplicate') !== false || strpos($errorMsg, 'already exists') !== false) {
        echo "  ⚠️  Already applied, marking as executed\n";
    } else {
        echo "  ✅ Success\n";
    }
    
    // Record migration
    $stmt = $mysqli->prepare("INSERT IGNORE INTO schema_migrations (migration) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->close();
}

echo "\n=== Migration Complete ===\n";

// Count tables
$result = $mysqli->query("SHOW TABLES");
$tables = $result ? $result->num_rows : 0;
echo "Total tables: $tables\n";

// Check specific tables
$result = $mysqli->query("SHOW TABLES LIKE 'maintenance_requests'");
if ($result && $result->num_rows > 0) {
    echo "✅ maintenance_requests table exists\n";
}
$result?->free();

$mysqli->close();



