<?php
/**
 * LOKA - Create Request Page
 */

$pageTitle = 'New Request';
$errors = [];

// Prefill from Availability (or direct links): start_date / end_date as Y-m-d
$prefillStart = '';
$prefillEnd = '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $startDateQ = trim((string) get('start_date', ''));
    $endDateQ = trim((string) get('end_date', ''));
    $startDtQ = trim((string) get('start_datetime', ''));
    $endDtQ = trim((string) get('end_datetime', ''));

    if ($startDtQ !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $startDtQ)) {
        $prefillStart = strlen($startDtQ) <= 10 ? $startDtQ . ' 08:00' : substr($startDtQ, 0, 16);
    } elseif ($startDateQ !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateQ)) {
        $prefillStart = $startDateQ . ' 08:00';
    }

    if ($endDtQ !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $endDtQ)) {
        $prefillEnd = strlen($endDtQ) <= 10 ? $endDtQ . ' 17:00' : substr($endDtQ, 0, 16);
    } elseif ($endDateQ !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateQ)) {
        $prefillEnd = $endDateQ . ' 17:00';
    } elseif ($prefillStart !== '') {
        $prefillEnd = substr($prefillStart, 0, 10) . ' 17:00';
    }
}

// Get available vehicles for selection (cached)
$availableVehicles = getAvailableVehicles();

// Get all active employees for passenger selection (cached)
$employees = getEmployees();

// Get department approvers (cached)
$approvers = getApprovers();

// Get all active drivers (cached)
$allDrivers = getActiveDrivers();

// Get motorpool heads (cached)
$motorpoolHeads = getMotorpoolHeads();

// Get user's saved workflows
$savedWorkflows = db()->fetchAll(
    "SELECT sw.*, 
            a.name as approver_name, 
            m.name as motorpool_name
     FROM saved_workflows sw
     JOIN users a ON sw.approver_id = a.id
     JOIN users m ON sw.motorpool_head_id = m.id
     WHERE sw.user_id = ?
     ORDER BY sw.is_default DESC, sw.name ASC",
    [userId()]
);

// Get default workflow
$defaultWorkflow = db()->fetch(
    "SELECT * FROM saved_workflows WHERE user_id = ? AND is_default = 1",
    [userId()]
);

// Handle save workflow action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'save_workflow') {
    requireCsrf();
    
    $workflowName = postSafe('workflow_name', '', 50);
    $approverId = (int) post('approver_id');
    $motorpoolHeadId = (int) post('motorpool_head_id');
    $setDefault = post('set_default') ? 1 : 0;
    
    if (empty($workflowName) || !$approverId || !$motorpoolHeadId) {
        $errors[] = 'Please fill in all workflow fields';
    } else {
        try {
            // If setting as default, clear other defaults
            if ($setDefault) {
                db()->update('saved_workflows', ['is_default' => 0], 'user_id = ?', [userId()]);
            }
            
            // Check if exists
            $existing = db()->fetch(
                "SELECT id FROM saved_workflows WHERE user_id = ? AND name = ?",
                [userId(), $workflowName]
            );
            
            if ($existing) {
                db()->update('saved_workflows', [
                    'approver_id' => $approverId,
                    'motorpool_head_id' => $motorpoolHeadId,
                    'is_default' => $setDefault,
                    'updated_at' => date(DATETIME_FORMAT)
                ], 'id = ?', [$existing->id]);
            } else {
                db()->insert('saved_workflows', [
                    'user_id' => userId(),
                    'name' => $workflowName,
                    'approver_id' => $approverId,
                    'motorpool_head_id' => $motorpoolHeadId,
                    'is_default' => $setDefault,
                    'created_at' => date(DATETIME_FORMAT)
                ]);
            }
            
            redirectWith('/?page=requests&action=create', 'success', 'Workflow saved successfully!');
        } catch (Exception $e) {
            $errors[] = 'Failed to save workflow';
        }
    }
}

// Handle delete workflow action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete_workflow') {
    requireCsrf();
    $workflowId = (int) post('workflow_id');
    db()->delete('saved_workflows', 'id = ? AND user_id = ?', [$workflowId, userId()]);
    redirectWith('/?page=requests&action=create', 'success', 'Workflow deleted.');
}

// Load booking rules settings globally for both GET and POST requests
$bookingSettings = [];
$settingsData = db()->fetchAll("SELECT `key`, value FROM settings WHERE `key` IN ('max_advance_booking_days', 'min_advance_booking_hours', 'max_trip_duration_hours')");
foreach ($settingsData as $s) {
    $bookingSettings[$s->key] = $s->value;
}
$maxAdvanceDays = (int) ($bookingSettings['max_advance_booking_days'] ?? 30);
$minAdvanceHours = (int) ($bookingSettings['min_advance_booking_hours'] ?? 24);
$maxTripHours = (int) ($bookingSettings['max_trip_duration_hours'] ?? 72);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') !== 'save_workflow' && post('action') !== 'delete_workflow') {
    requireCsrf();
    
    // Get form data
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
    
    if (empty($startDatetime)) {
        $errors[] = 'Start date/time is required';
    }
    if (empty($endDatetime)) {
        $errors[] = 'End date/time is required';
    }
