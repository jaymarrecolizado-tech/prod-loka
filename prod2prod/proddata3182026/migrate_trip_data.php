<?php
/**
 * Trip Data Migration Script
 * Migrates trip-related data from old database to new database
 *
 * Usage: php migrate_trip_data.php
 */

// Configuration
$oldDbConfig = [
    'host' => '127.0.0.1',
    'dbname' => 'loka_old',
    'username' => 'root',
    'password' => '',
    'port' => 3306
];

$newDbConfig = [
    'host' => '127.0.0.1',
    'dbname' => 'loka_new',
    'username' => 'root',
    'password' => '',
    'port' => 3306
];

// Trip-related tables to migrate (in order of dependency)
$tables = [
    'users' => [
        'primary_key' => 'id',
        'columns' => '*',
        'where' => '1=1'
    ],
    'departments' => [
        'primary_key' => 'id',
        'columns' => '*',
        'where' => '1=1'
    ],
    'vehicles' => [
        'primary_key' => 'id',
        'columns' => '*',
        'where' => '1=1'
    ],
    'drivers' => [
        'primary_key' => 'id',
        'columns' => '*',
        'where' => '1=1'
    ],
    'requests' => [
        'primary_key' => 'id',
        'columns' => '*',
        'where' => '1=1'
    ],
    'request_passengers' => [
        'primary_key' => 'id',
        'columns' => '*',
        'where' => '1=1'
    ],
    'assignment_history' => [
        'primary_key' => 'id',
        'columns' => '*',
        'where' => '1=1'
    ],
    'approvals' => [
        'primary_key' => 'id',
        'columns' => '*',
        'where' => '1=1'
    ],
    'approval_workflow' => [
        'primary_key' => 'id',
        'columns' => '*',
        'where' => '1=1'
    ],
    'fuel_records' => [
        'primary_key' => 'id',
        'columns' => '*',
        'where' => '1=1'
    ],
    'maintenance' => [
        'primary_key' => 'id',
        'columns' => '*',
        'where' => '1=1'
    ],
    'maintenance_requests' => [
        'primary_key' => 'id',
        'columns' => '*',
        'where' => '1=1'
    ],
];

// Statistics
$stats = [
    'total_tables' => 0,
    'total_rows' => 0,
    'inserted' => 0,
    'skipped' => 0,
    'errors' => 0,
    'table_stats' => []
];

/**
 * Connect to database
 */
function connect($config) {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        echo "✓ Connected to {$config['dbname']}\n";
        return $pdo;
    } catch (PDOException $e) {
        echo "✗ Failed to connect to {$config['dbname']}: {$e->getMessage()}\n";
        exit(1);
    }
}

/**
 * Get table columns from database
 */
function getTableColumns($pdo, $table) {
    try {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $columns = [];
        while ($row = $stmt->fetch()) {
            $columns[] = $row['Field'];
        }
        return $columns;
    } catch (PDOException $e) {
        echo "  ✗ Failed to get columns for $table: {$e->getMessage()}\n";
        return false;
    }
}

/**
 * Check if table exists
 */
