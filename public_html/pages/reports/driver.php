<?php
/**
 * LOKA - Driver Report
 */

$driverScoped = isDriver() && !canAccessOpsReports();
if (!$driverScoped && !canAccessOpsReports()) {
    requireRole(ROLE_APPROVER);
}

$pageTitle = 'Driver Report';
$myDriverId = currentDriverId();
$searchQuery = listSearchQuery();
$perPage = resolvePerPage();

$driverId = get('driver_id');
$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-t'));

// Drivers only see themselves; ops see all
if ($driverScoped && $myDriverId) {
    $driverId = (string) $myDriverId;
    $drivers = db()->fetchAll(
        "SELECT d.id, u.name, u.phone, d.license_number, d.status
         FROM drivers d
         JOIN users u ON d.user_id = u.id
         WHERE d.id = ? AND d.deleted_at IS NULL AND u.deleted_at IS NULL",
        [$myDriverId]
    );
} else {
    $drivers = db()->fetchAll(
        "SELECT d.id, u.name, u.phone, d.license_number, d.status
         FROM drivers d
         JOIN users u ON d.user_id = u.id
         WHERE d.deleted_at IS NULL AND u.deleted_at IS NULL
         ORDER BY u.name"
    );
}

// Get driver trip history
$trips = [];
$driverInfo = null;
$pag = listPaginationState(0, null, $perPage);
$tripListParams = [
    'page' => 'reports',
    'action' => 'driver',
    'driver_id' => $driverId,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'per_page' => $pag['perPage'],
    'q' => $searchQuery,
];

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

    $tripBaseParams = [$driverId, $driverId, $startDate, $endDate . ' 23:59:59'];

    $allTripsForStats = db()->fetchAll(
        "SELECT r.status,
                v.plate_number,
                TIMESTAMPDIFF(MINUTE, r.start_datetime, r.end_datetime) as planned_duration,
                TIMESTAMPDIFF(MINUTE, r.actual_dispatch_datetime, r.actual_arrival_datetime) as actual_duration
         FROM requests r
         LEFT JOIN vehicles v ON r.vehicle_id = v.id
         WHERE (r.driver_id = ? OR r.requested_driver_id = ?)
         AND r.start_datetime BETWEEN ? AND ?
         AND r.deleted_at IS NULL",
        $tripBaseParams
    );

    $tableWhere = "(r.driver_id = ? OR r.requested_driver_id = ?)
         AND r.start_datetime BETWEEN ? AND ?
         AND r.deleted_at IS NULL";
    $tableParams = $tripBaseParams;
    if ($searchQuery) {
        $tableWhere .= " AND (
            CAST(r.id AS CHAR) LIKE ? OR
            r.destination LIKE ? OR
            r.purpose LIKE ? OR
            u.name LIKE ? OR
            d.name LIKE ? OR
            v.plate_number LIKE ?
        )";
        $searchTerm = '%' . $searchQuery . '%';
        array_push($tableParams, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }

    $countRow = db()->fetch(
        "SELECT COUNT(*) as c
         FROM requests r
         JOIN users u ON r.user_id = u.id
         LEFT JOIN departments d ON r.department_id = d.id
         LEFT JOIN vehicles v ON r.vehicle_id = v.id
         WHERE {$tableWhere}",
        $tableParams
    );
    $pag = listPaginationState((int) ($countRow->c ?? 0), null, $perPage);
    $tripListParams['per_page'] = $pag['perPage'];

    $trips = db()->fetchAll(
        "SELECT r.id, r.start_datetime, r.end_datetime, r.purpose, r.destination,
                r.status, r.passenger_count, r.actual_dispatch_datetime, r.actual_arrival_datetime,
                u.name as requester_name, d.name as department_name,
                v.plate_number, v.make, v.model,
                TIMESTAMPDIFF(MINUTE, r.start_datetime, r.end_datetime) as planned_duration,
                TIMESTAMPDIFF(MINUTE, r.actual_dispatch_datetime, r.actual_arrival_datetime) as actual_duration
         FROM requests r
         JOIN users u ON r.user_id = u.id
         LEFT JOIN departments d ON r.department_id = d.id
         LEFT JOIN vehicles v ON r.vehicle_id = v.id
         WHERE {$tableWhere}
         ORDER BY r.start_datetime DESC
         LIMIT ? OFFSET ?",
        array_merge($tableParams, [$pag['perPage'], $pag['offset']])
    );
}

// Stats (full date range, not narrowed by table search)
$stats = (object)[
    'total_trips' => count($allTripsForStats ?? []),
    'completed_trips' => 0,
    'total_hours' => 0,
    'unique_vehicles' => []
];