if ($startDatetime && $endDatetime) {
        $startDt = new DateTime($startDatetime, $manilaTz);
        $endDt = new DateTime($endDatetime, $manilaTz);
        if ($endDt <= $startDt) {
            $errors[] = 'End date/time must be after start date/time';
        }
        
        // Booking rules validation
        $hoursUntilStart = ($startDt->getTimestamp() - $now->getTimestamp()) / 3600;
        
        // Check minimum advance booking time
        if ($hoursUntilStart < $minAdvanceHours) {
            $errors[] = "Bookings must be made at least {$minAdvanceHours} hours in advance. Please select a later start time.";
        }
        
        // Check maximum advance booking time
        $maxAdvanceSeconds = $maxAdvanceDays * 24 * 3600;
        if ($hoursUntilStart > $maxAdvanceSeconds) {
            $errors[] = "Bookings cannot be made more than {$maxAdvanceDays} days in advance. Please select an earlier start time.";
        }
        
        // Check maximum trip duration
        $tripDurationHours = ($endDt->getTimestamp() - $startDt->getTimestamp()) / 3600;
        if ($tripDurationHours > $maxTripHours) {
            $errors[] = "Trip duration cannot exceed {$maxTripHours} hours. Please shorten your trip or split it into multiple requests.";
        }
    }
    if (empty($purpose)) {
        $errors[] = 'Purpose is required';
    }
    if (empty($destinations)) {
        $errors[] = 'At least one destination is required';
    }
    if (!$vehicleId) {
        $errors[] = 'Please select a vehicle';
    }
    if (!$approverId) {
        $errors[] = 'Please select a department approver';
    }
    if (!$motorpoolHeadId) {
        $errors[] = 'Please select a motorpool head';
    }
    
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
    
    // Create request if no errors
    if (empty($errors)) {
        // Soft guard: same user + destination + start within 2 minutes (double-click / slow SMTP)
        $dup = db()->fetch(
            "SELECT id FROM requests
             WHERE user_id = ? AND destination = ? AND start_datetime = ?
               AND deleted_at IS NULL
               AND created_at >= DATE_SUB(NOW(), INTERVAL 120 SECOND)
             ORDER BY id DESC LIMIT 1",
            [userId(), $destination, $startDatetime]
        );
        if ($dup) {
            redirectWith(
                '/?page=requests&action=view&id=' . (int) $dup->id,
                'warning',
                'A matching request was just submitted. Opened the existing one to avoid duplicates.'
            );
        }

        try {
            db()->beginTransaction();
            
            // Insert request with selected approvers
            $requestId = db()->insert('requests', [
                'user_id' => userId(),
                'department_id' => currentUser()->department_id,
                'approver_id' => $approverId,
                'motorpool_head_id' => $motorpoolHeadId,
                'requested_driver_id' => $requestedDriverId,
                'vehicle_id' => $vehicleId,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'purpose' => $purpose,
                'destination' => $destination,
                'passenger_count' => $passengerCount,
                'notes' => $notes,
                'status' => STATUS_PENDING,
                'created_at' => date(DATETIME_FORMAT),
                'updated_at' => date(DATETIME_FORMAT)
            ]);
            
            // Insert passengers
            foreach ($passengerIds as $p) {
                if (is_numeric($p)) {
                    // System user
                    db()->insert('request_passengers', [
                        'request_id' => $requestId,
                        'user_id' => (int)$p,
                        'created_at' => date(DATETIME_FORMAT)
                    ]);
                } else {
                    // Guest name
                    db()->insert('request_passengers', [
                        'request_id' => $requestId,
                        'guest_name' => trim($p),
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
            
            // Create approval workflow record
            db()->insert('approval_workflow', [
                'request_id' => $requestId,
                'department_id' => currentUser()->department_id,
                'step' => 'department',
                'status' => 'pending',
                'created_at' => date(DATETIME_FORMAT),
                'updated_at' => date(DATETIME_FORMAT)
            ]);
            
            // =====================================================
            // DEFER NOTIFICATIONS UNTIL AFTER COMMIT
            // This prevents orphaned emails if transaction fails
            // =====================================================
            
            $deferredNotifications = [];
            
            // Queue requester confirmation
            $deferredNotifications[] = [
                'user_id' => userId(),
                'type' => 'request_confirmation',
                'title' => 'Request Submitted Successfully',
                'message' => 'Your vehicle request to ' . $destination . ' on ' . date('M j, Y g:i A', strtotime($startDatetime)) . ' has been submitted and is awaiting approval.',
                'link' => '/?page=requests&action=view&id=' . $requestId
            ];
            
            // Queue approver notification
            $deferredNotifications[] = [
                'user_id' => $approverId,
                'type' => 'request_submitted',
                'title' => 'New Request Awaiting Your Approval',
                'message' => currentUser()->name . ' submitted a vehicle request for ' . $destination . ' on ' . date('M j, Y', strtotime($startDatetime)) . '. You have been selected as the approver.',
                'link' => '/?page=approvals&action=view&id=' . $requestId
            ];

            // Queue motorpool head notification (informational only - no approval needed yet)
            $deferredNotifications[] = [
                'user_id' => $motorpoolHeadId,
                'type' => 'request_submitted_motorpool',
                'title' => 'New Vehicle Request Submitted',
                'message' => currentUser()->name . ' submitted a vehicle request for ' . $destination . ' on ' . date('M j, Y', strtotime($startDatetime)) . '. This request is now pending department approval.',
                'link' => '/?page=approvals&action=view&id=' . $requestId
            ];

            // Audit log
            auditLog('request_created', 'request', $requestId, null, [
                'purpose' => $purpose,
                'destination' => $destination,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'passenger_count' => $passengerCount,
                'approver_id' => $approverId,
                'motorpool_head_id' => $motorpoolHeadId,
                'requested_driver_id' => $requestedDriverId
            ]);
            
            db()->commit();
            
            // =====================================================
            // SEND NOTIFICATIONS AFTER SUCCESSFUL COMMIT
            // =====================================================
            
            // Send deferred notifications
            foreach ($deferredNotifications as $notif) {
                notify($notif['user_id'], $notif['type'], $notif['title'], $notif['message'], $notif['link']);
            }
            
            // Notify passengers using batch function
            notifyPassengersBatch(
                $requestId,
                'added_to_request',
                'Added to Vehicle Request',
                currentUser()->name . ' has added you as a passenger for a trip to ' . $destination . ' on ' . date('M j, Y', strtotime($startDatetime)) . '. The request is now awaiting approval.',
                '/?page=requests&action=view&id=' . $requestId
            );
            
            // Notify requested driver (if specified)
            if ($requestedDriverId) {
                notifyDriver(
                    $requestedDriverId,
                    'driver_requested',
                    'You Have Been Requested as Driver',
                    currentUser()->name . ' has requested you as the driver for a trip to ' . $destination . ' on ' . date('M j, Y g:i A', strtotime($startDatetime)) . '. The request is pending approval and you will be notified once approved.',
                    '/?page=requests&action=view&id=' . $requestId
                );
            }
            
            redirectWith('/?page=requests', 'success', 'Request submitted successfully! Awaiting approval.');
            
        } catch (Exception $e) {
            db()->rollback();
            error_log("Request creation error: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            
            // Show detailed error in development
            if (APP_ENV === 'development') {
                $errors[] = 'Error: ' . $e->getMessage();
            } else {
                $errors[] = 'Failed to submit request. Please try again.';
            }
        }
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <!-- Page Header -->
    <div class="mb-6">
        <h4 class="mb-1 text-2xl font-bold text-base-content">New Vehicle Request</h4>
        <nav aria-label="breadcrumb">
            <ol class="mb-0 flex items-center gap-1 text-sm text-base-content/60">
                <li><a href="<?= APP_URL ?>" class="hover:text-primary">Dashboard</a></li>
                <li><span class="mx-1">/</span></li>
                <li><a href="<?= APP_URL ?>/?page=requests" class="hover:text-primary">Requests</a></li>
                <li><span class="mx-1">/</span></li>
                <li class="text-base-content font-medium">New Request</li>
            </ol>
        </nav>
    </div>
    
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <div class="loka-card">
                <div class="border-b border-base-200 px-5 py-4">
                    <h5 class="font-semibold text-base-content"><i class="bi bi-file-earmark-plus mr-2"></i>Request Details</h5>
                </div>
                <div class="p-5">
                    <?php if (!empty($errors)): ?>
                    <div class="mb-4 rounded-xl border border-error/20 bg-error/10 px-4 py-3 text-sm text-error">
                        <ul class="mb-0 list-disc pl-4">
                            <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" novalidate id="requestForm" class="space-y-6" data-loka-busy-submit="1">
                        <?= csrfField() ?>
                        <!-- Hidden input to store selected passengers for form submission -->
                        <input type="hidden" name="passengers_json" id="passengers_json" value="">
                        
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <!-- Date/Time -->
                            <div>
                                <label for="start_datetime" class="loka-form-label">Start Date/Time <span class="text-error">*</span></label>
                                <input type="text" class="loka-form-input" id="start_datetime" 
                                       name="start_datetime" value="<?= e(post('start_datetime', $prefillStart)) ?>" required>
                            </div>
                            
                            <div>
                                <label for="end_datetime" class="loka-form-label">End Date/Time <span class="text-error">*</span></label>
                                <input type="text" class="loka-form-input" id="end_datetime" 
                                       name="end_datetime" value="<?= e(post('end_datetime', $prefillEnd)) ?>" required>
                            </div>
                            
                            <!-- Purpose -->
                            <div class="md:col-span-2">
                                <label for="purpose" class="loka-form-label">Purpose <span class="text-error">*</span></label>
                                <textarea class="loka-form-input" id="purpose" name="purpose" rows="3" 
                                          placeholder="Describe the purpose of this trip..." required maxlength="1000"><?= e(post('purpose', '')) ?></textarea>
                            </div>
                            
                            <!-- Destinations (Multiple) -->
                             <div class="md:col-span-2">
                                 <label class="loka-form-label">Destinations <span class="text-error">*</span></label>
                                 <div class="mb-2 rounded-lg border border-info/20 bg-info/10 px-3 py-2 text-sm text-info">
                                     <i class="bi bi-info-circle mr-1"></i>
                                     <strong>Note:</strong> Add locations in sequential order (first stop to last stop).
                                 </div>
                                 <div id="destinationsContainer" class="space-y-2">
                                     <?php 
                                     $destinations = post('destinations', []);
                                     if (empty($destinations)) {
                                         $destinations = [''];
                                     }
                                     foreach ($destinations as $index => $dest): 
                                     ?>
                                     <div class="destination-row flex items-center gap-2">
                                         <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-bold text-white">
                                             <i class="bi bi-geo-alt"></i> <?= $index + 1 ?>
                                         </span>
                                         <input type="text" class="loka-form-input flex-1 destination-input" 
                                                name="destinations[]" 
                                                value="<?= e($dest) ?>" 
                                                placeholder="Enter location address..."
                                                <?= $index === 0 ? 'required' : '' ?>>
                                         <?php if ($index > 0): ?>
                                         <button type="button" class="loka-btn-outline-error loka-btn-sm remove-destination" title="Remove location">
                                             <i class="bi bi-trash"></i>
                                         </button>
                                         <?php endif; ?>
                                     </div>
                                     <?php endforeach; ?>
                                 </div>
                                 <button type="button" class="loka-btn-outline-primary loka-btn-sm mt-2" id="addDestinationBtn">
                                     <i class="bi bi-plus-circle mr-1"></i>Add Another Location
                                 </button>
                                 <input type="hidden" name="destination" id="destinationCombined">
                             </div>
                            
                            <!-- Passengers Summary & Modal Trigger -->
                            <div class="md:col-span-2 mt-2">
                                <div class="rounded-xl border border-primary/25 bg-primary/10 p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h6 class="mb-0 font-semibold"><i class="bi bi-people-fill mr-2"></i>Passengers</h6>
                                            <div id="passengerCountText" class="mt-1">
                                                 <span class="loka-badge loka-badge-primary">1</span>
                                                <span class="ml-1 text-sm text-base-content/60">Passenger (Requester Included)</span>
                                            </div>
                                        </div>
                                         <button type="button" class="loka-btn-primary loka-btn-sm" onclick="passengerManager.openModal()">
                                            <i class="bi bi-person-plus mr-1"></i>Add / Manage
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Vehicle Selection -->
                            <div>
                                <label for="vehicle_id" class="loka-form-label">Select Vehicle <span class="text-error">*</span></label>
                                <select class="select select-bordered w-full" id="vehicle_id" name="vehicle_id" required>
                                    <option value="">Choose a vehicle...</option>
                                    <?php foreach ($availableVehicles as $vehicle): 
                                        $vehicle = (object) $vehicle;
                                    ?>
                                    <option value="<?= $vehicle->id ?>" 
                                            data-capacity="<?= $vehicle->passenger_capacity ?>"
                                            data-type="<?= e($vehicle->type_name ?? '') ?>"
                                            <?= post('vehicle_id') == $vehicle->id ? 'selected' : '' ?>>
                                        <?= e($vehicle->plate_number) ?> - <?= e($vehicle->make . ' ' . $vehicle->model) ?>
                                        (<?= e($vehicle->type_name ?? '') ?>, <?= $vehicle->passenger_capacity ?> seats)
                                        <?= $vehicle->status === 'in_use' ? ' [Currently in use]' : '' ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-xs text-base-content/60">Select the vehicle you need for this trip</p>
                                <div id="vehicleCapacityAlert" class="mt-2 hidden rounded-lg border border-warning/20 bg-warning/10 px-3 py-2 text-sm text-warning">
                                    <i class="bi bi-exclamation-triangle mr-1"></i>
                                    <span class="message"></span>
                                </div>
                            </div>
                            
                            <!-- Notes -->
                            <div>
                                <label for="notes" class="loka-form-label">Additional Notes</label>
                                <textarea class="loka-form-input" id="notes" name="notes" rows="2" 
                                          placeholder="Any special requirements or notes..." maxlength="500"><?= e(post('notes', '')) ?></textarea>
                            </div>

                            <!-- Requested Driver -->
                            <div>
                                <label for="requested_driver_id" class="loka-form-label">Requested Driver (Optional)</label>
                                <select class="select select-bordered w-full" id="requested_driver_id" name="requested_driver_id">
                                    <option value="">No preference</option>
                                    <?php foreach ($allDrivers as $driver): 
                                        $driver = (object) $driver;
                                    ?>
                                    <option value="<?= $driver->id ?>" <?= post('requested_driver_id') == $driver->id ? 'selected' : '' ?>>
                                        <?= e($driver->driver_name) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="driverConflictAlert" class="mt-2 hidden rounded-lg border border-warning/20 bg-warning/10 px-3 py-2 text-sm text-warning">
                                    <i class="bi bi-exclamation-triangle mr-1"></i>
                                    <span class="message"></span>
                                </div>
                                <p class="mt-1 text-xs text-base-content/60">You can request a specific driver. Motorpool will confirm availability.</p>
                            </div>
                        </div>
                        
                        <!-- Approval Workflow Section -->
                        <div class="rounded-xl bg-base-200/50 p-5">
                            <div class="mb-4 flex items-center justify-between rounded-lg bg-primary/10 px-4 py-3">
                                <h6 class="font-semibold"><i class="bi bi-diagram-3 mr-2"></i>Approval Workflow</h6>
                                <?php if (!empty($savedWorkflows)): ?>
                                <div class="dropdown dropdown-end">
                                    <div tabindex="0" role="button" class="loka-btn-outline-primary loka-btn-sm">
                                        <i class="bi bi-bookmark mr-1"></i>Load Saved
                                    </div>
                                    <ul tabindex="0" class="dropdown-content menu z-[1] w-64 rounded-xl border border-base-200 bg-base-100 p-1 shadow-lg">
                                        <?php foreach ($savedWorkflows as $wf): 
                                            $wf = (object) $wf;
                                        ?>
                                        <li>
                                            <a class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-base-200 workflow-load" 
                                               href="#" 
                                               data-approver="<?= $wf->approver_id ?>" 
                                               data-motorpool="<?= $wf->motorpool_head_id ?>">
                                                <span>
                                                    <?= e($wf->name) ?>
                                                    <?php if ($wf->is_default): ?>
                                                     <span class="loka-badge loka-badge-success badge-sm ml-1">Default</span>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- Department Approver -->
                                <div>
                                    <label for="approver_id" class="loka-form-label">
                                        Department Approver <span class="text-error">*</span>
                                    </label>
                                    <select class="select select-bordered w-full" id="approver_id" name="approver_id" required>
                                        <option value="">Select approver...</option>
                                        <?php foreach ($approvers as $app): 
                                            $app = (object) $app;
                                        ?>
                                        <option value="<?= $app->id ?>" 
                                                <?= (post('approver_id') == $app->id || ($defaultWorkflow && $defaultWorkflow->approver_id == $app->id && !post('approver_id'))) ? 'selected' : '' ?>>
                                            <?= e($app->name) ?> (<?= e($app->department_name ?? 'Admin') ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="mt-1 text-xs text-base-content/60">First level approval</p>
                                </div>
                                
                                <!-- Motorpool Head -->
                                <div>
                                    <label for="motorpool_head_id" class="loka-form-label">
                                        Motorpool Head <span class="text-error">*</span>
                                    </label>
                                    <select class="select select-bordered w-full" id="motorpool_head_id" name="motorpool_head_id" required>
                                        <option value="">Select motorpool head...</option>
                                        <?php foreach ($motorpoolHeads as $mp): 
                                            $mp = (object) $mp;
                                        ?>
                                        <option value="<?= $mp->id ?>" 
                                                <?= (post('motorpool_head_id') == $mp->id || ($defaultWorkflow && $defaultWorkflow->motorpool_head_id == $mp->id && !post('motorpool_head_id'))) ? 'selected' : '' ?>>
                                            <?= e($mp->name) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="mt-1 text-xs text-base-content/60">Final approval & vehicle assignment</p>
                                </div>
                            </div>
                            
                            <!-- Save Workflow Option -->
                            <div class="mt-4 border-t border-base-300 pt-4">
                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="checkbox" class="checkbox checkbox-primary checkbox-sm" id="saveWorkflowCheck">
                                    <span>Save this workflow for future use</span>
                                </label>
                                <div id="saveWorkflowFields" class="mt-2 hidden">
                                    <div class="grid grid-cols-1 gap-2 md:grid-cols-12">
                                        <div class="md:col-span-6">
                                            <input type="text" class="loka-form-input" 
                                                   id="workflow_name_input" placeholder="Workflow name (e.g., Default, Project X)">
                                        </div>
                                        <div class="md:col-span-4">
                                            <label class="flex cursor-pointer items-center gap-2 mt-2 text-sm">
                                                <input type="checkbox" class="checkbox checkbox-primary checkbox-sm" id="set_default_input">
                                                <span>Set as default</span>
                                            </label>
                                        </div>
                                        <div class="md:col-span-2">
                                             <button type="button" class="loka-btn-outline-success loka-btn-sm w-full" id="saveWorkflowBtn">
                                                <i class="bi bi-save"></i> Save
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4 border-base-300">
                        
                        <div class="flex gap-2">
                            <button type="submit" class="loka-btn-primary">
                                <i class="bi bi-send mr-1"></i>Submit Request
                            </button>
                            <a href="<?= APP_URL ?>/?page=requests" class="loka-btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Info -->
        <div class="lg:col-span-4 space-y-4">
            <div class="loka-card bg-base-200/50 border-0">
                <div class="p-5">
                    <h6 class="font-semibold"><i class="bi bi-info-circle mr-2"></i>Approval Process</h6>
                    <ol class="mb-0 mt-2 space-y-2 pl-4 text-sm text-base-content/60">
                        <li>Submit your request with trip details</li>
                        <li><strong>Your selected approver</strong> reviews and approves</li>
                        <li><strong>Your selected motorpool head</strong> assigns vehicle and driver</li>
                        <li>You receive notification when approved</li>
                    </ol>
                </div>
            </div>
            
            <!-- Saved Workflows Management -->
            <?php if (!empty($savedWorkflows)): ?>
            <div class="loka-card">
                <div class="flex items-center justify-between border-b border-base-200 px-5 py-4">
                    <h6 class="font-semibold"><i class="bi bi-bookmark mr-2"></i>Saved Workflows</h6>
                </div>
                <div class="divide-y divide-base-200">
                    <?php foreach ($savedWorkflows as $wf): 
                        $wf = (object) $wf;
                    ?>
                    <div class="flex items-start justify-between px-5 py-3">
                        <div class="flex-1">
                            <div class="flex items-center">
                                <strong class="text-sm"><?= e($wf->name) ?></strong>
                                <?php if ($wf->is_default): ?>
                                 <span class="loka-badge loka-badge-success badge-sm ml-2">Default</span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-0.5 text-xs text-base-content/60">
                                <i class="bi bi-person-check mr-1"></i><?= e($wf->approver_name) ?>
                            </p>
                            <p class="text-xs text-base-content/60">
                                <i class="bi bi-car-front mr-1"></i><?= e($wf->motorpool_name) ?>
                            </p>
                        </div>
                        <form method="post" class="ml-2" onsubmit="return confirm('Delete this workflow?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_workflow">
                            <input type="hidden" name="workflow_id" value="<?= $wf->id ?>">
                            <button type="submit" class="loka-btn-outline-error loka-btn-sm">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="loka-card">
                <div class="p-5">
                    <h6 class="font-semibold"><i class="bi bi-person mr-2"></i>Requester Info</h6>
                    <p class="mb-1 text-sm font-medium"><?= e(currentUser()->name) ?></p>
                    <p class="mb-1 text-xs text-base-content/60"><?= e(currentUser()->email) ?></p>
                    <p class="mb-0 text-sm">
                        <?php 
                        $dept = db()->fetch("SELECT name FROM departments WHERE id = ?", [currentUser()->department_id]);
                        echo e($dept->name ?? 'No Department');
                        ?>
                    </p>
                </div>
            </div>
            
            <!-- Selected Passengers Preview -->
            <div class="loka-card" id="passengerPreview">
                <div class="border-b border-base-200 px-5 py-3">
                    <h6 class="text-xs font-bold uppercase"><i class="bi bi-people mr-2 text-primary"></i>Passenger List</h6>
                </div>
                <div class="p-3">
                    <ul class="mb-0 space-y-2" id="passengerList">
                        <li class="flex items-center">
                            <div class="mr-3 flex h-9 w-9 items-center justify-center rounded-full bg-primary/10">
                                <i class="bi bi-person-fill text-primary"></i>
                            </div>
                            <div>
                                <div class="text-sm font-bold"><?= e(currentUser()->name) ?></div>
                                <div class="text-xs font-medium text-primary">Requester</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Passenger Management Modal -->
    <dialog id="passengerModal" class="modal">
        <div class="modal-box bg-base-100 p-0 max-w-lg">
            <div class="p-5 bg-primary text-white rounded-t-xl">
                <h5 class="font-semibold flex items-center gap-2">
                    <i class="bi bi-people mr-2"></i>Manage Passengers
                </h5>
            </div>
            <div class="p-5 space-y-4">
                <!-- Search Box -->
                <div>
                    <label for="passengerSearch" class="loka-form-label font-bold">
                        <i class="bi bi-search mr-1"></i>Search Employees
                    </label>
                    <input type="text" class="loka-form-input" id="passengerSearch" 
                           placeholder="Search by name, email, or department...">
                </div>
                
                <!-- Employee List with Checkboxes -->
                <div>
                    <div class="max-h-[300px] overflow-y-auto rounded-xl border border-base-300 p-2">
                        <div id="employeeList">
                            <?php 
                            $selectedPassengers = post('passengers', []);
                            foreach ($employees as $emp): 
                                $emp = (object) $emp;
                                $isSelected = in_array($emp->id, $selectedPassengers);
                            ?>
                            <label class="flex cursor-pointer items-center justify-between gap-3 border-b border-base-200 py-2 px-2 employee-item" 
                                   data-name="<?= strtolower(e($emp->name)) ?>"
                                   data-department="<?= strtolower(e($emp->department_name ?? '')) ?>"
                                   data-email="<?= strtolower(e($emp->email ?? '')) ?>"
                                   data-search-text="<?= strtolower(e($emp->name . ' ' . ($emp->department_name ?? '') . ' ' . ($emp->email ?? ''))) ?>">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" class="checkbox checkbox-primary checkbox-sm passenger-checkbox" 
                                           value="<?= $emp->id ?>" 
                                           id="emp_<?= $emp->id ?>"
                                           data-type="employee"
                                           data-department="<?= e($emp->department_name ?? 'No Dept') ?>"
                                           <?= $isSelected ? 'checked' : '' ?>>
                                    <div>
                                        <div class="text-sm font-medium"><?= e($emp->name) ?></div>
                                        <div class="text-xs text-base-content/60"><?= e($emp->department_name ?? 'No Department') ?></div>
                                    </div>
                                </div>
                                <i class="bi bi-person-fill text-base-content/40"></i>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div id="noEmployeesFound" class="hidden py-8 text-center text-base-content/50">
                            <i class="bi bi-search mb-2 block text-4xl"></i>
                            <p class="mb-0 text-sm">No employees found matching your search</p>
                        </div>
                    </div>
                </div>
                
                <!-- Add Guest Name -->
                <div>
                    <label for="guestName" class="loka-form-label font-bold">
                        <i class="bi bi-person-plus mr-1"></i>Add Guest Name
                    </label>
                    <div class="flex gap-2">
                        <input type="text" class="loka-form-input flex-1" id="guestName" 
                               placeholder="Enter guest name...">
                        <button type="button" class="loka-btn-outline-primary" id="addGuestBtn">
                            <i class="bi bi-plus-lg mr-1"></i>Add Guest
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-base-content/60">Add external guests who are not employees</p>
                </div>
                
                <!-- Selected Passengers List -->
                <div>
                    <h6 class="mb-3 border-b border-base-200 pb-2 text-xs font-bold uppercase text-base-content/60">
                        <i class="bi bi-list-check mr-1"></i>Selected Passengers (<span id="selectedCount">0</span>)
                    </h6>
                    <div class="max-h-[250px] overflow-y-auto rounded-xl border border-base-300 p-2">
                        <ul class="mb-0 space-y-2" id="selectedPassengerList">
                            <!-- Populated by JS -->
                        </ul>
                        <div id="noSelectedPassengers" class="py-4 text-center text-base-content/50">
                            <i class="bi bi-info-circle mr-1"></i>No passengers selected yet
                        </div>
                    </div>
                </div>
                
                <!-- Hidden inputs for form submission -->
                <div id="passengerInputs" class="hidden">
                    <!-- Will be populated by JS before form submission -->
                </div>
            </div>
            <div class="p-5 border-t border-base-200">
                <form method="dialog">
                    <button type="submit" class="loka-btn-primary w-full rounded-xl py-2.5">
                        Confirm Selection
                    </button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="submit">close</button>
        </form>
    </dialog>
</div>

<?php 
ob_start(); 
?>
<script>
(function() {
    'use strict';
    
    /**
     * Passenger Selection Manager
     * Handles search, selection, and guest addition in the passenger modal
     */
    class PassengerManager {
        constructor() {
            this.selectedPassengers = new Map();
            this.searchTimeout = null;
            this.modalInitialized = false;
            
            this.init();
        }
        
        init() {
            // Use event delegation for elements that might not exist yet
            this.setupEventDelegation();
            
            // Initialize when modal opens
            this.setupModalHandlers();
            
            // Initialize selected passengers from page load (if any checkboxes are checked)
            this.loadInitialSelections();
        }
        
        openModal() {
            const modal = document.getElementById('passengerModal');
            if (modal) {
                modal.showModal();
                this.initializeModal();
            }
        }
        
        setupEventDelegation() {
            // Use event delegation for checkboxes (works even if elements are added dynamically)
            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('passenger-checkbox')) {
                    this.handleCheckboxChange(e.target);
                }
            });
            
            // Use event delegation for search input
            document.addEventListener('input', (e) => {
                if (e.target.id === 'passengerSearch') {
                    this.handleSearch(e.target.value);
                }
            });
            
            // Use event delegation for guest name input
            document.addEventListener('keypress', (e) => {
                if (e.target.id === 'guestName' && e.key === 'Enter') {
                    e.preventDefault();
                    this.addGuest();
                }
            });
            
            // Use event delegation for add guest button
            document.addEventListener('click', (e) => {
                if (e.target.id === 'addGuestBtn' || e.target.closest('#addGuestBtn')) {
                    e.preventDefault();
                    this.addGuest();
                }
                
                // Handle remove passenger buttons
                if (e.target.classList.contains('remove-passenger') || e.target.closest('.remove-passenger')) {
                    const btn = e.target.closest('.remove-passenger') || e.target;
                    const key = btn.dataset.passengerKey;
                    if (key) {
                        this.removePassenger(key);
                    }
                }
            });
        }
        
        setupModalHandlers() {
            const modal = document.getElementById('passengerModal');
            if (!modal) {
                console.warn('Passenger modal not found');
                return;
            }
            
            // When dialog is closed (via X, Escape, or .close())
            modal.addEventListener('close', () => {
                this.modalInitialized = false;
            });
        }
        
        initializeModal() {
            if (this.modalInitialized) return;
            
            const searchInput = document.getElementById('passengerSearch');
            const employeeList = document.getElementById('employeeList');
            const guestInput = document.getElementById('guestName');
            
            if (!searchInput || !employeeList) {
                console.error('Required modal elements not found');
                return;
            }
            
            // Clear and reset search
            searchInput.value = '';
            this.resetEmployeeList();
            
            // Focus search input
            setTimeout(() => {
                searchInput.focus();
            }, 100);
            
            // Initialize guest input
            if (guestInput) {
                guestInput.value = '';
            }
            
            this.modalInitialized = true;
            this.updateDisplay();
        }
        
        resetEmployeeList() {
            const employeeList = document.getElementById('employeeList');
            const noEmployeesFound = document.getElementById('noEmployeesFound');
            
            if (employeeList) {
                employeeList.querySelectorAll('.employee-item').forEach(item => {
                    item.style.display = '';
                });
                employeeList.style.display = 'block';
            }
            
            if (noEmployeesFound) {
                noEmployeesFound.classList.add('d-none');
            }
        }
        
        handleSearch(searchTerm) {
            // Debounce search
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.performSearch(searchTerm);
            }, 150);
        }
        
        performSearch(searchTerm) {
            const employeeList = document.getElementById('employeeList');
            const noEmployeesFound = document.getElementById('noEmployeesFound');
            
            if (!employeeList) return;
            
            const items = employeeList.querySelectorAll('.employee-item');
            if (items.length === 0) return;
            
            const searchTerms = searchTerm.toLowerCase().trim().split(/\s+/).filter(t => t.length > 0);
            let visibleCount = 0;
            
            items.forEach(item => {
                if (searchTerms.length === 0) {
                    item.style.display = '';
                    visibleCount++;
                    return;
                }
                
                const searchText = item.dataset.searchText || '';
                const name = item.dataset.name || '';
                const email = item.dataset.email || '';
                const department = item.dataset.department || '';
                
                // All search terms must match somewhere
                let matches = true;
                for (const term of searchTerms) {
                    if (!name.includes(term) && !email.includes(term) && !department.includes(term) && !searchText.includes(term)) {
                        matches = false;
                        break;
                    }
                }
                
                if (matches) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (visibleCount === 0 && searchTerm.trim()) {
                if (noEmployeesFound) {
                    noEmployeesFound.classList.remove('d-none');
                }
                employeeList.style.display = 'none';
            } else {
                if (noEmployeesFound) {
                    noEmployeesFound.classList.add('d-none');
                }
                employeeList.style.display = 'block';
            }
        }
        
        handleCheckboxChange(checkbox) {
            if (!checkbox.dataset.type || checkbox.dataset.type !== 'employee') return;
            
            const item = checkbox.closest('.employee-item');
            if (!item) return;
            
            const nameEl = item.querySelector('label .fw-medium');
            if (!nameEl) return;
            
            if (checkbox.checked) {
                this.selectedPassengers.set(checkbox.value, {
                    type: 'employee',
                    name: nameEl.textContent.trim(),
                    department: checkbox.dataset.department || 'No Dept'
                });
            } else {
                this.selectedPassengers.delete(checkbox.value);
            }
            
            this.updateDisplay();
        }
        
        addGuest() {
            const guestInput = document.getElementById('guestName');
            if (!guestInput) return;
            
            const guestName = guestInput.value.trim();
            if (!guestName) {
                this.showMessage('Please enter a guest name', 'warning');
                guestInput.focus();
                return;
            }
            
            // Validate guest name (alphanumeric, spaces, hyphens, dots)
            if (!/^[a-zA-Z0-9\s\-\.]+$/.test(guestName)) {
                this.showMessage('Guest name can only contain letters, numbers, spaces, hyphens, and dots', 'warning');
                guestInput.focus();
                return;
            }
            
            // Check if guest already exists
            for (const [key, passenger] of this.selectedPassengers.entries()) {
                if (passenger.type === 'guest' && passenger.name.toLowerCase() === guestName.toLowerCase()) {
                    this.showMessage('This guest is already added', 'warning');
                    guestInput.focus();
                    guestInput.select();
                    return;
                }
            }
            
            // Add guest with unique key
            const guestKey = 'guest_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            this.selectedPassengers.set(guestKey, {
                type: 'guest',
                name: guestName,
                department: 'External Guest'
            });
            
            // Clear input
            guestInput.value = '';
            guestInput.focus();
            
            // Update display
            this.updateDisplay();
            
            // Visual feedback
            this.showMessage('Guest added successfully', 'success');
        }
        
        removePassenger(key) {
            this.selectedPassengers.delete(key);
            
            // If it's an employee, uncheck the checkbox
            if (!key.startsWith('guest_')) {
                const checkbox = document.getElementById('emp_' + key);
                if (checkbox) {
                    checkbox.checked = false;
                }
            }
            
            this.updateDisplay();
        }
        
        loadInitialSelections() {
            document.querySelectorAll('.passenger-checkbox:checked').forEach(checkbox => {
                if (checkbox.dataset.type === 'employee') {
                    const item = checkbox.closest('.employee-item');
                    if (item) {
                        const nameEl = item.querySelector('label .fw-medium');
                        if (nameEl) {
                            this.selectedPassengers.set(checkbox.value, {
                                type: 'employee',
                                name: nameEl.textContent.trim(),
                                department: checkbox.dataset.department || 'No Dept'
                            });
                        }
                    }
                }
            });
            this.updateDisplay();
        }
        
        updateDisplay() {
            const count = this.selectedPassengers.size + 1; // +1 for requester
            
            // Update count badge
            const passengerCountText = document.getElementById('passengerCountText');
            if (passengerCountText) {
                const label = count === 1 ? 'Passenger (You)' : 'Passengers (Including You)';
                passengerCountText.innerHTML = '<span class="loka-badge bg-primary rounded-pill">' + count + '</span>' +
                                             '<span class="small text-muted ms-1">' + label + '</span>';
            }
            
            // Update selected count in modal
            const selectedCount = document.getElementById('selectedCount');
            if (selectedCount) {
                selectedCount.textContent = this.selectedPassengers.size;
            }
            
            // Update sidebar preview
            this.updateSidebarPreview();
            
            // Update modal selected list
            this.updateModalSelectedList();
            
            // Trigger capacity check
            setTimeout(() => {
                if (typeof checkVehicleCapacity === 'function') {
                    checkVehicleCapacity();
                }
            }, 100);
        }
        
        updateSidebarPreview() {
            const passengerList = document.getElementById('passengerList');
            if (!passengerList) return;
            
            let html = '<li class="mb-2 d-flex align-items-center">' +
                '<div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">' +
                    '<i class="bi bi-person-fill text-primary"></i>' +
                '</div>' +
                '<div>' +
                    '<div class="fw-bold small"><?= e(currentUser()->name) ?></div>' +
                    '<div class="x-small text-primary fw-medium">Requester</div>' +
                '</div>' +
            '</li>';
            
            this.selectedPassengers.forEach((passenger, key) => {
                const isGuest = passenger.type === 'guest';
                const icon = isGuest ? 'bi-person-plus' : 'bi-person';
                const iconColor = isGuest ? 'text-success' : 'text-secondary';
                const iconBg = isGuest ? 'bg-success' : 'bg-secondary';
                
                html += '<li class="mb-2 d-flex align-items-center">' +
                    '<div class="' + iconBg + ' bg-opacity-10 p-2 rounded-circle me-3">' +
                        '<i class="bi ' + icon + ' ' + iconColor + '"></i>' +
                    '</div>' +
                    '<div>' +
                        '<div class="fw-bold small">' + this.escapeHtml(passenger.name) + '</div>' +
                        '<div class="x-small text-muted">' + this.escapeHtml(passenger.department) + '</div>' +
                    '</div>' +
                '</li>';
            });
            
            passengerList.innerHTML = html;
        }
        
        updateModalSelectedList() {
            const selectedList = document.getElementById('selectedPassengerList');
            const noSelected = document.getElementById('noSelectedPassengers');
            
            if (!selectedList) return;
            
            if (this.selectedPassengers.size === 0) {
                selectedList.innerHTML = '';
                if (noSelected) {
                    noSelected.style.display = 'block';
                }
                return;
            }
            
            if (noSelected) {
                noSelected.style.display = 'none';
            }
            
            let html = '';
            this.selectedPassengers.forEach((passenger, key) => {
                const isGuest = passenger.type === 'guest';
                const icon = isGuest ? 'bi-person-plus' : 'bi-person';
                const iconColor = isGuest ? 'text-success' : 'text-secondary';
                const iconBg = isGuest ? 'bg-success' : 'bg-secondary';
                
                html += '<li class="mb-2 py-2 px-3 bg-base-200 border border-base-200 rounded-xl flex justify-between items-center shadow-sm">' +
                    '<div class="flex items-center">' +
                        '<div class="' + iconBg + ' bg-opacity-10 p-1 rounded-circle me-2" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">' +
                            '<i class="bi ' + icon + ' ' + iconColor + '" style="font-size: 0.8rem;"></i>' +
                        '</div>' +
                        '<div>' +
                            '<div class="small fw-bold">' + this.escapeHtml(passenger.name) + '</div>' +
                            '<div class="x-small text-muted">' + this.escapeHtml(passenger.department) + '</div>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="btn btn-sm btn-link text-danger p-0 remove-passenger" data-passenger-key="' + this.escapeHtml(key) + '">' +
                        '<i class="bi bi-x-circle-fill"></i>' +
                    '</button>' +
                '</li>';
            });
            
            selectedList.innerHTML = html;
        }
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        showMessage(message, type) {
            // Use existing toast system if available, otherwise create simple alert
            if (typeof showToast === 'function') {
                showToast(message, type);
            } else {
                // Fallback: simple alert
                const toast = document.createElement('div');
                toast.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
                toast.style.zIndex = '9999';
                toast.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" onclick="this.closest('.alert').remove()"></button>
                `;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            }
        }
        
        getSelectedPassengers() {
            return this.selectedPassengers;
        }
    }
    
    // Initialize passenger manager immediately
    const passengerManager = new PassengerManager();
    
    // Make it globally accessible
    window.passengerManager = passengerManager;
    
    // Destination Manager - Handle multiple sequential destinations
    class DestinationManager {
        constructor() {
            this.container = document.getElementById('destinationsContainer');
            this.addBtn = document.getElementById('addDestinationBtn');
            this.maxDestinations = 10;
            
            this.init();
        }
        
        init() {
            if (this.addBtn) {
                this.addBtn.addEventListener('click', () => this.addDestination());
            }
            
            // Event delegation for remove buttons
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('remove-destination') || e.target.closest('.remove-destination')) {
                    const row = e.target.closest('.destination-row');
                    if (row) {
                        this.removeDestination(row);
                    }
                }
            });
        }
        
        addDestination() {
            const rows = this.container.querySelectorAll('.destination-row');
            if (rows.length >= this.maxDestinations) {
                alert('Maximum of ' + this.maxDestinations + ' destinations allowed');
                return;
            }
            
            const index = rows.length;
            const row = document.createElement('div');
            row.className = 'destination-row mb-2';
            row.innerHTML = `
                <div class="flex items-center gap-2">
                    <span class="px-3 py-2.5 bg-primary text-white rounded-xl text-sm font-medium" style="min-width: 45px;">
                        <i class="bi bi-geo-alt"></i> ${index + 1}
                    </span>
                    <input type="text" class="loka-form-input flex-1 destination-input" 
                           name="destinations[]" 
                           placeholder="Enter location address...">
                    <button type="button" class="loka-btn-outline-error loka-btn-sm" title="Remove location">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
            
            this.container.appendChild(row);
            row.querySelector('input').focus();
            this.updateNumbers();
        }
        
        removeDestination(row) {
            const rows = this.container.querySelectorAll('.destination-row');
            if (rows.length <= 1) {
                alert('At least one destination is required');
                return;
            }
            
            row.remove();
            this.updateNumbers();
        }
        
        updateNumbers() {
            const rows = this.container.querySelectorAll('.destination-row');
            rows.forEach((row, index) => {
                const badge = row.querySelector('.input-group-text');
                if (badge) {
                    badge.innerHTML = '<i class="bi bi-geo-alt"></i> ' + (index + 1);
                }
                
                const input = row.querySelector('.destination-input');
                if (input) {
                    input.required = (index === 0);
                }
                
                // Show/hide remove button based on index
                const removeBtn = row.querySelector('.remove-destination');
                if (removeBtn) {
                    removeBtn.style.display = (index === 0 && rows.length === 1) ? 'none' : '';
                }
            });
        }
        
        getCombinedDestination() {
            const inputs = this.container.querySelectorAll('.destination-input');
            const destinations = [];
            inputs.forEach(input => {
                const val = input.value.trim();
                if (val) {
                    destinations.push(val);
                }
            });
            return destinations.join(' → ');
        }
    }
    
    const destinationManager = new DestinationManager();
    window.destinationManager = destinationManager;
    
    // Form submission handler - prepare passenger data
    const requestForm = document.getElementById('requestForm');
    if (requestForm) {
        requestForm.addEventListener('submit', function(e) {
            // Wait for passengerManager to be initialized
            const manager = window.passengerManager;
            if (!manager) {
                console.error('PassengerManager not initialized');
                return;
            }
            
            const selectedPassengers = manager.getSelectedPassengers();
            
            // Remove any existing passenger inputs from form
            const existingInputs = requestForm.querySelectorAll('input[name="passengers[]"]');
            existingInputs.forEach(input => input.remove());
            
            // Create hidden inputs and append directly to form
            selectedPassengers.forEach(function(passenger, key) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'passengers[]';
                // For guests, use the name; for employees, use the ID
                input.value = key.startsWith('guest_') ? passenger.name : key;
                requestForm.appendChild(input);
            });
            
            // Store as JSON backup
            const passengersArray = [];
            selectedPassengers.forEach(function(passenger, key) {
                passengersArray.push(key.startsWith('guest_') ? passenger.name : key);
            });
            
            const passengersJson = document.getElementById('passengers_json');
            if (passengersJson) {
                passengersJson.value = JSON.stringify(passengersArray);
            }
            
            // Combine destinations into hidden field
            const destManager = window.destinationManager;
            if (destManager) {
                const combinedDest = document.getElementById('destinationCombined');
                if (combinedDest) {
                    combinedDest.value = destManager.getCombinedDestination();
                }
            }
        });
    }
    
    // Load saved workflow
    document.querySelectorAll('.workflow-load').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('approver_id').value = this.dataset.approver;
            document.getElementById('motorpool_head_id').value = this.dataset.motorpool;
        });
    });
    
    // Toggle save workflow fields
    const saveWorkflowCheck = document.getElementById('saveWorkflowCheck');
    const saveWorkflowFields = document.getElementById('saveWorkflowFields');
    
    if (saveWorkflowCheck && saveWorkflowFields) {
        saveWorkflowCheck.addEventListener('change', function() {
            saveWorkflowFields.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Save workflow button
    const saveWorkflowBtn = document.getElementById('saveWorkflowBtn');
    if (saveWorkflowBtn) {
        saveWorkflowBtn.addEventListener('click', function() {
            const name = document.getElementById('workflow_name_input').value.trim();
            const approverId = document.getElementById('approver_id').value;
            const motorpoolId = document.getElementById('motorpool_head_id').value;
            const setDefault = document.getElementById('set_default_input').checked;
            
            if (!name) {
                alert('Please enter a workflow name');
                return;
            }
            if (!approverId || !motorpoolId) {
                alert('Please select both an approver and motorpool head');
                return;
            }
            
            // Create and submit a form
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="save_workflow">
                <input type="hidden" name="workflow_name" value="${name}">
                <input type="hidden" name="approver_id" value="${approverId}">
                <input type="hidden" name="motorpool_head_id" value="${motorpoolId}">
                <input type="hidden" name="set_default" value="${setDefault ? '1' : '0'}">
            `;
            document.body.appendChild(form);
            form.submit();
        });
    }

    // Vehicle Capacity and Conflict Check Logic
    const vehicleSelect = document.getElementById('vehicle_id');
    const driverSelect = document.getElementById('requested_driver_id');
    const startInput = document.getElementById('start_datetime');
    const endInput = document.getElementById('end_datetime');
    const conflictAlert = document.getElementById('driverConflictAlert');
    const vehicleCapacityAlert = document.getElementById('vehicleCapacityAlert');
    const passengerCountText = document.getElementById('passengerCountText');

    function checkVehicleCapacity() {
        if (!vehicleSelect || !vehicleSelect.value) {
            vehicleCapacityAlert.classList.add('d-none');
            return;
        }

        const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
        const capacity = parseInt(selectedOption.dataset.capacity) || 0;
        
        // Get passenger count from the badge
        const passengerCountMatch = passengerCountText ? passengerCountText.textContent.match(/\d+/) : null;
        const passengerCount = passengerCountMatch ? parseInt(passengerCountMatch[0]) : 1;

        if (capacity > 0 && passengerCount > capacity) {
            vehicleCapacityAlert.querySelector('.message').textContent = 
                `Warning: This vehicle can only accommodate ${capacity} passengers, but you have ${passengerCount} passengers selected.`;
            vehicleCapacityAlert.classList.remove('d-none');
        } else {
            vehicleCapacityAlert.classList.add('d-none');
        }
    }

    function checkVehicleAvailability() {
        if (!vehicleSelect || !vehicleSelect.value || !startInput.value || !endInput.value) {
            return;
        }

        const vehicleId = vehicleSelect.value;
        const start = startInput.value;
        const end = endInput.value;

        fetch(`<?= APP_URL ?>/?page=api&action=check_conflict&type=vehicle&id=${vehicleId}&start=${start}&end=${end}`)
            .then(res => res.json())
            .then(data => {
                if (data.conflict) {
                    vehicleCapacityAlert.querySelector('.message').textContent = 
                        `⚠️ ${data.message} - This vehicle may not be available for the selected dates.`;
                    vehicleCapacityAlert.classList.remove('d-none');
                    vehicleCapacityAlert.classList.remove('alert-warning');
                    vehicleCapacityAlert.classList.add('alert-danger');
                } else {
                    // Only hide if it's not a capacity warning
                    if (!vehicleCapacityAlert.querySelector('.message').textContent.includes('accommodate')) {
                        vehicleCapacityAlert.classList.add('d-none');
                    }
                    vehicleCapacityAlert.classList.remove('alert-danger');
                    vehicleCapacityAlert.classList.add('alert-warning');
                }
            })
            .catch(err => {
                console.error('Error checking vehicle availability:', err);
            });
    }

    function checkDriverConflicts() {
        const driverId = driverSelect.value;
        const start = startInput.value;
        const end = endInput.value;

        if (!driverId || !start || !end) {
            conflictAlert.classList.add('d-none');
            return;
        }

        fetch(`<?= APP_URL ?>/?page=api&action=check_conflict&type=driver&id=${driverId}&start=${start}&end=${end}`)
            .then(res => res.json())
            .then(data => {
                if (data.conflict) {
                    conflictAlert.querySelector('.message').textContent = data.message;
                    conflictAlert.classList.remove('d-none');
                } else {
                    conflictAlert.classList.add('d-none');
                }
            });
    }

    // Event listeners
    if (vehicleSelect) {
        vehicleSelect.addEventListener('change', function() {
            checkVehicleCapacity();
            checkVehicleAvailability();
        });
    }
    
    if (driverSelect) {
        driverSelect.addEventListener('change', checkDriverConflicts);
    }
    
    if (startInput) {
        startInput.addEventListener('change', function() {
            checkVehicleAvailability();
            checkDriverConflicts();
        });
    }
    
    if (endInput) {
        endInput.addEventListener('change', function() {
            checkVehicleAvailability();
            checkDriverConflicts();
        });
    }

    // Initial capacity check on page load
    setTimeout(function() {
        checkVehicleCapacity();
    }, 500);
    
function initRequestDatePickers() {
        if (typeof flatpickr === 'undefined') return false;

        const bookingMaxAdvanceDays = <?= (int) ($bookingSettings['max_advance_booking_days'] ?? 30) ?>;
        const bookingMaxTripHours = <?= (int) ($bookingSettings['max_trip_duration_hours'] ?? 72) ?>;
        const startEl = document.getElementById('start_datetime');
        const endEl = document.getElementById('end_datetime');
        if (!startEl || !endEl) return true;
        if (startEl._flatpickr) return true;

        const today = new Date();
        const maxDate = new Date(today);
        maxDate.setDate(maxDate.getDate() + bookingMaxAdvanceDays);

        const pickerOpts = {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            altInput: true,
            altFormat: 'Y-m-d h:i K',
            altInputClass: 'loka-form-input flatpickr-alt-input w-full',
            time_24hr: false,
            allowInput: true,
            minDate: today,
            maxDate: maxDate,
            minuteIncrement: 15,
            disableMobile: true
        };

        const startPicker = flatpickr(startEl, Object.assign({}, pickerOpts, {
            defaultDate: startEl.value || null
        }));
        const endPicker = flatpickr(endEl, Object.assign({}, pickerOpts, {
            defaultDate: endEl.value || null
        }));

        // Ensure alt inputs show parsed values (prefills / POST replay)
        if (startEl.value) startPicker.setDate(startEl.value, false);
        if (endEl.value) endPicker.setDate(endEl.value, false);

        if (startPicker && endPicker) {
            startPicker.config.onChange.push(function (selectedDates) {
                if (selectedDates.length > 0) {
                    endPicker.set('minDate', selectedDates[0]);
                    const maxEndDate = new Date(selectedDates[0]);
                    maxEndDate.setHours(maxEndDate.getHours() + bookingMaxTripHours);
                    endPicker.set('maxDate', maxEndDate);
                }
            });
            if (startPicker.selectedDates.length > 0) {
                endPicker.set('minDate', startPicker.selectedDates[0]);
            }
        }
        return true;
    }

    if (!initRequestDatePickers()) {
        setTimeout(initRequestDatePickers, 400);
    }
})();
</script>
<?php 
$pageScripts = ob_get_clean(); 
require_once INCLUDES_PATH . '/footer.php'; 
?>
