<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=fleet_management;charset=utf8mb4", "root", "");
    $stmt = $pdo->query("SELECT r.vehicle_id, tt.start_date, tt.end_date, tt.destination, r.destination as r_dest FROM trip_tickets tt JOIN requests r ON tt.request_id = r.id");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
