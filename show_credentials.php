<?php
/**
 * Show all user credentials from database
 * For testing purposes only
 */

require_once __DIR__ . '/public_html/config/database.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

echo "=== LOKA FMS - User Credentials ===\n\n";
echo "Database: " . DB_NAME . "\n";
echo str_repeat("=", 80) . "\n\n";

// Get all users
$result = $mysqli->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY id");

if ($result && $result->num_rows > 0) {
    echo sprintf("%-3s | %-25s | %-35s | %-15s | %-10s\n", "ID", "Name", "Email", "Role", "Status");
    echo str_repeat("-", 100) . "\n";

    while ($row = $result->fetch_assoc()) {
        printf("%-3s | %-25s | %-35s | %-15s | %-10s\n",
            $row['id'],
            substr($row['name'] ?: 'N/A', 0, 25),
            substr($row['email'], 0, 35),
            $row['role'],
            $row['status']
        );
    }

    echo "\n" . str_repeat("=", 80) . "\n";
    echo "\nDefault/Reset Passwords:\n";
    echo "  - admin@fleet.local: admin123 (just reset)\n";
    echo "  - Other users: (passwords are hashed, unknown)\n\n";

    echo "User Roles:\n";
    echo "  - admin: Full system access\n";
    echo "  - motorpool_head: Manages vehicles and drivers\n";
    echo "  - approver: Approves/rejects trip requests\n";
    echo "  - requester: Creates trip requests\n";
    echo "  - guard: Manages vehicle dispatch/arrival\n\n";

} else {
    echo "No users found in database.\n";
}

$mysqli->close();

echo str_repeat("=", 80) . "\n";
