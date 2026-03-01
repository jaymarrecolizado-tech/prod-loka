<?php
/**
 * LOKA Database Reset Script
 *
 * WARNING: This will DELETE all data and import from the SQL dump file
 */

// Configuration
$dbName = 'lokaloka2';
$dbUser = 'root';
$dbPass = '';
$sqlFile = 'C:\wamp64\www\Projects\loka2\datafromonline\loka 2-27-26127_0_0_1.sql';

echo "========================================\n";
echo "LOKA Database Reset and Import\n";
echo "========================================\n\n";

echo "Database: $dbName\n";
echo "SQL File: $sqlFile\n\n";

// Check if SQL file exists
if (!file_exists($sqlFile)) {
    die("ERROR: SQL file not found: $sqlFile\n");
}

echo "File size: " . number_format(filesize($sqlFile)) . " bytes\n\n";

// Confirm
echo "WARNING: This will DELETE all data in the local database!\n";
echo "Type 'yes' to continue: ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) !== 'yes') {
    echo "\nOperation cancelled.\n";
    exit(0);
}
fclose($handle);

echo "\n";

try {
    // Connect to MySQL (without database)
    $pdo = new PDO("mysql:host=localhost", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Drop database
    echo "[1/4] Dropping database...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    echo "OK - Dropped.\n";

    // Create database
    echo "[2/4] Creating database...\n";
    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "OK - Created.\n";

    // Connect to the new database
    $pdo->exec("USE `$dbName`");

    // Read and execute SQL file
    echo "[3/4] Importing data from SQL file...\n";
    $sql = file_get_contents($sqlFile);

    // Split SQL into individual statements
    // Remove comments and split by semicolons
    $sql = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
    $sql = preg_replace('/\/\*[\s\S]*?\*\//', '', $sql); // Remove multi-line comments

    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $count = 0;
    foreach ($statements as $statement) {
        if (empty($statement)) continue;

        // Skip CREATE DATABASE and USE statements since we already handled them
        if (preg_match('/^(CREATE DATABASE|USE)/i', $statement)) {
            continue;
        }

        try {
            $pdo->exec($statement);
            $count++;
        } catch (PDOException $e) {
            // Show error but continue
            echo "Warning: " . $e->getMessage() . "\n";
        }
    }

    echo "OK - Executed $count statements.\n";

    // Verify import
    echo "[4/4] Verifying import...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "OK - Users in database: " . number_format($result['count']) . "\n";

    echo "\n========================================\n";
    echo "Database reset completed successfully!\n";
    echo "========================================\n";

} catch (PDOException $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}
