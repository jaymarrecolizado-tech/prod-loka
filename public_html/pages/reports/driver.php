<?php
/**
 * LOKA - Driver Report
 */

requireRole(ROLE_APPROVER);

$pageTitle = 'Driver Report';

$driverId = get('driver_id');
$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-t'));

// Get all drivers for dropdown
$drivers = db()->fetchAll(
    "SELECT d.id, u.name, u.phone, d.license_number, d.status
     FROM drivers d
     JOIN users u ON d.user_id = u.id
     WHERE d.deleted_at IS NULL AND u.deleted_at IS NULL
     ORDER BY u.name"
);

// Get driver trip history
$trips = [];
$driverInfo = null;

if ($driverId) {
    $driverInfo = db()->fetch(
        "SELECT d.*, u.name, u.email, u.phone, u.department_id,
                dept.name as department_name
         FROM drivers d
         JOIN users u ON d.user_id = u.id
         LEFT JOIN departments dept ON u.department_id = dept.id
         WHERE d.id = ? AND d.deleted_at IS NULL",
        [$driverId]
    );
    
    $trips = db()->fetchAll(
        "SELECT r.id, r.control_number, r.start_datetime, r.end_datetime, r.purpose, r.destination,
                r.status, r.passenger_count, r.actual_dispatch_datetime, r.actual_arrival_datetime,
                u.name as requester_name, d.name as department_name,
                v.plate_number, v.make, v.model,
                TIMESTAMPDIFF(MINUTE, r.start_datetime, r.end_datetime) as planned_duration,
                TIMESTAMPDIFF(MINUTE, r.actual_dispatch_datetime, r.actual_arrival_datetime) as actual_duration
         FROM requests r
         JOIN users u ON r.user_id = u.id
         LEFT JOIN departments d ON r.department_id = d.id
         LEFT JOIN vehicles v ON r.vehicle_id = v.id
         WHERE r.driver_id = ? 
         AND r.start_datetime BETWEEN ? AND ?
         AND r.deleted_at IS NULL
         ORDER BY r.start_datetime DESC",
        [$driverId, $startDate, $endDate . ' 23:59:59']
    );
}

// Stats
$stats = (object)[
    'total_trips' => count($trips),
    'completed_trips' => 0,
    'total_hours' => 0,
    'unique_vehicles' => []
];

if (!empty($trips)) {
    foreach ($trips as $t) {
        if ($t->status === 'completed') $stats->completed_trips++;
        if ($t->actual_duration) $stats->total_hours += $t->actual_duration / 60;
        elseif ($t->planned_duration) $stats->total_hours += $t->planned_duration / 60;
        if ($t->plate_number) $stats->unique_vehicles[$t->plate_number] = true;
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h4 class="mb-1">Driver Report</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=reports">Reports</a></li>
                <li class="breadcrumb-item active">Driver Report</li>
            </ol>
        </nav>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="reports">
                <input type="hidden" name="action" value="driver">
                <div class="col-md-3">
                    <label class="form-label">Driver</label>
                    <select class="form-select" name="driver_id" required>
                        <option value="">Select Driver...</option>
                        <?php foreach ($drivers as $d): ?>
                        <option value="<?= $d->id ?>" <?= $driverId == $d->id ? 'selected' : '' ?>>
                            <?= e($d->name) ?> - <?= e($d->license_number ?: 'No License') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?= e($startDate) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?= e($endDate) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>Generate
                    </button>
                </div>
                <?php if ($driverId && !empty($trips)): ?>
                <div class="col-md-3 text-end">
                    <a href="<?= APP_URL ?>/?page=reports&action=export-driver&driver_id=<?= $driverId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-outline-danger">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($driverInfo): ?>
    <!-- Driver Info -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-3">Driver Information</h6>
                    <div class="d-flex align-items-center mb-3">
                        <div class="avatar-circle bg-primary text-white me-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <?= strtoupper(substr($driverInfo->name, 0, 1)) ?>
                        </div>
                        <div>
                            <h4 class="mb-0"><?= e($driverInfo->name) ?></h4>
                            <p class="text-muted mb-0"><?= e($driverInfo->phone ?: 'No phone') ?></p>
                        </div>
                    </div>
                    <hr>
                    <div class="row g-2">
                        <div class="col-6">
                            <small class="text-muted">License No.</small>
                            <p class="mb-0 fw-bold"><?= e($driverInfo->license_number ?: 'N/A') ?></p>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Status</small>
                            <p class="mb-0"><?= driverStatusBadge($driverInfo->status) ?></p>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">License Expiry</small>
                            <p class="mb-0 fw-bold"><?= $driverInfo->license_expiry ? formatDate($driverInfo->license_expiry) : 'N/A' ?></p>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Department</small>
                            <p class="mb-0 fw-bold"><?= e($driverInfo->department_name ?: 'N/A') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="card bg-primary bg-opacity-10 h-100">
                        <div class="card-body text-center">
                            <h3 class="text-primary mb-0"><?= $stats->total_trips ?></h3>
                            <small class="text-muted">Total Trips</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success bg-opacity-10 h-100">
                        <div class="card-body text-center">
                            <h3 class="text-success mb-0"><?= $stats->completed_trips ?></h3>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info bg-opacity-10 h-100">
                        <div class="card-body text-center">
                            <h3 class="text-info mb-0"><?= number_format($stats->total_hours, 1) ?>h</h3>
                            <small class="text-muted">Total Hours</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning bg-opacity-10 h-100">
                        <div class="card-body text-center">
                            <h3 class="text-warning mb-0"><?= count($stats->unique_vehicles) ?></h3>
                            <small class="text-muted">Vehicles Driven</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trip History Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Trip History</h5>
        </div>
        <div class="card-body">
            <?php if (empty($trips)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-clipboard-x fs-1"></i>
                <p class="mt-2">No trips found for this driver in the selected period.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date/Time</th>
                            <th>Vehicle</th>
                            <th>Destination</th>
                            <th>Requester</th>
                            <th>Passengers</th>
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
                                <small class="text-muted d-block">to <?= formatDateTime($trip->end_datetime) ?></small>
                            </td>
                            <td>
                                <strong><?= e($trip->plate_number ?: 'N/A') ?></strong>
                                <small class="text-muted d-block"><?= e($trip->make . ' ' . $trip->model) ?></small>
                            </td>
                            <td><?= e($trip->destination) ?></td>
                            <td>
                                <?= e($trip->requester_name) ?>
                                <small class="text-muted d-block"><?= e($trip->department_name) ?></small>
                            </td>
                            <td><?= $trip->passenger_count ?></td>
                            <td><?= requestStatusBadge($trip->status) ?></td>
                            <td>
                                <?php if ($trip->actual_duration): ?>
                                    <?= floor($trip->actual_duration / 60) ?>h <?= $trip->actual_duration % 60 ?>m
                                    <small class="text-success d-block">Actual</small>
                                <?php else: ?>
                                    <?= floor($trip->planned_duration / 60) ?>h <?= $trip->planned_duration % 60 ?>m
                                    <small class="text-muted d-block">Planned</small>
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
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-person fs-1"></i>
            <p class="mt-2">Select a driver to view their trip history.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
