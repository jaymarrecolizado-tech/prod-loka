<?php
/**
 * LOKA - Driver Schedule / My Trips Page
 * 
 * Shows drivers their assigned trips (both past and upcoming)
 */

$driver = db()->fetch(
    "SELECT d.*, u.name, u.email, u.phone 
     FROM drivers d 
     JOIN users u ON d.user_id = u.id 
     WHERE d.user_id = ? AND d.deleted_at IS NULL",
    [userId()]
);

if (!$driver) {
    redirectWith('/?page=dashboard', 'danger', 'You are not registered as a driver.');
}

$pageTitle = 'My Trips';

$filter = get('filter', 'upcoming');
$today = date('Y-m-d');

$sql = "SELECT r.*,
            u.name as requester_name, u.phone as requester_phone,
            d.name as department_name,
            v.plate_number, v.make, v.model as vehicle_model,
            mph.name as motorpool_head_name,
            tt.id as trip_ticket_id,
            tt.status as trip_ticket_status,
            tt.trip_type as trip_ticket_type
        FROM requests r
        JOIN users u ON r.user_id = u.id
        JOIN departments d ON r.department_id = d.id
        LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
        LEFT JOIN users mph ON r.motorpool_head_id = mph.id
        LEFT JOIN trip_tickets tt ON r.id = tt.request_id AND tt.deleted_at IS NULL
        WHERE (r.driver_id = ? OR r.requested_driver_id = ?)
        AND r.deleted_at IS NULL";

$params = [$driver->id, $driver->id];

switch ($filter) {
    case 'past':
        $sql .= " AND r.end_datetime < NOW()";
        $sql .= " ORDER BY r.start_datetime DESC";
        break;
    case 'all':
        $sql .= " ORDER BY r.start_datetime DESC";
        break;
    case 'upcoming':
    default:
        $sql .= " AND r.end_datetime >= NOW()";
        $sql .= " ORDER BY r.start_datetime ASC";
        break;
}

$trips = db()->fetchAll($sql, $params);

