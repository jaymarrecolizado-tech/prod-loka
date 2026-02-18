<?php
/**
 * Import Users from CSV
 * Run: php import_users.php
 */

// Load config directly without full index
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            putenv(sprintf('%s=%s', trim($parts[0]), trim($parts[1])));
        }
    }
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/classes/Database.php';

$csvFile = __DIR__ . '/../data/DICT R2 - Email - Sheet1.csv';

if (!file_exists($csvFile)) {
    die("CSV file not found: $csvFile\n");
}

$rows = array_map('str_getcsv', file($csvFile));
array_shift($rows); // Remove header row

$db = Database::getInstance();
$imported = 0;
$skipped = 0;

echo "Starting import...\n";

foreach ($rows as $row) {
    if (count($row) < 4) continue;
    
    $surname = trim($row[0]);
    $firstName = trim($row[1]);
    $middleName = trim($row[2]);
    $email = trim($row[3]);
    
    // Clean email
    $email = str_replace(' ', '', $email);
    $email = strtolower($email);
    
    // Fix missing .dict.gov.ph
    if (strpos($email, '@dict') === false && strpos($email, '@') !== false) {
        $email = str_replace('@', '@dict.gov.ph', $email);
    }
    // Fix @dictgov.ph
    $email = str_replace('@dictgov.ph', '@dict.gov.ph', $email);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Skipping invalid email: $email\n";
        $skipped++;
        continue;
    }
    
    // Check if user exists
    $existing = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        echo "User already exists: $email\n";
        $skipped++;
        continue;
    }
    
    // Generate name from parts
    $name = "$firstName $surname";
    if (!empty($middleName)) {
        $name .= " " . substr($middleName, 0, 1) . ".";
    }
    
    // Hash password
    $passwordHash = password_hash('password123', PASSWORD_DEFAULT);
    
    // Insert user - assign as REQUESTER role
    $stmt = $db->getConnection()->prepare("
        INSERT INTO users (name, email, password, role, department_id, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, NULL, ?, NOW(), NOW())
    ");
    
    try {
        $stmt->execute([$name, $email, $passwordHash, 'requester', 'active']);
        echo "Imported: $name <$email>\n";
        $imported++;
    } catch (Exception $e) {
        echo "Error importing $email: " . $e->getMessage() . "\n";
        $skipped++;
    }
}

echo "\n=== Import Complete ===\n";
echo "Imported: $imported\n";
echo "Skipped: $skipped\n";
