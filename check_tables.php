<?php
/**
 * Check table structures for foreign key issues
 */
$pdo = new PDO('mysql:host=localhost;dbname=lokaloka2', 'root', '');

echo "=== Drivers Table ===\n";
$stmt = $pdo->query('DESCRIBE drivers');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf('%s %s %s %s', $row['Field'], $row['Type'], $row['Null'], $row['Key']);
    if (!empty($row['Extra'])) echo ' ' . $row['Extra'];
    echo "\n";
}

echo "\n=== Users Table ===\n";
$stmt = $pdo->query('DESCRIBE users');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf('%s %s %s %s', $row['Field'], $row['Type'], $row['Null'], $row['Key']);
    if (!empty($row['Extra'])) echo ' ' . $row['Extra'];
    echo "\n";
}

echo "\n=== Requests Table ===\n";
$stmt = $pdo->query('DESCRIBE requests');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf('%s %s %s %s', $row['Field'], $row['Type'], $row['Null'], $row['Key']);
    if (!empty($row['Extra'])) echo ' ' . $row['Extra'];
    echo "\n";
}

$pdo = null;
