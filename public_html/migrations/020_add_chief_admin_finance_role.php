<?php
/**
 * Migration 020: Add chief_admin_finance role to users table ENUM
 */

$envFile = __DIR__ . '/../.env';
$dbHost = 'localhost';
$dbName = 'loka_fleet';
$dbUser = 'root';
$dbPass = '';
$dbCharset = 'utf8mb4';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            switch ($name) {
                case 'DB_HOST': $dbHost = $value; break;
                case 'DB_NAME': $dbName = $value; break;
                case 'DB_USER': $dbUser = $value; break;
                case 'DB_PASSWORD': $dbPass = $value; break;
                case 'DB_CHARSET': $dbCharset = $value; break;
            }
        }
    }
}

echo "Migration 020: Adding chief_admin_finance role to users table...\n";

try {
    $pdo = new PDO(
        sprintf("mysql:host=%s;dbname=%s;charset=%s", $dbHost, $dbName, $dbCharset),
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec("
        ALTER TABLE users 
        MODIFY COLUMN role ENUM(
            'requester',
            'guard',
            'approver',
            'motorpool_head',
            'chief_admin_finance',
            'admin'
        ) NOT NULL DEFAULT 'requester'
    ");

    echo "✅ Successfully added 'chief_admin_finance' to users.role ENUM\n";
    echo "✅ Migration 020 complete\n";
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
