<?php
/**
 * Extract ONLY INSERT statements from old SQL
 * For migrating data into EXISTING table structure in new database
 */

$oldSqlFile = __DIR__ . '/127_0_0_1old.sql';
$outputFile = __DIR__ . '/banner.sql';

// Tables to extract data from
$tables = [
    'users',
    'departments',
    'vehicles',
    'drivers',
    'requests',
    'request_passengers',
    'assignment_history',
    'approvals',
    'approval_workflow'
];

echo "Extracting INSERT statements from old database...\n";
echo "Output: banner.sql\n\n";

$content = file_get_contents($oldSqlFile);

// Create output with NO table creation, NO drops, ONLY INSERT statements
$output = "--\n";
$output .= "-- BANNER.SQL - Data Migration File\n";
$output .= "-- Purpose: Insert data from old DB into EXISTING structure of new DB\n";
$output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$output .= "--\n\n";

$output .= "-- Disable foreign key checks for safe import\n";
$output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

$totalRows = 0;

foreach ($tables as $table) {
    echo "Processing: $table ... ";

    // Find INSERT statement for this table
    // Pattern: INSERT INTO `table` (...) VALUES (...);
    $pattern = '/INSERT\s+INTO\s+`' . preg_quote($table, '/') . '`\s*\(([^)]+)\)\s*VALUES\s+([^;]+);/s';

    if (preg_match($pattern, $content, $matches)) {
        $columns = $matches[1];
        $values = $matches[2];

        // Convert to INSERT IGNORE to avoid conflicts
        $insert = "INSERT IGNORE INTO `$table` ($columns) VALUES $values;";

        $output .= $insert . "\n";

        // Count rows
        $rows = preg_split('/\),\s*\(/', trim($values, '()'));
        $rowCount = count($rows);
        $totalRows += $rowCount;

        echo "✓ $rowCount rows\n";
    } else {
        echo "⊘ No data\n";
    }
}

$output .= "\n-- Re-enable foreign key checks\n";
$output .= "SET FOREIGN_KEY_CHECKS = 1;\n\n";

$output .= "-- End of migration\n";

file_put_contents($outputFile, $output);

echo "\n" . str_repeat("=", 60) . "\n";
echo "EXTRACTION COMPLETE\n";
echo str_repeat("=", 60) . "\n";
echo "Output file: $outputFile\n";
echo "File size: " . number_format(filesize($outputFile)) . " bytes\n";
echo "Total rows extracted: $totalRows\n";
echo "\n✓ banner.sql created!\n";
echo "This file contains ONLY INSERT IGNORE statements.\n";
echo "NO CREATE TABLE, NO DROP TABLE.\n";
echo "Ready to import into existing database structure.\n";
?>
