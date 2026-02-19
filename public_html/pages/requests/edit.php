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
$currentPassengers = db()->fetchAll(
    "SELECT COALESCE(user_id, guest_name) as identifier, user_id, guest_name FROM request_passengers WHERE request_id = ?",
    [$requestId]
);
$currentPassengerIdentifiers = array_column($currentPassengers, 'identifier');
$currentPassengerIds = array_filter(array_column($currentPassengers, 'user_id'));

// Handle form submission
if (beginFormProcessing()) {
    // Collect form data
    $passengerIds = getRequestPassengerIds();
    $passengerCount = calculatePassengerCount($passengerIds);

    $data = [
        'start_datetime' => postString('start_datetime', '', 20),
        'end_datetime' => postString('end_datetime', '', 20),
        'purpose' => postString('purpose', '', 500),
        'destination' => postString('destination', '', 255),
        'vehicle_id' => postInt('vehicle_id') ?: null,
        'notes' => postString('notes', '', 1000),
        'approver_id' => postInt('approver_id'),
        'motorpool_head_id' => postInt('motorpool_head_id'),
        'requested_driver_id' => postInt('requested_driver_id') ?: null,
        'passenger_count' => $passengerCount,
        'passengers_ids' => $passengerIds
    ];

    // Validate using shared function
    $errors = validateRequestForm($data, $requestId);

    // Process if no errors
    if (empty($errors)) {
        $result = processRequestUpdate($requestId, $data, $request, $currentPassengerIdentifiers);
        if ($result->isSuccess()) {
            // Send notifications after successful commit
            sendRequestUpdateNotifications($result);
            $result->redirect();
        } else {
            $errors = $result->getErrors();
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

<div class="container-fluid py-4">
    <div class="mb-4">
        <h4 class="mb-1">Edit Request #<?= $requestId ?>
            <?php if ($request->status === STATUS_REVISION): ?>
                <span class="badge bg-warning text-dark ms-2"><i class="bi bi-arrow-repeat me-1"></i>Revision Requested</span>
            <?php endif; ?>
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=requests">Requests</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
    </div>

    <?php if ($request->status === STATUS_REVISION && $revisionComments): ?>
    <div class="alert alert-warning border-start border-warning border-4 mb-4">
        <h6 class="alert-heading mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Revision Requested by <?= e($revisionBy) ?></h6>
        <p class="mb-0"><strong>Reason:</strong> <?= nl2br(e($revisionComments)) ?></p>
        <hr class="my-2">
        <small class="text-muted">Please address the feedback above and resubmit your request for approval.</small>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit Details</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <?= csrfField() ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="start_datetime" class="form-label">Start Date/Time <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control datetimepicker" id="start_datetime"
                                    name="start_datetime"
                                    value="<?= e(post('start_datetime', $request->start_datetime)) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="end_datetime" class="form-label">End Date/Time <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control datetimepicker" id="end_datetime"
                                    name="end_datetime" value="<?= e(post('end_datetime', $request->end_datetime)) ?>"
                                    required>
                            </div>

                            <div class="col-12">
                                <label for="purpose" class="form-label">Purpose <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control" id="purpose" name="purpose" rows="3"
                                    required><?= e(post('purpose', $request->purpose)) ?></textarea>
                            </div>

                            <div class="col-12">
                                <label for="destination" class="form-label">Destination <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="destination" name="destination"
                                    value="<?= e(post('destination', $request->destination)) ?>" required>
                            </div>

                            <!-- Passengers Summary & Modal Trigger -->
                            <div class="col-12 mt-4">
                                <div class="card border-primary border-opacity-25 bg-primary bg-opacity-10">
                                    <div class="card-body d-flex justify-content-between align-items-center py-2">
                                        <div>
                                            <h6 class="mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Passengers</h6>
                                            <div id="passengerCountText" class="mt-1">
                                                <span class="badge bg-primary rounded-pill"><?= $request->passenger_count ?></span>
                                                <span class="small text-muted ms-1">Passengers (Requester Included)</span>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#passengerModal">
                                            <i class="bi bi-person-plus me-1"></i>Manage Passengers
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Vehicle Selection -->
                            <div class="col-md-6 mt-3">
                                <label for="vehicle_id" class="form-label">Select Vehicle</label>
                                <select class="form-select" id="vehicle_id" name="vehicle_id">
                                    <option value="">Choose a vehicle...</option>
                                    <?php foreach ($availableVehicles as $vehicle): ?>
                                    <option value="<?= $vehicle->id ?>" 
                                            data-capacity="<?= $vehicle->passenger_capacity ?>"
                                            data-type="<?= e($vehicle->type_name) ?>"
                                            <?= (post('vehicle_id') == $vehicle->id || $request->vehicle_id == $vehicle->id) ? 'selected' : '' ?>>
                                        <?= e($vehicle->plate_number) ?> - <?= e($vehicle->make . ' ' . $vehicle->model) ?>
                                        (<?= e($vehicle->type_name) ?>, <?= $vehicle->passenger_capacity ?> seats)
                                        <?= $vehicle->status === 'in_use' ? ' [Currently in use]' : '' ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Select the vehicle you need for this trip</small>
                                <div id="vehicleCapacityAlert" class="alert alert-warning mt-2 small py-2 d-none">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    <span class="message"></span>
                                </div>
                            </div>

                            <!-- Requested Driver -->
                            <div class="col-md-6 mt-3">
                                <label for="requested_driver_id" class="form-label">Requested Driver (Optional)</label>
                                <select class="form-select" id="requested_driver_id" name="requested_driver_id">
                                    <option value="">No preference</option>
                                    <?php foreach ($allDrivers as $driver): ?>
                                    <option value="<?= $driver->id ?>" <?= (post('requested_driver_id') == $driver->id || $request->requested_driver_id == $driver->id) ? 'selected' : '' ?>>
                                        <?= e($driver->driver_name) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">You can request a specific driver. Motorpool will confirm availability.</small>
                            </div>
                        </div>

                        <!-- Approval Workflow Section -->
                        <div class="card bg-light mt-4">
                            <div class="card-header bg-primary bg-opacity-10">
                                <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Approval Workflow</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- Department Approver -->
                                    <div class="col-md-6">
                                        <label for="approver_id" class="form-label">
                                            Department Approver <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="approver_id" name="approver_id" required>
                                            <option value="">Select approver...</option>
                                            <?php foreach ($approvers as $app): ?>
                                            <option value="<?= $app->id ?>" 
                                                    <?= (post('approver_id') == $app->id || $request->approver_id == $app->id) ? 'selected' : '' ?>>
                                                <?= e($app->name) ?> (<?= e($app->department_name ?: 'Admin') ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">First level approval</small>
                                    </div>
                                    
                                    <!-- Motorpool Head -->
                                    <div class="col-md-6">
                                        <label for="motorpool_head_id" class="form-label">
                                            Motorpool Head <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="motorpool_head_id" name="motorpool_head_id" required>
                                            <option value="">Select motorpool head...</option>
                                            <?php foreach ($motorpoolHeads as $mp): ?>
                                            <option value="<?= $mp->id ?>" 
                                                    <?= (post('motorpool_head_id') == $mp->id || $request->motorpool_head_id == $mp->id) ? 'selected' : '' ?>>
                                                <?= e($mp->name) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Final approval & vehicle assignment</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mt-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes"
                                rows="2" placeholder="Any special requirements or notes..."><?= e(post('notes', $request->notes)) ?></textarea>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save Changes
                            </button>
                            <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $requestId ?>"
                                class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Passenger Management Modal -->
    <div class="modal fade" id="passengerModal" tabindex="-1" aria-labelledby="passengerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title" id="passengerModalLabel"><i class="bi bi-people me-2"></i>Manage Passengers</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label for="passengers" class="form-label d-flex justify-content-between">
                            <span class="fw-bold">Search Employees / Guests</span>
                            <span class="x-small text-primary"><i class="bi bi-keyboard me-1"></i>Enter guest names</span>
                        </label>
                        <select class="form-select border-primary" id="passengers" name="passengers[]" multiple>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp->id ?>" 
                                    data-email="<?= e($emp->email) ?>"
                                    data-department="<?= e($emp->department_name ?: 'No Dept') ?>"
                                    <?= in_array($emp->id, post('passengers', $currentPassengerIds)) ? 'selected' : '' ?>>
                                <?= e($emp->name) ?> <?= $emp->department_name ? '(' . e($emp->department_name) . ')' : '' ?>
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
                        <h6 class="small fw-bold text-uppercase text-muted border-bottom pb-2 mb-3">
                            <i class="bi bi-list-check me-1"></i>Selected List
                        </h6>
                        <ul class="list-unstyled mb-0" id="modalPassengerList" style="max-height: 300px; overflow-y: auto;">
                            <!-- Populated by JS -->
                        </ul>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-primary w-100 py-2 rounded-3" data-bs-dismiss="modal">
                        Confirm Selection
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <div class="card bg-light border-0">
            <div class="card-body">
                <h6><i class="bi bi-info-circle me-2"></i>Edit Request</h6>
                <p class="small text-muted mb-0">
                    You are modifying an existing vehicle request. Ensure all details are correct before saving. 
                    Passengers will be notified of any significant changes.
                </p>
            </div>
        </div>

            <!-- Selected Passengers Preview -->
            <div class="card mt-3" id="passengerPreview">
                <div class="card-header bg-white">
                    <h6 class="mb-0 small fw-bold text-uppercase"><i class="bi bi-people me-2 text-primary"></i>Passenger List</h6>
                </div>
                <div class="card-body py-2">
                    <ul class="list-unstyled mb-0" id="passengerList">
                        <li class="mb-2 d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                <i class="bi bi-person-fill text-primary"></i>
                            </div>
                            <div>
                                <div class="fw-bold small"><?= e(currentUser()->name) ?></div>
                                <div class="x-small text-primary fw-medium">Requester</div>
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
                            
                            echo '<li class="mb-2 d-flex align-items-center">
                                <div class="'.$iconBg.' bg-opacity-10 p-2 rounded-circle me-3">
                                    <i class="bi '.$icon.' '.$iconColor.'"></i>
                                </div>
                                <div>
                                    <div class="fw-bold small">'.e($name).'</div>
                                    <div class="x-small text-muted">'.e($dept).'</div>
                                </div>
                            </li>';
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

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
                passengerCountText.innerHTML = '<span class="badge bg-primary rounded-pill">' + count + '</span>' +
                                             '<span class="small text-muted ms-1">' + label + '</span>';
            }
            
            let previewHtml = '<li class="mb-2 d-flex align-items-center">' +
                '<div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">' +
                    '<i class="bi bi-person-fill text-primary"></i>' +
                '</div>' +
                '<div>' +
                    '<div class="fw-bold small"><?= e(currentUser()->name) ?></div>' +
                    '<div class="x-small text-primary fw-medium">Requester</div>' +
                '</div>' +
            '</li>';

            let modalHtml = '<li class="mb-2 py-2 px-3 bg-light rounded d-flex align-items-center animate__animated animate__fadeIn">' +
                '<i class="bi bi-person-badge-fill me-2 text-primary"></i>' +
                '<div class="small fw-bold text-primary"><?= e(currentUser()->name) ?> (You - Requester)</div>' +
                '<span class="badge bg-primary ms-auto" style="font-size: 0.6rem;">REQUIRED</span>' +
            '</li>';
            
            if (items.length > 0) {
                items.forEach(function(value) {
                    const opt = passengerSelect.options[value];
                    if (opt) {
                        const dept = opt.department || 'External Guest';
                        const icon = opt.department ? 'bi-person' : 'bi-person-plus';
                        const iconColor = opt.department ? 'text-secondary' : 'text-success';
                        const iconBg = opt.department ? 'bg-secondary' : 'bg-success';
                        
                        previewHtml += '<li class="mb-2 d-flex align-items-center animate__animated animate__fadeIn">' +
                            '<div class="' + iconBg + ' bg-opacity-10 p-2 rounded-circle me-3">' +
                                '<i class="bi ' + icon + ' ' + iconColor + '"></i>' +
                            '</div>' +
                            '<div>' +
                                '<div class="fw-bold small">' + opt.text.replace(' (Guest)', '') + '</div>' +
                                '<div class="x-small text-muted">' + dept + '</div>' +
                            '</div>' +
                        '</li>';

                        modalHtml += '<li class="mb-2 py-2 px-3 bg-white border border-light rounded d-flex justify-content-between align-items-center shadow-sm animate__animated animate__fadeIn">' +
                            '<div class="d-flex align-items-center">' +
                                '<div class="' + iconBg + ' bg-opacity-10 p-1 rounded-circle me-2" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">' +
                                    '<i class="bi ' + icon + ' ' + iconColor + '" style="font-size: 0.8rem;"></i>' +
                                '</div>' +
                                '<div>' +
                                    '<div class="small fw-bold">' + opt.text.replace(' (Guest)', '') + '</div>' +
                                    '<div class="x-small text-muted">' + dept + '</div>' +
                                '</div>' +
                            '</div>' +
                            '<button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removePassenger(\'' + value + '\')">' +
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
                console.log('TomSelect already initialized');
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
                            return '<div class="py-2 px-2"><i class="bi bi-plus-circle me-1 text-success"></i>Add guest "<strong>' + escape(data.text) + '</strong>"</div>';
                        }
                        return '<div class="py-2 px-2">' +
                            '<div class="fw-medium">' + escape(data.text) + '</div>' +
                            '<div class="small text-muted">' + escape(data.department || '') + '</div>' +
                            '</div>';
                    },
                    item: function (data, escape) {
                        const icon = data.department ? 'bi-person-fill' : 'bi-person-plus';
                        return '<div class="d-flex align-items-center">' +
                            '<i class="bi ' + icon + ' me-1"></i>' +
                            '<span>' + escape(data.text.replace(' (Guest)', '')) + '</span>' +
                            '</div>';
                    },
                    no_results: function(data, escape) {
                        return '<div class="py-2 px-2 text-muted">No employees found. Type a name to add as guest.</div>';
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
            
            console.log('TomSelect initialized successfully for passengers');

            // Handle focus when modal opens
            const modalEl = document.getElementById('passengerModal');
            modalEl.addEventListener('shown.bs.modal', function () {
                passengerSelect.focus();
            });
        } catch (error) {
            console.error('Error initializing passenger select:', error);
            // Fallback: ensure the select still works as a regular multi-select
            if (passengersSelect) {
                passengersSelect.style.minHeight = '100px';
            }
        }
    });
</script>
<?php
$pageScripts = ob_get_clean();
require_once INCLUDES_PATH . '/footer.php';
?>