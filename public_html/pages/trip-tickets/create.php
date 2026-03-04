<?php
/**
 * LOKA - Create Trip Ticket Form
 * 
 * Pre-filled form for creating trip ticket after trip completion
 */

requireRole(ROLE_GUARD);

$pageTitle = 'Create Trip Ticket';
$requestId = (int) get('request_id', 0);

// Validate request ID
if (!$requestId) {
    redirectWith('/?page=guard', 'danger', 'Invalid request ID.');
}

// Get request with all trip details for pre-filling
$request = db()->fetch(
    "SELECT r.*, 
            v.plate_number, v.make, v.model as vehicle_model, v.color,
            d.id as driver_id, d.name as driver_name, d.license_number as driver_license,
            u.name as requester_name, u.department_id, dept.name as department_name,
            dg.name as dispatch_guard,
            r.actual_dispatch_datetime, r.actual_arrival_datetime,
            r.mileage_start
     FROM requests r
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN drivers d ON r.driver_id = d.id AND d.deleted_at IS NULL
     LEFT JOIN users u ON r.user_id = u.id
     LEFT JOIN departments dept ON u.department_id = dept.id
     LEFT JOIN users dg ON r.dispatch_guard_id = dg.id
     WHERE r.id = ? AND r.deleted_at IS NULL
     FOR UPDATE",
    [$requestId]
);

if (!$request) {
    redirectWith('/?page=guard', 'danger', 'Request not found.');
}

// Can only create trip ticket for completed trips
if ($request->status !== STATUS_COMPLETED) {
    redirectWith(
        '/?page=requests&action=view&id=' . $requestId,
        'warning',
        'Trip ticket can only be created for completed trips. Current status: ' . $request->status
    );
}

// Check if ticket already exists
$existingTicket = db()->fetch(
    "SELECT id FROM trip_tickets WHERE request_id = ? AND deleted_at IS NULL",
    [$requestId]
);

if ($existingTicket) {
    redirectWith(
        '/?page=trip-tickets&action=view&id=' . $existingTicket->id,
        'info',
        'A trip ticket already exists for this trip. You can view or update it.'
    );
}

// Calculate pre-filled values
$startDate = $request->actual_dispatch_datetime ? date('Y-m-d\TH:i', strtotime($request->actual_dispatch_datetime)) : '';
$endDate = $request->actual_arrival_datetime ? date('Y-m-d\TH:i', strtotime($request->actual_arrival_datetime)) : '';
$tripPurpose = $request->purpose;
$destination = $request->destination;
$startMileage = $request->mileage_start;

// Calculate duration
$durationHours = 0;
$durationMinutes = 0;
if ($request->actual_dispatch_datetime && $request->actual_arrival_datetime) {
    $start = strtotime($request->actual_dispatch_datetime);
    $end = strtotime($request->actual_arrival_datetime);
    $diff = $end - $start;
    $durationHours = floor($diff / 3600);
    $durationMinutes = floor(($diff % 3600) / 60);
}

// Get passengers count
$passengers = db()->fetchColumn(
    "SELECT COUNT(*) FROM passengers WHERE request_id = ?",
    [$requestId]
);

