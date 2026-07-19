<?php
/**
 * LOKA - Vehicle History Report
 */

$driverScoped = isDriver() && !canAccessOpsReports();
if (!$driverScoped && !canAccessOpsReports()) {
    requireRole(ROLE_APPROVER);
}

$pageTitle = 'Vehicle History Report';
$myDriverId = currentDriverId();

$vehicleId = get('vehicle_id');
$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-t'));

// Vehicles for dropdown (drivers: only vehicles they have driven)
if ($driverScoped && $myDriverId) {
    $vehicles = db()->fetchAll(
        "SELECT DISTINCT v.id, v.plate_number, v.make, v.model, vt.name as type_name
         FROM vehicles v
         LEFT JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
         JOIN requests r ON r.vehicle_id = v.id AND r.deleted_at IS NULL
         WHERE v.deleted_at IS NULL
           AND (r.driver_id = ? OR r.requested_driver_id = ?)
         ORDER BY v.plate_number",
        [$myDriverId, $myDriverId]
    );
} else {
    $vehicles = db()->fetchAll(
        "SELECT v.id, v.plate_number, v.make, v.model, vt.name as type_name
         FROM vehicles v
         LEFT JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
         WHERE v.deleted_at IS NULL
         ORDER BY v.plate_number"
    );
}

// Get vehicle trip history
$trips = [];
$vehicleInfo = null;

if ($vehicleId) {
    // Drivers may only open vehicles they have driven
    if ($driverScoped && $myDriverId) {
        $allowed = false;
        foreach ($vehicles as $v) {
            if ((int) $v->id === (int) $vehicleId) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            redirectWith('/?page=reports&action=vehicle-history', 'danger', 'You can only view vehicles you have driven.');
        }
    }

    $vehicleInfo = db()->fetch(
        "SELECT v.*, vt.name as type_name, vt.passenger_capacity
         FROM vehicles v
         LEFT JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
         WHERE v.id = ? AND v.deleted_at IS NULL",
        [$vehicleId]
    );

    $tripSql = "SELECT r.id, r.start_datetime, r.end_datetime, r.purpose, r.destination,
                r.status, r.passenger_count, r.actual_dispatch_datetime, r.actual_arrival_datetime,
                u.name as requester_name, d.name as department_name,
                dr_user.name as driver_name,
                TIMESTAMPDIFF(MINUTE, r.start_datetime, r.end_datetime) as planned_duration,
                TIMESTAMPDIFF(MINUTE, r.actual_dispatch_datetime, r.actual_arrival_datetime) as actual_duration
         FROM requests r
         JOIN users u ON r.user_id = u.id
         LEFT JOIN departments d ON r.department_id = d.id
         LEFT JOIN drivers dr ON r.driver_id = dr.id
         LEFT JOIN users dr_user ON dr.user_id = dr_user.id
         WHERE r.vehicle_id = ?
         AND r.start_datetime BETWEEN ? AND ?
         AND r.deleted_at IS NULL";
    $tripParams = [$vehicleId, $startDate, $endDate . ' 23:59:59'];
    if ($driverScoped && $myDriverId) {
        $tripSql .= " AND (r.driver_id = ? OR r.requested_driver_id = ?)";
        $tripParams[] = $myDriverId;
        $tripParams[] = $myDriverId;
    }
    $tripSql .= " ORDER BY r.start_datetime DESC";
    $trips = db()->fetchAll($tripSql, $tripParams);
}

// Stats
$stats = (object)[
    'total_trips' => count($trips),
    'completed_trips' => 0,
    'total_hours' => 0,
    'total_km' => 0
];

