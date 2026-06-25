<?php
require_once 'config/bootstrap.php';
$db = Database::getInstance();
echo "DB connected\n";
$user = $db->fetch("SELECT id, email, status FROM users WHERE email = ?", ["admin@fleet.local"]);
if ($user) {
    echo "User found: " . json_encode($user) . "\n";
} else {
    echo "User NOT found\n";
    $users = $db->fetchAll("SELECT id, email, status FROM users LIMIT 5");
    echo "Existing users:\n";
    foreach ($users as $u) {
        echo "  - " . $u["email"] . " (" . $u["status"] . ")\n";
    }
}
