<?php
/**
 * LOKA - Completed Trips Page
 *
 * Role-based view of completed trips:
 * - Driver: Only their completed trips
 * - Guard: Trips they dispatched/received
 * - Approver: Department completed trips
 * - Motorpool Head: All completed trips
 * - Admin: All completed trips
 */

$pageTitle = 'Completed Trips';

$role = userRole();
$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-t'));
$search = get('search', '');
$page = getInt('page', 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Check if user is a driver
$driver = db()->fetch(
    "SELECT id FROM drivers WHERE user_id = ? AND deleted_at IS NULL",
    [userId()]
);
$isDriver = ($driver !== null);

// Build base query with role-based filtering
$sql = "SELECT r.*,
            u.name as requester_name, u.phone as requester_phone,
            d.name as department_name,
            v.plate_number, v.make, v.model as vehicle_model, v.color,
            dr.license_number as driver_license,
            driver_user.name as driver_name, driver_user.phone as driver_phone,
            dispatch_guard.name as dispatch_guard_name,
            arrival_guard.name as arrival_guard_name,
            TIMESTAMPDIFF(MINUTE, r.actual_dispatch_datetime, r.actual_arrival_datetime) as actual_duration
     FROM requests r
     JOIN users u ON r.user_id = u.id
     JOIN departments d ON r.department_id = d.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN drivers dr ON r.driver_id = dr.id AND dr.deleted_at IS NULL
     LEFT JOIN users driver_user ON dr.user_id = driver_user.id
     LEFT JOIN users dispatch_guard ON r.dispatch_guard_id = dispatch_guard.id
     LEFT JOIN users arrival_guard ON r.arrival_guard_id = arrival_guard.id
     WHERE r.status = ?
     AND r.deleted_at IS NULL";

$params = [STATUS_COMPLETED];

// Role-based filtering
if ($isDriver) {
    // Drivers see only their completed trips
    $sql .= " AND (r.driver_id = ? OR r.requested_driver_id = ?)";
    $params[] = $driver->id;
    $params[] = $driver->id;
} elseif ($role === ROLE_GUARD) {
    // Guards see trips they dispatched or received
    $sql .= " AND (r.dispatch_guard_id = ? OR r.arrival_guard_id = ?)";
    $params[] = userId();
    $params[] = userId();
} elseif ($role === ROLE_APPROVER) {
    // Approvers see completed trips from their department
    $userDepartmentId = db()->fetchColumn(
        "SELECT department_id FROM users WHERE id = ?",
        [userId()]
    );
    $sql .= " AND r.department_id = ?";
    $params[] = $userDepartmentId;
} elseif ($role === ROLE_MOTORPOOL || $role === ROLE_ADMIN) {
    // Motorpool heads and admins see all completed trips
    // No additional filtering needed
} else {
    // Requesters see only their own completed trips
    $sql .= " AND r.user_id = ?";
    $params[] = userId();
}

// Apply date range filter
$sql .= " AND DATE(r.completed_at) BETWEEN ? AND ?";
$params[] = $startDate;
$params[] = $endDate . ' 23:59:59';

// Apply search filter
if ($search) {
    $sql .= " AND (
        v.plate_number LIKE ? OR
        u.name LIKE ? OR
        driver_user.name LIKE ? OR
        r.destination LIKE ? OR
        r.purpose LIKE ?
    )";
    $searchTerm = '%' . $search . '%';
    $params = array_merge($params, array_fill(0, 5, $searchTerm));
}

// Get total count for pagination
$countSql = str_replace(
    "SELECT r.*,\n            u.name as requester_name, u.phone as requester_phone,\n            d.name as department_name,\n            v.plate_number, v.make, v.model as vehicle_model, v.color,\n            dr.license_number as driver_license,\n            driver_user.name as driver_name, driver_user.phone as driver_phone,\n            dispatch_guard.name as dispatch_guard_name,\n            arrival_guard.name as arrival_guard_name,\n            TIMESTAMPDIFF(MINUTE, r.actual_dispatch_datetime, r.actual_arrival_datetime) as actual_duration",
    "SELECT COUNT(*) as total",
    $sql
);
$countSql = preg_replace('/ORDER BY.*/', '', $countSql);
$totalCount = db()->fetchColumn($countSql, $params);
$totalPages = ceil($totalCount / $limit);