$stats = [
    'upcoming' => db()->fetchColumn(
        "SELECT COUNT(*) FROM requests
         WHERE (driver_id = ? OR requested_driver_id = ?)
         AND end_datetime >= NOW()
         AND status IN (?, ?)
         AND deleted_at IS NULL",
        [$driver->id, $driver->id, STATUS_APPROVED, STATUS_PENDING_MOTORPOOL]
    ),
    'completed' => db()->fetchColumn(
        "SELECT COUNT(*) FROM requests
         WHERE driver_id = ?
         AND status = ?
         AND deleted_at IS NULL",
        [$driver->id, STATUS_COMPLETED]
    ),
    'this_month' => db()->fetchColumn(
        "SELECT COUNT(*) FROM requests
         WHERE driver_id = ?
         AND MONTH(start_datetime) = MONTH(NOW())
         AND YEAR(start_datetime) = YEAR(NOW())
         AND status IN (?, ?)
         AND deleted_at IS NULL",
        [$driver->id, STATUS_APPROVED, STATUS_COMPLETED]
    ),
    'trip_tickets_pending' => db()->fetchColumn(
        "SELECT COUNT(*) FROM trip_tickets tt
         JOIN requests r ON tt.request_id = r.id
         WHERE r.driver_id = ?
         AND tt.status = 'submitted'
         AND tt.deleted_at IS NULL",
        [$driver->id]
    ),
    'trip_tickets_approved' => db()->fetchColumn(
        "SELECT COUNT(*) FROM trip_tickets tt
         JOIN requests r ON tt.request_id = r.id
         WHERE r.driver_id = ?
         AND tt.status = 'approved'
         AND tt.deleted_at IS NULL",
        [$driver->id]
    )
];

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-truck mr-2"></i>My Trips</h4>
            <p class="text-base-content/60 mb-0">View your assigned and requested trips</p>
        </div>
        <div class="flex gap-2">
            <span class="loka-badge bg-base-200 text-base-content border border-base-300 text-sm">
                <i class="bi bi-person-badge mr-1"></i><?= e($driver->name) ?>
            </span>
            <a href="?page=my-trips&action=export-pdf&filter=<?= $filter ?>" class="bg-success text-success-content hover:bg-success/90 px-4 py-2 text-sm font-medium rounded-xl inline-flex items-center gap-2 transition-colors">
                <i class="bi bi-file-earmark-pdf mr-1"></i>Export PDF
            </a>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-3 mb-4">
        <div class="col-span-12 md:col-span-3">
            <div class="loka-card h-full">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary/10 rounded-xl p-3">
                                <i class="bi bi-calendar-event text-primary text-xl"></i>
                            </div>
                        </div>
                        <div class="flex-1 ml-3">
                            <h6 class="text-base-content/60 mb-1">Upcoming Trips</h6>
                            <h3 class="mb-0"><?= $stats['upcoming'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="loka-card h-full">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success/10 rounded-xl p-3">
                                <i class="bi bi-check-circle text-success text-xl"></i>
                            </div>
                        </div>
                        <div class="flex-1 ml-3">
                            <h6 class="text-base-content/60 mb-1">Completed Trips</h6>
                            <h3 class="mb-0"><?= $stats['completed'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="loka-card h-full">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info/10 rounded-xl p-3">
                                <i class="bi bi-calendar-check text-info text-xl"></i>
                            </div>
                        </div>
                        <div class="flex-1 ml-3">
                            <h6 class="text-base-content/60 mb-1">This Month</h6>
                            <h3 class="mb-0"><?= $stats['this_month'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="loka-card h-full">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning/10 rounded-xl p-3">
                                <i class="bi bi-file-earmark-text text-warning text-xl"></i>
                            </div>
                        </div>
                        <div class="flex-1 ml-3">
                            <h6 class="text-base-content/60 mb-1">Trip Tickets</h6>
                            <h3 class="mb-0">
                                <?= $stats['trip_tickets_approved'] ?>
                                <?php if ($stats['trip_tickets_pending'] > 0): ?>
                                    <span class="loka-badge bg-warning ml-1"><?= $stats['trip_tickets_pending'] ?> pending</span>
                                <?php endif; ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="loka-card">
        <div class="px-6 py-4 border-b border-base-200 bg-white">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'upcoming' ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/?page=my-trips">
                        <i class="bi bi-calendar-event mr-1"></i>Upcoming
                        <?php if ($stats['upcoming'] > 0): ?>
                            <span class="loka-badge bg-primary ml-1"><?= $stats['upcoming'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'past' ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/?page=my-trips&filter=past">
                        <i class="bi bi-clock-history mr-1"></i>Past Trips
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/?page=my-trips&filter=all">
                        <i class="bi bi-list-ul mr-1"></i>All Trips
                    </a>
                </li>
            </ul>
        </div>
        <div class="p-6">
            <?php if (empty($trips)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x text-4xl text-base-content/60"></i>
                    <p class="text-base-content/60 mt-3">
                        <?php if ($filter === 'upcoming'): ?>
                            No upcoming trips assigned to you.
                        <?php elseif ($filter === 'past'): ?>
                            No past trips found.
                        <?php else: ?>
                            No trips found.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="loka-table-responsive">
                    <table class="loka-table">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Date & Time</th>
                                <th>Destination</th>
                                <th>Vehicle</th>
                                <th>Mileage</th>
                                <th>Requester</th>
                                <th>Status</th>
                                <th>Role</th>
                                <th>Trip Ticket</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trips as $trip): ?>
                                <?php 
                                $isAssignedDriver = ($trip->driver_id == $driver->id);
                                $isRequestedDriver = ($trip->requested_driver_id == $driver->id && !$isAssignedDriver);
                                ?>
                                <tr>
                                    <td>
                                        <strong>#<?= $trip->id ?></strong>
                                    </td>
                                    <td>
                                        <div class="text-sm">
                                            <i class="bi bi-box-arrow-right text-success mr-1"></i>
                                            <?= formatDateTime($trip->start_datetime) ?>
                                        </div>
                                        <div class="text-sm">
                                            <i class="bi bi-box-arrow-in-left text-error mr-1"></i>
                                            <?= formatDateTime($trip->end_datetime) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="font-medium"><?= e($trip->destination) ?></div>
                                        <small class="text-base-content/60"><?= truncate($trip->purpose, 50) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($trip->plate_number): ?>
                                            <div class="font-medium"><?= e($trip->plate_number) ?></div>
                                            <small class="text-base-content/60"><?= e($trip->make . ' ' . $trip->vehicle_model) ?></small>
                                        <?php else: ?>
                                            <span class="text-base-content/60">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($trip->mileage_actual): ?>
                                            <div class="font-medium text-primary"><?= number_format($trip->mileage_actual) ?> km</div>
                                            <small class="text-base-content/60">Actual distance</small>
                                        <?php elseif ($trip->mileage_start): ?>
                                            <div class="font-medium"><?= number_format($trip->mileage_start) ?> km</div>
                                            <small class="text-base-content/60">Start only</small>
                                        <?php else: ?>
                                            <span class="text-base-content/60">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="font-medium"><?= e($trip->requester_name) ?></div>
                                        <small class="text-base-content/60"><?= e($trip->department_name) ?></small>
                                    </td>
                                    <td><?= requestStatusBadge($trip->status) ?></td>
                                    <td>
                                        <?php if ($isAssignedDriver): ?>
                                            <span class="loka-badge bg-success">Assigned Driver</span>
                                        <?php elseif ($isRequestedDriver): ?>
                                            <span class="loka-badge bg-warning text-dark">Requested</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- Trip Ticket Status -->
                                        <?php if ($trip->trip_ticket_id): ?>
                                            <?php
                                            $ticketStatusClass = '';
                                            $ticketStatusIcon = '';
                                            switch ($trip->trip_ticket_status) {
                                                case 'submitted':
                                                    $ticketStatusClass = 'warning';
                                                    $ticketStatusIcon = 'clock';
                                                    break;
                                                case 'reviewed':
                                                    $ticketStatusClass = 'info';
                                                    $ticketStatusIcon = 'arrow-counterclockwise';
                                                    break;
                                                case 'approved':
                                                    $ticketStatusClass = 'success';
                                                    $ticketStatusIcon = 'check-circle';
                                                    break;
                                            }
                                            ?>
                                            <span class="loka-badge bg-<?= $ticketStatusClass ?>">
                                                <i class="bi bi-<?= $ticketStatusIcon ?> mr-1"></i>
                                                <?= ucfirst($trip->trip_ticket_status) ?>
                                            </span>
                                        <?php elseif ($trip->status === STATUS_COMPLETED): ?>
                                            <span class="text-base-content/60">-</span>
                                        <?php else: ?>
                                            <span class="text-base-content/60">-</span>
                                        <?php endif; ?>
                                    </td>
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
            <?php endif; ?>
        </div>
    </div>

    <?php if ($driver->status !== 'available'): ?>
    <div class="loka-alert loka-alert-warning mt-4">
        <i class="bi bi-exclamation-triangle mr-2"></i>
        <strong>Status Notice:</strong> Your current driver status is 
        <span class="loka-badge bg-<?= $driver->status === 'on_trip' ? 'primary' : ($driver->status === 'on_leave' ? 'warning text-dark' : 'error') ?>">
            <?= ucfirst(str_replace('_', ' ', $driver->status)) ?>
        </span>.
        <?php if ($driver->status === 'on_leave'): ?>
            Contact motorpool to update your availability.
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