if (!empty($allTripsForStats)) {
    foreach ($allTripsForStats as $t) {
        if ($t->status === 'completed') {
            $stats->completed_trips++;
        }
        if ($t->actual_duration) {
            $stats->total_hours += $t->actual_duration / 60;
        } elseif ($t->planned_duration) {
            $stats->total_hours += $t->planned_duration / 60;
        }
        $plate = $t->plate_number ?? null;
        if ($plate) {
            $stats->unique_vehicles[$plate] = true;
        }
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
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
    <div class="loka-card mb-4">
        <div class="loka-card-body">
            <form method="GET" class="grid grid-cols-12 gap-3 items-end">
                <input type="hidden" name="page" value="reports">
                <input type="hidden" name="action" value="driver">
                <div class="col-span-12 md:col-span-3">
                    <label class="loka-form-label">Driver</label>
                    <?php if ($driverScoped): ?>
                        <input type="hidden" name="driver_id" value="<?= (int) $myDriverId ?>">
                        <input type="text" class="loka-form-input" value="<?= e($drivers[0]->name ?? 'You') ?>" readonly>
                    <?php else: ?>
                    <select class="loka-form-input" name="driver_id" required>
                        <option value="">Select Driver...</option>
                        <?php foreach ($drivers as $d): ?>
                        <option value="<?= $d->id ?>" <?= $driverId == $d->id ? 'selected' : '' ?>>
                            <?= e($d->name) ?> - <?= e($d->license_number ?: 'No License') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="col-span-6 md:col-span-2">
                    <label class="loka-form-label">Start Date</label>
                    <input type="date" class="loka-form-input" name="start_date" value="<?= e($startDate) ?>">
                </div>
                <div class="col-span-6 md:col-span-2">
                    <label class="loka-form-label">End Date</label>
                    <input type="date" class="loka-form-input" name="end_date" value="<?= e($endDate) ?>">
                </div>
                <?= perPageFieldHtml($pag['perPage'], 'loka-form-input') ?>
                <?= listSearchFieldHtml($searchQuery, 'ID, destination, requester, vehicle...', 'loka-form-input') ?>
                <div class="col-span-12 md:col-span-2 flex items-end">
                    <button type="submit" class="loka-btn-primary">
                        <i class="bi bi-search me-1"></i>Generate
                    </button>
                </div>
                <?php if ($driverId && $pag['total'] > 0): ?>
                <div class="col-span-12 md:col-span-3 flex flex-wrap justify-end items-end gap-2">
                    <a href="<?= APP_URL ?>/?page=reports&action=export-driver-csv&driver_id=<?= $driverId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="loka-btn-outline-primary">
                        <i class="bi bi-file-earmark-csv me-1"></i>CSV
                    </a>
                    <a href="<?= APP_URL ?>/?page=reports&action=export-driver&driver_id=<?= $driverId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="loka-btn-outline-error">
                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($driverInfo): ?>
    <!-- Driver Info -->
    <div class="grid grid-cols-12 gap-4 mb-4">
        <div class="col-span-12 md:col-span-4">
            <div class="loka-card h-full">
                <div class="loka-card-body">
                    <h6 class="text-base-content/60 mb-3">Driver Information</h6>
                    <div class="flex items-center mb-3">
                        <div class="avatar-circle bg-primary text-white me-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <?= strtoupper(substr($driverInfo->name, 0, 1)) ?>
                        </div>
                        <div>
                            <h4 class="mb-0"><?= e($driverInfo->name) ?></h4>
                            <p class="text-base-content/60 mb-0"><?= e($driverInfo->phone ?: 'No phone') ?></p>
                        </div>
                    </div>
                    <hr>
                    <div class="grid grid-cols-12 gap-2">
                        <div class="col-span-6">
                            <small class="text-base-content/60">License No.</small>
                            <p class="mb-0 fw-bold"><?= e($driverInfo->license_number ?: 'N/A') ?></p>
                        </div>
                        <div class="col-span-6">
                            <small class="text-base-content/60">Status</small>
                            <p class="mb-0"><?= driverStatusBadge($driverInfo->status) ?></p>
                        </div>
                        <div class="col-span-6">
                            <small class="text-base-content/60">License Expiry</small>
                            <p class="mb-0 fw-bold"><?= $driverInfo->license_expiry ? formatDate($driverInfo->license_expiry) : 'N/A' ?></p>
                        </div>
                        <div class="col-span-6">
                            <small class="text-base-content/60">Department</small>
                            <p class="mb-0 fw-bold"><?= e($driverInfo->department_name ?: 'N/A') ?></p>
                        </div>
                    </div>
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
                            <h3 class="text-warning mb-0"><?= count($stats->unique_vehicles) ?></h3>
                            <small class="text-base-content/60">Vehicles Driven</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trip History Table -->
    <div class="loka-card">
        <div class="px-4 md:px-6 pt-4 md:pt-6">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Trip History <span class="text-base-content/50 text-sm font-normal">(<?= number_format($pag['total']) ?> total)</span></h5>
        </div>
        <div class="loka-card-body">
            <?php if (empty($trips)): ?>
            <div class="text-center py-4 text-base-content/60">
                <i class="bi bi-clipboard-x fs-1"></i>
                <p class="mt-2">No trips found for this driver in the selected period.</p>
            </div>
            <?php else: ?>
            <div class="loka-table-responsive">
                <table class="loka-table table-hover">
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
                                <small class="text-base-content/60 block">to <?= formatDateTime($trip->end_datetime) ?></small>
                            </td>
                            <td>
                                <strong><?= e($trip->plate_number ?: 'N/A') ?></strong>
                                <small class="text-base-content/60 block"><?= e($trip->make . ' ' . $trip->model) ?></small>
                            </td>
                            <td><?= e($trip->destination) ?></td>
                            <td>
                                <?= e($trip->requester_name) ?>
                                <small class="text-base-content/60 block"><?= e($trip->department_name) ?></small>
                            </td>
                            <td><?= $trip->passenger_count ?></td>
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
            <?= listPaginationFooter($pag, $tripListParams) ?>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="loka-card">
        <div class="loka-card-body text-center py-5 text-base-content/60">
            <i class="bi bi-person fs-1"></i>
            <p class="mt-2">Select a driver to view their trip history.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
