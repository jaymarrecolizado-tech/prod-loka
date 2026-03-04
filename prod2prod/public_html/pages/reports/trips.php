<?php
/**
 * LOKA - Trip Requests Report
 */

requireRole(ROLE_APPROVER);

$pageTitle = 'Trip Requests Report';

$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-t'));
$status = get('status', '');

// Build query with filters
$whereClause = "WHERE r.deleted_at IS NULL AND r.created_at BETWEEN ? AND ?";
$params = [$startDate, $endDate . ' 23:59:59'];

if ($status) {
    $whereClause .= " AND r.status = ?";
    $params[] = $status;
}

// Get stats
$stats = db()->fetch(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN r.status IN ('pending', 'pending_motorpool') THEN 1 ELSE 0 END) as pending
     FROM requests r
     $whereClause",
    $params
);

// Get requests
$requests = db()->fetchAll(
    "SELECT r.id, r.created_at, r.start_datetime, r.end_datetime, r.purpose, r.destination,
            r.status, r.passenger_count, r.actual_dispatch_datetime, r.actual_arrival_datetime,
            u.name as requester_name, dept.name as department_name,
            v.plate_number, v.make, v.model,
            dr_user.name as driver_name,
            TIMESTAMPDIFF(MINUTE, r.start_datetime, r.end_datetime) as planned_duration,
            TIMESTAMPDIFF(MINUTE, r.actual_dispatch_datetime, r.actual_arrival_datetime) as actual_duration
     FROM requests r
     JOIN users u ON r.user_id = u.id
     LEFT JOIN departments dept ON r.department_id = dept.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id
     LEFT JOIN drivers dr ON r.driver_id = dr.id
     LEFT JOIN users dr_user ON dr.user_id = dr_user.id
     $whereClause
     ORDER BY r.created_at DESC
     LIMIT 500",
    $params
);

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Trip Requests Report</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=reports">Reports</a></li>
                    <li class="breadcrumb-item active">Trip Requests</li>
                </ol>
            </nav>
        </div>
        <div class="btn-group">
            <a href="<?= APP_URL ?>/?page=reports&action=export&start_date=<?= $startDate ?>&end_date=<?= $endDate ?><?= $status ? '&status=' . $status : '' ?>" 
               class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-csv me-1"></i>Export CSV
            </a>
            <a href="<?= APP_URL ?>/?page=reports&action=export-pdf&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" 
               class="btn btn-outline-danger">
                <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="reports">
                <input type="hidden" name="action" value="trips">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?= e($startDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?= e($endDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="pending_motorpool" <?= $status === 'pending_motorpool' ? 'selected' : '' ?>>Pending Motorpool</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="revision" <?= $status === 'revision' ? 'selected' : '' ?>>Revision</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="<?= APP_URL ?>/?page=reports&action=trips" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card bg-primary bg-opacity-10">
                <div class="card-body text-center py-2">
                    <h4 class="text-primary mb-0"><?= $stats->total ?></h4>
                    <small class="text-muted">Total</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success bg-opacity-10">
                <div class="card-body text-center py-2">
                    <h4 class="text-success mb-0"><?= $stats->approved ?></h4>
                    <small class="text-muted">Approved</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info bg-opacity-10">
                <div class="card-body text-center py-2">
                    <h4 class="text-info mb-0"><?= $stats->completed ?></h4>
                    <small class="text-muted">Completed</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger bg-opacity-10">
                <div class="card-body text-center py-2">
                    <h4 class="text-danger mb-0"><?= $stats->rejected ?></h4>
                    <small class="text-muted">Rejected</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning bg-opacity-10">
                <div class="card-body text-center py-2">
                    <h4 class="text-warning mb-0"><?= $stats->pending ?></h4>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Trip Requests</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($requests)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-clipboard-x fs-1"></i>
                <p class="mt-2">No trip requests found for the selected period.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Created</th>
                            <th>Scheduled</th>
                            <th>Requester</th>
                            <th>Destination</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Status</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td>
                                <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $req->id ?>">
                                    <strong>#<?= $req->id ?></strong>
                                </a>
                            </td>
                            <td><?= formatDate($req->created_at) ?></td>
                            <td>
                                <small>
                                    <?= formatDateTime($req->start_datetime) ?><br>
                                    <span class="text-muted">to <?= formatDateTime($req->end_datetime) ?></span>
                                </small>
                            </td>
                            <td>
                                <?= e($req->requester_name) ?>
                                <small class="d-block text-muted"><?= e($req->department_name) ?></small>
                            </td>
                            <td><?= e(strlen($req->destination) > 30 ? substr($req->destination, 0, 30) . '...' : $req->destination) ?></td>
                            <td>
                                <?php if ($req->plate_number): ?>
                                <strong><?= e($req->plate_number) ?></strong>
                                <small class="d-block text-muted"><?= e($req->make) ?></small>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($req->driver_name ?: '-') ?></td>
                            <td><?= requestStatusBadge($req->status) ?></td>
                            <td>
                                <?php if ($req->actual_duration): ?>
                                    <span class="text-success"><?= floor($req->actual_duration / 60) ?>h <?= $req->actual_duration % 60 ?>m</span>
                                <?php elseif ($req->planned_duration): ?>
                                    <span class="text-muted"><?= floor($req->planned_duration / 60) ?>h <?= $req->planned_duration % 60 ?>m</span>
                                <?php else: ?>
                                    -
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
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
