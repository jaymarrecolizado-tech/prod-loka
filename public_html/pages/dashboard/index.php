<?php
/**
 * LOKA - Dashboard Page
 */

$pageTitle = 'Dashboard';

// Get statistics
$userId = userId();
$userRole = userRole();
$departmentId = currentUser()->department_id;

// Pagination for Recent Activity
$perPage = 10;
$currentPage = max(1, (int) get('p', 1));
$offset = ($currentPage - 1) * $perPage;

// Dashboard statistics with error handling
try {
    $myRequestsCount = db()->count('requests', 'user_id = ? AND deleted_at IS NULL', [$userId]);
} catch (Exception $e) {
    error_log("Dashboard stats error (my requests): " . $e->getMessage());
    $myRequestsCount = 0;
}

// Pending Approvals count (based on role)
$pendingApprovalsCount = 0;
try {
    if (isMotorpool()) {
        $pendingApprovalsCount = db()->count('requests', "status = 'pending_motorpool' AND deleted_at IS NULL");
    } elseif (isApprover()) {
        $pendingApprovalsCount = db()->count('requests', "status = 'pending' AND department_id = ? AND deleted_at IS NULL", [$departmentId]);
    }
} catch (Exception $e) {
    error_log("Dashboard stats error (pending approvals): " . $e->getMessage());
    $pendingApprovalsCount = 0;
}

// Available Vehicles count
try {
    $availableVehiclesCount = db()->count('vehicles', "status = 'available' AND deleted_at IS NULL");
} catch (Exception $e) {
    error_log("Dashboard stats error (available vehicles): " . $e->getMessage());
    $availableVehiclesCount = 0;
}

// Active Drivers count
try {
    $activeDriversCount = db()->count('drivers', "status = 'available' AND deleted_at IS NULL");
} catch (Exception $e) {
    error_log("Dashboard stats error (active drivers): " . $e->getMessage());
    $activeDriversCount = 0;
}

// Upcoming Trips (approved requests starting in next 7 days) - optimized with subquery
try {
    $upcomingTrips = db()->fetchAll(
        "SELECT r.*, u.name as requester_name, v.plate_number, v.make, v.model
         FROM (
             SELECT * FROM requests
             WHERE status = 'approved' 
             AND start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
             AND deleted_at IS NULL
             ORDER BY start_datetime ASC
             LIMIT 5
         ) r
         LEFT JOIN users u ON r.user_id = u.id
         LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL"
    );
} catch (Exception $e) {
    error_log("Dashboard error (upcoming trips): " . $e->getMessage());
    $upcomingTrips = [];
}

// Recent Activity with pagination (my requests or all for admin)
if (isAdmin()) {
    $totalActivity = db()->count('requests', 'deleted_at IS NULL');
    $recentActivity = db()->fetchAll(
        "SELECT r.*, u.name as requester_name, d.name as department_name
         FROM requests r
         JOIN users u ON r.user_id = u.id
         JOIN departments d ON r.department_id = d.id
         WHERE r.deleted_at IS NULL
         ORDER BY r.updated_at DESC
         LIMIT ? OFFSET ?",
        [$perPage, $offset]
    );
} else {
    $totalActivity = db()->count('requests', 'user_id = ? AND deleted_at IS NULL', [$userId]);
    $recentActivity = db()->fetchAll(
        "SELECT r.*, u.name as requester_name, d.name as department_name
         FROM requests r
         JOIN users u ON r.user_id = u.id
         JOIN departments d ON r.department_id = d.id
         WHERE r.user_id = ? AND r.deleted_at IS NULL
         ORDER BY r.updated_at DESC
         LIMIT ? OFFSET ?",
        [$userId, $perPage, $offset]
    );
}

$totalPages = ceil($totalActivity / $perPage);

// Vehicle status distribution (for motorpool/admin)
$vehicleStats = [];
if (isMotorpool()) {
    $vehicleStats = db()->fetchAll(
        "SELECT status, COUNT(*) as count FROM vehicles WHERE deleted_at IS NULL GROUP BY status"
    );
}

require_once INCLUDES_PATH . '/header.php';

// Analytics Data for Charts (only for admin/motorpool/approver)
$analyticsData = null;
$showCharts = isAdmin() || isMotorpool() || isApprover();

