<?php
/**
 * LOKA - Override Vehicle/Driver for Approved Request
 * 
 * Allows Motorpool Head to reassign vehicle/driver even after approval
 */

requireRole(ROLE_MOTORPOOL);
requireCsrf();

$requestId = postInt('request_id');
$vehicleId = postInt('vehicle_id');
$driverId = postInt('driver_id');
$overrideReason = postSafe('override_reason', '', 1000);

if (!$requestId || !$vehicleId || !$driverId) {
    redirectWith('/?page=requests&action=view&id=' . $requestId, 'danger', 'Missing required fields.');
}

if (empty($overrideReason)) {
    redirectWith('/?page=requests&action=view&id=' . $requestId, 'danger', 'Override reason is required.');
}

try {
    db()->beginTransaction();
    
    // Get current request with row lock
    $request = db()->fetch(
        "SELECT r.*, u.name as requester_name, u.email as requester_email
         FROM requests r
         JOIN users u ON r.user_id = u.id
         WHERE r.id = ? AND r.deleted_at IS NULL
         FOR UPDATE",
        [$requestId]
    );
    
    if (!$request) {
        throw new Exception('Request not found.');
    }
    
    if ($request->status !== STATUS_APPROVED) {
        throw new Exception('Can only override approved requests.');
    }
    
    // Store old assignments
    $oldVehicleId = $request->vehicle_id;
    $oldDriverId = $request->driver_id;
    
    // Get new vehicle and driver info
    $newVehicle = db()->fetch(
        "SELECT v.*, vt.name as type_name FROM vehicles v JOIN vehicle_types vt ON v.vehicle_type_id = vt.id WHERE v.id = ?",
        [$vehicleId]
    );
    $newDriver = db()->fetch(
        "SELECT d.*, u.name as driver_name FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.id = ?",
        [$driverId]
    );
    
    if (!$newVehicle || !$newDriver) {
        throw new Exception('Invalid vehicle or driver selected.');
    }
    
    // Release old vehicle and driver (if different)
    if ($oldVehicleId && $oldVehicleId != $vehicleId) {
        db()->update('vehicles', ['status' => VEHICLE_AVAILABLE, 'updated_at' => date(DATETIME_FORMAT)], 'id = ?', [$oldVehicleId]);
    }
    if ($oldDriverId && $oldDriverId != $driverId) {
        db()->update('drivers', ['status' => DRIVER_AVAILABLE, 'updated_at' => date(DATETIME_FORMAT)], 'id = ?', [$oldDriverId]);
    }
    
    // Set new vehicle and driver to in_use/on_trip
    if ($oldVehicleId != $vehicleId) {
        db()->update('vehicles', ['status' => VEHICLE_IN_USE, 'updated_at' => date(DATETIME_FORMAT)], 'id = ?', [$vehicleId]);
    }
    if ($oldDriverId != $driverId) {
        db()->update('drivers', ['status' => DRIVER_ON_TRIP, 'updated_at' => date(DATETIME_FORMAT)], 'id = ?', [$driverId]);
    }
    
    // Update request
    db()->update('requests', [
        'vehicle_id' => $vehicleId,
        'driver_id' => $driverId,
        'status' => STATUS_MODIFIED,
        'updated_at' => date(DATETIME_FORMAT)
    ], 'id = ?', [$requestId]);
    
    // Create approval record for override
    db()->insert('approvals', [
        'request_id' => $requestId,
        'approver_id' => userId(),
        'approval_type' => 'motorpool',
        'status' => 'approved',
        'comments' => "VEHICLE/DRIVER OVERRIDE: " . $overrideReason,
        'created_at' => date(DATETIME_FORMAT)
    ]);
    
    // Audit log
    auditLog('vehicle_driver_override', 'request', $requestId, [
        'old_vehicle_id' => $oldVehicleId,
        'old_driver_id' => $oldDriverId,
        'new_vehicle_id' => $vehicleId,
        'new_driver_id' => $driverId
    ], [
        'override_reason' => $overrideReason,
        'old_vehicle_id' => $oldVehicleId,
        'old_driver_id' => $oldDriverId,
        'new_vehicle_id' => $vehicleId,
        'new_driver_id' => $driverId
    ]);
    
    db()->commit();
    
    // Send notifications
    $vehicleInfo = "{$newVehicle->plate_number} - {$newVehicle->make} {$newVehicle->model}";
    $driverInfo = $newDriver->driver_name;
    
    // Notify requester
    notify(
        $request->user_id,
        'vehicle_driver_override',
        'Vehicle/Driver Assignment Changed',
        "Your approved trip to {$request->destination} has been reassigned.\n\nNew Vehicle: {$vehicleInfo}\nNew Driver: {$driverInfo}\n\nReason: {$overrideReason}",
        '/?page=requests&action=view&id=' . $requestId
    );
    
    // Notify new driver
    notify(
        $newDriver->user_id,
        'driver_assigned',
        'You Have Been Assigned as Driver (Override)',
        "You have been assigned as the driver for a trip to {$request->destination}.\n\nDeparture: " . formatDateTime($request->start_datetime) . "\nVehicle: {$vehicleInfo}\n\nThis is an override assignment.",
        '/?page=requests&action=view&id=' . $requestId
    );
    
    // Notify old driver if changed
    if ($oldDriverId && $oldDriverId != $driverId) {
        $oldDriver = db()->fetch("SELECT user_id FROM drivers WHERE id = ?", [$oldDriverId]);
        if ($oldDriver) {
            notify(
                $oldDriver->user_id,
                'driver_unassigned',
                'Driver Assignment Removed',
                "You have been removed from the trip to {$request->destination} on " . formatDate($request->start_datetime) . ".\n\nReason: {$overrideReason}",
                '/?page=requests&action=view&id=' . $requestId
            );
        }
    }
    
    // Notify passengers
    $passengers = db()->fetchAll(
        "SELECT user_id FROM request_passengers WHERE request_id = ? AND user_id IS NOT NULL",
        [$requestId]
    );
    
    foreach ($passengers as $passenger) {
        notify(
            $passenger->user_id,
            'trip_vehicle_changed',
            'Trip Vehicle/Driver Changed',
            "Your trip to {$request->destination} has a new vehicle/driver assignment.\n\nNew Vehicle: {$vehicleInfo}\nNew Driver: {$driverInfo}",
            '/?page=requests&action=view&id=' . $requestId
        );
    }
    
    redirectWith('/?page=requests&action=view&id=' . $requestId, 'success', 'Vehicle and driver have been reassigned successfully.');
    
} catch (Exception $e) {
    db()->rollback();
    error_log("Override error: " . $e->getMessage());
    redirectWith('/?page=requests&action=view&id=' . $requestId, 'danger', 'Failed to override: ' . $e->getMessage());
}
