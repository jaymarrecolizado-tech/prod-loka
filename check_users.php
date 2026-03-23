<?php
require_once __DIR__ . '/public_html/config/database.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

echo "=== Database Structure ===\n\n";

// First check users table structure
echo "Users table columns:\n";
$result = $mysqli->query("DESCRIBE users");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

echo "\n=== Users in database ===\n";
$result = $mysqli->query("SELECT * FROM users LIMIT 10");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | ";
        echo "Name: " . ($row['first_name'] ?? 'N/A') . " " . ($row['last_name'] ?? 'N/A') . " | ";
        echo "Email: " . ($row['email'] ?? 'N/A') . " | ";
        echo "Role: " . ($row['role'] ?? 'N/A') . "\n";
    }
} else {
    echo "No users found.\n";
}

echo "\n=== Tables ===\n";
$result = $mysqli->query("SHOW TABLES");
if ($result) {
    echo "Total tables: " . $result->num_rows . "\n";
}

$mysqli->close();
