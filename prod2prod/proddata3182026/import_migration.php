<?php
/**
 * Import Trip Data Migration
 * Executes the migration SQL file into the target database
 */

$migrationFile = __DIR__ . '/trip_data_migration.sql';
$targetDb = 'lokaloka2';

echo "============================================================\n";
echo "TRIP DATA MIGRATION IMPORT\n";
echo "============================================================\n";
echo "Target database: $targetDb\n";
echo "Migration file: $migrationFile\n";
echo "============================================================\n\n";

// Check if migration file exists
if (!file_exists($migrationFile)) {
    echo "✗ Error: Migration file not found: $migrationFile\n";
    echo "Please run 'php extract_trip_data.php' first.\n";
    exit(1);
}

// Check file size
$fileSize = filesize($migrationFile);
echo "Migration file size: " . number_format($fileSize) . " bytes\n\n";

// Connect to MySQL
try {
    $pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to MySQL\n";
} catch (PDOException $e) {
    echo "✗ Failed to connect to MySQL: " . $e->getMessage() . "\n";
    exit(1);
}

// Select target database
try {
    $pdo->exec("USE $targetDb");
    echo "✓ Using database: $targetDb\n";
} catch (PDOException $e) {
    echo "✗ Failed to use database: " . $e->getMessage() . "\n";
    exit(1);
}

// Read migration SQL
echo "\nReading migration file...\n";
$sql = file_get_contents($migrationFile);
if ($sql === false) {
    echo "✗ Failed to read migration file\n";
    exit(1);
}
echo "✓ Migration file read\n";

// Count statements
preg_match_all('/INSERT IGNORE INTO\s+`(\w+)`/', $sql, $matches);
$statementCount = count($matches[0]);
echo "✓ Found $statementCount INSERT statements\n\n";

// Get current counts before migration
echo "Current data before migration:\n";
echo str_repeat("-", 50) . "\n";
$tables = ['users', 'departments', 'vehicles', 'drivers', 'requests', 'request_passengers', 'assignment_history', 'approvals', 'approval_workflow'];
$beforeCounts = [];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
        $beforeCounts[$table] = $count;
        printf("%-25s %8d rows\n", $table, $count);
    } catch (Exception $e) {
        printf("%-25s %8s\n", $table, "ERROR");
        $beforeCounts[$table] = 0;
    }
}
echo str_repeat("-", 50) . "\n\n";

// Confirm import
echo "WARNING: This will import data into $targetDb database.\n";
echo "Existing records with same IDs will be preserved (INSERT IGNORE).\n";
echo "\nContinue? (yes/no): ";
$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if (strtolower($response) !== 'yes' && strtolower($response) !== 'y') {
    echo "\n✗ Migration cancelled.\n";
    exit(0);
}

echo "\nStarting import...\n";

// Execute migration
$startTime = microtime(true);

try {
    // Split into individual statements (INSERT statements)
    $statements = explode(';', $sql);
    $executed = 0;
    $errors = 0;

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt) || strpos($stmt, '--') === 0) {
            continue;
        }

        try {
            $result = $pdo->exec($stmt);
            if ($result !== false) {
                $executed++;
            }
        } catch (PDOException $e) {
            $errors++;
            echo "  ⚠ Error: " . substr($e->getMessage(), 0, 100) . "\n";
        }
    }

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    echo "\n✓ Import completed in $duration seconds\n";
    echo "✓ Executed $executed statements\n";
    if ($errors > 0) {
        echo "✗ $errors errors encountered\n";
    }

} catch (PDOException $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Get counts after migration
echo "\nData after migration:\n";
echo str_repeat("-", 50) . "\n";
$afterCounts = [];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
        $afterCounts[$table] = $count;
        $added = $count - $beforeCounts[$table];
        $addedStr = $added > 0 ? "(+$added)" : "($added)";
        printf("%-25s %8d rows %12s\n", $table, $count, $addedStr);
    } catch (Exception $e) {
        printf("%-25s %8s\n", $table, "ERROR");
        $afterCounts[$table] = $beforeCounts[$table];
    }
}
echo str_repeat("-", 50) . "\n";

// Calculate totals
$totalBefore = array_sum($beforeCounts);
$totalAfter = array_sum($afterCounts);
$totalAdded = $totalAfter - $totalBefore;

echo "\nTotal before migration: $totalBefore rows\n";
echo "Total after migration:  $totalAfter rows\n";
echo "New rows added:        $totalAdded rows\n";

echo "\n============================================================\n";
echo "✓ MIGRATION SUCCESSFUL\n";
echo "============================================================\n\n";

// Show some sample imported requests
echo "Sample of newly imported requests:\n";
echo str_repeat("-", 80) . "\n";
printf("%-5s %-12s %-30s %-30s\n", "ID", "Status", "Destination", "Purpose");
echo str_repeat("-", 80) . "\n";

$stmt = $pdo->query("
    SELECT id, status, destination, purpose
    FROM requests
    WHERE id < 61
    ORDER BY created_at DESC
    LIMIT 10
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dest = substr($row['destination'], 0, 28);
    $purpose = substr($row['purpose'], 0, 28);
    printf("%-5s %-12s %-30s %-30s\n",
        $row['id'],
        $row['status'],
        $dest,
        $purpose
    );
}
echo str_repeat("-", 80) . "\n\n";

echo "✓ You can now check the data in your lokaloka2 database!\n";
echo "\n";
?>
