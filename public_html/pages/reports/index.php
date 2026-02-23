<?php
/**
 * LOKA - Reports Page
 */

requireRole(ROLE_APPROVER);

$pageTitle = 'Reports';

// Date range
$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-t'));

// Vehicle utilization stats
$vehicleStats = db()->fetchAll(
    "SELECT v.plate_number, v.make, v.model, COUNT(r.id) as trip_count,
            SUM(TIMESTAMPDIFF(HOUR, r.start_datetime, r.end_datetime)) as total_hours
     FROM vehicles v
     LEFT JOIN requests r ON v.id = r.vehicle_id AND r.status IN ('approved', 'completed')
            AND r.start_datetime BETWEEN ? AND ?
     WHERE v.deleted_at IS NULL
     GROUP BY v.id
     ORDER BY trip_count DESC
     LIMIT 10",
    [$startDate, $endDate . ' 23:59:59']
);

// Department usage stats
$deptStats = db()->fetchAll(
    "SELECT d.name as department_name, COUNT(r.id) as request_count,
            SUM(CASE WHEN r.status = 'approved' OR r.status = 'completed' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
     FROM departments d
     LEFT JOIN requests r ON d.id = r.department_id AND r.created_at BETWEEN ? AND ?
     WHERE d.deleted_at IS NULL
     GROUP BY d.id
     ORDER BY request_count DESC",
    [$startDate, $endDate . ' 23:59:59']
);

// Overall stats
$overallStats = db()->fetch(
    "SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' OR status = 'completed' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'pending' OR status = 'pending_motorpool' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN actual_dispatch_datetime IS NOT NULL THEN 1 ELSE 0 END) as dispatched,
        SUM(CASE WHEN actual_arrival_datetime IS NOT NULL THEN 1 ELSE 0 END) as arrived
     FROM requests
     WHERE created_at BETWEEN ? AND ? AND deleted_at IS NULL",
    [$startDate, $endDate . ' 23:59:59']
);

// Trip completion stats (with actual times)
$tripStats = db()->fetchAll(
    "SELECT r.id, r.start_datetime, r.end_datetime, r.destination,
            r.actual_dispatch_datetime, r.actual_arrival_datetime,
            v.plate_number, v.make, v.model,
            u.name as requester_name, d.name as department_name,
            TIMESTAMPDIFF(MINUTE, r.actual_dispatch_datetime, r.actual_arrival_datetime) as actual_duration_minutes,
            TIMESTAMPDIFF(MINUTE, r.start_datetime, r.end_datetime) as planned_duration_minutes
     FROM requests r
     JOIN users u ON r.user_id = u.id
     JOIN departments d ON r.department_id = d.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id
     WHERE r.status = 'completed'
     AND r.actual_dispatch_datetime IS NOT NULL
     AND r.actual_arrival_datetime IS NOT NULL
     AND r.created_at BETWEEN ? AND ?
     AND r.deleted_at IS NULL
     ORDER BY r.actual_arrival_datetime DESC
     LIMIT 20",
    [$startDate, $endDate . ' 23:59:59']
);

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Reports</h4>
            <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li><li class="breadcrumb-item active">Reports</li></ol></nav>
        </div>
        <div class="btn-group">
            <a href="<?= APP_URL ?>/?page=reports&action=export&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-csv me-1"></i>Export CSV
            </a>
            <a href="<?= APP_URL ?>/?page=reports&action=export-pdf&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-outline-danger">
                <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
            </a>
        </div>
    </div>
    
    <!-- Date Filter -->
    <div class="card table-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="reports">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="text" class="form-control datepicker" name="start_date" value="<?= e($startDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="text" class="form-control datepicker" name="end_date" value="<?= e($endDate) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-filter me-1"></i>Apply Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-2">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-value text-primary"><?= $overallStats->total_requests ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-value text-success"><?= $overallStats->approved ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-value text-info"><?= $overallStats->completed ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-value text-danger"><?= $overallStats->rejected ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-value text-warning"><?= $overallStats->pending ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-value text-secondary"><?= $overallStats->dispatched ?></div>
                    <div class="stat-label">Dispatched</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Trip Completion Report -->
    <div class="card table-card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Completed Trips (With Actual Times)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($tripStats)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-clipboard-x fs-1"></i>
                    <p class="mt-2">No completed trips with recorded dispatch/arrival times in this period.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Requester</th>
                                <th>Vehicle</th>
                                <th>Destination</th>
                                <th>Scheduled</th>
                                <th>Actual Dispatch</th>
                                <th>Actual Arrival</th>
                                <th>Planned Duration</th>
                                <th>Actual Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tripStats as $trip): ?>
                                <?php
                                $plannedHours = floor($trip->planned_duration_minutes / 60);
                                $plannedMins = $trip->planned_duration_minutes % 60;
                                $actualHours = floor($trip->actual_duration_minutes / 60);
                                $actualMins = $trip->actual_duration_minutes % 60;
                                $durationDiff = $trip->actual_duration_minutes - $trip->planned_duration_minutes;
                                ?>
                                <tr>
                                    <td><strong>#<?= $trip->id ?></strong></td>
                                    <td>
                                        <?= e($trip->requester_name) ?>
                                        <small class="d-block text-muted"><?= e($trip->department_name) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= e($trip->plate_number) ?></strong>
                                        <small class="d-block text-muted"><?= e($trip->make . ' ' . $trip->model) ?></small>
                                    </td>
                                    <td><?= e($trip->destination) ?></td>
                                    <td>
                                        <small>
                                            <?= formatDateTime($trip->start_datetime) ?><br>
                                            <span class="text-muted">to <?= formatDateTime($trip->end_datetime) ?></span>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="text-success">
                                            <i class="bi bi-box-arrow-right me-1"></i>
                                            <?= formatDateTime($trip->actual_dispatch_datetime) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-primary">
                                            <i class="bi bi-box-arrow-in-left me-1"></i>
                                            <?= formatDateTime($trip->actual_arrival_datetime) ?>
                                        </span>
                                    </td>
                                    <td><?= $plannedHours ?>h <?= $plannedMins ?>m</td>
                                    <td>
                                        <?= $actualHours ?>h <?= $actualMins ?>m
                                        <?php if ($durationDiff > 0): ?>
                                            <span class="badge bg-warning ms-1">+<?= $durationDiff ?>m</span>
                                        <?php elseif ($durationDiff < 0): ?>
                                            <span class="badge bg-success ms-1"><?= $durationDiff ?>m</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary ms-1">On time</span>
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
    
    <div class="row g-4">
        <!-- Vehicle Utilization -->
        <div class="col-lg-6">
            <div class="card table-card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-car-front me-2"></i>Vehicle Utilization</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Vehicle</th><th>Trips</th><th>Hours</th></tr></thead>
                            <tbody>
                                <?php foreach ($vehicleStats as $stat): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($stat->plate_number) ?></strong>
                                        <small class="d-block text-muted"><?= e($stat->make . ' ' . $stat->model) ?></small>
                                    </td>
                                    <td><?= $stat->trip_count ?></td>
                                    <td><?= $stat->total_hours ?: 0 ?>h</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Department Usage -->
        <div class="col-lg-6">
            <div class="card table-card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-building me-2"></i>Department Usage</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Department</th><th>Requests</th><th>Approved</th><th>Rejected</th></tr></thead>
                            <tbody>
                                <?php foreach ($deptStats as $stat): ?>
                                <tr>
                                    <td><strong><?= e($stat->department_name) ?></strong></td>
                                    <td><?= $stat->request_count ?></td>
                                    <td><span class="text-success"><?= $stat->approved_count ?></span></td>
                                    <td><span class="text-danger"><?= $stat->rejected_count ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
