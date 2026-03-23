<?php
/**
 * SQL File Trip Data Extractor
 * Properly extracts INSERT statements with their VALUES from SQL dump
 */

$oldSqlFile = __DIR__ . '/127_0_0_1old.sql';
$outputFile = __DIR__ . '/trip_data_migration.sql';

// Tables to extract
$tables = [
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

echo "============================================================\n";
echo "SQL FILE TRIP DATA EXTRACTOR\n";
echo "============================================================\n";
echo "Input: $oldSqlFile\n";
echo "Output: $outputFile\n";
echo "============================================================\n\n";

if (!file_exists($oldSqlFile)) {
    echo "✗ Error: File not found: $oldSqlFile\n";
    exit(1);
}

$content = file_get_contents($oldSqlFile);

// Create output file with header
$output = "--\n";
$output .= "-- Trip Data Migration Script\n";
$output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$output .= "-- Source: $oldSqlFile\n";
$output .= "--\n\n";
$output .= "-- Disable foreign key checks\n";
$output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

$totalExtracted = 0;

foreach ($tables as $table) {
    echo "Processing: $table ... ";

    // Find INSERT INTO statement for this table
    // Pattern matches: INSERT INTO `table` (...) VALUES (...), (...), ...;
    $pattern = '/INSERT\s+INTO\s+`' . preg_quote($table, '/') . '`\s*\([^)]+\)\s*VALUES\s+([^;]+);/s';

    if (preg_match($pattern, $content, $matches)) {
        $columnsPart = '';
        $valuesPart = $matches[1];

        // Extract column list
        $colPattern = '/INSERT\s+INTO\s+`' . preg_quote($table, '/') . '`\s*\(([^)]+)\)/s';
        if (preg_match($colPattern, $content, $colMatch)) {
            $columnsPart = $colMatch[1];
        }

        // Convert to INSERT IGNORE to avoid duplicates
        $insert = "INSERT IGNORE INTO `$table` ($columnsPart) VALUES $valuesPart;\n";
        $output .= $insert;

        // Count rows (split by "),(" pattern)
        $rows = preg_split('/\),\s*\(/', trim($valuesPart, '()'));
        $rowCount = count($rows);

        echo "✓ Extracted $rowCount row(s)\n";
        $totalExtracted += $rowCount;
    } else {
        echo "⊘ No data found\n";
    }
}

// Add footer
$output .= "\n-- Re-enable foreign key checks\n";
$output .= "SET FOREIGN_KEY_CHECKS = 1;\n\n";
$output .= "-- End of migration\n";

// Write output file
file_put_contents($outputFile, $output);

echo "\n============================================================\n";
echo "EXTRACTION SUMMARY\n";
echo "============================================================\n";
echo "Total rows extracted: $totalExtracted\n";
echo "Output file: $outputFile\n";
echo "File size: " . number_format(filesize($outputFile)) . " bytes\n";
echo "============================================================\n";

if ($totalExtracted > 0) {
    echo "\n✓ Extraction completed successfully!\n";
    echo "\nTo import into new database:\n";
    echo "  mysql -u root -p loka_new < $outputFile\n";
} else {
    echo "\n⚠ No data extracted\n";
}

echo "\n";
?>