if (!empty($trips)) {
    foreach ($trips as $t) {
        if ($t->status === 'completed') $stats->completed_trips++;
        if ($t->actual_duration) $stats->total_hours += $t->actual_duration / 60;
        elseif ($t->planned_duration) $stats->total_hours += $t->planned_duration / 60;
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
    <div class="mb-4">
        <h4 class="mb-1">Vehicle History Report</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=reports">Reports</a></li>
                <li class="breadcrumb-item active">Vehicle History</li>
            </ol>
        </nav>
    </div>

    <!-- Filters -->
    <div class="loka-card mb-4">
        <div class="loka-card-body">
            <form method="GET" class="grid grid-cols-12 gap-3 items-end">
                <input type="hidden" name="page" value="reports">
                <input type="hidden" name="action" value="vehicle-history">
                <div class="col-span-12 md:col-span-3">
                    <label class="loka-form-label">Vehicle</label>
                    <select class="loka-form-input" name="vehicle_id" required>
                        <option value="">Select Vehicle...</option>
                        <?php foreach ($vehicles as $v): ?>
                        <option value="<?= $v->id ?>" <?= $vehicleId == $v->id ? 'selected' : '' ?>>
                            <?= e($v->plate_number) ?> - <?= e($v->make . ' ' . $v->model) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-span-6 md:col-span-2">
                    <label class="loka-form-label">Start Date</label>
                    <input type="date" class="loka-form-input" name="start_date" value="<?= e($startDate) ?>">
                </div>
                <div class="col-span-6 md:col-span-2">
                    <label class="loka-form-label">End Date</label>
                    <input type="date" class="loka-form-input" name="end_date" value="<?= e($endDate) ?>">
                </div>
                <div class="col-span-12 md:col-span-2 flex items-end">
                    <button type="submit" class="loka-btn-primary">
                        <i class="bi bi-search me-1"></i>Generate
                    </button>
                </div>
                <?php if ($vehicleId && !empty($trips)): ?>
                <div class="col-span-12 md:col-span-3 flex flex-wrap justify-end items-end gap-2">
                    <a href="<?= APP_URL ?>/?page=reports&action=export-vehicle-csv&vehicle_id=<?= $vehicleId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="loka-btn-outline-primary">
                        <i class="bi bi-file-earmark-csv me-1"></i>CSV
                    </a>
                    <a href="<?= APP_URL ?>/?page=reports&action=export-vehicle-history&vehicle_id=<?= $vehicleId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="loka-btn-outline-error">
                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($vehicleInfo): ?>
    <!-- Vehicle Info -->
    <div class="grid grid-cols-12 gap-4 mb-4">
        <div class="col-span-12 md:col-span-4">
            <div class="loka-card h-full">
                <div class="loka-card-body">
                    <h6 class="text-base-content/60 mb-3">Vehicle Information</h6>
                    <h4 class="mb-1"><?= e($vehicleInfo->plate_number) ?></h4>
                    <p class="text-base-content/60 mb-2"><?= e($vehicleInfo->make . ' ' . $vehicleInfo->model) ?> (<?= e($vehicleInfo->year) ?>)</p>
                    <span class="loka-badge bg-primary"><?= e($vehicleInfo->type_name) ?></span>
                    <span class="loka-badge bg-<?= $vehicleInfo->status === 'available' ? 'success' : ($vehicleInfo->status === 'in_use' ? 'warning' : 'secondary') ?>">
                        <?= ucfirst($vehicleInfo->status) ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-8">
            <div class="grid grid-cols-12 gap-3">
                <div class="col-span-6 md:col-span-3">
                    <div class="loka-card bg-primary bg-opacity-10 h-full">
                        <div class="loka-card-body text-center">
                            <h3 class="text-primary mb-0"><?= $stats->total_trips ?></h3>
                            <small class="text-base-content/60">Total Trips</small>
                        </div>
                    </div>
                </div>
                <div class="col-span-6 md:col-span-3">
                    <div class="loka-card bg-success bg-opacity-10 h-full">
                        <div class="loka-card-body text-center">
                            <h3 class="text-success mb-0"><?= $stats->completed_trips ?></h3>
                            <small class="text-base-content/60">Completed</small>
                        </div>
                    </div>
                </div>
                <div class="col-span-6 md:col-span-3">
                    <div class="loka-card bg-info bg-opacity-10 h-full">
                        <div class="loka-card-body text-center">
                            <h3 class="text-info mb-0"><?= number_format($stats->total_hours, 1) ?>h</h3>
                            <small class="text-base-content/60">Total Hours</small>
                        </div>
                    </div>
                </div>
                <div class="col-span-6 md:col-span-3">
                    <div class="loka-card bg-warning bg-opacity-10 h-full">
                        <div class="loka-card-body text-center">
                            <h3 class="text-warning mb-0"><?= number_format($vehicleInfo->mileage ?? 0) ?></h3>
                            <small class="text-base-content/60">Current Mileage (km)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trip History Table -->
    <div class="loka-card">
        <div class="px-4 md:px-6 pt-4 md:pt-6">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Trip History</h5>
        </div>
        <div class="loka-card-body">
            <?php if (empty($trips)): ?>
            <div class="text-center py-4 text-base-content/60">
                <i class="bi bi-clipboard-x fs-1"></i>
                <p class="mt-2">No trips found for this vehicle in the selected period.</p>
            </div>
            <?php else: ?>
            <div class="loka-table-responsive">
                <table class="loka-table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date/Time</th>
                            <th>Destination</th>
                            <th>Purpose</th>
                            <th>Requester</th>
                            <th>Driver</th>
                            <th>Status</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): ?>
                        <tr>
                            <td>
                                <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $trip->id ?>">
                                    <strong>#<?= $trip->id ?></strong>
                                </a>
                            </td>
                            <td>
                                <?= formatDateTime($trip->start_datetime) ?>
                                <small class="text-base-content/60 block">to <?= formatDateTime($trip->end_datetime) ?></small>
                            </td>
                            <td><?= e($trip->destination) ?></td>
                            <td><?= e(strlen($trip->purpose) > 40 ? substr($trip->purpose, 0, 40) . '...' : $trip->purpose) ?></td>
                            <td>
                                <?= e($trip->requester_name) ?>
                                <small class="text-base-content/60 block"><?= e($trip->department_name) ?></small>
                            </td>
                            <td><?= e($trip->driver_name ?: '-') ?></td>
                            <td><?= requestStatusBadge($trip->status) ?></td>
                            <td>
                                <?php if ($trip->actual_duration): ?>
                                    <?= floor($trip->actual_duration / 60) ?>h <?= $trip->actual_duration % 60 ?>m
                                    <small class="text-success block">Actual</small>
                                <?php else: ?>
                                    <?= floor($trip->planned_duration / 60) ?>h <?= $trip->planned_duration % 60 ?>m
                                    <small class="text-base-content/60 block">Planned</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="loka-card">
        <div class="loka-card-body text-center py-5 text-base-content/60">
            <i class="bi bi-car-front fs-1"></i>
            <p class="mt-2">Select a vehicle to view its trip history.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