if ($showCharts) {
    // Get data for the last 30 days
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
    $today = date('Y-m-d');

    // Daily trip counts (last 7 days)
    $dailyTrips = db()->fetchAll(
        "SELECT DATE(start_datetime) as trip_date, COUNT(*) as count
         FROM requests
         WHERE DATE(start_datetime) >= ?
         AND deleted_at IS NULL
         GROUP BY DATE(start_datetime)
         ORDER BY trip_date ASC",
        [$sevenDaysAgo]
    );

    // Fill missing dates with 0
    $dailyTripData = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $count = 0;
        foreach ($dailyTrips as $dt) {
            if ($dt->trip_date === $date) {
                $count = $dt->count;
                break;
            }
        }
        $dailyTripData[] = [
            'date' => date('M/d', strtotime($date)),
            'count' => $count
        ];
    }

    // Status distribution
    $statusDistribution = db()->fetchAll(
        "SELECT status, COUNT(*) as count
         FROM requests
         WHERE created_at >= ?
         AND deleted_at IS NULL
         GROUP BY status",
        [$thirtyDaysAgo]
    );

    // Department trip counts (last 30 days)
    $departmentStats = db()->fetchAll(
        "SELECT d.name as department, COUNT(*) as count
         FROM requests r
         JOIN departments d ON r.department_id = d.id
         WHERE r.created_at >= ?
         AND r.deleted_at IS NULL
         GROUP BY d.name
         ORDER BY count DESC
         LIMIT 8",
        [$thirtyDaysAgo]
    );

    // Vehicle utilization (last 30 days)
    $vehicleUtilization = db()->fetchAll(
        "SELECT v.plate_number,
                 COUNT(r.id) as trip_count,
                 COALESCE(SUM(r.mileage_actual), 0) as total_mileage
         FROM vehicles v
         LEFT JOIN requests r ON r.vehicle_id = v.id
             AND r.status = 'completed'
             AND r.actual_arrival_datetime >= ?
             AND r.deleted_at IS NULL
         WHERE v.deleted_at IS NULL
         GROUP BY v.id
         ORDER BY trip_count DESC
         LIMIT 10",
        [$thirtyDaysAgo]
    );

    // Peak hours analysis
    $peakHours = db()->fetchAll(
        "SELECT HOUR(start_datetime) as hour, COUNT(*) as count
         FROM requests
         WHERE DATE(start_datetime) >= ?
         AND deleted_at IS NULL
         GROUP BY HOUR(start_datetime)
         ORDER BY hour ASC",
        [$thirtyDaysAgo]
    );

    // Fill all hours (0-23) with 0
    $hourlyData = array_fill(0, 24, 0);
    foreach ($peakHours as $ph) {
        $hourlyData[$ph->hour] = $ph->count;
    }

    $analyticsData = [
        'dailyTrips' => $dailyTripData,
        'statusDistribution' => $statusDistribution,
        'departmentStats' => $departmentStats,
        'vehicleUtilization' => $vehicleUtilization,
        'peakHours' => $hourlyData
    ];
}
?>

