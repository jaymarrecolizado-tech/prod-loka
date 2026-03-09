<?php
/**
 * LOKA - Add Trip Ticket Types Migration
 *
 * Adds support for Type 1 (per-trip) and Type 2 (weekly summary) trip tickets
 */

// Load environment variables from .env file
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
                case 'DB_HOST':
                    $dbHost = $value;
                    break;
                case 'DB_NAME':
                    $dbName = $value;
                    break;
                case 'DB_USER':
                    $dbUser = $value;
                    break;
                case 'DB_PASSWORD':
                    $dbPass = $value;
                    break;
                case 'DB_CHARSET':
                    $dbCharset = $value;
                    break;
            }
        }
    }
}

echo "=== Migration: Add Trip Ticket Types ===\n\n";

try {
    $pdo = new PDO(
        sprintf("mysql:host=%s;dbname=%s;charset=%s", $dbHost, $dbName, $dbCharset),
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Connected to database: " . $dbName . "\n\n";

    // Add ticket_type column to trip_tickets table
    $sql = "ALTER TABLE trip_tickets
    ADD COLUMN ticket_type ENUM('type1', 'type2') NOT NULL DEFAULT 'type1'
    COMMENT 'Type 1 = per-trip, Type 2 = weekly summary'
    AFTER id";

    try {
        $pdo->exec($sql);
        echo "✓ Added ticket_type column to trip_tickets table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠ Column ticket_type already exists\n";
        } else {
            throw $e;
        }
    }

    // Add ticket_number column for Type 2 numbering
    $sql = "ALTER TABLE trip_tickets
    ADD COLUMN ticket_number VARCHAR(50) DEFAULT NULL
    COMMENT 'Formatted ticket number (e.g., 2026-448SQB-0301)'
    AFTER ticket_type";

    try {
        $pdo->exec($sql);
        echo "✓ Added ticket_number column to trip_tickets table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠ Column ticket_number already exists\n";
        } else {
            throw $e;
        }
    }

    // Add week_number column for Type 2 tickets
    $sql = "ALTER TABLE trip_tickets
    ADD COLUMN week_number INT UNSIGNED DEFAULT NULL
    COMMENT 'Week number for Type 2 tickets'
    AFTER ticket_number";

    try {
        $pdo->exec($sql);
        echo "✓ Added week_number column to trip_tickets table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠ Column week_number already exists\n";
        } else {
            throw $e;
        }
    }

    // Add week_start and week_end for Type 2 tickets
    $sql = "ALTER TABLE trip_tickets
    ADD COLUMN week_start DATE DEFAULT NULL
    COMMENT 'Week start date for Type 2 tickets'
    AFTER week_number";

    try {
        $pdo->exec($sql);
        echo "✓ Added week_start column to trip_tickets table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠ Column week_start already exists\n";
        } else {
            throw $e;
        }
    }

    $sql = "ALTER TABLE trip_tickets
    ADD COLUMN week_end DATE DEFAULT NULL
    COMMENT 'Week end date for Type 2 tickets'
    AFTER week_start";

    try {
        $pdo->exec($sql);
        echo "✓ Added week_end column to trip_tickets table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠ Column week_end already exists\n";
        } else {
            throw $e;
        }
    }

    // Add fuel_refill_data column for Type 2 manual input (JSON)
    $sql = "ALTER TABLE trip_tickets
    ADD COLUMN fuel_refill_data JSON DEFAULT NULL
    COMMENT 'JSON data for Type 2 fuel refill entries'
    AFTER fuel_cost";

    try {
        $pdo->exec($sql);
        echo "✓ Added fuel_refill_data column to trip_tickets table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠ Column fuel_refill_data already exists\n";
        } else {
            throw $e;
        }
    }

    // Add approval_type column for Type 2 tickets (dept_approver or motorpool)
    $sql = "ALTER TABLE trip_tickets
    ADD COLUMN approval_by ENUM('dept_approver', 'motorpool_head') DEFAULT 'dept_approver'
    COMMENT 'Who approved Type 2 ticket'
    AFTER status";

    try {
        $pdo->exec($sql);
        echo "✓ Added approval_by column to trip_tickets table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠ Column approval_by already exists\n";
        } else {
            throw $e;
        }
    }

    // Add indexes
    $sql = "ALTER TABLE trip_tickets
    ADD INDEX IF NOT EXISTS idx_ticket_type (ticket_type),
    ADD INDEX IF NOT EXISTS idx_ticket_number (ticket_number),
    ADD INDEX IF NOT EXISTS idx_week (week_start, week_end)";

    try {
        $pdo->exec($sql);
        echo "✓ Added indexes for Type 2 tickets\n";
    } catch (PDOException $e) {
        echo "⚠ Indexes may already exist\n";
    }

    echo "\n" . str_repeat('=', 50) . "\n";
    echo "MIGRATION COMPLETE\n";
    echo str_repeat('=', 50) . "\n\n";

    echo "Schema changes:\n";
    echo "- Added ticket_type column (type1/type2)\n";
    echo "- Added ticket_number column for formatted ticket numbers\n";
    echo "- Added week_number, week_start, week_end for Type 2 tickets\n";
    echo "- Added fuel_refill_data (JSON) for manual fuel input\n";
    echo "- Added approval_by column for Type 2 approval tracking\n\n";

} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