// Add ordering and pagination
$sql .= " ORDER BY r.completed_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$trips = db()->fetchAll($sql, $params);

// Calculate statistics (without pagination)
$statsSql = str_replace(' ORDER BY r.completed_at DESC LIMIT ? OFFSET ?', '', $sql);
$statsSql = preg_replace('/LIMIT \? OFFSET \?$/', '', $statsSql);
$allTripsForStats = db()->fetchAll($statsSql, array_slice($params, 0, -2));

$totalTrips = count($allTripsForStats);
$totalDistance = 0;
$totalHours = 0;
$totalPassengers = 0;

foreach ($allTripsForStats as $t) {
    if ($t->mileage_actual) $totalDistance += $t->mileage_actual;
    if ($t->actual_duration) $totalHours += $t->actual_duration / 60;
    $passengerCount = db()->fetchColumn(
        "SELECT COUNT(*) FROM request_passengers WHERE request_id = ?",
        [$t->id]
    );
    $totalPassengers += $passengerCount;
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-check-all me-2"></i>Completed Trips</h4>
            <p class="text-muted mb-0">
                <?php if ($isDriver): ?>
                    Your completed trip history
                <?php elseif ($role === ROLE_GUARD): ?>
                    Trips you tracked at the gate
                <?php elseif ($role === ROLE_APPROVER): ?>
                    Your department's completed trips
                <?php elseif (in_array($role, [ROLE_MOTORPOOL, ROLE_ADMIN])): ?>
                    All completed trips in the system
                <?php else: ?>
                    Your completed trip history
                <?php endif; ?>
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-success" onclick="exportCompletedTrips()">
                <i class="bi bi-file-earmark-excel me-1"></i>Export CSV
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded p-3">
                                <i class="bi bi-list-check text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total Trips</h6>
                            <h3 class="mb-0"><?= $totalTrips ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 rounded p-3">
                                <i class="bi bi-speedometer2 text-success fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total Distance</h6>
                            <h3 class="mb-0"><?= number_format($totalDistance) ?> km</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-opacity-10 rounded p-3">
                                <i class="bi bi-clock text-info fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total Hours</h6>
                            <h3 class="mb-0"><?= number_format($totalHours, 1) ?>h</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning bg-opacity-10 rounded p-3">
                                <i class="bi bi-people text-warning fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total Passengers</h6>
                            <h3 class="mb-0"><?= $totalPassengers ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="page" value="completed-trips">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?= $startDate ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?= $endDate ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?= e($search) ?>"
                           placeholder="Vehicle, requester, driver, destination...">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="<?= APP_URL ?>/?page=completed-trips" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Completed Trips Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Completed Trips (<?= $totalCount ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($trips)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x fs-1 text-muted"></i>
                    <p class="text-muted mt-3">No completed trips found for the selected criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="completedTripsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Completed Date</th>
                                <th>Vehicle</th>
                                <th>Driver</th>
                                <th>Requester</th>
                                <th>Destination</th>
                                <th>Duration</th>
                                <th>Mileage</th>
                                <th>Passengers</th>
                                <?php if (in_array($role, [ROLE_MOTORPOOL, ROLE_ADMIN])): ?>
                                <th>Dispatch</th>
                                <th>Arrival</th>
                                <?php endif; ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trips as $trip): ?>
                            <?php
                            $passengerCount = db()->fetchColumn(
                                "SELECT COUNT(*) FROM request_passengers WHERE request_id = ?",
                                [$trip->id]
                            );
                            ?>
                            <tr>
                                <td><strong>#<?= $trip->id ?></strong></td>
                                <td>
                                    <div class="small">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?= formatDate($trip->completed_at) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($trip->plate_number): ?>
                                        <div class="fw-medium"><?= e($trip->plate_number) ?></div>
                                        <small class="text-muted"><?= e($trip->make . ' ' . $trip->vehicle_model) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($trip->driver_name): ?>
                                        <div class="fw-medium"><?= e($trip->driver_name) ?></div>
                                        <?php if ($trip->driver_license): ?>
                                        <small class="text-muted"><?= e($trip->driver_license) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-medium"><?= e($trip->requester_name) ?></div>
                                    <small class="text-muted"><?= e($trip->department_name) ?></small>
                                </td>
                                <td>
                                    <div><?= e($trip->destination) ?></div>
                                    <small class="text-muted"><?= truncate($trip->purpose, 30) ?></small>
                                </td>
                                <td>
                                    <?php if ($trip->actual_duration): ?>
                                        <?= floor($trip->actual_duration / 60) ?>h <?= ($trip->actual_duration % 60) ?>m
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($trip->mileage_actual): ?>
                                        <span class="fw-medium text-primary"><?= number_format($trip->mileage_actual) ?> km</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= $passengerCount ?></span>
                                </td>
                                <?php if (in_array($role, [ROLE_MOTORPOOL, ROLE_ADMIN])): ?>
                                <td>
                                    <?php if ($trip->actual_dispatch_datetime): ?>
                                        <small>
                                            <i class="bi bi-box-arrow-right text-success"></i>
                                            <?= date('M/d H:i', strtotime($trip->actual_dispatch_datetime)) ?>
                                        </small>
                                        <br><small class="text-muted">by <?= e($trip->dispatch_guard_name) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($trip->actual_arrival_datetime): ?>
                                        <small>
                                            <i class="bi bi-box-arrow-in-left text-danger"></i>
                                            <?= date('M/d H:i', strtotime($trip->actual_arrival_datetime)) ?>
                                        </small>
                                        <br><small class="text-muted">by <?= e($trip->arrival_guard_name) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $trip->id ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=completed-trips&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&search=<?= e($search) ?>&p=<?= $page - 1 ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=completed-trips&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&search=<?= e($search) ?>&p=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=completed-trips&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&search=<?= e($search) ?>&p=<?= $page + 1 ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function exportCompletedTrips() {
    const table = document.getElementById('completedTripsTable');
    if (!table) return;

    let csv = [];
    const headers = ['ID', 'Completed Date', 'Vehicle Plate', 'Vehicle Model', 'Driver', 'Requester', 'Department', 'Destination', 'Purpose', 'Duration (min)', 'Mileage (km)', 'Passengers'];
    csv.push(headers.map(h => `"${h}"`).join(','));

    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length > 0) {
            const id = cells[0].textContent.trim();
            const completedDate = cells[1].textContent.trim().replace('Calendar', '').trim();
            const vehiclePlate = cells[2].querySelector('.fw-medium')?.textContent.trim() || '';
            const vehicleModel = cells[2].querySelector('.text-muted')?.textContent.trim() || '';
            const driver = cells[3].querySelector('.fw-medium')?.textContent.trim() || '';
            const requester = cells[4].querySelector('.fw-medium')?.textContent.trim() || '';
            const department = cells[4].querySelector('.text-muted')?.textContent.trim() || '';
            const destination = cells[5].querySelector('div')?.textContent.trim() || '';
            const purpose = cells[5].querySelector('.text-muted')?.textContent.trim() || '';
            const duration = cells[6].textContent.trim();
            const mileage = cells[7].textContent.trim();
            const passengers = cells[8].textContent.trim();

            const rowData = [
                id, completedDate, vehiclePlate, vehicleModel, driver, requester, department,
                destination, purpose, duration, mileage, passengers
            ].map(val => `"${String(val).replace(/"/g, '""')}"`).join(',');

            csv.push(rowData);
        }
    });

    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'completed_trips_' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
}
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
