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
$showAll = get('all', '1'); // Default to show all completed trips
$searchQuery = listSearchQuery();
$perPage = resolvePerPage();

// Sorting (latest completed first by default)
$allowedSortColumns = [
    'id' => 'r.id',
    'completed_date' => 'r.actual_arrival_datetime',
    'plate_number' => 'v.plate_number',
    'driver_name' => 'driver_user.name',
    'requester_name' => 'u.name',
    'destination' => 'r.destination',
    'duration' => 'TIMESTAMPDIFF(MINUTE, r.actual_dispatch_datetime, r.actual_arrival_datetime)',
    'mileage' => 'r.mileage_actual',
];
$sortState = resolveTableSort($allowedSortColumns, 'completed_date', 'DESC');
$sort = $sortState['key'];
$sortDir = $sortState['dir'];

// Date filter - only apply if not showing all
$startDate = null;
$endDate = null;
if ($showAll !== '1') {
    $startDate = get('start_date', date('Y-m-01'));
    $endDate = get('end_date', date('Y-m-t'));
}

// Check if user is a driver
$driver = db()->fetch(
    "SELECT id FROM drivers WHERE user_id = ? AND deleted_at IS NULL",
    [userId()]
);
$isDriver = ($driver !== null);

$fromSql = "FROM requests r
     JOIN users u ON r.user_id = u.id
     JOIN departments d ON r.department_id = d.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN drivers dr ON r.driver_id = dr.id AND dr.deleted_at IS NULL
     LEFT JOIN users driver_user ON dr.user_id = driver_user.id
     LEFT JOIN users dispatch_guard ON r.dispatch_guard_id = dispatch_guard.id
     LEFT JOIN users arrival_guard ON r.arrival_guard_id = arrival_guard.id
     WHERE r.status = ?
     AND r.actual_arrival_datetime IS NOT NULL
     AND r.deleted_at IS NULL";

$params = [STATUS_COMPLETED];
$whereSql = '';

// Role-based filtering
if ($isDriver) {
    $whereSql .= " AND (r.driver_id = ? OR r.requested_driver_id = ?)";
    $params[] = $driver->id;
    $params[] = $driver->id;
} elseif ($role === ROLE_GUARD) {
    $whereSql .= " AND (r.dispatch_guard_id = ? OR r.arrival_guard_id = ?)";
    $params[] = userId();
    $params[] = userId();
} elseif ($role === ROLE_APPROVER) {
    $userDepartmentId = db()->fetchColumn(
        "SELECT department_id FROM users WHERE id = ?",
        [userId()]
    );
    $whereSql .= " AND r.department_id = ?";
    $params[] = $userDepartmentId;
} elseif ($role !== ROLE_MOTORPOOL && $role !== ROLE_ADMIN) {
    $whereSql .= " AND r.user_id = ?";
    $params[] = userId();
}

if ($startDate && $endDate) {
    $whereSql .= " AND DATE(r.actual_arrival_datetime) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate . ' 23:59:59';
}

if ($searchQuery) {
    $whereSql .= " AND (
        v.plate_number LIKE ? OR
        u.name LIKE ? OR
        driver_user.name LIKE ? OR
        r.destination LIKE ? OR
        r.purpose LIKE ? OR
        CAST(r.id AS CHAR) LIKE ?
    )";
    $searchTerm = '%' . $searchQuery . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$countRow = db()->fetch("SELECT COUNT(*) as c {$fromSql}{$whereSql}", $params);
$pag = listPaginationState((int) ($countRow->c ?? 0), null, $perPage);
$totalCount = $pag['total'];

$trips = db()->fetchAll(
    "SELECT r.*,
            u.name as requester_name, u.phone as requester_phone,
            d.name as department_name,
            v.plate_number, v.make, v.model as vehicle_model, v.color,
            dr.license_number as driver_license,
            driver_user.name as driver_name, driver_user.phone as driver_phone,
            dispatch_guard.name as dispatch_guard_name,
            arrival_guard.name as arrival_guard_name,
            TIMESTAMPDIFF(MINUTE, r.actual_dispatch_datetime, r.actual_arrival_datetime) as actual_duration
     {$fromSql}{$whereSql}
     ORDER BY {$sortState['orderSql']}
     LIMIT ? OFFSET ?",
    array_merge($params, [$pag['perPage'], $pag['offset']])
);

$baseParams = tableSortQueryParams($sortState, [
    'page' => 'completed-trips',
    'all' => $showAll,
    'per_page' => $pag['perPage'],
    'q' => $searchQuery,
]);
if ($startDate) {
    $baseParams['start_date'] = $startDate;
}
if ($endDate) {
    $baseParams['end_date'] = $endDate;
}

