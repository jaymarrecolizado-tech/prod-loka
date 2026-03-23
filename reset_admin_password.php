<?php
/**
 * Reset admin password
 */
require_once __DIR__ . '/public_html/config/database.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

$newPassword = 'Q1w2e3r4t5!@#QWE';
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$email = 'admin@fleet.local';

$stmt = $mysqli->prepare('UPDATE users SET password = ? WHERE email = ?');
$stmt->bind_param('ss', $hashedPassword, $email);

if ($stmt->execute()) {
    echo "========================================\n";
    echo "Password updated successfully!\n";
    echo "========================================\n";
    echo "Email:    $email\n";
    echo "Password: $newPassword\n";
    echo "========================================\n";
} else {
    echo "Error updating password: " . $stmt->error . "\n";
}

$stmt->close();
$mysqli->close();
