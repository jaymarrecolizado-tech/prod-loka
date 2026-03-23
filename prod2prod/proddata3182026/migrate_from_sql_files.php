<?php
/**
 * SQL File Migration Script
 * Extracts trip data from old SQL dump file and creates migration SQL for new database
 *
 * Usage: php migrate_from_sql_files.php
 */

// File paths
$oldSqlFile = __DIR__ . '/127_0_0_1old.sql';
$newSqlFile = __DIR__ . '/127_0_0_1new.sql';
$outputFile = __DIR__ . '/trip_data_migration.sql';

// Tables to extract
$tripTables = [
    'users',
    'departments',
    'vehicles',
    'drivers',
    'requests',
    'request_passengers',
    'assignment_history',
    'approvals',
    'approval_workflow',
    'fuel_records',
    'maintenance',
    'maintenance_requests'
];

$stats = [
    'tables_processed' => 0,
    'total_statements' => 0,
    'output_statements' => 0
];

/**
 * Extract INSERT statements for a specific table from SQL file
 */
function extractInserts($sqlFile, $table) {
    global $stats;

    if (!file_exists($sqlFile)) {
        echo "✗ File not found: $sqlFile\n";
        return [];
    }

    $content = file_get_contents($sqlFile);
    $inserts = [];

    // Pattern to match INSERT statements for the table
    $pattern = '/INSERT\s+INTO\s+`' . preg_quote($table, '/') . '`\s*\(([^)]+)\)\s*VALUES\s*(.*?);/s';

    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        echo "  Found " . count($matches) . " INSERT statement(s) for table `$table`\n";

        foreach ($matches as $match) {
            $columns = $match[1];
            $values = $match[2];

            $inserts[] = [
                'columns' => $columns,
                'values' => $values,
                'full_statement' => $match[0]
            ];
        }

        $stats['total_statements'] += count($inserts);
    } else {
        echo "  No INSERT statements found for table `$table`\n";
    }

    return $inserts;
}

/**
 * Get columns from CREATE TABLE statement
 */
function getTableColumns($sqlFile, $table) {
    if (!file_exists($sqlFile)) {
        return [];
    }

    $content = file_get_contents($sqlFile);
    $pattern = '/CREATE\s+TABLE\s+`' . preg_quote($table, '/') . '`\s*\((.*?)\)\s*ENGINE/s';

    if (preg_match($pattern, $content, $match)) {
        $tableDef = $match[1];

        // Extract column definitions
        $columns = [];
        preg_match_all('/`(\w+)`\s+([^\s,]+)/', $tableDef, $colMatches, PREG_SET_ORDER);

        foreach ($colMatches as $col) {
            $columns[$col[1]] = $col[2];
        }

        return $columns;
    }

    return [];
}

/**
 * Generate INSERT IGNORE statements
 */
function generateMigrationInserts($table, $inserts) {
    global $stats;

    if (empty($inserts)) {
        return [];
    }

    $output = [];

    foreach ($inserts as $insert) {
        $columns = $insert['columns'];
        $values = $insert['values'];

        // Convert INSERT to INSERT IGNORE
        $migrationInsert = "INSERT IGNORE INTO `$table` ($columns) VALUES $values;";

        $output[] = $migrationInsert;
        $stats['output_statements']++;
    }

    return $output;
}

/**
 * Write migration file
 */
function writeMigrationFile($outputFile, $header, $statements) {
    $content = $header . implode("\n", $statements) . "\n";

    if (file_put_contents($outputFile, $content)) {
        echo "\n✓ Migration SQL written to: $outputFile\n";
        return true;
    } else {
        echo "\n✗ Failed to write migration file: $outputFile\n";
        return false;
    }
}

// Main execution
echo str_repeat("=", 60) . "\n";
echo "SQL FILE TRIP DATA MIGRATION TOOL\n";
echo str_repeat("=", 60) . "\n";
echo "OLD SQL: $oldSqlFile\n";
echo "NEW SQL: $newSqlFile\n";
echo "OUTPUT: $outputFile\n";
echo str_repeat("=", 60) . "\n";

if (!file_exists($oldSqlFile)) {
    echo "\n✗ Old SQL file not found: $oldSqlFile\n";
    exit(1);
}

// Check file sizes
echo "\nFile sizes:\n";
echo "  OLD: " . number_format(filesize($oldSqlFile)) . " bytes\n";
echo "  NEW: " . number_format(filesize($newSqlFile)) . " bytes\n";

// Generate migration header
$header = "--\n";
$header .= "-- Trip Data Migration Script\n";
$header .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$header .= "-- Source: $oldSqlFile\n";
$header .= "-- Target: $newSqlFile\n";
$header .= "--\n\n";

$header .= "-- Disable foreign key checks\n";
$header .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

$allStatements = [];

// Process each table
echo "\nProcessing trip-related tables:\n";
echo str_repeat("-", 60) . "\n";

foreach ($tripTables as $table) {
    echo "\n$table:\n";

    // Extract INSERT statements from old SQL
    $inserts = extractInserts($oldSqlFile, $table);

    if (!empty($inserts)) {
        // Generate migration statements
        $migrationStatements = generateMigrationInserts($table, $inserts);

        if (!empty($migrationStatements)) {
            echo "  ✓ Generated " . count($migrationStatements) . " migration statement(s)\n";
            $allStatements = array_merge($allStatements, $migrationStatements);
            $stats['tables_processed']++;
        }
    }
}

// Add footer
$footer = "\n-- Re-enable foreign key checks\n";
$footer .= "SET FOREIGN_KEY_CHECKS = 1;\n\n";

// Write migration file
writeMigrationFile($outputFile, $header, $allStatements);

// Append footer
file_put_contents($outputFile, $footer, FILE_APPEND);

// Print summary
echo "\n" . str_repeat("=", 60) . "\n";
echo "MIGRATION SUMMARY\n";
echo str_repeat("=", 60) . "\n";
echo "Tables processed: {$stats['tables_processed']}\n";
echo "Total statements found: {$stats['total_statements']}\n";
echo "Statements in output: {$stats['output_statements']}\n";
echo str_repeat("=", 60) . "\n";

if ($stats['output_statements'] > 0) {
    echo "\n✓ Migration SQL file created successfully!\n";
    echo "\nTo apply the migration, run:\n";
    echo "  mysql -u root -p loka_new < $outputFile\n";
} else {
    echo "\n⊘ No data to migrate\n";
}

echo "\n";
?>