// Pre-select vehicle plate if available
$plateNumber = $request->plate_number ? $request->plate_number . ' - ' . $request->make . ' ' . $request->vehicle_model : '';

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $tripType = post('trip_type', 'official');
    $newStartDate = post('start_date');
    $newEndDate = post('end_date');
    $destinationInput = postSafe('destination', '', 255);
    $purposeInput = postSafe('purpose', '', 500);
    $passengersInput = (int) post('passengers', $passengers);
    $startMileageInput = post('start_mileage') ? (int)post('start_mileage') : null;
    $endMileageInput = post('end_mileage') ? (int)post('end_mileage') : null;
    $distanceTraveled = post('distance_traveled') ? (int)post('distance_traveled') : null;
    $fuelConsumed = post('fuel_consumed') ? (float)post('fuel_consumed') : null;
    $fuelCost = post('fuel_cost') ? (float)post('fuel_cost') : null;
    
    // Documents (will be handled by upload endpoint)
    $travelOrderPath = null;
    $obSlipPath = null;
    $otherDocumentsPath = null;
    
    // Issues
    $hasIssues = post('has_issues') ? 1 : 0;
    $issuesDescription = postSafe('issues_description', '', 1000);
    $resolved = post('resolved') ? 1 : 0;
    $resolutionNotes = postSafe('resolution_notes', '', 1000);
    $guardNotes = postSafe('guard_notes', '', 1000);
    
    // Validation
    if (!$newStartDate) {
        $errors[] = 'Start date is required';
    }
    if (!$newEndDate) {
        $errors[] = 'End date is required';
    }
    if (!$destinationInput) {
        $errors[] = 'Destination is required';
    }
    if (!$tripType || !in_array($tripType, ['official', 'personal', 'maintenance', 'other'])) {
        $errors[] = 'Invalid trip type';
    }
    
    if (empty($errors)) {
        try {
            db()->beginTransaction();
            
            // Insert trip ticket
            $ticketId = db()->insert('trip_tickets', [
                'request_id' => $requestId,
                'driver_id' => $request->driver_id,
                'trip_type' => $tripType,
                'start_date' => date('Y-m-d H:i:s', strtotime($newStartDate)),
                'end_date' => date('Y-m-d H:i:s', strtotime($newEndDate)),
                'destination' => $destinationInput,
                'purpose' => $purposeInput,
                'passengers' => $passengersInput,
                'start_mileage' => $startMileageInput,
                'end_mileage' => $endMileageInput,
                'distance_traveled' => $distanceTraveled,
                'fuel_consumed' => $fuelConsumed,
                'fuel_cost' => $fuelCost,
                'travel_order_path' => $travelOrderPath,
                'ob_slip_path' => $obSlipPath,
                'other_documents_path' => $otherDocumentsPath,
                'has_issues' => $hasIssues,
                'issues_description' => $issuesDescription,
                'resolved' => $resolved,
                'resolution_notes' => $resolutionNotes,
                'dispatch_guard_id' => $request->dispatch_guard_id ?: userId(),
                'arrival_guard_id' => userId(),
                'guard_notes' => $guardNotes,
                'status' => 'submitted',
                'created_by' => userId()
            ]);
            
            // Link ticket to request
            db()->update('requests', 
                ['trip_ticket_id' => $ticketId], 
                'id = ?', 
                [$requestId]
            );
            
            // Audit log
            auditLog(
                'trip_ticket_created',
                'trip_ticket',
                $ticketId,
                null,
                [
                    'request_id' => $requestId,
                    'driver_id' => $request->driver_id,
                    'trip_type' => $tripType
                ]
            );
            
            db()->commit();
            
            redirectWith(
                '/?page=trip-tickets&action=view&id=' . $ticketId,
                'success',
                'Trip ticket created successfully! You can now upload documents.'
            );
            
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Failed to create trip ticket: ' . $e->getMessage();
        }
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">
                <i class="bi bi-file-earmark-plus me-2"></i>
                Create Trip Ticket
            </h1>
            <p class="text-muted mb-0">Document completed trip details and documents</p>
        </div>
        <div>
            <a href="?page=guard" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Guard Dashboard
            </a>
            <a href="?page=trip-tickets" class="btn btn-outline-primary">
                <i class="bi bi-list-check me-1"></i>All Trip Tickets
            </a>
        </div>
    </div>

    <!-- Trip Reference Card -->
    <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="bi bi-link-45deg me-2"></i>
                Trip Reference: Request #<?= $requestId ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl class="row">
                        <dt class="col-sm-4">Requester:</dt>
                        <dd class="col-sm-8"><?= e($request->requester_name) ?></dd>
                        
                        <dt class="col-sm-4">Department:</dt>
                        <dd class="col-sm-8"><?= e($request->department_name) ?></dd>
                        
                        <dt class="col-sm-4">Driver:</dt>
                        <dd class="col-sm-8">
                            <?= e($request->driver_name) ?>
                            <small class="text-muted">(<?= e($request->driver_license) ?>)</small>
                        </dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="row">
                        <dt class="col-sm-4">Vehicle:</dt>
                        <dd class="col-sm-8">
                            <?= $plateNumber ?: 'No vehicle assigned' ?>
                        </dd>
                        
                        <dt class="col-sm-4">Destination:</dt>
                        <dd class="col-sm-8"><?= e($request->destination) ?></dd>
                        
                        <dt class="col-sm-4">Original Purpose:</dt>
                        <dd class="col-sm-8"><?= truncate($request->purpose, 50) ?></dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <dl class="row">
                        <dt class="col-sm-5">Dispatched:</dt>
                        <dd class="col-sm-7">
                            <?= $request->actual_dispatch_datetime ? formatDateTime($request->actual_dispatch_datetime) : '-' ?>
                        </dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <dl class="row">
                        <dt class="col-sm-5">Arrived:</dt>
                        <dd class="col-sm-7">
                            <?= $request->actual_arrival_datetime ? formatDateTime($request->actual_arrival_datetime) : '-' ?>
                        </dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <dl class="row">
                        <dt class="col-sm-5">Duration:</dt>
                        <dd class="col-sm-7">
                            <?php if ($durationHours > 0): ?>
                                <span class="badge bg-info">
                                    <?= $durationHours ?>h <?= $durationMinutes ?>m
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <dl class="row">
                        <dt class="col-sm-5">Start Mileage:</dt>
                        <dd class="col-sm-7">
                            <strong><?= $startMileage ? number_format($startMileage) . ' km' : '-' ?></strong>
                        </dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="row">
                        <dt class="col-sm-5">Dispatched By:</dt>
                        <dd class="col-sm-7">
                            <?= e($request->dispatch_guard) ?: '-' ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Trip Ticket Form -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-pencil-square me-2"></i>
                Trip Ticket Details
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong><i class="bi bi-exclamation-triangle me-2"></i>Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="needs-validation">
                <?= csrfInput() ?>
                
                <!-- Trip Type & Purpose -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Trip Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="trip_type" required>
                            <option value="official" <?= $tripType === 'official' ? 'selected' : '' ?>>Official Business</option>
                            <option value="personal" <?= $tripType === 'personal' ? 'selected' : '' ?>>Personal</option>
                            <option value="maintenance" <?= $tripType === 'maintenance' ? 'selected' : '' ?>>Maintenance Run</option>
                            <option value="other" <?= $tripType === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                        <small class="text-muted">Select the nature of this trip</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Purpose</label>
                        <textarea class="form-control" name="purpose" rows="2" placeholder="Detailed purpose of the trip..."><?= e($purposeInput) ?></textarea>
                        <small class="text-muted">Overrides original purpose if provided</small>
                    </div>
                </div>

                <!-- Date & Time (Pre-filled but editable) -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Start Date <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" name="start_date" value="<?= $startDate ?>" required>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>Pre-filled from dispatch time (can be edited)
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Date <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" name="end_date" value="<?= $endDate ?>" required>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>Pre-filled from arrival time (can be edited)
                        </small>
                    </div>
                </div>

                <!-- Destination (Pre-filled but editable) -->
                <div class="mb-3">
                    <label class="form-label">Destination <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="destination" value="<?= e($destination) ?>" required>
                </div>

                <!-- Passengers -->
                <div class="mb-3">
                    <label class="form-label">Number of Passengers</label>
                    <input type="number" class="form-control" name="passengers" min="0" value="<?= $passengers ?>">
                    <small class="text-muted">Including driver</small>
                </div>

                <!-- Mileage -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Start Odometer</label>
                        <input type="number" class="form-control" name="start_mileage" value="<?= $startMileage ?>" placeholder="Starting reading">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Odometer</label>
                        <input type="number" class="form-control" name="end_mileage" placeholder="Ending reading" required>
                        <small class="text-danger">Required</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Distance Traveled (km)</label>
                        <input type="number" class="form-control" name="distance_traveled" placeholder="Auto-calculated if different">
                    </div>
                </div>

                <!-- Fuel -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Fuel Consumed (L)</label>
                        <input type="number" step="0.01" class="form-control" name="fuel_consumed" placeholder="Total liters consumed">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fuel Cost (PHP)</label>
                        <input type="number" step="0.01" class="form-control" name="fuel_cost" placeholder="Total cost in PHP">
                    </div>
                </div>

                <hr class="my-4">

                <!-- Documents Upload Section -->
                <div class="mb-4">
                    <h6 class="mb-3">
                        <i class="bi bi-files me-1"></i>
                        Upload Documents
                    </h6>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> Upload Travel Order (TO) and OB Slip documents here. These are required for official business trips.
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Travel Order (TO)</label>
                            <input type="file" class="form-control" name="travel_order" accept=".pdf,.jpg,.jpeg,.png" id="travelOrderInput">
                            <small class="text-muted">PDF or Image files</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">OB Slip</label>
                            <input type="file" class="form-control" name="ob_slip" accept=".pdf,.jpg,.jpeg,.png" id="obSlipInput">
                            <small class="text-muted">PDF or Image files</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Other Documents</label>
                            <input type="file" class="form-control" name="other_documents" accept=".pdf,.zip" multiple id="otherDocsInput">
                            <small class="text-muted">Optional additional documents</small>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Issues Section -->
                <div class="mb-4">
                    <h6 class="mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Report Issues (Optional)
                    </h6>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="has_issues" id="hasIssues" onchange="toggleIssuesFields()">
                        <label class="form-check-label" for="hasIssues">Were there any issues or incidents during this trip?</label>
                    </div>

                    <div id="issuesFields" class="mb-3" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Issues Description</label>
                                <textarea class="form-control" name="issues_description" rows="3" placeholder="Describe any issues, incidents, or concerns..."></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Resolved?</label>
                                <select class="form-select" name="resolved">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label">Resolution Notes</label>
                            <textarea class="form-control" name="resolution_notes" rows="2" placeholder="How were the issues resolved? (if applicable)"></textarea>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Guard Notes -->
                <div class="mb-4">
                    <label class="form-label">Guard Notes</label>
                    <textarea class="form-control" name="guard_notes" rows="3" placeholder="Any additional observations, comments, or notes..."></textarea>
                    <small class="text-muted">Include any special circumstances or relevant information</small>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between align-items-center">
                    <a href="?page=guard" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>
                        Create Trip Ticket
                    </button>
                </div>

                <!-- Info Box -->
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-shield-check me-2"></i>
                    <strong>Important:</strong> After creating the trip ticket, you will be able to upload documents. All trip details will be saved and linked to request #<?= $requestId ?>.
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleIssuesFields() {
    const hasIssues = document.getElementById('hasIssues').checked;
    document.getElementById('issuesFields').style.display = hasIssues ? 'block' : 'none';
}

// Handle file uploads (basic implementation)
document.querySelector('form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    
    // Check if any files were selected
    const hasFiles = formData.has('travel_order') || formData.has('ob_slip') || formData.has('other_documents');
    
    if (hasFiles) {
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i> Uploading...';
        
        // Upload files first (simplified - in production, use dedicated upload endpoint)
        const uploadPromises = [];
        
        ['travel_order', 'ob_slip', 'other_documents'].forEach(fieldName => {
            const fileInput = document.getElementById(fieldName + 'Input');
            if (fileInput && fileInput.files[0]) {
                const file = fileInput.files[0];
                const uploadPromise = new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Store file data in FormData
                        formData.append(fieldName + '_data', e.target.result);
                        resolve();
                    };
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                });
                uploadPromises.push(uploadPromise);
            }
        });
        
        await Promise.all(uploadPromises);
    }
    
    // Submit the form normally
    form.submit();
});
</script>

<style>
.needs-validation input:invalid {
    border-color: #dc3545;
}

.card-header.bg-primary {
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
}
</style>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
