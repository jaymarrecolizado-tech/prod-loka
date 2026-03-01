<?php
/**
 * Create Missing Tables
 */

try {
    $pdo = new PDO("mysql:host=localhost;dbname=lokaloka2", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== Creating Missing Tables ===\n\n";

    // Check if security_log exists, if not create it
    $stmt = $pdo->query("SHOW TABLES LIKE 'security_log'");
    if ($stmt->rowCount() == 0) {
        echo "[1] Creating security_log table...\n";
        $pdo->exec("
            CREATE TABLE `security_log` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `event_type` VARCHAR(100) NOT NULL,
                `description` TEXT,
                `user_id` INT UNSIGNED DEFAULT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `user_agent` TEXT,
                `severity` ENUM('low','medium','high','critical') DEFAULT 'medium',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_event_type` (`event_type`),
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_created_at` (`created_at`),
                INDEX `idx_severity` (`severity`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        echo "  ✅ security_log table created\n";
    } else {
        echo "  ✅ security_log table already exists\n";
    }

    // Check if vehicle_types table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'vehicle_types'");
    if ($stmt->rowCount() == 0) {
        echo "\n[2] Creating vehicle_types table...\n";
        $pdo->exec("
            CREATE TABLE `vehicle_types` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT,
                `passenger_capacity` INT UNSIGNED DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_name` (`name`,`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        echo "  ✅ vehicle_types table created\n";
    } else {
        echo "\n  ✅ vehicle_types table already exists\n";
    }

    echo "\n=== Complete ===\n";
    echo "You can now access the application!\n";

} catch (PDOException $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}