<div class="loka-page">
    <!-- Page Header -->
    <div class="loka-page-header mb-6">
        <div>
            <h4 class="mb-1 text-xl font-bold text-base-content">Dashboard</h4>
            <p class="text-sm text-base-content/60 mb-0">Welcome back, <?= e(currentUser()->name) ?>!</p>
        </div>
        <div>
            <a href="<?= APP_URL ?>/?page=requests&action=create" class="loka-btn-primary">
                <i class="bi bi-plus-lg mr-1"></i>New Request
            </a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <!-- My Requests -->
        <div class="loka-stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="loka-stat-value text-primary"><?= $myRequestsCount ?></div>
                    <div class="loka-stat-label">My Requests</div>
                </div>
                <div class="loka-stat-icon bg-primary/10 text-primary">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
            </div>
        </div>
        
        <?php if (isApprover()): ?>
        <!-- Pending Approvals -->
        <div class="loka-stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="loka-stat-value text-warning"><?= $pendingApprovalsCount ?></div>
                    <div class="loka-stat-label">Pending Approvals</div>
                </div>
                <div class="loka-stat-icon bg-warning/10 text-warning">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Available Vehicles -->
        <div class="loka-stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="loka-stat-value text-success"><?= $availableVehiclesCount ?></div>
                    <div class="loka-stat-label">Available Vehicles</div>
                </div>
                <div class="loka-stat-icon bg-success/10 text-success">
                    <i class="bi bi-car-front"></i>
                </div>
            </div>
        </div>
        
        <!-- Active Drivers -->
        <div class="loka-stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="loka-stat-value text-info"><?= $activeDriversCount ?></div>
                    <div class="loka-stat-label">Available Drivers</div>
                </div>
                <div class="loka-stat-icon bg-info/10 text-info">
                    <i class="bi bi-person-badge"></i>
                </div>
            </div>
        </div>
    </div>

    <?php if ($showCharts && $analyticsData): ?>
    <!-- Analytics Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <!-- Daily Trips Chart -->
        <div class="lg:col-span-2">
            <div class="loka-card">
                <div class="flex items-center justify-between border-b border-base-200 px-5 py-4">
                    <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-graph-up mr-2"></i>Trips (Last 7 Days)</h5>
                </div>
                <div class="px-5 py-4">
                    <div class="loka-chart-container">
                        <canvas id="dailyTripsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Distribution -->
        <div class="col-span-1">
            <div class="loka-card">
                <div class="border-b border-base-200 px-5 py-4">
                    <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-pie-chart mr-2"></i>Status Distribution</h5>
                </div>
                <div class="px-5 py-4">
                    <div class="loka-chart-container-sm">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <!-- Department Trips -->
        <div class="col-span-1">
            <div class="loka-card">
                <div class="border-b border-base-200 px-5 py-4">
                    <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-building mr-2"></i>Trips by Department</h5>
                </div>
                <div class="px-5 py-4">
                    <div class="loka-chart-container">
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Peak Hours -->
        <div class="col-span-1">
            <div class="loka-card">
                <div class="border-b border-base-200 px-5 py-4">
                    <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-clock mr-2"></i>Peak Hours (Last 30 Days)</h5>
                </div>
                <div class="px-5 py-4">
                    <div class="loka-chart-container">
                        <canvas id="peakHoursChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Pass analytics data to external chart module
        window.dashboardAnalytics = <?= json_encode($analyticsData) ?>;
    </script>
    <script src="<?= ASSETS_PATH ?>/js/charts/dashboard.js" defer></script>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <!-- Upcoming Trips -->
        <div class="col-span-1">
            <div class="loka-card h-full">
                <div class="flex items-center justify-between border-b border-base-200 px-5 py-4">
                    <h5 class="font-semibold text-base-content"><i class="bi bi-calendar-event mr-2"></i>Upcoming Trips</h5>
                    <a href="<?= APP_URL ?>/?page=requests" class="loka-btn-secondary text-xs">View All</a>
                </div>
                <div>
                    <?php if (empty($upcomingTrips)): ?>
                    <div class="loka-empty py-4">
                        <i class="bi bi-calendar-x"></i>
                        <p class="mb-0">No upcoming trips</p>
                    </div>
                    <?php else: ?>
                    <div class="loka-table-responsive">
                        <table class="loka-table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Requester</th>
                                    <th>Vehicle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingTrips as $trip): ?>
                                <tr>
                                    <td>
                                        <div class="font-medium"><?= formatDateTime($trip->start_datetime) ?></div>
                                    </td>
                                    <td><?= e($trip->requester_name) ?></td>
                                    <td>
                                        <?php if ($trip->plate_number): ?>
                                        <span class="loka-badge bg-base-200 text-base-content"><?= e($trip->plate_number) ?></span>
                                        <?php else: ?>
                                        <span class="text-base-content/40">-</span>
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
        
        <!-- Recent Activity / My Requests -->
        <div class="col-span-1">
            <div class="loka-card h-full">
                <div class="flex items-center justify-between border-b border-base-200 px-5 py-4">
                    <h5 class="font-semibold text-base-content"><i class="bi bi-clock-history mr-2"></i><?= isAdmin() ? 'Recent Activity' : 'My Requests' ?></h5>
                    <span class="text-xs text-base-content/50"><?= $totalActivity ?> total</span>
                </div>
                <div>
                    <?php if (empty($recentActivity)): ?>
                    <div class="loka-empty py-4">
                        <i class="bi bi-inbox"></i>
                        <p class="mb-0">No requests found</p>
                    </div>
                    <?php else: ?>
                    <ul class="activity-feed px-3">
                        <?php foreach ($recentActivity as $activity): ?>
                        <li>
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-medium">
                                        <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $activity->id ?>" class="no-underline text-primary hover:underline">
                                            <?= truncate($activity->purpose, 40) ?>
                                        </a>
                                    </div>
                                    <span class="text-xs text-base-content/50">
                                        <?= e($activity->requester_name) ?> • <?= e($activity->department_name) ?>
                                    </span>
                                </div>
                                <div class="text-right">
                                    <?= requestStatusBadge($activity->status) ?>
                                    <div class="activity-time"><?= formatDateTime($activity->updated_at) ?></div>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <?php if ($totalPages > 1): ?>
                    <div class="border-t border-base-200 px-5 py-3 bg-transparent">
                        <nav aria-label="Activity pagination">
                            <ul class="flex justify-center gap-1 list-none p-0 m-0">
                                <!-- Previous -->
                                <li class="<?= $currentPage <= 1 ? 'opacity-50 pointer-events-none' : '' ?>">
                                    <a class="px-3 py-1.5 text-sm rounded-lg hover:bg-base-200 text-base-content no-underline" href="<?= APP_URL ?>/?page=dashboard&p=<?= $currentPage - 1 ?>">&laquo;</a>
                                </li>
                                
                                <?php
                                $start = max(1, $currentPage - 2);
                                $end = min($totalPages, $currentPage + 2);
                                
                                if ($start > 1): ?>
                                <li><a class="px-3 py-1.5 text-sm rounded-lg hover:bg-base-200 text-base-content no-underline" href="<?= APP_URL ?>/?page=dashboard&p=1">1</a></li>
                                <?php if ($start > 2): ?>
                                <li class="opacity-50 pointer-events-none"><span class="px-3 py-1.5 text-sm">...</span></li>
                                <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start; $i <= $end; $i++): ?>
                                <li>
                                    <a class="px-3 py-1.5 text-sm rounded-lg no-underline <?= $i === $currentPage ? 'bg-primary text-primary-content' : 'hover:bg-base-200 text-base-content' ?>" href="<?= APP_URL ?>/?page=dashboard&p=<?= $i ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($end < $totalPages): ?>
                                <?php if ($end < $totalPages - 1): ?>
                                <li class="opacity-50 pointer-events-none"><span class="px-3 py-1.5 text-sm">...</span></li>
                                <?php endif; ?>
                                <li><a class="px-3 py-1.5 text-sm rounded-lg hover:bg-base-200 text-base-content no-underline" href="<?= APP_URL ?>/?page=dashboard&p=<?= $totalPages ?>"><?= $totalPages ?></a></li>
                                <?php endif; ?>
                                
                                <!-- Next -->
                                <li class="<?= $currentPage >= $totalPages ? 'opacity-50 pointer-events-none' : '' ?>">
                                    <a class="px-3 py-1.5 text-sm rounded-lg hover:bg-base-200 text-base-content no-underline" href="<?= APP_URL ?>/?page=dashboard&p=<?= $currentPage + 1 ?>">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                        <div class="text-center mt-2">
                            <span class="text-xs text-base-content/50">Page <?= $currentPage ?> of <?= $totalPages ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (isMotorpool() && !empty($vehicleStats)): ?>
    <!-- Vehicle Status Overview -->
    <div class="grid grid-cols-1 gap-4 mt-6">
        <div class="col-span-1">
            <div class="loka-card">
                <div class="border-b border-base-200 px-5 py-4">
                    <h5 class="font-semibold text-base-content"><i class="bi bi-pie-chart mr-2"></i>Vehicle Status Overview</h5>
                </div>
                <div class="px-5 py-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                        <?php foreach ($vehicleStats as $stat): ?>
                        <div class="py-3">
                            <div class="text-3xl font-bold mb-1"><?= $stat->count ?></div>
                            <div><?= vehicleStatusBadge($stat->status) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
