<?php
/**
 * LOKA - Approval Review Page
 */

requireRole(ROLE_APPROVER);

$requestId = (int) get('id');

// Get request with full details
$request = db()->fetch(
    "SELECT r.*,
            u.name as requester_name, u.email as requester_email, u.phone as requester_phone,
            d.name as department_name,
            v.plate_number as vehicle_plate, v.make as vehicle_make, v.model as vehicle_model,
            v.color as vehicle_color, v.year as vehicle_year, v.mileage as vehicle_mileage,
            vt.name as vehicle_type, vt.passenger_capacity as vehicle_capacity,
            drv.license_number as driver_license, drv_u.name as driver_name, drv_u.phone as driver_phone
     FROM requests r
     JOIN users u ON r.user_id = u.id
     JOIN departments d ON r.department_id = d.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
     LEFT JOIN drivers drv ON r.driver_id = drv.id AND drv.deleted_at IS NULL
     LEFT JOIN users drv_u ON drv.user_id = drv_u.id
     WHERE r.id = ? AND r.deleted_at IS NULL",
    [$requestId]
);

if (!$request) {
    redirectWith('/?page=approvals', 'danger', 'Request not found.');
}

// Mark request as viewed by this approver
db()->update('requests', ['viewed_at' => date(DATETIME_FORMAT)], 'id = ?', [$requestId]);

// Check if user can approve this request
$canApprove = false;
$approvalType = '';
$userRole = userRole();
$isCurrentUserApprover = $request->approver_id == userId();
$isCurrentUserMotorpool = $request->motorpool_head_id == userId();

if ($request->status === STATUS_PENDING_MOTORPOOL) {
    if ($request->motorpool_head_id == userId() || isAdmin()) {
        $canApprove = true;
        $approvalType = 'motorpool';
    }
} elseif ($request->status === STATUS_PENDING) {
    if ($request->approver_id == userId() || isAdmin()) {
        $canApprove = true;
        $approvalType = 'department';
    }
} elseif ($request->status === STATUS_REVISION) {
    // Both approver and motorpool can process revision requests
    if ($request->approver_id == userId() || isAdmin()) {
        $canApprove = true;
        $approvalType = 'department';
    } elseif ($request->motorpool_head_id == userId()) {
        $canApprove = true;
        $approvalType = 'motorpool';
    }
}

// Get approval workflow status for each stage
$departmentApproval = db()->fetch(
    "SELECT a.*, u.name as approver_name 
     FROM approvals a 
     JOIN users u ON a.approver_id = u.id 
     WHERE a.request_id = ? AND a.approval_type = 'department' 
     ORDER BY a.created_at DESC LIMIT 1",
    [$requestId]
);

$motorpoolApproval = db()->fetch(
    "SELECT a.*, u.name as approver_name 
     FROM approvals a 
     JOIN users u ON a.approver_id = u.id 
     WHERE a.request_id = ? AND a.approval_type = 'motorpool' 
     ORDER BY a.created_at DESC LIMIT 1",
    [$requestId]
);

// Get available vehicles (for motorpool)
$availableVehicles = [];
$availableDrivers = [];

if ($approvalType === 'motorpool') {
    $availableDrivers = db()->fetchAll(
        "SELECT d.*, u.name as driver_name, u.phone as driver_phone
         FROM drivers d
         JOIN users u ON d.user_id = u.id
         WHERE d.deleted_at IS NULL
         AND u.status = 'active' AND u.deleted_at IS NULL
         ORDER BY u.name"
    );

    $availableVehicles = db()->fetchAll(
        "SELECT v.*, vt.name as type_name, vt.passenger_capacity
         FROM vehicles v
         JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
         WHERE v.deleted_at IS NULL
         ORDER BY vt.name, v.plate_number"
    );
}

// Get the requested driver name if any
$requestedDriver = null;
if ($request->requested_driver_id) {
    $requestedDriver = db()->fetch(
        "SELECT u.name FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.id = ?",
        [$request->requested_driver_id]
    );
}

// Get requested/preferred vehicle name if any (user selected during request creation)
$requestedVehicle = null;
if ($request->vehicle_id) {
    $requestedVehicle = db()->fetch(
        "SELECT v.*, vt.name as type_name, vt.passenger_capacity
         FROM vehicles v
         JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
         WHERE v.id = ?",
        [$request->vehicle_id]
    );
}

// Check conflicts for requested vehicle and driver (real-time for motorpool)
$vehicleConflictsList = [];
$driverConflictsList = [];
$vehicleConflictSeverity = 'none';
$driverConflictSeverity = 'none';
$vehicleConflicts = 0;
$driverConflicts = 0;

if ($approvalType === 'motorpool') {
    // Check vehicle conflicts
    if ($request->vehicle_id) {
        $vehicleConflict = checkVehicleConflict($request->vehicle_id, $request->start_datetime, $request->end_datetime, $requestId);
        if ($vehicleConflict) {
            $vehicleConflictsList[] = $vehicleConflict;
            $vehicleConflicts = count($vehicleConflictsList);
            $overlap = calculateOverlapMinutes($vehicleConflict, $request->start_datetime, $request->end_datetime);
            $vehicleConflictSeverity = $overlap <= 60 ? 'minor' : ($overlap <= 120 ? 'moderate' : 'severe');
        }
    }

    // Check driver conflicts
    if ($request->requested_driver_id) {
        $driverConflict = checkDriverConflict($request->requested_driver_id, $request->start_datetime, $request->end_datetime, $requestId);
        if ($driverConflict) {
            $driverConflictsList[] = $driverConflict;
            $driverConflicts = count($driverConflictsList);
            $overlap = calculateOverlapMinutes($driverConflict, $request->start_datetime, $request->end_datetime);
            $driverConflictSeverity = $overlap <= 60 ? 'minor' : ($overlap <= 120 ? 'moderate' : 'severe');
        }
    }
}

$hasConflicts = ($vehicleConflictSeverity !== 'none' || $driverConflictSeverity !== 'none');
$totalConflicts = $vehicleConflicts + $driverConflicts;
$allConflictsList = array_merge($vehicleConflictsList, $driverConflictsList);

// Get approval history
$approvals = db()->fetchAll(
    "SELECT a.*, u.name as approver_name
     FROM approvals a
     JOIN users u ON a.approver_id = u.id
     WHERE a.request_id = ?
     ORDER BY a.created_at ASC",
    [$requestId]
);

$pageTitle = 'Review Request #' . $requestId;

require_once INCLUDES_PATH . '/header.php';
?>