function tableExists($pdo, $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Migrate data from old to new
 */
function migrateTable($oldPdo, $newPdo, $table, $config) {
    global $stats;

    echo "\n--- Migrating table: $table ---\n";

    // Check if table exists in both databases
    if (!tableExists($oldPdo, $table)) {
        echo "  ✗ Table not found in OLD database\n";
        return;
    }
    if (!tableExists($newPdo, $table)) {
        echo "  ✗ Table not found in NEW database\n";
        return;
    }

    $primaryKey = $config['primary_key'];
    $columns = $config['columns'];
    $where = $config['where'];

    // Get column list
    $oldColumns = getTableColumns($oldPdo, $table);
    $newColumns = getTableColumns($newPdo, $table);

    if (!$oldColumns || !$newColumns) {
        echo "  ✗ Failed to get table columns\n";
        return;
    }

    // Use intersection of columns that exist in both
    $commonColumns = array_intersect($oldColumns, $newColumns);
    $columnList = implode(', ', array_map(fn($c) => "`$c`", $commonColumns));
    $placeholders = implode(', ', array_fill(0, count($commonColumns), '?'));

    echo "  Columns: " . count($commonColumns) . " common columns\n";

    // Get count of rows to migrate
    $countStmt = $oldPdo->query("SELECT COUNT(*) FROM `$table` WHERE $where");
    $totalRows = $countStmt->fetchColumn();
    echo "  Total rows in OLD: $totalRows\n";

    if ($totalRows == 0) {
        echo "  ⊘ No data to migrate\n";
        $stats['table_stats'][$table] = [
            'total' => 0,
            'inserted' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
        return;
    }

    $stats['total_tables']++;
    $stats['total_rows'] += $totalRows;

    // Fetch data from old database
    $selectSql = "SELECT $columnList FROM `$table` WHERE $where ORDER BY `$primaryKey`";
    $stmt = $oldPdo->query($selectSql);

    $inserted = 0;
    $skipped = 0;
    $errors = 0;

    // Prepare INSERT IGNORE statement
    $insertSql = "INSERT IGNORE INTO `$table` ($columnList) VALUES ($placeholders)";
    $insertStmt = $newPdo->prepare($insertSql);

    // Migrate row by row
    while ($row = $stmt->fetch()) {
        try {
            $values = [];
            foreach ($commonColumns as $column) {
                $values[] = $row[$column];
            }

            $result = $insertStmt->execute($values);

            if ($result) {
                if ($insertStmt->rowCount() > 0) {
                    $inserted++;
                } else {
                    $skipped++;
                }
            } else {
                $skipped++;
            }
        } catch (PDOException $e) {
            $errors++;
            echo "  ✗ Error inserting row with $primaryKey = {$row[$primaryKey]}: {$e->getMessage()}\n";
        }
    }

    echo "  ✓ Inserted: $inserted\n";
    echo "  ⊘ Skipped: $skipped\n";
    if ($errors > 0) {
        echo "  ✗ Errors: $errors\n";
    }

    $stats['inserted'] += $inserted;
    $stats['skipped'] += $skipped;
    $stats['errors'] += $errors;

    $stats['table_stats'][$table] = [
        'total' => $totalRows,
        'inserted' => $inserted,
        'skipped' => $skipped,
        'errors' => $errors
    ];
}

/**
 * Print summary
 */
function printSummary($stats) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "MIGRATION SUMMARY\n";
    echo str_repeat("=", 60) . "\n";
    echo "Tables processed: {$stats['total_tables']}\n";
    echo "Total rows in OLD: {$stats['total_rows']}\n";
    echo "Rows inserted: {$stats['inserted']}\n";
    echo "Rows skipped (duplicates): {$stats['skipped']}\n";
    echo "Errors: {$stats['errors']}\n";
    echo str_repeat("=", 60) . "\n";

    echo "\nPer-table details:\n";
    echo str_repeat("-", 60) . "\n";
    printf("%-25s %10s %10s %10s %10s\n", "Table", "Total", "Inserted", "Skipped", "Errors");
    echo str_repeat("-", 60) . "\n";

    foreach ($stats['table_stats'] as $table => $tableStats) {
        printf("%-25s %10d %10d %10d %10d\n",
            $table,
            $tableStats['total'],
            $tableStats['inserted'],
            $tableStats['skipped'],
            $tableStats['errors']
        );
    }
    echo str_repeat("=", 60) . "\n";
}

// Main execution
echo str_repeat("=", 60) . "\n";
echo "TRIP DATA MIGRATION TOOL\n";
echo "OLD: {$oldDbConfig['host']}/{$oldDbConfig['dbname']}\n";
echo "NEW: {$newDbConfig['host']}/{$newDbConfig['dbname']}\n";
echo str_repeat("=", 60) . "\n";

// Connect to databases
$oldPdo = connect($oldDbConfig);
$newPdo = connect($newDbConfig);

// Disable foreign key checks during migration
$newPdo->exec("SET FOREIGN_KEY_CHECKS = 0");
echo "\n✓ Foreign key checks disabled\n";

// Start transaction
$newPdo->beginTransaction();
echo "✓ Transaction started\n";

// Migrate each table
foreach ($tables as $table => $config) {
    migrateTable($oldPdo, $newPdo, $table, $config);
}

// Commit transaction
$newPdo->commit();
echo "\n✓ Transaction committed\n";

// Re-enable foreign key checks
$newPdo->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "✓ Foreign key checks re-enabled\n";

// Print summary
printSummary($stats);

echo "\n✓ Migration completed!\n";
?>
