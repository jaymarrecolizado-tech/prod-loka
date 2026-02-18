-- Migration 013: Assignment History Table
-- Tracks all vehicle/driver assignment changes for requests
-- Created: 2026-02-18

CREATE TABLE IF NOT EXISTS assignment_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    vehicle_id INT UNSIGNED NULL,
    driver_id INT UNSIGNED NULL,
    assigned_by INT UNSIGNED NOT NULL,
    action ENUM('assigned', 'overridden', 'released', 'completed') NOT NULL DEFAULT 'assigned',
    previous_vehicle_id INT UNSIGNED NULL,
    previous_driver_id INT UNSIGNED NULL,
    reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_request_id (request_id),
    INDEX idx_vehicle_id (vehicle_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_assigned_by (assigned_by),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (previous_vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (previous_driver_id) REFERENCES drivers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