<div class="px-4 py-6 sm:px-6 lg:px-8 max-w-[1600px] mx-auto">
    <!-- Page Header with Context-Aware Status -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">Review Request #<?= $requestId ?></h1>
            <nav class="mt-1 text-sm text-base-content/60">
                <a href="<?= APP_URL ?>" class="hover:underline">Dashboard</a>
                <span class="mx-1">/</span>
                <a href="<?= APP_URL ?>/?page=approvals" class="hover:underline">Approvals</a>
                <span class="mx-1">/</span>
                <span>Review</span>
            </nav>
        </div>
        <div>
            <?php
            // Context-aware status display based on current user's role and the request state
            $statusHtml = '';
            
            if ($request->status === STATUS_PENDING && $isCurrentUserApprover && $canApprove) {
                $statusHtml = '<span class="loka-badge badge-warning gap-1 text-sm"><i class="bi bi-hourglass-split"></i>Pending Your Approval</span>';
            } elseif ($request->status === STATUS_PENDING_MOTORPOOL && $isCurrentUserMotorpool && $canApprove) {
                $statusHtml = '<span class="loka-badge badge-warning gap-1 text-sm"><i class="bi bi-hourglass-split"></i>Awaiting Your Approval</span>';
            } elseif ($request->status === STATUS_PENDING) {
                $statusHtml = '<span class="loka-badge badge-info gap-1 text-sm"><i class="bi bi-clock-history"></i>Awaiting Department Approval</span>';
            } elseif ($request->status === STATUS_PENDING_MOTORPOOL) {
                $statusHtml = '<span class="loka-badge badge-primary gap-1 text-sm"><i class="bi bi-truck-front"></i>Awaiting Motorpool Assignment</span>';
            } elseif ($request->status === STATUS_APPROVED) {
                $statusHtml = '<span class="loka-badge badge-success gap-1 text-sm"><i class="bi bi-check-circle"></i>Fully Approved</span>';
            } elseif ($request->status === STATUS_REJECTED) {
                $statusHtml = '<span class="loka-badge badge-error gap-1 text-sm"><i class="bi bi-x-circle"></i>Rejected</span>';
            } elseif ($request->status === STATUS_REVISION) {
                $statusHtml = '<span class="loka-badge badge-warning gap-1 text-sm"><i class="bi bi-arrow-repeat"></i>Under Revision</span>';
            } elseif ($request->status === STATUS_CANCELLED) {
                $statusHtml = '<span class="loka-badge badge-ghost gap-1 text-sm"><i class="bi bi-slash-circle"></i>Cancelled</span>';
            } else {
                $statusHtml = requestStatusBadge($request->status);
            }
            
            echo $statusHtml;
            ?>
        </div>
    </div>

    <!-- Approval Workflow Progress Tracker -->
    <div class="loka-card mb-6">
        <div class="border-b border-base-200 px-5 py-4">
            <h5 class="font-semibold"><i class="bi bi-diagram-3 mr-2"></i>Approval Workflow Status</h5>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Department Approval Stage -->
                <div class="rounded-xl border p-4 <?= $departmentApproval && $departmentApproval->status === 'approved' ? 'border-success bg-success/10' : ($departmentApproval && $departmentApproval->status === 'rejected' ? 'border-error bg-error/10' : ($departmentApproval && $departmentApproval->status === 'revision' ? 'border-warning bg-warning/10' : 'bg-base-200/50')) ?>">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="loka-badge <?= $departmentApproval ? ($departmentApproval->status === 'approved' ? 'badge-success' : ($departmentApproval->status === 'rejected' ? 'badge-error' : 'badge-warning')) : 'badge-ghost' ?> badge-sm rounded-full p-2">
                            <i class="bi bi-<?= $departmentApproval ? ($departmentApproval->status === 'approved' ? 'check-circle' : ($departmentApproval->status === 'rejected' ? 'x-circle' : 'arrow-repeat')) : 'clock' ?>"></i>
                        </span>
                        <strong class="text-sm">Department Approval</strong>
                    </div>
                    <div class="space-y-1 text-sm">
                        <?php if ($departmentApproval): ?>
                            <div><strong>Status:</strong> 
                                <span class="<?= $departmentApproval->status === 'approved' ? 'text-success' : ($departmentApproval->status === 'rejected' ? 'text-error' : 'text-warning') ?>">
                                    <?= ucfirst($departmentApproval->status) ?>
                                </span>
                            </div>
                            <div><strong>By:</strong> <?= e($departmentApproval->approver_name) ?></div>
                            <div><strong>Date:</strong> <?= formatDateTime($departmentApproval->created_at) ?></div>
                            <?php if ($departmentApproval->comments): ?>
                                <div class="mt-1 italic text-base-content/60">"<?= e($departmentApproval->comments) ?>"</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-base-content/60">Pending - Waiting for department approval</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Motorpool Approval Stage -->
                <div class="rounded-xl border p-4 <?= $motorpoolApproval && $motorpoolApproval->status === 'approved' ? 'border-success bg-success/10' : ($motorpoolApproval && $motorpoolApproval->status === 'rejected' ? 'border-error bg-error/10' : ($motorpoolApproval && $motorpoolApproval->status === 'revision' ? 'border-warning bg-warning/10' : 'bg-base-200/50')) ?>">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="loka-badge <?= $motorpoolApproval ? ($motorpoolApproval->status === 'approved' ? 'badge-success' : ($motorpoolApproval->status === 'rejected' ? 'badge-error' : 'badge-warning')) : 'badge-ghost' ?> badge-sm rounded-full p-2">
                            <i class="bi bi-<?= $motorpoolApproval ? ($motorpoolApproval->status === 'approved' ? 'check-circle' : ($motorpoolApproval->status === 'rejected' ? 'x-circle' : 'arrow-repeat')) : 'clock' ?>"></i>
                        </span>
                        <strong class="text-sm">Motorpool Approval</strong>
                    </div>
                    <div class="space-y-1 text-sm">
                        <?php if ($motorpoolApproval): ?>
                            <div><strong>Status:</strong> 
                                <span class="<?= $motorpoolApproval->status === 'approved' ? 'text-success' : ($motorpoolApproval->status === 'rejected' ? 'text-error' : 'text-warning') ?>">
                                    <?= ucfirst($motorpoolApproval->status) ?>
                                </span>
                            </div>
                            <div><strong>By:</strong> <?= e($motorpoolApproval->approver_name) ?></div>
                            <div><strong>Date:</strong> <?= formatDateTime($motorpoolApproval->created_at) ?></div>
                            <?php if ($motorpoolApproval->comments): ?>
                                <div class="mt-1 italic text-base-content/60">"<?= e($motorpoolApproval->comments) ?>"</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-base-content/60"><?= $departmentApproval ? 'Waiting for motorpool approval' : 'Waiting for department approval first' ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Vehicle/Driver Assignment (if approved) -->
            <?php if ($request->status === STATUS_APPROVED && ($request->vehicle_id || $request->driver_id)): ?>
                <div class="mt-4 rounded-xl border border-success bg-success/10 p-4">
                    <strong class="text-sm"><i class="bi bi-check-square mr-1"></i>Final Assignment:</strong>
                    <?php
                    $vehicle = $request->vehicle_id ? db()->fetch("SELECT plate_number, make, model FROM vehicles WHERE id = ?", [$request->vehicle_id]) : null;
                    $driver = $request->driver_id ? db()->fetch("SELECT d.*, u.name FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.id = ?", [$request->driver_id]) : null;
                    ?>
                    <div class="mt-1 text-sm">
                        <?php if ($vehicle): ?>
                            <span class="mr-3"><i class="bi bi-car-front mr-1"></i>Vehicle: <strong><?= e($vehicle->plate_number) ?> - <?= e($vehicle->make) ?> <?= e($vehicle->model) ?></strong></span>
                        <?php endif; ?>
                        <?php if ($driver): ?>
                            <span><i class="bi bi-person-badge mr-1"></i>Driver: <strong><?= e($driver->name) ?></strong></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <!-- Request Details -->
        <div class="lg:col-span-8">
            <div class="loka-card mb-6">
                <div class="border-b border-base-200 px-5 py-4">
                    <h5 class="font-semibold"><i class="bi bi-file-earmark-text mr-2"></i>Request Details</h5>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm text-base-content/60">Requester</label>
                            <div class="font-bold"><?= e($request->requester_name) ?></div>
                            <div class="text-sm text-base-content/60"><?= e($request->requester_email) ?></div>
                        </div>
                        <div>
                            <label class="text-sm text-base-content/60">Department</label>
                            <div class="font-bold"><?= e($request->department_name) ?></div>
                        </div>
                        <div>
                            <label class="text-sm text-base-content/60">Start Date/Time</label>
                            <div class="font-medium"><?= formatDateTime($request->start_datetime) ?></div>
                        </div>
                        <div>
                            <label class="text-sm text-base-content/60">End Date/Time</label>
                            <div class="font-medium"><?= formatDateTime($request->end_datetime) ?></div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-base-content/60">Purpose</label>
                            <div class="font-medium"><?= nl2br(e($request->purpose)) ?></div>
                        </div>
                        <div>
                            <label class="text-sm text-base-content/60">Destination</label>
                            <div class="font-medium"><?= e($request->destination) ?></div>
                        </div>
                        <div>
                            <label class="text-sm text-base-content/60">Passenger Count</label>
                            <div class="font-medium"><?= $request->passenger_count ?></div>
                        </div>
                        <!-- Passengers List -->
                        <?php
                        $passengers = db()->fetchAll(
                            "SELECT rp.*, u.name as user_name, u.email as user_email, d.name as department_name
                             FROM request_passengers rp
                             LEFT JOIN users u ON rp.user_id = u.id
                             LEFT JOIN departments d ON u.department_id = d.id
                             WHERE rp.request_id = ?
                             ORDER BY 
                                 CASE WHEN rp.user_id IS NOT NULL THEN 0 ELSE 1 END,
                                 u.name ASC, rp.guest_name ASC",
                            [$requestId]
                        );
                        ?>
                        <?php if (!empty($passengers)): ?>
                            <div class="col-span-1 mt-3">
                                <label class="mb-2 block text-sm text-base-content/60">
                                    <i class="bi bi-people-fill mr-1"></i>Passengers List
                                </label>
                                <div class="rounded-xl border border-base-300 bg-base-200/50 p-4">
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <!-- Requester (always first) -->
                                        <div class="mb-2">
                                            <div class="flex items-center rounded-xl border border-base-300 bg-base-100 p-3">
                                                <div class="mr-3 flex h-9 w-9 items-center justify-center rounded-full bg-primary/10">
                                                    <i class="bi bi-person-fill text-primary"></i>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="text-sm font-bold"><?= e($request->requester_name) ?></div>
                                                    <div class="text-xs font-medium text-primary">Requester</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Other Passengers -->
                                        <?php foreach ($passengers as $passenger): ?>
                                            <div class="mb-2">
                                                <div class="flex items-center rounded-xl border border-base-300 bg-base-100 p-3">
                                                    <?php if ($passenger->user_id): ?>
                                                        <!-- System User -->
                                                        <div class="mr-3 flex h-9 w-9 items-center justify-center rounded-full bg-secondary/10">
                                                            <i class="bi bi-person text-secondary"></i>
                                                        </div>
                                                        <div class="flex-1">
                                                            <div class="text-sm font-bold"><?= e($passenger->user_name) ?></div>
                                                            <div class="text-xs text-base-content/60">
                                                                <?= e($passenger->department_name ?: 'No Department') ?>
                                                            </div>
                                                            <?php if ($passenger->user_email): ?>
                                                                <div class="text-xs text-base-content/60">
                                                                    <i class="bi bi-envelope mr-1"></i><?= e($passenger->user_email) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <!-- Guest -->
                                                        <div class="mr-3 flex h-9 w-9 items-center justify-center rounded-full bg-success/10">
                                                            <i class="bi bi-person-plus text-success"></i>
                                                        </div>
                                                        <div class="flex-1">
                                                            <div class="text-sm font-bold"><?= e($passenger->guest_name) ?></div>
                                                            <div class="text-xs font-medium text-success">External Guest</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($requestedDriver): ?>
                            <div class="col-span-1 mt-2">
                                <label class="text-sm text-base-content/60">Requested Driver</label>
                                <div class="flex items-center">
                                    <span class="font-bold text-primary"><i
                                            class="bi bi-person-badge mr-1"></i><?= e($requestedDriver->name) ?></span>
                                    <div id="requestedDriverConflict" class="ml-3 hidden text-sm text-error">
                                        <i class="bi bi-exclamation-circle mr-1"></i>Conflict detected!
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($request->notes): ?>
                            <div class="col-span-1">
                                <label class="text-sm text-base-content/60">Additional Notes</label>
                                <div><?= nl2br(e($request->notes)) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Vehicle Information -->
            <?php if ($request->vehicle_plate || $approvalType === 'motorpool'): ?>
                <div class="loka-card mb-6">
                    <div class="border-b border-base-200 px-5 py-4">
                        <h5 class="font-semibold"><i class="bi bi-car-front mr-2"></i>Vehicle Information</h5>
                    </div>
                    <div class="p-5">
                        <?php if ($request->vehicle_plate): ?>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <label class="text-sm text-base-content/60">Plate Number</label>
                                    <div class="text-xl font-bold"><?= e($request->vehicle_plate) ?></div>
                                </div>
                                <div>
                                    <label class="text-sm text-base-content/60">Make & Model</label>
                                    <div class="font-bold">
                                        <?= e($request->vehicle_make) ?> <?= e($request->vehicle_model) ?>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm text-base-content/60">Vehicle Type</label>
                                    <div><?= e($request->vehicle_type ?: 'N/A') ?></div>
                                </div>
                                <div>
                                    <label class="text-sm text-base-content/60">Year</label>
                                    <div><?= $request->vehicle_year ?: 'N/A' ?></div>
                                </div>
                                <div>
                                    <label class="text-sm text-base-content/60">Color</label>
                                    <div><?= e($request->vehicle_color ?: 'N/A') ?></div>
                                </div>
                                <div>
                                    <label class="text-sm text-base-content/60">Passenger Capacity</label>
                                    <div>
                                        <i class="bi bi-people mr-1"></i>
                                        <?= $request->vehicle_capacity ?: 'N/A' ?> seats
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm text-base-content/60">Current Mileage</label>
                                    <div>
                                        <i class="bi bi-speedometer2 mr-1"></i>
                                        <?= number_format($request->vehicle_mileage ?? 0) ?> km
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="py-8 text-center">
                                <i class="bi bi-car-front text-4xl text-base-content/40"></i>
                                <p class="mt-2 text-sm text-base-content/60">
                                    Vehicle will be assigned by Motorpool Head during approval.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Driver Information (if assigned) -->
            <?php if ($request->driver_name): ?>
                <div class="loka-card mb-6">
                    <div class="border-b border-base-200 px-5 py-4">
                        <h5 class="font-semibold"><i class="bi bi-person-badge mr-2"></i>Driver Information</h5>
                    </div>
                    <div class="p-5">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label class="text-sm text-base-content/60">Driver Name</label>
                                <div class="font-bold"><?= e($request->driver_name) ?></div>
                            </div>
                            <div>
                                <label class="text-sm text-base-content/60">Phone</label>
                                <div><?= e($request->driver_phone) ?></div>
                            </div>
                            <div>
                                <label class="text-sm text-base-content/60">License Number</label>
                                <div><?= e($request->driver_license) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Approval History -->
            <?php if (!empty($approvals)): ?>
                <div class="loka-card mb-6">
                    <div class="border-b border-base-200 px-5 py-4">
                        <h5 class="font-semibold"><i class="bi bi-clock-history mr-2"></i>Approval History</h5>
                    </div>
                    <div class="p-5">
                        <?php foreach ($approvals as $approval): ?>
                            <div class="mb-3 flex gap-3">
                                <div>
                                    <span
                                        class="loka-badge <?= $approval->status === 'approved' ? 'badge-success' : 'badge-error' ?> rounded-full p-2">
                                        <i class="bi bi-<?= $approval->status === 'approved' ? 'check' : 'x' ?>-lg"></i>
                                    </span>
                                </div>
                                <div>
                                    <div class="font-medium">
                                        <?= e($approval->approver_name) ?>
                                        <span class="<?= $approval->status === 'approved' ? 'text-success' : 'text-error' ?>">
                                            <?= ucfirst($approval->status) ?>
                                        </span>
                                        (<?= ucfirst($approval->approval_type) ?>)
                                    </div>
                                    <div class="text-sm text-base-content/60"><?= formatDateTime($approval->created_at) ?></div>
                                    <?php if ($approval->comments): ?>
                                        <div class="mt-1 italic">"<?= e($approval->comments) ?>"</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Approval Action Form -->
            <?php if ($canApprove): ?>
                <div class="loka-card mb-6 border border-primary/30">
                    <div class="border-b border-primary/20 bg-primary/5 px-5 py-4">
                        <h5 class="font-semibold text-primary"><i class="bi bi-check-circle mr-2"></i>Take Action</h5>
                    </div>
                    <div class="p-5">
                        <form method="POST" id="approvalForm" action="<?= APP_URL ?>/?page=approvals&action=process">
                            <?= csrfField() ?>
                            <input type="hidden" name="request_id" value="<?= $requestId ?>">
                            <input type="hidden" name="approval_type" value="<?= $approvalType ?>">

                            <?php if ($approvalType === 'motorpool'): ?>
                                <!-- Vehicle/Driver Assignment (Only for Approval) -->
                                <div id="assignmentSection">
                                    <!-- Requested Preferences Section -->
                                    <?php
                                    $hasRequestedDriver = $requestedDriver && $request->requested_driver_id;
                                    $hasRequestedVehicle = $requestedVehicle && $request->vehicle_id;
                                    $recommendedVehicle = null;
                                    
                                    if ($request->passenger_count <= 4) {
                                        $recommendedVehicle = 'Sedan or Hatchback (4-seater)';
                                    } elseif ($request->passenger_count <= 7) {
                                        $recommendedVehicle = 'SUV or Van (7-seater)';
                                    } elseif ($request->passenger_count <= 15) {
                                        $recommendedVehicle = 'Mini Bus (15-seater)';
                                    } else {
                                        $recommendedVehicle = 'Bus or Large Vehicle';
                                    }
                                    ?>
                                    
                                    <?php if ($hasRequestedDriver || $hasRequestedVehicle || $recommendedVehicle): ?>
                                        <div class="mb-4 rounded-xl border border-info bg-info/10 p-4">
                                            <h6 class="mb-2 font-semibold">
                                                <i class="bi bi-info-circle mr-1"></i>Requested Preferences
                                            </h6>

                                            <?php if ($hasRequestedVehicle): ?>
                                                <div class="mb-2">
                                                    <strong>Requested Vehicle:</strong>
                                                    <span class="loka-badge badge-primary ml-1">
                                                        <i class="bi bi-car-front mr-1"></i><?= e($requestedVehicle->plate_number) ?> - <?= e($requestedVehicle->make . ' ' . $requestedVehicle->model) ?>
                                                    </span>
                                                    <span class="text-sm text-base-content/60">(will be auto-selected)</span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($hasRequestedDriver): ?>
                                                <div class="mb-2">
                                                    <strong>Requested Driver:</strong>
                                                    <span class="loka-badge badge-primary ml-1">
                                                        <i class="bi bi-person-badge mr-1"></i><?= e($requestedDriver->name) ?>
                                                    </span>
                                                    <span class="text-sm text-base-content/60">(will be auto-selected)</span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($recommendedVehicle): ?>
                                                <div>
                                                    <strong>Recommended Vehicle:</strong>
                                                    <span class="loka-badge badge-success ml-1">
                                                        <i class="bi bi-truck mr-1"></i><?= $recommendedVehicle ?>
                                                    </span>
                                                    <span class="text-sm text-base-content/60">(for <?= $request->passenger_count ?> passenger<?= $request->passenger_count > 1 ? 's' : '' ?>)</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Conflict Status Dashboard -->
                                    <div id="conflictDashboard" class="mb-6 rounded-xl border <?= $hasConflicts ? 'border-warning' : 'border-success' ?>">
                                        <div class="flex items-center justify-between <?= $hasConflicts ? 'bg-warning text-white' : 'bg-success text-white' ?> rounded-t-xl px-5 py-3">
                                            <h6 class="font-semibold">
                                                <i class="bi bi-<?= $hasConflicts ? 'exclamation-triangle' : 'check-circle' ?> mr-1"></i>
                                                Conflict Status
                                            </h6>
                                            <?php if ($hasConflicts): ?>
                                                <span class="loka-badge badge-dark rounded-full" id="conflictCountBadge">
                                                    <?= $totalConflicts ?> conflict<?= $totalConflicts > 1 ? 's' : '' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="bg-base-100 p-5">
                                            <!-- Status Badges Row -->
                                            <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <!-- Vehicle Status -->
                                                <div>
                                                    <div class="flex items-center rounded-xl border border-base-300 bg-base-200/50 p-3">
                                                        <div class="mr-3">
                                                            <span class="loka-badge <?= $vehicleConflictSeverity === 'none' ? 'badge-success' : ($vehicleConflictSeverity === 'minor' ? 'badge-warning' : 'badge-error') ?> rounded-full p-2 text-xl" id="vehicleStatusBadge">
                                                                <i class="bi bi-<?= $vehicleConflictSeverity === 'none' ? 'check-lg' : 'exclamation-lg' ?>"></i>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm text-base-content/60">Vehicle Assignment</div>
                                                            <div class="font-bold">
                                                                <?= $vehicleConflictSeverity === 'none' ? 'Available (no conflicts)' : ($vehicleConflictSeverity === 'minor' ? 'Minor Conflict' : 'Major Conflict') ?>
                                                            </div>
                                                            <?php if ($vehicleConflictSeverity !== 'none'): ?>
                                                                <div class="text-sm text-error"><?= $vehicleConflicts ?> overlapping trip<?= $vehicleConflicts > 1 ? 's' : '' ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Driver Status -->
                                                <div>
                                                    <div class="flex items-center rounded-xl border border-base-300 bg-base-200/50 p-3">
                                                        <div class="mr-3">
                                                            <span class="loka-badge <?= $driverConflictSeverity === 'none' ? 'badge-success' : ($driverConflictSeverity === 'minor' ? 'badge-warning' : 'badge-error') ?> rounded-full p-2 text-xl" id="driverStatusBadge">
                                                                <i class="bi bi-<?= $driverConflictSeverity === 'none' ? 'check-lg' : 'exclamation-lg' ?>"></i>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm text-base-content/60">Driver Assignment</div>
                                                            <div class="font-bold">
                                                                <?= $driverConflictSeverity === 'none' ? 'Available (no conflicts)' : ($driverConflictSeverity === 'minor' ? 'Minor Conflict' : 'Major Conflict') ?>
                                                            </div>
                                                            <?php if ($driverConflictSeverity !== 'none'): ?>
                                                                <div class="text-sm text-error"><?= $driverConflicts ?> overlapping trip<?= $driverConflicts > 1 ? 's' : '' ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Conflict Details (Collapsible) -->
                                            <?php if ($hasConflicts): ?>
                                                <details class="rounded-xl border border-base-300">
                                                    <summary class="cursor-pointer px-5 py-3 font-medium hover:bg-base-200">
                                                        <i class="bi bi-chevron-down mr-2"></i>
                                                        View Conflict Details
                                                    </summary>
                                                    <div class="border-t border-base-200 p-5">
                                                        <!-- Vehicle Conflicts -->
                                                        <?php if (!empty($vehicleConflictsList)): ?>
                                                            <h6 class="mt-0 font-semibold text-warning"><i class="bi bi-car-front mr-1"></i>Vehicle Conflicts</h6>
                                                            <?php foreach ($vehicleConflictsList as $conflict): ?>
                                                                <div class="mb-2 rounded-xl border border-warning bg-warning/10 p-3">
                                                                    <strong>Request #<?= $conflict['id'] ?></strong>
                                                                    <div class="text-sm">
                                                                        <i class="bi bi-person mr-1"></i><?= e($conflict['requester_name']) ?>
                                                                        <i class="bi bi-geo-alt ml-2 mr-1"></i><?= e($conflict['destination']) ?>
                                                                    </div>
                                                                    <div class="text-sm">
                                                                        <i class="bi bi-clock mr-1"></i>
                                                                        <?= formatDateTime($conflict['start_datetime']) ?> - <?= formatDateTime($conflict['end_datetime']) ?>
                                                                    </div>
                                                                    <div class="text-sm text-error">
                                                                        <i class="bi bi-exclamation-triangle mr-1"></i>
                                                                        Overlap: <?= calculateOverlapMinutes($conflict, $request->start_datetime, $request->end_datetime) ?> minutes
                                                                    </div>
                                                                    <a href="<?= APP_URL ?>/?page=approvals&action=view&id=<?= $conflict['id'] ?>"
                                                                       class="loka-btn-outline-primary loka-btn-sm mt-2" target="_blank">
                                                                        <i class="bi bi-arrow-up-right-square mr-1"></i>View Request #<?= $conflict['id'] ?>
                                                                    </a>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>

                                                        <!-- Driver Conflicts -->
                                                        <?php if (!empty($driverConflictsList)): ?>
                                                            <h6 class="mt-3 font-semibold text-warning"><i class="bi bi-person-badge mr-1"></i>Driver Conflicts</h6>
                                                            <?php foreach ($driverConflictsList as $conflict): ?>
                                                                <div class="mb-2 rounded-xl border border-warning bg-warning/10 p-3">
                                                                    <strong>Request #<?= $conflict['id'] ?></strong>
                                                                    <div class="text-sm">
                                                                        <i class="bi bi-person mr-1"></i><?= e($conflict['requester_name']) ?>
                                                                        <i class="bi bi-geo-alt ml-2 mr-1"></i><?= e($conflict['destination']) ?>
                                                                    </div>
                                                                    <div class="text-sm">
                                                                        <i class="bi bi-clock mr-1"></i>
                                                                        <?= formatDateTime($conflict['start_datetime']) ?> - <?= formatDateTime($conflict['end_datetime']) ?>
                                                                    </div>
                                                                    <div class="text-sm text-error">
                                                                        <i class="bi bi-exclamation-triangle mr-1"></i>
                                                                        Overlap: <?= calculateOverlapMinutes($conflict, $request->start_datetime, $request->end_datetime) ?> minutes
                                                                    </div>
                                                                    <a href="<?= APP_URL ?>/?page=approvals&action=view&id=<?= $conflict['id'] ?>"
                                                                       class="loka-btn-outline-primary loka-btn-sm mt-2" target="_blank">
                                                                        <i class="bi bi-arrow-up-right-square mr-1"></i>View Request #<?= $conflict['id'] ?>
                                                                    </a>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </details>

                                                <!-- Override Confirmation -->
                                                <div id="overrideConfirmation" class="mt-3 rounded-xl border border-error bg-error/10 p-4">
                                                    <h6 class="mb-2 font-semibold">
                                                        <i class="bi bi-exclamation-octagon mr-1"></i>Override Confirmation Required
                                                    </h6>
                                                    <div class="mb-2 flex items-center gap-2">
                                                        <input type="checkbox" class="checkbox checkbox-error checkbox-sm" id="confirmOverride" name="override_conflict" value="1">
                                                        <label class="font-bold" for="confirmOverride">
                                                            I want to proceed with these conflicts:
                                                        </label>
                                                    </div>
                                                    <ul class="mb-0 ml-6 list-decimal">
                                                        <?php foreach ($allConflictsList as $conflict): ?>
                                                            <li>
                                                                <strong>Request #<?= $conflict['id'] ?></strong>:
                                                                <?= e($conflict['requester_name']) ?> to <?= e($conflict['destination']) ?>
                                                                (<?= calculateOverlapMinutes($conflict, $request->start_datetime, $request->end_datetime) ?> min overlap)
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Vehicle Selection -->
                                    <div class="mb-3">
                                        <label for="vehicle_id" class="mb-1 text-sm font-medium">
                                            Assign Vehicle <span class="text-error">*</span>
                                        </label>
                                        <select class="select select-bordered w-full" id="vehicle_id" name="vehicle_id">
                                            <option value="">Select a vehicle...</option>
                                            <?php foreach ($availableVehicles as $vehicle): ?>
                                                <option value="<?= $vehicle->id ?>"
                                                    data-mileage="<?= $vehicle->mileage ?>"
                                                    <?= $hasRequestedVehicle && $vehicle->id == $request->vehicle_id ? 'selected' : '' ?>>
                                                    <?= e($vehicle->plate_number) ?> - <?= e($vehicle->make . ' ' . $vehicle->model) ?>
                                                    (<?= e($vehicle->type_name) ?>, <?= $vehicle->passenger_capacity ?> seats)
                                                    <?= $vehicle->status !== 'available' ? '[' . strtoupper($vehicle->status) . ']' : '' ?>
                                                    <?= $hasRequestedVehicle && $vehicle->id == $request->vehicle_id ? ' <i class="bi bi-check-circle-fill text-success ml-1"></i> (Requested)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="vehicleConflictAlert" class="mt-2 rounded-xl border border-warning bg-warning/10 p-2 text-sm hidden">
                                            <i class="bi bi-exclamation-triangle mr-1"></i>
                                            <span class="message"></span>
                                        </div>
                                        <?php if (empty($availableVehicles)): ?>
                                            <div class="mt-1 text-sm text-error">No vehicles available</div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Mileage Start (Optional) -->
                                    <div class="mb-3">
                                        <label for="mileage_start" class="mb-1 text-sm font-medium">Starting Mileage (Optional)</label>
                                        <input type="number" class="input input-bordered w-full" id="mileage_start" name="mileage_start"
                                               min="0" placeholder="Current odometer reading (optional)">
                                        <div class="mt-1 text-xs text-base-content/60">
                                            <i class="bi bi-info-circle mr-1"></i>
                                            Leave blank to skip mileage tracking. If entered, must be >= vehicle's current mileage.
                                            <span id="currentMileageDisplay"></span>
                                        </div>
                                    </div>

                                    <!-- Driver Selection -->
                                    <div class="mb-3">
                                        <label for="driver_id" class="mb-1 text-sm font-medium">
                                            Assign Driver <span class="text-error">*</span>
                                        </label>
                                        <select class="select select-bordered w-full" id="driver_id" name="driver_id">
                                            <option value="">Select a driver...</option>
                                            <?php foreach ($availableDrivers as $driver): ?>
                                                <option value="<?= $driver->id ?>" 
                                                    <?= $hasRequestedDriver && $driver->id == $request->requested_driver_id ? 'selected' : '' ?>>
                                                    <?= e($driver->driver_name) ?> - <?= e($driver->license_number) ?>
                                                    (<?= $driver->years_experience ?> yrs exp)
                                                    <?= $driver->status !== 'available' ? '[' . strtoupper($driver->status) . ']' : '' ?>
                                                    <?= $hasRequestedDriver && $driver->id == $request->requested_driver_id ? ' <i class="bi bi-check-circle-fill text-success ml-1"></i> (Requested)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="driverConflictAlert" class="mt-2 rounded-xl border border-warning bg-warning/10 p-2 text-sm hidden">
                                            <i class="bi bi-exclamation-triangle mr-1"></i>
                                            <span class="message"></span>
                                        </div>
                                    </div>

                                    <div id="overrideConfirm" class="mb-3 flex items-center gap-2 hidden">
                                        <input type="checkbox" class="checkbox checkbox-error checkbox-sm" id="confirmOverride"
                                            name="override_conflict" value="1">
                                        <label class="text-sm font-bold text-error" for="confirmOverride">
                                            I confirm this assignment despite schedule conflicts.
                                        </label>
                                    </div>
                                    <?php if (empty($availableDrivers)): ?>
                                        <div class="text-sm text-error">No drivers available</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                    <!-- Comments -->
                    <div class="mb-4">
                        <label for="comments" class="mb-1 text-sm font-medium">
                            Comments 
                            <span class="text-error" id="commentsRequired">*</span>
                            <span class="text-xs text-base-content/60" id="commentsOptional">(Optional for approval)</span>
                        </label>
                        <textarea class="textarea textarea-bordered w-full" id="comments" name="comments" rows="3"
                            placeholder="Enter your comments or remarks..." maxlength="500"></textarea>
                        <div class="mt-1 text-sm text-error hidden" id="commentsFeedback">Comments are required when rejecting or requesting revision.</div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-wrap gap-2">
                            <input type="hidden" name="approval_action" id="approvalActionInput" value="">
                            <button type="button" id="approveBtn" class="bg-success text-success-content hover:bg-success/90 px-4 py-2 text-sm font-medium rounded-xl inline-flex items-center gap-2 transition-colors" data-action="approve">
                            <span class="btn-text">
                                <i class="bi bi-check-lg mr-1"></i>Approve
                            </span>
                            <span class="btn-loading hidden">
                                <span class="loading loading-spinner loading-sm mr-1" role="status" aria-hidden="true"></span>
                                Processing...
                            </span>
                        </button>
                            <button type="button" id="revisionBtn" class="bg-warning text-warning-content hover:bg-warning/90 px-4 py-2 text-sm font-medium rounded-xl inline-flex items-center gap-2 transition-colors" data-action="revision">
                            <span class="btn-text">
                                <i class="bi bi-arrow-repeat mr-1"></i>Request Revision
                            </span>
                            <span class="btn-loading hidden">
                                <span class="loading loading-spinner loading-sm mr-1" role="status" aria-hidden="true"></span>
                                Processing...
                            </span>
                        </button>
                            <button type="button" id="rejectBtn" class="bg-error text-error-content hover:bg-error/90 px-4 py-2 text-sm font-medium rounded-xl inline-flex items-center gap-2 transition-colors" data-action="reject">
                            <span class="btn-text">
                                <i class="bi bi-x-lg mr-1"></i>Reject
                            </span>
                            <span class="btn-loading hidden">
                                <span class="loading loading-spinner loading-sm mr-1" role="status" aria-hidden="true"></span>
                                Processing...
                            </span>
                        </button>
                        <a href="<?= APP_URL ?>/?page=approvals" class="loka-btn-secondary">Cancel</a>
                    </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="rounded-xl border border-info bg-info/10 p-4">
                <div class="flex items-center gap-2">
                    <i class="bi bi-info-circle text-lg"></i>
                    <div>
                        <?php if ($request->status === STATUS_APPROVED): ?>
                            This request has already been fully approved.
                        <?php elseif ($request->status === STATUS_REJECTED): ?>
                            This request has been rejected.
                        <?php elseif ($request->status === STATUS_CANCELLED): ?>
                            This request was cancelled by the requester.
                        <?php else: ?>
                            You cannot take action on this request at this time.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="lg:col-span-4">
        <div class="loka-card">
            <div class="border-b border-base-200 px-5 py-4">
                <h6 class="font-semibold"><i class="bi bi-info-circle mr-2"></i>Quick Info</h6>
            </div>
            <div class="p-5">
                <div class="mb-3">
                    <div class="text-xs text-base-content/60">Request ID</div>
                    <div class="font-bold">#<?= $requestId ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-xs text-base-content/60">Submitted</div>
                    <div><?= formatDateTime($request->created_at) ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-xs text-base-content/60">Duration</div>
                    <div>
                        <?php
                        $start = new DateTime($request->start_datetime);
                        $end = new DateTime($request->end_datetime);
                        $diff = $start->diff($end);
                        if ($diff->days > 0) {
                            echo $diff->days . ' day(s) ' . $diff->h . ' hour(s)';
                        } else {
                            echo $diff->h . ' hour(s) ' . $diff->i . ' min(s)';
                        }
                        ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-base-content/60">Contact</div>
                    <div><?= e($request->requester_phone ?: 'N/A') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const vehicleSelect = document.getElementById('vehicle_id');
        const driverSelect = document.getElementById('driver_id');
        const vAlert = document.getElementById('vehicleConflictAlert');
        const dAlert = document.getElementById('driverConflictAlert');
        const overrideBox = document.getElementById('overrideConfirm');
        const approveBtn = document.getElementById('approveBtn');
        const rejectBtn = document.getElementById('rejectBtn');
        const revisionBtn = document.getElementById('revisionBtn');
        const requestedConflict = document.getElementById('requestedDriverConflict');
        const approvalForm = document.getElementById('approvalForm');

        const start = '<?= $request->start_datetime ?>';
        const end = '<?= $request->end_datetime ?>';
        const requestId = '<?= $request->id ?>';

        function check(type, id, alertEl) {
            if (!id) {
                alertEl.classList.add('hidden');
                updateConflictDashboardItem(type, {conflict: false});
                return;
            }

            fetch(`<?= APP_URL ?>/?page=api&action=check_conflict&type=${type}&id=${id}&start=${start}&end=${end}&exclude_id=${requestId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.conflict && data.conflicts.length > 0) {
                        const conflict = data.conflicts[0];
                        const overlapBadge = `<span class="loka-badge badge-${getSeverityColor(data.severity)} ml-2">${data.severity.toUpperCase()} (${data.overlap_minutes}min)</span>`;

                        alertEl.querySelector('.message').innerHTML = `
                            <strong>Conflict with Request #${conflict.id}</strong>${overlapBadge}<br>
                            <small class="text-base-content/60">${conflict.requester_name} → ${conflict.destination}</small>
                            <br>
                            <small class="text-base-content/60">${data.start_datetime} - ${data.end_datetime}</small>
                            <br>
                            <a href="<?= APP_URL ?>/?page=approvals&action=view&id=${conflict.id}"
                               target="_blank"
                               class="text-sm text-primary">
                               <i class="bi bi-arrow-up-right-square mr-1"></i>View Request
                            </a>
                        `;
                        alertEl.classList.remove('hidden');

                        // Update conflict dashboard
                        updateConflictDashboardItem(type, data);
                    } else {
                        alertEl.classList.add('hidden');
                        updateConflictDashboardItem(type, {conflict: false});
                    }
                    updateUI();
                });
        }

        function getSeverityColor(severity) {
            switch(severity) {
                case 'minor': return 'warning';
                case 'moderate': return 'warning';
                case 'severe': return 'error';
                default: return 'base-300';
            }
        }

        function getStatusTextEl(badgeEl) {
            // Badge is inside a flex container; navigate to sibling div > .font-bold
            const container = badgeEl.closest('.flex.items-center');
            return container ? container.querySelector('.font-bold') : null;
        }

        function updateConflictDashboardItem(type, data) {
            const vehicleBadge = document.getElementById('vehicleStatusBadge');
            const driverBadge = document.getElementById('driverStatusBadge');

            if (type === 'vehicle' && vehicleBadge) {
                if (data.conflict) {
                    vehicleBadge.className = `badge badge-${getSeverityColor(data.severity)} rounded-full p-2 text-lg`;
                    vehicleBadge.innerHTML = '<i class="bi bi-exclamation-lg"></i>';
                    const vehicleStatusText = getStatusTextEl(vehicleBadge);
                    if (vehicleStatusText) {
                        vehicleStatusText.textContent = data.severity === 'minor' ? 'Minor Conflict' : 'Major Conflict';
                        vehicleStatusText.className = 'font-bold text-error';
                    }
                } else {
                    vehicleBadge.className = 'badge badge-success rounded-full p-2 text-lg';
                    vehicleBadge.innerHTML = '<i class="bi bi-check-lg"></i>';
                    const vehicleStatusText = getStatusTextEl(vehicleBadge);
                    if (vehicleStatusText) {
                        vehicleStatusText.textContent = 'Available (no conflicts)';
                        vehicleStatusText.className = 'font-bold';
                    }
                }
            }

            if (type === 'driver' && driverBadge) {
                if (data.conflict) {
                    driverBadge.className = `badge badge-${getSeverityColor(data.severity)} rounded-full p-2 text-lg`;
                    driverBadge.innerHTML = '<i class="bi bi-exclamation-lg"></i>';
                    const driverStatusText = getStatusTextEl(driverBadge);
                    if (driverStatusText) {
                        driverStatusText.textContent = data.severity === 'minor' ? 'Minor Conflict' : 'Major Conflict';
                        driverStatusText.className = 'font-bold text-error';
                    }
                } else {
                    driverBadge.className = 'badge badge-success rounded-full p-2 text-lg';
                    driverBadge.innerHTML = '<i class="bi bi-check-lg"></i>';
                    const driverStatusText = getStatusTextEl(driverBadge);
                    if (driverStatusText) {
                        driverStatusText.textContent = 'Available (no conflicts)';
                        driverStatusText.className = 'font-bold';
                    }
                }
            }

            // Update conflict count
            const vConflict = vehicleBadge && !vehicleBadge.classList.contains('badge-success');
            const dConflict = driverBadge && !driverBadge.classList.contains('badge-success');
            const totalConflicts = (vConflict ? 1 : 0) + (dConflict ? 1 : 0);

            const countBadge = document.getElementById('conflictCountBadge');
            if (countBadge) {
                if (totalConflicts > 0) {
                    countBadge.textContent = `${totalConflicts} conflict${totalConflicts > 1 ? 's' : ''}`;
                    countBadge.classList.remove('hidden');
                } else {
                    countBadge.classList.add('hidden');
                }
            }

            // Toggle override confirmation
            const overrideSection = document.getElementById('overrideConfirmation');
            const conflictDashboard = document.getElementById('conflictDashboard');
            const conflictHeader = conflictDashboard ? conflictDashboard.querySelector('div') : null;
            if (overrideSection && conflictDashboard) {
                if (totalConflicts > 0) {
                    overrideSection.classList.remove('hidden');
                    conflictDashboard.className = conflictDashboard.className.replace(/border-\w+/, 'border-warning');
                    if (conflictHeader) {
                        conflictHeader.className = conflictHeader.className.replace(/bg-\w+/, 'bg-warning');
                    }
                } else {
                    overrideSection.classList.add('hidden');
                    conflictDashboard.className = conflictDashboard.className.replace(/border-\w+/, 'border-success');
                    if (conflictHeader) {
                        conflictHeader.className = conflictHeader.className.replace(/bg-\w+/, 'bg-success');
                    }
                }
            }
        }

        function updateUI() {
            const hasConflict = !vAlert.classList.contains('hidden') || !dAlert.classList.contains('hidden');
            if (hasConflict) {
                overrideBox.classList.remove('hidden');
                if (approveBtn) {
                    approveBtn.disabled = !document.getElementById('confirmOverride').checked;
                }
            } else {
                overrideBox.classList.add('hidden');
                if (approveBtn) {
                    approveBtn.disabled = false;
                }
            }
        }

        if (vehicleSelect) vehicleSelect.addEventListener('change', () => check('vehicle', vehicleSelect.value, vAlert));
        if (driverSelect) driverSelect.addEventListener('change', () => check('driver', driverSelect.value, dAlert));
        if (overrideBox) document.getElementById('confirmOverride').addEventListener('change', updateUI);

        // Handle vehicle mileage display
        if (vehicleSelect) {
            const updateMileageDisplay = () => {
                const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
                const mileageDisplay = document.getElementById('currentMileageDisplay');
                const mileageInput = document.getElementById('mileage_start');
                if (selectedOption && selectedOption.value) {
                    const currentMileage = selectedOption.getAttribute('data-mileage');
                    if (mileageDisplay && currentMileage) {
                        mileageDisplay.innerHTML = `<br>Vehicle current mileage: <strong>${currentMileage} km</strong>`;
                    }
                    if (mileageInput && currentMileage) {
                        mileageInput.min = currentMileage;
                        mileageInput.placeholder = `Minimum: ${currentMileage} km`;
                    }
                } else {
                    if (mileageDisplay) mileageDisplay.innerHTML = '';
                    if (mileageInput) {
                        mileageInput.min = 0;
                        mileageInput.placeholder = 'Current odometer reading (optional)';
                    }
                }
            };
            vehicleSelect.addEventListener('change', updateMileageDisplay);
            // Initial call for pre-selected vehicle
            updateMileageDisplay();
        }

        // Initial check for requested driver (Approver view info)
        <?php if ($request->requested_driver_id): ?>
            fetch(`<?= APP_URL ?>/?page=api&action=check_conflict&type=driver&id=<?= $request->requested_driver_id ?>&start=${start}&end=${end}&exclude_id=${requestId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.conflict) requestedConflict.classList.remove('hidden');
                });
        <?php endif; ?>
        
        // Set initial state - show assignment fields by default (for approval)
        toggleAssignmentFields('approve');

        // Toggle assignment fields based on action
        function toggleAssignmentFields(action) {
            const assignmentSection = document.getElementById('assignmentSection');
            const commentsField = document.getElementById('comments');
            const commentsRequired = document.getElementById('commentsRequired');
            const commentsOptional = document.getElementById('commentsOptional');
            const vehicleSelect = document.getElementById('vehicle_id');
            const driverSelect = document.getElementById('driver_id');
            const approvalType = '<?= $approvalType ?>';
            
            if (action === 'reject' || action === 'revision') {
                // Hide assignment section for rejection/revision
                if (assignmentSection) {
                    assignmentSection.style.display = 'none';
                }
                // Make comments required for rejection/revision
                if (commentsField) {
                    commentsField.setAttribute('required', 'required');
                    commentsField.placeholder = action === 'revision' 
                        ? 'Please explain what needs to be revised (required)...'
                        : 'Please provide a reason for rejection (required)...';
                    commentsField.classList.remove('input-error');
                }
                if (commentsRequired) commentsRequired.style.display = 'inline';
                if (commentsOptional) commentsOptional.style.display = 'none';
                // Remove required from vehicle/driver
                if (vehicleSelect) {
                    vehicleSelect.removeAttribute('required');
                    vehicleSelect.classList.remove('input-error');
                }
                if (driverSelect) {
                    driverSelect.removeAttribute('required');
                    driverSelect.classList.remove('input-error');
                }
            } else {
                // Show assignment section for approval
                if (assignmentSection) {
                    assignmentSection.style.display = 'block';
                }
                // Make comments optional for approval
                if (commentsField) {
                    commentsField.removeAttribute('required');
                    commentsField.placeholder = 'Optional comments...';
                    commentsField.classList.remove('input-error');
                }
                if (commentsRequired) commentsRequired.style.display = 'none';
                if (commentsOptional) commentsOptional.style.display = 'inline';
                // Add required to vehicle/driver for motorpool approval only
                if (approvalType === 'motorpool') {
                    if (vehicleSelect) {
                        vehicleSelect.setAttribute('required', 'required');
                    }
                    if (driverSelect) {
                        driverSelect.setAttribute('required', 'required');
                    }
                }
            }
        }
        
        let isSubmitting = false;

        function setActionLoading(submitBtn, loading) {
            [approveBtn, rejectBtn, revisionBtn].forEach((btn) => {
                if (!btn) return;
                btn.disabled = loading;
                const text = btn.querySelector('.btn-text');
                const spin = btn.querySelector('.btn-loading');
                if (btn === submitBtn) {
                    if (text) text.classList.toggle('hidden', loading);
                    if (spin) spin.classList.toggle('hidden', !loading);
                } else {
                    if (text) text.classList.remove('hidden');
                    if (spin) spin.classList.add('hidden');
                }
            });
        }

        function validateAndSubmit(action) {
            if (!approvalForm || isSubmitting) return;

            const actionInput = document.getElementById('approvalActionInput');
            if (actionInput) actionInput.value = action;

            const formData = new FormData(approvalForm);
            formData.set('approval_action', action);

            const submitBtn = action === 'approve' ? approveBtn : (action === 'revision' ? revisionBtn : rejectBtn);
            if (!submitBtn) return;

            const comments = (formData.get('comments') || '').toString().trim();

            if ((action === 'reject' || action === 'revision') && !comments) {
                const msg = action === 'revision'
                    ? 'Please explain what needs to be revised.'
                    : 'Please provide a reason for rejection.';
                if (typeof showToast === 'function') showToast(msg, 'warning');
                else alert(msg);
                const commentsEl = document.getElementById('comments');
                if (commentsEl) {
                    commentsEl.classList.add('input-error');
                    commentsEl.focus();
                }
                return;
            }

            const approvalType = '<?= $approvalType ?>';
            if (action === 'approve' && approvalType === 'motorpool') {
                const vehicleId = formData.get('vehicle_id');
                const driverId = formData.get('driver_id');
                if (!vehicleId || !driverId) {
                    if (typeof showToast === 'function') {
                        showToast('Please select both a vehicle and driver for approval.', 'warning');
                    } else {
                        alert('Please select both a vehicle and driver for approval.');
                    }
                    if (!vehicleId && vehicleSelect) vehicleSelect.classList.add('input-error');
                    if (!driverId && driverSelect) driverSelect.classList.add('input-error');
                    return;
                }
            }

            document.querySelectorAll('.input-error').forEach((el) => el.classList.remove('input-error'));

            isSubmitting = true;
            setActionLoading(submitBtn, true);
            formData.append('ajax', '1');

            const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            const timeoutId = controller
                ? setTimeout(() => controller.abort(), 25000)
                : null;

            fetch(approvalForm.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(async (response) => {
                const contentType = response.headers.get('content-type') || '';
                const raw = await response.text();
                let data = null;
                if (contentType.includes('application/json')) {
                    try { data = JSON.parse(raw); } catch (e) { data = null; }
                } else if (raw && raw.trim().startsWith('{')) {
                    try { data = JSON.parse(raw); } catch (e) { data = null; }
                }

                if (!response.ok) {
                    const msg = (data && data.message)
                        ? data.message
                        : (raw && raw.length < 200 ? raw : 'Request failed. Please refresh and try again.');
                    throw new Error(msg);
                }

                return data;
            })
            .then((data) => {
                const success = data && typeof data === 'object' ? !!data.success : true;
                const message = data && typeof data === 'object' && data.message
                    ? data.message
                    : (action === 'approve'
                        ? 'Request approved successfully!'
                        : (action === 'revision' ? 'Request sent for revision.' : 'Request rejected.'));

                if (!success) {
                    throw new Error(data && data.message ? data.message : 'An error occurred. Please try again.');
                }

                if (typeof showToast === 'function') showToast(message, 'success');
                window.location.href = '<?= APP_URL ?>/?page=approvals&tab=processed&p_processed=1';
            })
            .catch((error) => {
                console.error('Approval action error:', error);
                const msg = (error && error.name === 'AbortError')
                    ? 'The server took too long. Refresh the page — the action may have completed.'
                    : (error && error.message ? error.message : 'An error occurred. Please try again.');
                if (typeof showToast === 'function') showToast(msg, 'danger');
                else alert(msg);
                isSubmitting = false;
                setActionLoading(submitBtn, false);
            })
            .finally(() => {
                if (timeoutId) clearTimeout(timeoutId);
            });
        }

        if (approveBtn) {
            approveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                toggleAssignmentFields('approve');
                validateAndSubmit('approve');
            });
        }

        if (revisionBtn) {
            revisionBtn.addEventListener('click', function (e) {
                e.preventDefault();
                toggleAssignmentFields('revision');
                validateAndSubmit('revision');
            });
        }

        if (rejectBtn) {
            rejectBtn.addEventListener('click', function (e) {
                e.preventDefault();
                toggleAssignmentFields('reject');
                validateAndSubmit('reject');
            });
        }
    });
</script>