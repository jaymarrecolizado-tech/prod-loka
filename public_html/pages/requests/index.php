<?php
$pageTitle = 'Requests';
$currentPage = 'requests';

requireRole(ROLE_REQUESTER);

$db = Database::getInstance();

// Pagination settings
$perPage = 15;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

// Filters
$statusFilter = getSafe('status', '', 50);
$dateFrom = getSafe('date_from', '', 20);
$dateTo = getSafe('date_to', '', 20);
$searchQuery = getSafe('q', '', 100);

// Build query conditions
$where = [];
$params = [];

// Role-based filtering
if (hasRole(ROLE_REQUESTER) && !hasRole(ROLE_APPROVER)) {
    $where[] = "r.user_id = ?";
    $params[] = userId();
}

if ($statusFilter) {
    $where[] = "r.status = ?";
    $params[] = $statusFilter;
}

if ($dateFrom) {
    $where[] = "r.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo) {
    $where[] = "r.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

if ($searchQuery) {
    $where[] = "(r.purpose LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countSql = "SELECT COUNT(*) FROM requests r
             LEFT JOIN users u ON r.user_id = u.id
             {$whereClause}";
$totalItems = $db->fetch($countSql, $params)->{"COUNT(*)"} ?? 0;
$totalPages = max(1, ceil($totalItems / $perPage));

// Fetch requests
$sql = "SELECT r.*,
        u.first_name, u.last_name, u.email,
        v.plate_number, v.brand, v.model,
        d.first_name as driver_first, d.last_name as driver_last
        FROM requests r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN vehicles v ON r.vehicle_id = v.id
        LEFT JOIN drivers dr ON r.driver_id = dr.id
        LEFT JOIN users d ON dr.user_id = d.id
        {$whereClause}
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$requests = $db->fetchAll($sql, $params);

// Status counts for badges
$statusCounts = $db->fetchAll("
    SELECT status, COUNT(*) as count
    FROM requests
    " . (hasRole(ROLE_REQUESTER) && !hasRole(ROLE_APPROVER) ? "WHERE r.user_id = " . userId() : "") . "
    GROUP BY status
");
$statusMap = array_column($statusCounts, 'count', 'status');

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Requests</h1>
            <p class="text-sm text-base-content/60">Manage your vehicle requests</p>
        </div>
        <a href="?page=requests&action=create" class="loka-btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Request
        </a>
    </div>

    <!-- Status Tabs -->
    <div class="flex gap-2 mb-6 flex-wrap">
        <a href="?page=requests" class="loka-badge <?= !$statusFilter ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content hover:bg-base-300' ?> transition-colors">
            All (<?= $totalItems ?>)
        </a>
        <?php
        $statuses = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];
        foreach ($statuses as $s => $label):
            $count = $statusMap[$s] ?? 0;
            $colors = [
                'pending' => 'bg-warning/20 text-warning',
                'approved' => 'bg-success/20 text-success',
                'rejected' => 'bg-error/20 text-error',
                'cancelled' => 'bg-base-200 text-base-content/60',
            ];
        ?>
        <a href="?page=requests&status=<?= $s ?>" class="loka-badge <?= $statusFilter === $s ? $colors[$s] . ' ring-2 ring-current' : 'bg-base-200 text-base-content hover:bg-base-300' ?> transition-colors">
            <?= $label ?> (<?= $count ?>)
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="loka-card mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="page" value="requests">
            <div class="flex flex-col gap-1.5 flex-1 min-w-[200px]">
                <label class="label">
                    <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Search</span>
                </label>
                <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Purpose, requester..." class="input input-bordered input-sm w-full bg-base-100">
            </div>
            <div class="flex flex-col gap-1.5 min-w-[130px]">
                <label class="label">
                    <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Status</span>
                </label>
                <select name="status" class="select select-bordered select-sm bg-base-100">
                    <option value="">All Status</option>
                    <?php foreach ($statuses as $s => $label): ?>
                    <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex flex-col gap-1.5 min-w-[140px]">
                <label class="label">
                    <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">From</span>
                </label>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="input input-bordered input-sm bg-base-100">
            </div>
            <div class="flex flex-col gap-1.5 min-w-[140px]">
                <label class="label">
                    <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">To</span>
                </label>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="input input-bordered input-sm bg-base-100">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="loka-btn-primary loka-btn-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Filter
                </button>
                <a href="?page=requests" class="loka-btn-secondary loka-btn-sm">Clear</a>
            </div>
        </form>
    </div>

    <!-- Requests Table -->
    <div class="loka-card">
        <div class="loka-table-responsive">
            <table class="loka-table">
                <thead>
                    <tr>
                        <th>Control No.</th>
                        <th>Purpose</th>
                        <th>Requester</th>
                        <th>Date Needed</th>
                        <th>Route</th>
                        <th>Passengers</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-center w-20">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="loka-empty">
                                <svg class="mx-auto w-12 h-12 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <p class="mt-2 text-base-content/60">No requests found</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                    <tr>
                        <td>
                            <span class="font-mono text-xs font-semibold text-primary"><?= e($req['control_no'] ?? 'N/A') ?></span>
                        </td>
                        <td>
                            <div class="max-w-[200px]">
                                <p class="font-medium text-sm text-base-content truncate" title="<?= e($req['purpose']) ?>"><?= e($req['purpose']) ?></p>
                                <?php if (!empty($req['vehicle_type_name'])): ?>
                                <span class="loka-badge loka-badge-sm bg-base-200 text-base-content/70 mt-1"><?= e($req['vehicle_type_name']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="loka-avatar loka-avatar-sm">
                                    <?= strtoupper(substr($req['first_name'], 0, 1) . substr($req['last_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-base-content"><?= e($req['first_name'] . ' ' . $req['last_name']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($req['date_needed']): ?>
                            <p class="text-sm text-base-content"><?= date('M d, Y', strtotime($req['date_needed'])) ?></p>
                            <p class="text-xs text-base-content/60"><?= date('h:i A', strtotime($req['date_needed'])) ?></p>
                            <?php else: ?>
                            <span class="text-sm text-base-content/40">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="max-w-[180px]">
                                <p class="text-sm text-base-content truncate" title="<?= e($req['origin'] ?? '') ?> → <?= e($req['destination'] ?? '') ?>"><?= e($req['origin'] ?? 'N/A') ?> → <?= e($req['destination'] ?? 'N/A') ?></p>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="loka-badge loka-badge-sm bg-base-200 text-base-content"><?= (int)($req['passengers'] ?? 1) ?></span>
                        </td>
                        <td>
                            <?php
                            $statusClasses = [
                                'pending' => 'bg-warning/20 text-warning',
                                'approved' => 'bg-success/20 text-success',
                                'rejected' => 'bg-error/20 text-error',
                                'cancelled' => 'bg-base-200 text-base-content/60',
                                'completed' => 'bg-info/20 text-info',
                            ];
                            $statusLabels = [
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'cancelled' => 'Cancelled',
                                'completed' => 'Completed',
                            ];
                            $cls = $statusClasses[$req['status']] ?? 'bg-base-200 text-base-content';
                            $lbl = $statusLabels[$req['status'] ?? 'pending'] ?? ucfirst($req['status']);
                            ?>
                            <span class="loka-badge <?= $cls ?>"><?= $lbl ?></span>
                        </td>
                        <td>
                            <p class="text-sm text-base-content"><?= date('M d', strtotime($req['created_at'])) ?></p>
                            <p class="text-xs text-base-content/60"><?= date('h:i A', strtotime($req['created_at'])) ?></p>
                        </td>
                        <td class="text-center">
                            <a href="?page=requests&action=view&id=<?= $req['id'] ?>" class="loka-btn-icon text-primary hover:bg-primary/10" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between mt-4 px-4 pb-4">
            <p class="text-sm text-base-content/60">
                Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalItems) ?> of <?= $totalItems ?>
            </p>
            <div class="join">
                <?php if ($page > 1): ?>
                <a href="?page=requests&p=<?= $page - 1 ?>&status=<?= e($statusFilter) ?>" class="join-item btn btn-sm">«</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                <a href="?page=requests&p=<?= $i ?>&status=<?= e($statusFilter) ?>" class="join-item btn btn-sm <?= $i === $page ? 'btn-primary' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="?page=requests&p=<?= $page + 1 ?>&status=<?= e($statusFilter) ?>" class="join-item btn btn-sm">»</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
