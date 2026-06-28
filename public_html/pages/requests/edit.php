<?php
/**
 * LOKA - Edit Request Page (Hardened Version)
 * 
 * Allows editing all relevant fields including:
 * - Date/time, purpose, destination, notes
 * - Passengers
 * - Vehicle selection
 * - Requested driver
 * - Approval workflow (approver and motorpool head)
 */

$requestId = (int) get('id');
$errors = [];

// Get request with FOR UPDATE locking - PREVENTS RACE CONDITIONS
$request = db()->fetch(
    "SELECT * FROM requests WHERE id = ? AND deleted_at IS NULL FOR UPDATE",
    [$requestId]
);

if (!$request) {
    redirectWith('/?page=requests', 'danger', 'Request not found.');
}

// Check ownership
if ($request->user_id !== userId() && !isAdmin()) {
    redirectWith('/?page=requests', 'danger', 'You can only edit your own requests.');
}

// Check if editable
$editableStatuses = [STATUS_PENDING, STATUS_DRAFT, STATUS_REVISION];
if (!in_array($request->status, $editableStatuses)) {
    redirectWith('/?page=requests', 'danger', 'This request cannot be edited in its current state.');
}

// Get available vehicles for selection
$availableVehicles = db()->fetchAll(
    "SELECT v.*, vt.name as type_name, vt.passenger_capacity
     FROM vehicles v
     JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
     WHERE v.deleted_at IS NULL 
     AND v.status IN ('available', 'in_use')
     ORDER BY vt.name, v.plate_number"
);

// Get all active employees for passenger selection (exclude current user)
$employees = db()->fetchAll(
    "SELECT u.id, u.name, u.email, d.name as department_name 
     FROM users u 
     LEFT JOIN departments d ON u.department_id = d.id
     WHERE u.status = 'active' AND u.deleted_at IS NULL AND u.id != ?
     ORDER BY u.name",
    [userId()]
);

// Get department approvers (approver or admin role in any department)
$approvers = db()->fetchAll(
    "SELECT u.id, u.name, d.name as department_name 
     FROM users u 
     LEFT JOIN departments d ON u.department_id = d.id
     WHERE u.role IN ('approver', 'admin') AND u.status = 'active' AND u.deleted_at IS NULL
     ORDER BY u.name"
);

// Get all active drivers
$allDrivers = db()->fetchAll(
    "SELECT d.*, u.name as driver_name, u.phone as driver_phone
     FROM drivers d
     JOIN users u ON d.user_id = u.id
     WHERE d.deleted_at IS NULL AND u.status = 'active' AND u.deleted_at IS NULL
     ORDER BY u.name"
);

// Get motorpool heads
$motorpoolHeads = db()->fetchAll(
    "SELECT u.id, u.name 
     FROM users u 
     WHERE u.role IN (?, ?) AND u.status = 'active' AND u.deleted_at IS NULL
     ORDER BY u.name",
    [ROLE_MOTORPOOL, ROLE_ADMIN]
);

