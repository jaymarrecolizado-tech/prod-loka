<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=lokaloka2;charset=utf8mb4", "root", "");
    $stmt = $pdo->query("SHOW COLUMNS FROM requests");
    file_put_contents('test_output.json', json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
} catch (Exception $e) {
    echo $e->getMessage();
}