// Calculate statistics (without pagination)
$statsRows = db()->fetchAll(
    "SELECT r.mileage_actual,
            TIMESTAMPDIFF(MINUTE, r.actual_dispatch_datetime, r.actual_arrival_datetime) as actual_duration,
            r.passenger_count,
            r.id
     {$fromSql}{$whereSql}",
    $params
);

$totalTrips = count($statsRows);
$totalDistance = 0;
$totalHours = 0;
$totalPassengers = 0;

foreach ($statsRows as $t) {
    if ($t->mileage_actual) {
        $totalDistance += $t->mileage_actual;
    }
    if ($t->actual_duration) {
        $totalHours += $t->actual_duration / 60;
    }
    $totalPassengers += (int) ($t->passenger_count ?? countRequestPassengers((int) $t->id));
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-check-all mr-2"></i>Completed Trips</h4>
            <p class="text-base-content/60 mb-0">
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
            <button type="button" class="bg-success text-success-content hover:bg-success/90 px-4 py-2 text-sm font-medium rounded-xl inline-flex items-center gap-2 transition-colors" onclick="exportCompletedTrips()">
                <i class="bi bi-file-earmark-excel mr-1"></i>Export CSV
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-12 gap-3 mb-4">
        <div class="col-span-12 md:col-span-3">
            <div class="loka-card">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary/10 rounded-xl p-3">
                                <i class="bi bi-list-check text-primary text-xl"></i>
                            </div>
                        </div>
                        <div class="flex-1 ml-3">
                            <h6 class="text-base-content/60 mb-1">Total Trips</h6>
                            <h3 class="mb-0"><?= $totalTrips ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="loka-card">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success/10 rounded-xl p-3">
                                <i class="bi bi-speedometer2 text-success text-xl"></i>
                            </div>
                        </div>
                        <div class="flex-1 ml-3">
                            <h6 class="text-base-content/60 mb-1">Total Distance</h6>
                            <h3 class="mb-0"><?= number_format($totalDistance) ?> km</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="loka-card">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info/10 rounded-xl p-3">
                                <i class="bi bi-clock text-info text-xl"></i>
                            </div>
                        </div>
                        <div class="flex-1 ml-3">
                            <h6 class="text-base-content/60 mb-1">Total Hours</h6>
                            <h3 class="mb-0"><?= number_format($totalHours, 1) ?>h</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="loka-card">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning/10 rounded-xl p-3">
                                <i class="bi bi-people text-warning text-xl"></i>
                            </div>
                        </div>
                        <div class="flex-1 ml-3">
                            <h6 class="text-base-content/60 mb-1">Total Passengers</h6>
                            <h3 class="mb-0"><?= $totalPassengers ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="loka-card mb-4">
        <div class="p-6">
            <form method="GET" class="loka-filter-form">
                <input type="hidden" name="page" value="completed-trips">
                <div class="min-w-[140px]">
                    <label class="loka-form-label">Date Range</label>
                    <select class="loka-form-input" name="all" onchange="this.form.submit()">
                        <option value="1" <?= $showAll === '1' ? 'selected' : '' ?>>All Time</option>
                        <option value="0" <?= $showAll !== '1' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                <?php if ($showAll !== '1'): ?>
                <div class="min-w-[150px]">
                    <label class="loka-form-label">Start Date</label>
                    <input type="date" class="loka-form-input" name="start_date" value="<?= $startDate ?? date('Y-m-01') ?>">
                </div>
                <div class="min-w-[150px]">
                    <label class="loka-form-label">End Date</label>
                    <input type="date" class="loka-form-input" name="end_date" value="<?= $endDate ?? date('Y-m-t') ?>">
                </div>
                <?php endif; ?>
                <?= perPageFieldHtml($pag['perPage'], 'loka-form-input') ?>
                <?= listSearchFieldHtml($searchQuery, 'Vehicle, requester, driver, destination...', 'loka-form-input') ?>
                <div class="flex items-center gap-2">
                    <button type="submit" class="loka-btn-primary">
                        <i class="bi bi-search mr-1"></i>Filter
                    </button>
                    <a href="<?= APP_URL ?>/?page=completed-trips" class="loka-btn-secondary" title="Refresh">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Completed Trips Table -->
    <div class="loka-card">
        <div class="loka-card-header flex justify-between items-center">
            <div>
                <h5 class="loka-card-title mb-0">Completed Trips (<?= number_format($totalCount) ?> total)</h5>
                <small class="text-base-content/60">
                    <?php if ($showAll === '1'): ?>
                        Showing all time
                    <?php else: ?>
                        Showing from <?= formatDate($startDate) ?> to <?= formatDate($endDate) ?>
                    <?php endif; ?>
                </small>
            </div>
        </div>
        <?php if (empty($trips)): ?>
            <div class="loka-empty">
                <i class="bi bi-calendar-x text-4xl text-base-content/60"></i>
                <p class="text-base-content/60 mt-3">No completed trips found for the selected criteria.</p>
            </div>
        <?php else: ?>
            <div class="loka-table-responsive">
                <table class="loka-table" id="completedTripsTable">
                    <thead>
                        <tr>
                            <?= tableSortTh('id', 'ID', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('completed_date', 'Completed Date', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('plate_number', 'Vehicle', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('driver_name', 'Driver', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('requester_name', 'Requester', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('destination', 'Destination', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('duration', 'Duration', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('mileage', 'Mileage', $sort, $sortDir, $baseParams) ?>
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
                            $passengerCount = (int) ($trip->passenger_count ?? countRequestPassengers((int) $trip->id));
                            ?>
                            <tr>
                                <td><strong>#<?= $trip->id ?></strong></td>
                                <td>
                                    <div class="small">
                                        <i class="bi bi-calendar3 mr-1"></i>
                                        <?= formatDate($trip->actual_arrival_datetime) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($trip->plate_number): ?>
                                        <div class="font-medium"><?= e($trip->plate_number) ?></div>
                                        <small class="text-base-content/60"><?= e($trip->make . ' ' . $trip->vehicle_model) ?></small>
                                    <?php else: ?>
                                        <span class="text-base-content/60">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($trip->driver_name): ?>
                                        <div class="font-medium"><?= e($trip->driver_name) ?></div>
                                        <?php if ($trip->driver_license): ?>
                                        <small class="text-base-content/60"><?= e($trip->driver_license) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-base-content/60">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium"><?= e($trip->requester_name) ?></div>
                                    <small class="text-base-content/60"><?= e($trip->department_name) ?></small>
                                </td>
                                <td>
                                    <div><?= e($trip->destination) ?></div>
                                    <small class="text-base-content/60"><?= truncate($trip->purpose, 30) ?></small>
                                </td>
                                <td>
                                    <?php if ($trip->actual_duration): ?>
                                        <?= floor($trip->actual_duration / 60) ?>h <?= ($trip->actual_duration % 60) ?>m
                                    <?php else: ?>
                                        <span class="text-base-content/60">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($trip->mileage_actual): ?>
                                        <span class="font-medium text-primary"><?= number_format($trip->mileage_actual) ?> km</span>
                                    <?php else: ?>
                                        <span class="text-base-content/60">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="loka-badge bg-info"><?= $passengerCount ?></span>
                                </td>
                                <?php if (in_array($role, [ROLE_MOTORPOOL, ROLE_ADMIN])): ?>
                                <td>
                                    <?php if ($trip->actual_dispatch_datetime): ?>
                                        <small>
                                            <i class="bi bi-box-arrow-right text-success"></i>
                                            <?= date('M/d H:i', strtotime($trip->actual_dispatch_datetime)) ?>
                                        </small>
                                        <br><small class="text-base-content/60">by <?= e($trip->dispatch_guard_name) ?></small>
                                    <?php else: ?>
                                        <span class="text-base-content/60">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($trip->actual_arrival_datetime): ?>
                                        <small>
                                            <i class="bi bi-box-arrow-in-left text-error"></i>
                                            <?= date('M/d H:i', strtotime($trip->actual_arrival_datetime)) ?>
                                        </small>
                                        <br><small class="text-base-content/60">by <?= e($trip->arrival_guard_name) ?></small>
                                    <?php else: ?>
                                        <span class="text-base-content/60">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $trip->id ?>"
                                       class="loka-btn-outline-primary loka-btn-sm">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?= listPaginationFooter($pag, $baseParams) ?>
            <?php endif; ?>
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
            const vehiclePlate = cells[2].querySelector('.font-medium')?.textContent.trim() || '';
            const vehicleModel = cells[2].querySelector('.text-base-content\\/60')?.textContent.trim() || '';
            const driver = cells[3].querySelector('.font-medium')?.textContent.trim() || '';
            const requester = cells[4].querySelector('.font-medium')?.textContent.trim() || '';
            const department = cells[4].querySelector('.text-base-content\\/60')?.textContent.trim() || '';
            const destination = cells[5].querySelector('div')?.textContent.trim() || '';
            const purpose = cells[5].querySelector('.text-base-content\\/60')?.textContent.trim() || '';
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