// Get current passengers (both users and guests)
$currentPassengers = db()->fetchAllArray(
    "SELECT COALESCE(user_id, guest_name) as identifier, user_id, guest_name FROM request_passengers WHERE request_id = ?",
    [$requestId]
);
$currentPassengerIdentifiers = array_column($currentPassengers, 'identifier');
$currentPassengerIds = array_filter(array_column($currentPassengers, 'user_id'));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $startDatetime = postSafe('start_datetime', '', 20);
    $endDatetime = postSafe('end_datetime', '', 20);
    $purpose = postSafe('purpose', '', 500);
    $destinationRaw = $_POST['destinations'] ?? [];
    $passengerIds = $_POST['passengers'] ?? [];
    
    // Process destinations - filter empty values and combine
    $destinations = array_filter(array_map('trim', $destinationRaw), function($d) {
        return !empty($d);
    });
    $destination = implode(' → ', $destinations);
    
    // Count passengers properly - filter out empty values
    $passengerIds = array_filter($passengerIds, function($p) {
        return !empty(trim($p));
    });
    
    // Passenger count = selected passengers + requester (1)
    $passengerCount = count($passengerIds) + 1;
    
    $vehicleId = postInt('vehicle_id') ?: null;
    $notes = postSafe('notes', '', 1000);
    $approverId = postInt('approver_id');
    $motorpoolHeadId = postInt('motorpool_head_id');
    $requestedDriverId = postInt('requested_driver_id') ?: null;

    // Validation
    $manilaTz = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $manilaTz);
    
    if (empty($startDatetime))
        $errors[] = 'Start date/time is required';
    if (empty($endDatetime))
        $errors[] = 'End date/time is required';
    if ($startDatetime && $endDatetime) {
        $startDt = new DateTime($startDatetime, $manilaTz);
        $endDt = new DateTime($endDatetime, $manilaTz);
        if ($endDt <= $startDt) {
            $errors[] = 'End date/time must be after start date/time';
        }
    }
    if (empty($purpose))
        $errors[] = 'Purpose is required';
    if (empty($destinations))
        $errors[] = 'At least one destination is required';
    if (!$approverId)
        $errors[] = 'Please select a department approver';
    if (!$motorpoolHeadId)
        $errors[] = 'Please select a motorpool head';
    
    // Validate passenger capacity against vehicle (if vehicle selected)
    if ($vehicleId) {
        $vehicle = db()->fetch(
            "SELECT v.*, vt.passenger_capacity 
             FROM vehicles v 
             JOIN vehicle_types vt ON v.vehicle_type_id = vt.id 
             WHERE v.id = ? AND v.deleted_at IS NULL",
            [$vehicleId]
        );
        
        if ($vehicle && $vehicle->passenger_capacity > 0 && $passengerCount > $vehicle->passenger_capacity) {
            $errors[] = "This vehicle can only accommodate {$vehicle->passenger_capacity} passengers, but you have {$passengerCount} passengers (including yourself). Please select a larger vehicle or reduce passengers.";
        }
    }

    if (empty($errors)) {
        try {
            db()->beginTransaction();

            $oldData = (array) $request;
            
            // Initialize notification queue
            $deferredNotifications = [];

            // If request was in revision status, reset to pending for resubmission
            $wasRevision = ($request->status === STATUS_REVISION);
            $updateData = [
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'purpose' => $purpose,
                'destination' => $destination,
                'notes' => $notes,
                'vehicle_id' => $vehicleId,
                'approver_id' => $approverId,
                'motorpool_head_id' => $motorpoolHeadId,
                'requested_driver_id' => $requestedDriverId,
                'updated_at' => date(DATETIME_FORMAT)
            ];
            
            if ($wasRevision) {
                // Check who sent it for revision to route appropriately
                $revisionApproval = db()->fetch(
                    "SELECT approval_type FROM approvals WHERE request_id = ? AND status = 'revision' ORDER BY created_at DESC LIMIT 1",
                    [$requestId]
                );
                
                // If motorpool sent for revision, route back to motorpool; otherwise department
                if ($revisionApproval && $revisionApproval->approval_type === 'motorpool') {
                    $updateData['status'] = STATUS_PENDING_MOTORPOOL;
                } else {
                    $updateData['status'] = STATUS_PENDING;
                }
                $updateData['viewed_at'] = null;
                
                // Notify the appropriate approver
                $approverId = ($revisionApproval && $revisionApproval->approval_type === 'motorpool') 
                    ? $request->motorpool_head_id 
                    : $request->approver_id;
                    
                $approver = db()->fetch(
                    "SELECT id, name, email FROM users WHERE id = ?",
                    [$approverId]
                );
                
                if ($approver) {
                    $deferredNotifications[] = [
                        'user_id' => $approver->id,
                        'type' => 'request_submitted',
                        'title' => 'Request Resubmitted for Approval',
                        'message' => currentUser()->name . " has resubmitted a vehicle request for {$destination} on " . date('M j, Y', strtotime($startDatetime)) . " after revision. Please review the updated request.",
                        'link' => '/?page=approvals&action=view&id=' . $requestId
                    ];
                }
            }

            db()->update('requests', $updateData, 'id = ?', [$requestId]);
            
            // Handle passenger changes (Syncing users and guests)
            $newPassengerValues = array_map('trim', $passengerIds);
            $added = array_diff($newPassengerValues, $currentPassengerIdentifiers);
            $removed = array_diff($currentPassengerIdentifiers, $newPassengerValues);

            // Remove old passengers
            foreach ($removed as $identifier) {
                if (is_numeric($identifier)) {
                    db()->delete('request_passengers', 'request_id = ? AND user_id = ?', [$requestId, (int) $identifier]);
                    $deferredNotifications[] = [
                        'user_id' => (int) $identifier,
                        'type' => 'removed_from_request',
                        'title' => 'Removed from Trip',
                        'message' => 'You have been removed from a vehicle request by ' . currentUser()->name . '.',
                        'link' => '/?page=requests&action=view&id=' . $requestId
                    ];
                } else {
                    db()->delete('request_passengers', 'request_id = ? AND guest_name = ?', [$requestId, $identifier]);
                }
            }

            // Add new passengers
            foreach ($added as $val) {
                if (is_numeric($val)) {
                    db()->insert('request_passengers', [
                        'request_id' => $requestId,
                        'user_id' => (int) $val,
                        'created_at' => date(DATETIME_FORMAT)
                    ]);
                    $deferredNotifications[] = [
                        'user_id' => (int) $val,
                        'type' => 'added_to_request',
                        'title' => 'Added to Vehicle Request',
                        'message' => currentUser()->name . ' has added you as a passenger for a trip to ' . $destination . ' on ' . date('M j, Y', strtotime($startDatetime)) . '.',
                        'link' => '/?page=requests&action=view&id=' . $requestId
                    ];
                } else {
                    db()->insert('request_passengers', [
                        'request_id' => $requestId,
                        'guest_name' => $val,
                        'created_at' => date(DATETIME_FORMAT)
                    ]);
                }
            }
            
            // Recalculate actual passenger count from database (requester + passengers)
            $actualPassengerCount = db()->fetch(
                "SELECT COUNT(*) + 1 as count FROM request_passengers WHERE request_id = ?",
                [$requestId]
            )->count;
            
            // Update passenger_count with actual count
            db()->update('requests', [
                'passenger_count' => $actualPassengerCount
            ], 'id = ?', [$requestId]);

            // Check if details changed - notify existing passengers
            $detailsChanged = (
                $oldData['destination'] !== $destination ||
                $oldData['start_datetime'] !== $startDatetime ||
                $oldData['end_datetime'] !== $endDatetime
            );
            
            // Notify existing (unchanged) system users if details changed
            $unchanged = array_intersect($currentPassengerIdentifiers, $newPassengerValues);
            if ($detailsChanged && !empty($unchanged)) {
                foreach ($unchanged as $id) {
                    if (is_numeric($id)) {
                        $deferredNotifications[] = [
                            'user_id' => (int) $id,
                            'type' => 'request_modified',
                            'title' => 'Trip Details Updated',
                            'message' => 'A trip you are part of has been modified by ' . currentUser()->name . '.',
                            'link' => '/?page=requests&action=view&id=' . $requestId
                        ];
                    }
                }
            }
            
            // Notify requester if request was modified
            if (
                $oldData['destination'] !== $destination ||
                $oldData['start_datetime'] !== $startDatetime ||
                $oldData['end_datetime'] !== $endDatetime ||
                $oldData['purpose'] !== $purpose
            ) {
                $deferredNotifications[] = [
                    'user_id' => $request->user_id,
                    'type' => 'request_modified',
                    'title' => 'Trip Details Updated',
                    'message' => 'Your vehicle request has been modified. Please review the updated details.',
                    'link' => '/?page=requests&action=view&id=' . $requestId
                ];
            }
            
            // Prepare driver notification (deferred)
            $deferredDriverNotification = null;
            $requestedDriverId = $request->requested_driver_id ?? null;
            
            if ($requestedDriverId && $detailsChanged) {
                $deferredDriverNotification = [
                    'driver_id' => $requestedDriverId,
                    'type' => 'driver_status_update',
                    'title' => 'Trip Details Updated',
                    'message' => 'A trip you were requested to drive has been modified. Please review the updated details.',
                    'link' => '/?page=requests&action=view&id=' . $requestId
                ];
            }

            auditLog('request_updated', 'request', $requestId, $oldData, [
                'purpose' => $purpose,
                'destination' => $destination,
                'passenger_count' => $passengerCount
            ]);

            db()->commit();
            
            // =====================================================
            // SEND NOTIFICATIONS AFTER SUCCESSFUL COMMIT
            // =====================================================
            
            // Send deferred notifications
            foreach ($deferredNotifications as $notif) {
                notify($notif['user_id'], $notif['type'], $notif['title'], $notif['message'], $notif['link']);
            }
            
            // Send driver notification if needed
            if ($deferredDriverNotification) {
                notifyDriver(
                    $deferredDriverNotification['driver_id'],
                    $deferredDriverNotification['type'],
                    $deferredDriverNotification['title'],
                    $deferredDriverNotification['message'],
                    $deferredDriverNotification['link']
                );
            }

            $message = $wasRevision 
                ? 'Request resubmitted successfully. It will be reviewed again by the approver.'
                : 'Request updated successfully.';
            redirectWith('/?page=requests&action=view&id=' . $requestId, 'success', $message);

        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Failed to update request. Please try again.';
            error_log("Request update error: " . $e->getMessage());
        }
    }
}

