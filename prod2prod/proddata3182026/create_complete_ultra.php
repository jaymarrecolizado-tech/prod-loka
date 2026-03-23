<?php
/**
 * Extract CREATE TABLE statements from new database
 */

$newSqlFile = __DIR__ . '/127_0_0_1new.sql';
$oldSqlFile = __DIR__ . '/127_0_0_1old.sql';
$outputFile = __DIR__ . '/ultra_complete.sql';

// Tables we need to create and import
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

echo "Extracting table structures from new database...\n";

$content = file_get_contents($newSqlFile);
$oldContent = file_get_contents($oldSqlFile);

$output = "--\n";
$output .= "-- COMPLETE MIGRATION SQL FILE\n";
$output .= "-- Includes table structures + data from old database\n";
$output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$output .= "--\n\n";

$output .= "-- Disable foreign key checks\n";
$output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tables as $table) {
    echo "Processing: $table\n";

    // Extract CREATE TABLE statement from new DB
    $pattern = '/CREATE TABLE `' . preg_quote($table, '/') . '` \((.*?)\) ENGINE=InnoDB/s';
    if (preg_match($pattern, $content, $matches)) {
        $output .= "--\n";
        $output .= "-- Table: $table\n";
        $output .= "--\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $output .= "CREATE TABLE `$table` (" . $matches[1] . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
    }

    // Extract INSERT IGNORE statements from old DB
    $pattern = '/INSERT\s+INTO\s+`' . preg_quote($table, '/') . '`\s*\(([^)]+)\)\s*VALUES\s+([^;]+);/s';
    if (preg_match($pattern, $oldContent, $matches)) {
        $columns = $matches[1];
        $values = $matches[2];
        $output .= "INSERT IGNORE INTO `$table` ($columns) VALUES $values;\n\n";
    }
}

$output .= "-- Re-enable foreign key checks\n";
$output .= "SET FOREIGN_KEY_CHECKS = 1;\n\n";

$output .= "-- End of migration\n";

file_put_contents($outputFile, $output);

echo "\n✓ Complete migration file created: $outputFile\n";
echo "File size: " . number_format(filesize($outputFile)) . " bytes\n";
?>
