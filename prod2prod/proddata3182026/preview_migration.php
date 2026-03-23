<?php
/**
 * Preview Migration Script
 * Shows what data will be imported before actually doing it
 */

$oldSqlFile = __DIR__ . '/127_0_0_1old.sql';
$targetDb = 'lokaloka2';

echo "============================================================\n";
echo "MIGRATION PREVIEW\n";
echo "============================================================\n";
echo "Target database: $targetDb\n";
echo "Source file: $oldSqlFile\n";
echo "============================================================\n\n";

// Connect to target database
$pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("USE $targetDb");

// Tables to check
$tables = [
    'users' => ['name' => 'Users', 'key' => 'id'],
    'departments' => ['name' => 'Departments', 'key' => 'id'],
    'vehicles' => ['name' => 'Vehicles', 'key' => 'id'],
    'drivers' => ['name' => 'Drivers', 'key' => 'id'],
    'requests' => ['name' => 'Requests', 'key' => 'id'],
    'request_passengers' => ['name' => 'Passengers', 'key' => 'id'],
    'assignment_history' => ['name' => 'Assignment History', 'key' => 'id'],
    'approvals' => ['name' => 'Approvals', 'key' => 'id'],
    'approval_workflow' => ['name' => 'Approval Workflow', 'key' => 'id'],
];

// Get old data counts
$content = file_get_contents($oldSqlFile);

echo "Current State vs. Old Data:\n";
echo str_repeat("-", 70) . "\n";
printf("%-25s %12s %12s %12s %12s\n", "Table", "In Target", "In Old", "To Add", "Skipped");
echo str_repeat("-", 70) . "\n";

foreach ($tables as $table => $info) {
    // Get current count in target DB
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $currentCount = $stmt->fetchColumn();
    } catch (Exception $e) {
        $currentCount = 0;
    }

    // Get existing IDs in target DB
    try {
        $stmt = $pdo->query("SELECT `{$info['key']}` FROM `$table`");
        $existingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $existingIds = [];
    }
    $existingIds = array_flip($existingIds);

    // Get old data count
    $pattern = '/INSERT\s+INTO\s+`' . preg_quote($table, '/') . '`\s*\([^)]+\)\s*VALUES\s+([^;]+);/s';
    $oldCount = 0;
    $willAdd = 0;
    $willSkip = 0;

    if (preg_match($pattern, $content, $matches)) {
        $valuesPart = $matches[1];
        $rows = preg_split('/\),\s*\(/', trim($valuesPart, '()'));
        $oldCount = count($rows);

        // Count how many are new vs duplicates
        foreach ($rows as $row) {
            // Extract ID from the row
            if (preg_match('/^\s*(\d+),/', $row, $idMatch)) {
                $id = $idMatch[1];
                if (isset($existingIds[$id])) {
                    $willSkip++;
                } else {
                    $willAdd++;
                }
            }
        }
    }

    printf("%-25s %12d %12d %12d %12d\n",
        $info['name'],
        $currentCount,
        $oldCount,
        $willAdd,
        $willSkip
    );
}

echo str_repeat("-", 70) . "\n";

// Check some specific examples
echo "\nSample Old Requests (top 5):\n";
echo str_repeat("-", 70) . "\n";

$pattern = '/INSERT\s+INTO\s+`requests`\s*\([^)]+\)\s*VALUES\s+([^;]+);/s';
if (preg_match($pattern, $content, $matches)) {
    $valuesPart = $matches[1];
    preg_match_all('/\(([^)]+)\)/', $valuesPart, $rowMatches);

    for ($i = 0; $i < min(5, count($rowMatches[1])); $i++) {
        $values = explode(',', $rowMatches[1][$i]);
        if (isset($values[0]) && isset($values[10]) && isset($values[11]) && isset($values[13])) {
            $id = trim($values[0]);
            $purpose = trim(preg_replace('/^["\'](.*)["\']$/', '$1', $values[10]));
            $destination = trim(preg_replace('/^["\'](.*)["\']$/', '$1', $values[11]));
            $status = trim(preg_replace('/^["\'](.*)["\']$/', '$1', $values[13]));

            printf("#%s | %s | %s | %s\n", $id, $status, $destination, $purpose);
        }
    }
}

echo str_repeat("-", 70) . "\n";

// Get some recent requests from target DB
echo "\nRecent Requests in Target DB:\n";
echo str_repeat("-", 70) . "\n";

$stmt = $pdo->query("
    SELECT id, status, destination,
           SUBSTRING(purpose, 1, 50) as purpose_preview
    FROM requests
    ORDER BY created_at DESC
    LIMIT 5
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("#%s | %s | %s | %s\n",
        $row['id'],
        $row['status'],
        $row['destination'],
        $row['purpose_preview'] . '...'
    );
}

echo str_repeat("-", 70) . "\n";

echo "\n=== READY TO IMPORT ===\n";
echo "\nTo proceed with the migration, run:\n";
echo "  mysql -u root -p lokaloka2 < trip_data_migration.sql\n";
echo "\nOr use PHP:\n";
echo "  php import_migration.php\n";
echo "\n";
?>
