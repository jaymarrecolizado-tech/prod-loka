<?php
/**
 * Reset Admin Password Script
 * For testing purposes only - DELETE after use!
 */

require_once __DIR__ . '/public_html/config/database.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

echo "=== Admin Password Reset ===\n\n";

// Set new password
$newPassword = 'admin123'; // Default test password
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update admin user (id = 1)
$stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = 1");
$stmt->bind_param("s", $hashedPassword);

if ($stmt->execute()) {
    echo "✅ Admin password has been reset!\n\n";
    echo "Login credentials:\n";
    echo "  URL: http://localhost/Projects/loka2/public_html/\n";
    echo "  Email: admin@fleet.local\n";
    echo "  Password: $newPassword\n\n";
    echo "Other test users:\n";
    $result = $mysqli->query("SELECT email, role FROM users WHERE status='active' LIMIT 5");
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['email']} ({$row['role']})\n";
    }
    echo "\n⚠️  Remember to delete this file after testing!\n";
} else {
    echo "❌ Failed to reset password: " . $stmt->error . "\n";
}

$stmt->close();
$mysqli->close();