$pageTitle = 'Edit Request #' . $requestId;

// If request is in revision status, get the revision comments
$revisionComments = null;
$revisionBy = null;
if ($request->status === STATUS_REVISION) {
    $revisionApproval = db()->fetch(
        "SELECT a.*, u.name as approver_name 
         FROM approvals a 
         JOIN users u ON a.approver_id = u.id 
         WHERE a.request_id = ? AND a.status = 'revision' 
         ORDER BY a.created_at DESC LIMIT 1",
        [$requestId]
    );
    if ($revisionApproval) {
        $revisionComments = $revisionApproval->comments;
        $revisionBy = $revisionApproval->approver_name;
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="max-w-6xl mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Edit Request #<?= $requestId ?>
            <?php if ($request->status === STATUS_REVISION): ?>
                <span class="loka-badge loka-badge-warning ml-2"><i class="bi bi-arrow-repeat mr-1"></i>Revision Requested</span>
            <?php endif; ?>
        </h1>
        <div class="text-sm breadcrumbs mt-1">
            <ul>
                <li><a href="<?= APP_URL ?>">Dashboard</a></li>
                <li><a href="<?= APP_URL ?>/?page=requests">Requests</a></li>
                <li>Edit</li>
            </ul>
        </div>
    </div>

    <?php if ($request->status === STATUS_REVISION && $revisionComments): ?>
    <div class="loka-alert loka-alert-warning border-l-4 border-warning mb-6">
        <h6 class="font-semibold mb-2"><i class="bi bi-exclamation-triangle mr-2"></i>Revision Requested by <?= e($revisionBy) ?></h6>
        <p class="mb-0"><strong>Reason:</strong> <?= nl2br(e($revisionComments)) ?></p>
        <hr class="my-2 border-warning/30">
        <small class="text-base-content/50">Please address the feedback above and resubmit your request for approval.</small>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Form -->
        <div class="lg:col-span-2">
            <div class="loka-card">
                <div class="p-4 border-b border-base-200">
                    <h2 class="text-lg font-semibold"><i class="bi bi-pencil mr-2"></i>Edit Details</h2>
                </div>
                <div class="p-6">
                    <?php if (!empty($errors)): ?>
                        <div class="loka-alert loka-alert-danger mb-4">
                            <ul class="list-disc pl-4">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="requestForm">
                        <?= csrfField() ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="start_datetime" class="label">
                                    <span class="label-text font-medium">Start Date/Time <span class="text-error">*</span></span>
                                </label>
                                <input type="text" class="input input-bordered w-full datetimepicker" id="start_datetime"
                                    name="start_datetime"
                                    value="<?= e(post('start_datetime', $request->start_datetime)) ?>" required>
                            </div>

                            <div>
                                <label for="end_datetime" class="label">
                                    <span class="label-text font-medium">End Date/Time <span class="text-error">*</span></span>
                                </label>
                                <input type="text" class="input input-bordered w-full datetimepicker" id="end_datetime"
                                    name="end_datetime" value="<?= e(post('end_datetime', $request->end_datetime)) ?>"
                                    required>
                            </div>

                            <div class="md:col-span-2">
                                <label for="purpose" class="label">
                                    <span class="label-text font-medium">Purpose <span class="text-error">*</span></span>
                                </label>
                                <textarea class="textarea textarea-bordered w-full" id="purpose" name="purpose" rows="3"
                                    required maxlength="1000"><?= e(post('purpose', $request->purpose)) ?></textarea>
                            </div>

                            <div class="md:col-span-2">
                                <label for="destination" class="label">
                                    <span class="label-text font-medium">Destination <span class="text-error">*</span></span>
                                </label>
                                <div class="loka-alert loka-alert-info text-sm py-2 mb-2">
                                    <i class="bi bi-info-circle mr-1"></i>
                                    <strong>Note:</strong> Add locations in sequential order (first stop to last stop).
                                </div>
                                <div id="destinationsContainer">
                                    <?php 
                                    $existingDest = post('destination', $request->destination);
                                    $destinations = post('destinations', []);
                                    if (empty($destinations) && $existingDest) {
                                        $destinations = array_map('trim', explode('→', $existingDest));
                                    }
                                    if (empty($destinations)) {
                                        $destinations = [''];
                                    }
                                    foreach ($destinations as $index => $dest): 
                                    ?>
                                    <div class="destination-row mb-2">
                                        <div class="join w-full">
                                            <span class="join-item badge badge-primary min-w-[45px]">
                                                <i class="bi bi-geo-alt"></i> <?= $index + 1 ?>
                                            </span>
                                            <input type="text" class="input input-bordered join-item flex-1 destination-input" 
                                                   name="destinations[]" 
                                                   value="<?= e($dest) ?>" 
                                                   placeholder="Enter location address..."
                                                   <?= $index === 0 ? 'required' : '' ?>>
                                            <?php if ($index > 0): ?>
                                            <button type="button" class="loka-btn-outline-error join-item remove-destination" title="Remove location">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="loka-btn-outline-primary loka-btn-sm mt-2" id="addDestinationBtn">
                                    <i class="bi bi-plus-circle mr-1"></i>Add Another Location
                                </button>
                                <input type="hidden" name="destination" id="destinationCombined">
                            </div>

                            <!-- Passengers Summary & Modal Trigger -->
                            <div class="md:col-span-2 mt-4">
                                <div class="border border-primary/25 bg-primary/10 rounded-xl p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h6 class="font-semibold"><i class="bi bi-people-fill mr-2 text-primary"></i>Passengers</h6>
                                            <div id="passengerCountText" class="mt-1">
                                                 <span class="loka-badge loka-badge-primary"><?= $request->passenger_count ?></span>
                                                <span class="text-sm text-base-content/60 ml-1">Passengers (Requester Included)</span>
                                            </div>
                                        </div>
                                        <button type="button" class="loka-btn-primary loka-btn-sm rounded-full px-4"
                                                onclick="document.getElementById('passengerModal').showModal()">
                                            <i class="bi bi-person-plus mr-1"></i>Manage Passengers
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Vehicle Selection -->
                            <div class="mt-3">
                                <label for="vehicle_id" class="label">
                                    <span class="label-text font-medium">Select Vehicle</span>
                                </label>
                                <select class="select select-bordered w-full" id="vehicle_id" name="vehicle_id">
                                    <option value="">Choose a vehicle...</option>
                                    <?php foreach ($availableVehicles as $vehicle): 
                                        $vehicle = (object) $vehicle;
                                    ?>
                                    <option value="<?= $vehicle->id ?>" 
                                            data-capacity="<?= $vehicle->passenger_capacity ?>"
                                            data-type="<?= e($vehicle->type_name ?? '') ?>"
                                            <?= (post('vehicle_id') == $vehicle->id || $request->vehicle_id == $vehicle->id) ? 'selected' : '' ?>>
                                        <?= e($vehicle->plate_number) ?> - <?= e($vehicle->make . ' ' . $vehicle->model) ?>
                                        (<?= e($vehicle->type_name ?? '') ?>, <?= $vehicle->passenger_capacity ?> seats)
                                        <?= $vehicle->status === 'in_use' ? ' [Currently in use]' : '' ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="label">
                                    <span class="label-text-alt text-base-content/50">Select the vehicle you need for this trip</span>
                                </label>
                                <div id="vehicleCapacityAlert" class="loka-alert loka-alert-warning text-sm py-2 mt-2 hidden">
                                    <i class="bi bi-exclamation-triangle mr-1"></i>
                                    <span class="message"></span>
                                </div>
                            </div>

                            <!-- Requested Driver -->
                            <div class="mt-3">
                                <label for="requested_driver_id" class="label">
                                    <span class="label-text font-medium">Requested Driver (Optional)</span>
                                </label>
                                <select class="select select-bordered w-full" id="requested_driver_id" name="requested_driver_id">
                                    <option value="">No preference</option>
                                    <?php foreach ($allDrivers as $driver): 
                                        $driver = (object) $driver;
                                    ?>
                                    <option value="<?= $driver->id ?>" <?= (post('requested_driver_id') == $driver->id || $request->requested_driver_id == $driver->id) ? 'selected' : '' ?>>
                                        <?= e($driver->driver_name) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="label">
                                    <span class="label-text-alt text-base-content/50">You can request a specific driver. Motorpool will confirm availability.</span>
                                </label>
                            </div>
                        </div>

                        <!-- Approval Workflow Section -->
                        <div class="bg-base-200/50 rounded-xl mt-6">
                            <div class="p-4 border-b border-base-200 bg-primary/10">
                                <h6 class="font-semibold"><i class="bi bi-diagram-3 mr-2"></i>Approval Workflow</h6>
                            </div>
                            <div class="p-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Department Approver -->
                                    <div>
                                        <label for="approver_id" class="label">
                                            <span class="label-text font-medium">Department Approver <span class="text-error">*</span></span>
                                        </label>
                                        <select class="select select-bordered w-full" id="approver_id" name="approver_id" required>
                                            <option value="">Select approver...</option>
                                            <?php foreach ($approvers as $app): 
                                                $app = (object) $app;
                                            ?>
                                            <option value="<?= $app->id ?>" 
                                                    <?= (post('approver_id') == $app->id || $request->approver_id == $app->id) ? 'selected' : '' ?>>
                                                <?= e($app->name) ?> (<?= e($app->department_name ?? 'Admin') ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="label">
                                            <span class="label-text-alt text-base-content/50">First level approval</span>
                                        </label>
                                    </div>
                                    
                                    <!-- Motorpool Head -->
                                    <div>
                                        <label for="motorpool_head_id" class="label">
                                            <span class="label-text font-medium">Motorpool Head <span class="text-error">*</span></span>
                                        </label>
                                        <select class="select select-bordered w-full" id="motorpool_head_id" name="motorpool_head_id" required>
                                            <option value="">Select motorpool head...</option>
                                            <?php foreach ($motorpoolHeads as $mp): 
                                                $mp = (object) $mp;
                                            ?>
                                            <option value="<?= $mp->id ?>" 
                                                    <?= (post('motorpool_head_id') == $mp->id || $request->motorpool_head_id == $mp->id) ? 'selected' : '' ?>>
                                                <?= e($mp->name) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="label">
                                            <span class="label-text-alt text-base-content/50">Final approval & vehicle assignment</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mt-4">
                            <label for="notes" class="label">
                                <span class="label-text font-medium">Additional Notes</span>
                            </label>
                            <textarea class="textarea textarea-bordered w-full" id="notes" name="notes"
                                rows="2" placeholder="Any special requirements or notes..." maxlength="500"><?= e(post('notes', $request->notes)) ?></textarea>
                        </div>

                        <hr class="my-6 border-base-200">

                        <div class="flex gap-3">
                            <button type="submit" class="loka-btn-primary">
                                <i class="bi bi-check-lg mr-1"></i>Save Changes
                            </button>
                            <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $requestId ?>"
                                class="loka-btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="loka-card bg-base-200/50">
                <div class="p-4">
                    <h6 class="font-semibold"><i class="bi bi-info-circle mr-2"></i>Edit Request</h6>
                    <p class="text-sm text-base-content/60">
                        You are modifying an existing vehicle request. Ensure all details are correct before saving. 
                        Passengers will be notified of any significant changes.
                    </p>
                </div>
            </div>

            <!-- Selected Passengers Preview -->
            <div class="loka-card mt-4" id="passengerPreview">
                <div class="p-4 border-b border-base-200">
                    <h6 class="font-semibold text-sm"><i class="bi bi-people mr-2 text-primary"></i>Passenger List</h6>
                </div>
                <div class="p-4">
                    <ul class="space-y-3" id="passengerList">
                        <li class="flex items-center">
                            <div class="bg-primary/10 p-2 rounded-full mr-3">
                                <i class="bi bi-person-fill text-primary"></i>
                            </div>
                            <div>
                                <div class="font-semibold text-sm"><?= e(currentUser()->name) ?></div>
                                <div class="text-xs text-primary font-medium">Requester</div>
                            </div>
                        </li>
                        <?php
                        // Backend preview for initial load
                        $passengers = db()->fetchAll("
                            SELECT u.name, d.name as department_name, rp.guest_name 
                            FROM request_passengers rp
                            LEFT JOIN users u ON rp.user_id = u.id
                            LEFT JOIN departments d ON u.department_id = d.id
                            WHERE rp.request_id = ?
                        ", [$requestId]);

                        foreach ($passengers as $p) {
                            $name = $p->name ?: $p->guest_name;
                            $dept = $p->department_name ?: 'External Guest';
                            $icon = $p->name ? 'bi-person' : 'bi-person-plus';
                            $iconBg = $p->name ? 'bg-secondary' : 'bg-success';
                            $iconColor = $p->name ? 'text-secondary' : 'text-success';
                            
                            echo '<li class="flex items-center">
                                <div class="'.$iconBg.' bg-opacity-10 p-2 rounded-full mr-3">
                                    <i class="bi '.$icon.' '.$iconColor.'"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-sm">'.e($name).'</div>
                                    <div class="text-xs text-base-content/50">'.e($dept).'</div>
                                </div>
                            </li>';
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Passenger Management Modal (DaisyUI dialog) -->
<dialog id="passengerModal" class="modal">
    <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg"><i class="bi bi-people mr-2"></i>Manage Passengers</h3>
        <div class="py-4">
            <div class="mb-4">
                <label for="passengers" class="label">
                    <span class="label-text font-semibold">Search Employees / Guests</span>
                    <span class="label-text-alt text-primary"><i class="bi bi-keyboard mr-1"></i>Enter guest names</span>
                </label>
                <select class="select select-bordered select-primary w-full" id="passengers" name="passengers[]" multiple>
                    <?php foreach ($employees as $emp): 
                        $emp = (object) $emp;
                    ?>
                    <option value="<?= $emp->id ?>" 
                            data-email="<?= e($emp->email ?? '') ?>"
                            data-department="<?= e($emp->department_name ?? 'No Dept') ?>"
                            <?= in_array($emp->id, post('passengers', $currentPassengerIds)) ? 'selected' : '' ?>>
                        <?= e($emp->name) ?> <?= !empty($emp->department_name) ? '(' . e($emp->department_name) . ')' : '' ?>
                    </option>
                    <?php endforeach; ?>

                    <?php
                    // Handle both initial load and post-error persistence
                    $selectedValues = post('passengers', $currentPassengerIds);
                    
                    foreach ($currentPassengers as $p) {
                        if ($p->guest_name && (empty($selectedValues) || in_array($p->guest_name, $selectedValues))) {
                            echo '<option value="' . e($p->guest_name) . '" selected>' . e($p->guest_name) . '</option>';
                        }
                    }
                    
                    // If form error, add newly typed guests that weren't in db yet
                    if (!empty($selectedValues)) {
                        foreach ($selectedValues as $val) {
                            if (!is_numeric($val) && !in_array($val, array_column($currentPassengers, 'guest_name'))) {
                                echo '<option value="' . e($val) . '" selected>' . e($val) . '</option>';
                            }
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div>
                <h6 class="text-xs font-bold uppercase text-base-content/50 border-b border-base-200 pb-2 mb-3">
                    <i class="bi bi-list-check mr-1"></i>Selected List
                </h6>
                <ul class="space-y-2 max-h-[300px] overflow-y-auto" id="modalPassengerList">
                    <!-- Populated by JS -->
                </ul>
            </div>
        </div>
        <div class="modal-action">
            <button type="button" class="loka-btn-primary w-full py-2 rounded-xl"
                    onclick="document.getElementById('passengerModal').close()">
                Confirm Selection
            </button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<?php ob_start(); ?>
<script>
    document.addEventListener('DOMContentLoaded', function initPassengerSelect() {
        // Wait for TomSelect to be available
        if (typeof TomSelect === 'undefined') {
            console.error('TomSelect library not loaded, retrying...');
            setTimeout(initPassengerSelect, 100); // Retry after 100ms
            return;
        }
        
        const passengerCountText = document.getElementById('passengerCountText');
        const modalPassengerList = document.getElementById('modalPassengerList');
        const passengersSelect = document.getElementById('passengers');
        
        let passengerSelect;
        
        function updatePassengerDisplay() {
            if (!passengerSelect) return;
            const items = passengerSelect.items || [];
            const count = items.length + 1; // +1 for requester
            
            const passengerPreview = document.getElementById('passengerPreview');
            const passengerList = document.getElementById('passengerList');

            if (passengerCountText) {
                const label = count === 1 ? 'Passenger (You)' : 'Passengers (Including You)';
                passengerCountText.innerHTML = '<span class="badge badge-primary">' + count + '</span>' +
                                             '<span class="text-sm text-base-content/60 ml-1">' + label + '</span>';
            }
            
            let previewHtml = '<li class="flex items-center">' +
                '<div class="bg-primary/10 p-2 rounded-full mr-3">' +
                    '<i class="bi bi-person-fill text-primary"></i>' +
                '</div>' +
                '<div>' +
                    '<div class="font-semibold text-sm"><?= e(currentUser()->name) ?></div>' +
                    '<div class="text-xs text-primary font-medium">Requester</div>' +
                '</div>' +
            '</li>';

            let modalHtml = '<li class="py-2 px-3 bg-base-200 rounded-lg flex items-center">' +
                '<i class="bi bi-person-badge-fill mr-2 text-primary"></i>' +
                '<div class="text-sm font-semibold text-primary"><?= e(currentUser()->name) ?> (You - Requester)</div>' +
                '<span class="badge badge-primary ml-auto text-[0.6rem]">REQUIRED</span>' +
            '</li>';
            
            if (items.length > 0) {
                items.forEach(function(value) {
                    const opt = passengerSelect.options[value];
                    if (opt) {
                        const dept = opt.department || 'External Guest';
                        const icon = opt.department ? 'bi-person' : 'bi-person-plus';
                        const iconColor = opt.department ? 'text-secondary' : 'text-success';
                        const iconBg = opt.department ? 'bg-secondary' : 'bg-success';
                        
                        previewHtml += '<li class="flex items-center">' +
                            '<div class="' + iconBg + ' bg-opacity-10 p-2 rounded-full mr-3">' +
                                '<i class="bi ' + icon + ' ' + iconColor + '"></i>' +
                            '</div>' +
                            '<div>' +
                                '<div class="font-semibold text-sm">' + opt.text.replace(' (Guest)', '') + '</div>' +
                                '<div class="text-xs text-base-content/50">' + dept + '</div>' +
                            '</div>' +
                        '</li>';

                        modalHtml += '<li class="py-2 px-3 bg-white border border-base-200 rounded-lg flex justify-between items-center shadow-sm">' +
                            '<div class="flex items-center">' +
                                '<div class="' + iconBg + ' bg-opacity-10 p-1 rounded-full mr-2 w-7 h-7 flex items-center justify-center">' +
                                    '<i class="bi ' + icon + ' ' + iconColor + '" style="font-size: 0.8rem;"></i>' +
                                '</div>' +
                                '<div>' +
                                    '<div class="text-sm font-semibold">' + opt.text.replace(' (Guest)', '') + '</div>' +
                                    '<div class="text-xs text-base-content/50">' + dept + '</div>' +
                                '</div>' +
                            '</div>' +
                            '<button type="button" class="btn btn-ghost btn-xs text-error p-0" onclick="removePassenger(\'' + value + '\')">' +
                                '<i class="bi bi-x-circle-fill"></i>' +
                            '</button>' +
                        '</li>';
                    }
                });
            }
            
            if (modalPassengerList) modalPassengerList.innerHTML = modalHtml;
            if (passengerList) passengerList.innerHTML = previewHtml;
        }

        // Global function for removal from modal list
        window.removePassenger = function(value) {
            passengerSelect.removeItem(value);
        };
        
        try {
            // Check if already initialized
            if (passengersSelect.classList.contains('tomselected')) {
                return;
            }
            
            passengerSelect = new TomSelect('#passengers', {
                plugins: ['remove_button', 'clear_button'],
                maxItems: null, // Allow unlimited selections
                create: true, // Allow creating guest names
                createOnBlur: true,
                createFilter: /^[a-zA-Z0-9\s\-\.]+$/, // Allow alphanumeric, spaces, hyphens, dots
                placeholder: 'Search employees or type guest names...',
                closeAfterSelect: false, // Keep dropdown open for multiple selections
                persist: false,
                render: {
                    option: function (data, escape) {
                        if (data.$isAdd) {
                            return '<div class="py-2 px-2"><i class="bi bi-plus-circle mr-1 text-success"></i>Add guest "<strong>' + escape(data.text) + '</strong>"</div>';
                        }
                        return '<div class="py-2 px-2">' +
                            '<div class="font-medium">' + escape(data.text) + '</div>' +
                            '<div class="text-sm text-base-content/50">' + escape(data.department || '') + '</div>' +
                            '</div>';
                    },
                    item: function (data, escape) {
                        const icon = data.department ? 'bi-person-fill' : 'bi-person-plus';
                        return '<div class="flex items-center">' +
                            '<i class="bi ' + icon + ' mr-1"></i>' +
                            '<span>' + escape(data.text.replace(' (Guest)', '')) + '</span>' +
                            '</div>';
                    },
                    no_results: function(data, escape) {
                        return '<div class="py-2 px-2 text-base-content/50">No employees found. Type a name to add as guest.</div>';
                    }
                },
                onInitialize: function () {
                    updatePassengerDisplay();
                },
                onChange: function () {
                    updatePassengerDisplay();
                },
                onItemAdd: function(value) {
                    updatePassengerDisplay();
                },
                onItemRemove: function(value) {
                    updatePassengerDisplay();
                }
            });
        } catch (error) {
            console.error('Error initializing passenger select:', error);
            // Fallback: ensure the select still works as a regular multi-select
            if (passengersSelect) {
                passengersSelect.style.minHeight = '100px';
            }
        }
    });
    
    // Destination Manager - Handle multiple sequential destinations
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('destinationsContainer');
        const addBtn = document.getElementById('addDestinationBtn');
        const maxDestinations = 10;
        
        if (!container || !addBtn) return;
        
        addBtn.addEventListener('click', function() {
            const rows = container.querySelectorAll('.destination-row');
            if (rows.length >= maxDestinations) {
                alert('Maximum of ' + maxDestinations + ' destinations allowed');
                return;
            }
            
            const index = rows.length;
            const row = document.createElement('div');
            row.className = 'destination-row mb-2';
            row.innerHTML = `
                <div class="join w-full">
                    <span class="join-item badge badge-primary min-w-[45px]">
                        <i class="bi bi-geo-alt"></i> ${index + 1}
                    </span>
                    <input type="text" class="input input-bordered join-item flex-1 destination-input" 
                           name="destinations[]" 
                           placeholder="Enter location address...">
                    <button type="button" class="btn btn-outline btn-error join-item remove-destination" title="Remove location">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(row);
            row.querySelector('input').focus();
            updateNumbers();
        });
        
        // Event delegation for remove buttons
        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-destination') || e.target.closest('.remove-destination')) {
                const row = e.target.closest('.destination-row');
                const rows = container.querySelectorAll('.destination-row');
                if (rows.length <= 1) {
                    alert('At least one destination is required');
                    return;
                }
                row.remove();
                updateNumbers();
            }
        });
        
        function updateNumbers() {
            const rows = container.querySelectorAll('.destination-row');
            rows.forEach((row, index) => {
                const badge = row.querySelector('.badge');
                if (badge) {
                    badge.innerHTML = '<i class="bi bi-geo-alt"></i> ' + (index + 1);
                }
                
                const input = row.querySelector('.destination-input');
                if (input) {
                    input.required = (index === 0);
                }
                
                // Show/hide remove button
                const removeBtn = row.querySelector('.remove-destination');
                if (removeBtn) {
                    removeBtn.style.display = (index === 0 && rows.length === 1) ? 'none' : '';
                }
            });
        }
        
        // Combine destinations before form submit
        const form = document.getElementById('requestForm');
        if (form) {
            form.addEventListener('submit', function() {
                const inputs = container.querySelectorAll('.destination-input');
                const destinations = [];
                inputs.forEach(input => {
                    const val = input.value.trim();
                    if (val) {
                        destinations.push(val);
                    }
                });
                document.getElementById('destinationCombined').value = destinations.join(' → ');
            });
        }
        
        updateNumbers();
    });
</script>
<?php
$pageScripts = ob_get_clean();
require_once INCLUDES_PATH . '/footer.php';
?>