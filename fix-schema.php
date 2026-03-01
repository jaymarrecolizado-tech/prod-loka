<?php
/**
 * Fix Database Schema - Add Missing Columns
 * This script adds missing columns to match the current code
 */

try {
    $pdo = new PDO("mysql:host=localhost;dbname=lokaloka2", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== Fixing Database Schema ===\n\n";

    // Check current columns in requests table
    echo "[1] Checking requests table structure...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM requests");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }
    echo "Found " . count($existingColumns) . " columns.\n";

    // Columns that should exist
    $requiredColumns = [
        'mileage_actual' => "INT UNSIGNED DEFAULT NULL COMMENT 'Actual trip mileage'",
        'mileage_start' => "INT UNSIGNED DEFAULT NULL COMMENT 'Mileage at trip start'",
        'actual_dispatch_datetime' => "DATETIME DEFAULT NULL COMMENT 'Actual dispatch time recorded by guard'",
        'actual_arrival_datetime' => "DATETIME DEFAULT NULL COMMENT 'Actual arrival time recorded by guard'",
        'dispatch_guard_id' => "INT UNSIGNED DEFAULT NULL COMMENT 'Guard who recorded dispatch'",
        'arrival_guard_id' => "INT UNSIGNED DEFAULT NULL COMMENT 'Guard who recorded arrival'",
        'guard_notes' => "TEXT DEFAULT NULL COMMENT 'Notes from guard about dispatch/arrival'",
        'requested_driver_id' => "INT UNSIGNED DEFAULT NULL COMMENT 'Driver requested by user'",
        'motorpool_head_id' => "INT UNSIGNED DEFAULT NULL COMMENT 'Motorpool head who approved'",
        'has_travel_order' => "TINYINT(1) DEFAULT 0 COMMENT 'Has travel order document'",
        'travel_order_number' => "VARCHAR(100) DEFAULT NULL COMMENT 'Travel order document number'",
        'has_official_business_slip' => "TINYINT(1) DEFAULT 0 COMMENT 'Has official business slip'",
        'ob_slip_number' => "VARCHAR(100) DEFAULT NULL COMMENT 'OB slip number'",
        'deleted_at' => "TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp'",
        'completed_at' => "TIMESTAMP NULL DEFAULT NULL COMMENT 'Completion timestamp'"
    ];

    echo "\n[2] Adding missing columns...\n";
    $added = 0;
    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $existingColumns)) {
            try {
                $sql = "ALTER TABLE requests ADD COLUMN `$column` $definition";
                $pdo->exec($sql);
                echo "  ✅ Added: $column\n";
                $added++;
            } catch (PDOException $e) {
                echo "  ❌ Error adding $column: " . $e->getMessage() . "\n";
            }
        }
    }

    if ($added === 0) {
        echo "  All required columns already exist.\n";
    } else {
        echo "  Added $added columns.\n";
    }

    // Add indexes
    echo "\n[3] Adding indexes...\n";
    $indexes = [
        "CREATE INDEX idx_requests_status_completed ON requests(status, actual_arrival_datetime)" => null,
        "CREATE INDEX idx_requests_deleted ON requests(deleted_at)" => null,
        "CREATE INDEX idx_requests_driver_completed ON requests(driver_id, status)" => null
    ];

    foreach ($indexes as $indexSql => $unused) {
        try {
            $pdo->exec($indexSql);
            echo "  ✅ Index created\n";
        } catch (PDOException $e) {
            // Index might already exist, ignore
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                echo "  ⚠️  " . $e->getMessage() . "\n";
            }
        }
    }

    // Check other important tables
    echo "\n[4] Checking other tables...\n";

    // Check if security_log exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'security_log'");
    if ($stmt->rowCount() == 0) {
        echo "  ⚠️  security_log table missing\n";
    } else {
        echo "  ✅ security_log table exists\n";
    }

    // Check if rate_limits exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'rate_limits'");
    if ($stmt->rowCount() == 0) {
        echo "  ⚠️  rate_limits table missing\n";
    } else {
        echo "  ✅ rate_limits table exists\n";
    }

    echo "\n=== Schema Fix Complete ===\n";

} catch (PDOException $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}
