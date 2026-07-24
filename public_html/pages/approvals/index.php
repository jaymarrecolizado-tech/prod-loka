<?php
/**
 * LOKA - Approvals Queue Page
 */

requireRole(ROLE_APPROVER);

$pageTitle = 'Approval Queue';
$tab = get('tab', 'pending');
$searchQuery = listSearchQuery();
$recordsPerPage = resolvePerPage();

// Get current page for pagination
$pendingPage = resolveListPage('p_pending');
$processedPage = resolveListPage('p_processed');

$pendingSearchSql = '';
$pendingSearchParams = [];
$processedSearchSql = '';
$processedSearchParams = [];

if ($searchQuery) {
    $searchTerm = '%' . $searchQuery . '%';
    $pendingSearchSql = " AND (
        CAST(r.id AS CHAR) LIKE ? OR
        u.name LIKE ? OR
        d.name LIKE ? OR
        r.purpose LIKE ? OR
        r.destination LIKE ? OR
        v.plate_number LIKE ?
    )";
    $pendingSearchParams = array_fill(0, 6, $searchTerm);
    $processedSearchSql = " AND (
        CAST(r.id AS CHAR) LIKE ? OR
        u.name LIKE ? OR
        d.name LIKE ? OR
        r.purpose LIKE ? OR
        r.destination LIKE ?
    )";
    $processedSearchParams = array_fill(0, 5, $searchTerm);
}

// Sorting (latest created / action first by default)
// Both list queries run every request; only the active tab uses request sort params.
$pendingSortColumns = [
    'id' => 'r.id',
    'requester' => 'u.name',
    'department' => 'd.name',
    'purpose' => 'r.purpose',
    'start_datetime' => 'r.start_datetime',
    'vehicle' => 'v.plate_number',
    'created_at' => 'r.created_at',
];
$processedSortColumns = [
    'id' => 'r.id',
    'requester' => 'u.name',
    'purpose' => 'r.purpose',
    'approval_type' => 'a.approval_type',
    'my_action' => 'a.status',
    'action_date' => 'a.created_at',
    'status' => 'r.status',
];
if ($tab === 'processed') {
    $sortState = resolveTableSort($processedSortColumns, 'action_date', 'DESC');
    $pendingOrderSql = 'r.created_at DESC';
    $processedOrderSql = $sortState['orderSql'];
} else {
    $sortState = resolveTableSort($pendingSortColumns, 'created_at', 'DESC');
    $pendingOrderSql = $sortState['orderSql'];
    $processedOrderSql = 'a.created_at DESC';
}
$sort = $sortState['key'];
$sortDir = $sortState['dir'];

// Determine which requests to show based on role
// Only the specifically assigned approver/motorpool head can see and process requests
if (isAdmin()) {
    $pendingRequestsCount = db()->fetchColumn(
        "SELECT COUNT(*)
         FROM requests r
         JOIN users u ON r.user_id = u.id
         JOIN departments d ON r.department_id = d.id
         LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
         WHERE r.status IN ('pending', 'pending_motorpool', 'revision') AND r.deleted_at IS NULL{$pendingSearchSql}",
        $pendingSearchParams
    );
    $pendingPag = listPaginationState((int) $pendingRequestsCount, $pendingPage, $recordsPerPage);
    $pendingOffset = $pendingPag['offset'];
    $pendingRequests = db()->fetchAll(
        "SELECT r.*, u.name as requester_name, d.name as department_name,
                appr.name as assigned_approver_name, mph.name as assigned_motorpool_name,
                v.plate_number as vehicle_plate, v.make as vehicle_make, v.model as vehicle_model,
                (SELECT status FROM approvals WHERE request_id = r.id AND approval_type = 'department' ORDER BY created_at DESC LIMIT 1) as dept_status,
                (SELECT status FROM approvals WHERE request_id = r.id AND approval_type = 'motorpool' ORDER BY created_at DESC LIMIT 1) as motorpool_status
         FROM requests r
         JOIN users u ON r.user_id = u.id
         JOIN departments d ON r.department_id = d.id
         LEFT JOIN users appr ON r.approver_id = appr.id
         LEFT JOIN users mph ON r.motorpool_head_id = mph.id
         LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
         WHERE r.status IN ('pending', 'pending_motorpool', 'revision') AND r.deleted_at IS NULL{$pendingSearchSql}
         ORDER BY {$pendingOrderSql}, r.viewed_at IS NULL DESC
         LIMIT ? OFFSET ?",
        array_merge($pendingSearchParams, [$recordsPerPage, $pendingOffset])
    );
    $queueType = 'All';
} elseif (isMotorpool()) {
    $pendingRequestsCount = db()->fetchColumn(
        "SELECT COUNT(*)
         FROM requests r
         JOIN users u ON r.user_id = u.id
         JOIN departments d ON r.department_id = d.id
         LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
         WHERE (r.status = 'pending_motorpool' OR r.status = 'revision')
         AND r.motorpool_head_id = ?
         AND r.deleted_at IS NULL{$pendingSearchSql}",
        array_merge([userId()], $pendingSearchParams)
    );
    $pendingPag = listPaginationState((int) $pendingRequestsCount, $pendingPage, $recordsPerPage);
    $pendingOffset = $pendingPag['offset'];
    $pendingRequests = db()->fetchAll(
        "SELECT r.*, u.name as requester_name, d.name as department_name,
                appr.name as assigned_approver_name,
                v.plate_number as vehicle_plate, v.make as vehicle_make, v.model as vehicle_model,
                (SELECT status FROM approvals WHERE request_id = r.id AND approval_type = 'department' ORDER BY created_at DESC LIMIT 1) as dept_status
         FROM requests r
         JOIN users u ON r.user_id = u.id
         JOIN departments d ON r.department_id = d.id
         LEFT JOIN users appr ON r.approver_id = appr.id
         LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
         WHERE (r.status = 'pending_motorpool' OR r.status = 'revision')
         AND r.motorpool_head_id = ?
         AND r.deleted_at IS NULL{$pendingSearchSql}
         ORDER BY {$pendingOrderSql}, r.viewed_at IS NULL DESC
         LIMIT ? OFFSET ?",
        array_merge([userId()], $pendingSearchParams, [$recordsPerPage, $pendingOffset])
    );
    $queueType = 'Motorpool';
} else {
    $pendingRequestsCount = db()->fetchColumn(
        "SELECT COUNT(*)
         FROM requests r
         JOIN users u ON r.user_id = u.id
         JOIN departments d ON r.department_id = d.id
         LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
         WHERE (r.status = 'pending' OR r.status = 'revision')
         AND r.approver_id = ?
         AND r.deleted_at IS NULL{$pendingSearchSql}",
        array_merge([userId()], $pendingSearchParams)
    );
    $pendingPag = listPaginationState((int) $pendingRequestsCount, $pendingPage, $recordsPerPage);
    $pendingOffset = $pendingPag['offset'];
    $pendingRequests = db()->fetchAll(
        "SELECT r.*, u.name as requester_name, d.name as department_name,
                mph.name as assigned_motorpool_name,
                v.plate_number as vehicle_plate, v.make as vehicle_make, v.model as vehicle_model,
                (SELECT status FROM approvals WHERE request_id = r.id AND approval_type = 'motorpool' ORDER BY created_at DESC LIMIT 1) as motorpool_status
         FROM requests r
         JOIN users u ON r.user_id = u.id
         JOIN departments d ON r.department_id = d.id
         LEFT JOIN users mph ON r.motorpool_head_id = mph.id
         LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
         WHERE (r.status = 'pending' OR r.status = 'revision')
         AND r.approver_id = ?
         AND r.deleted_at IS NULL{$pendingSearchSql}
         ORDER BY {$pendingOrderSql}, r.viewed_at IS NULL DESC
         LIMIT ? OFFSET ?",
        array_merge([userId()], $pendingSearchParams, [$recordsPerPage, $pendingOffset])
    );
    $queueType = 'Assigned';
}

// Get total count for processed requests
$processedRequestsCount = db()->fetchColumn(
    "SELECT COUNT(*)
     FROM approvals a
     JOIN requests r ON a.request_id = r.id
     JOIN users u ON r.user_id = u.id
     JOIN departments d ON r.department_id = d.id
     WHERE a.approver_id = ? AND r.deleted_at IS NULL{$processedSearchSql}",
    array_merge([userId()], $processedSearchParams)
);

$processedPag = listPaginationState((int) $processedRequestsCount, $processedPage, $recordsPerPage);
$processedOffset = $processedPag['offset'];

// Get processed requests with pagination
$processedRequests = db()->fetchAll(
    "SELECT r.*, u.name as requester_name, d.name as department_name,
            a.status as my_action, a.approval_type, a.created_at as action_date
     FROM approvals a
     JOIN requests r ON a.request_id = r.id
     JOIN users u ON r.user_id = u.id
     JOIN departments d ON r.department_id = d.id
     WHERE a.approver_id = ? AND r.deleted_at IS NULL{$processedSearchSql}
     ORDER BY {$processedOrderSql}
     LIMIT ? OFFSET ?",
    array_merge([userId()], $processedSearchParams, [$recordsPerPage, $processedOffset])
);

$baseParams = tableSortQueryParams($sortState, [
    'page' => 'approvals',
    'tab' => $tab,
    'q' => $searchQuery,
    'per_page' => $recordsPerPage,
]);

$pendingPageParams = tableSortQueryParams($sortState, [
    'page' => 'approvals',
    'tab' => 'pending',
    'q' => $searchQuery,
    'per_page' => $recordsPerPage,
]);

$processedPageParams = tableSortQueryParams($sortState, [
    'page' => 'approvals',
    'tab' => 'processed',
    'q' => $searchQuery,
    'per_page' => $recordsPerPage,
]);

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
    <!-- Page Header with Pending Count Badge -->
    <div class="flex justify-between items-center mb-4">
        <div>
            <h4 class="mb-1"><?= $queueType ?> Approval Queue</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Approvals</li>
                </ol>
            </nav>
        </div>
        <div>
            <?php
            if ($pendingRequestsCount > 0):
            ?>
            <span class="loka-badge bg-warning fs-6">
                <i class="bi bi-hourglass-split me-1"></i><?= $pendingRequestsCount ?> Pending Action<?= $pendingRequestsCount > 1 ? 's' : '' ?>
            </span>
            <?php else: ?>
            <span class="loka-badge bg-success fs-6">
                <i class="bi bi-check-circle me-1"></i>All Caught Up
            </span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Tabs -->
    <div role="tablist" class="tabs tabs-bordered mb-4">
        <a role="tab" class="tab <?= $tab === 'pending' ? 'tab-active' : '' ?>" href="<?= APP_URL ?>/?page=approvals&tab=pending&p_pending=1">
            <i class="bi bi-hourglass-split me-1"></i>Pending
            <?php if ($pendingRequestsCount > 0): ?>
            <span class="loka-badge bg-warning ms-1"><?= $pendingRequestsCount ?></span>
            <?php endif; ?>
        </a>
        <a role="tab" class="tab <?= $tab === 'processed' ? 'tab-active' : '' ?>" href="<?= APP_URL ?>/?page=approvals&tab=processed&p_processed=1">
            <i class="bi bi-check-circle me-1"></i>Processed
            <?php if ($processedRequestsCount > 0): ?>
            <span class="loka-badge bg-secondary ms-1"><?= $processedRequestsCount ?></span>
            <?php endif; ?>
        </a>
    </div>

    <div class="loka-card mb-4">
        <div class="p-4 md:p-6">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="page" value="approvals">
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                <?= listSearchFieldHtml($searchQuery, 'Request #, requester, purpose, destination...') ?>
                <?= perPageFieldHtml($recordsPerPage) ?>
                <div class="flex gap-2">
                    <button type="submit" class="loka-btn-primary loka-btn-sm">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="<?= APP_URL ?>/?page=approvals&tab=<?= e($tab) ?>" class="loka-btn-secondary loka-btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($tab === 'pending'): ?>
    <!-- Pending Approvals -->
    <div class="loka-card">
        <div class="p-4 md:p-6">
            <?php if (empty($pendingRequests)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h5>No pending approvals</h5>
                <p class="text-base-content/60">All caught up! No requests awaiting your approval.</p>
            </div>
            <?php else: ?>
            <div class="loka-table-responsive">
                <table class="loka-table">
                    <thead>
                        <tr>
                            <?= tableSortTh('id', 'ID', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('requester', 'Requester', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('department', 'Department', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('purpose', 'Purpose', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('start_datetime', 'Date/Time', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('vehicle', 'Vehicle', $sort, $sortDir, $baseParams) ?>
                            <th>Stage Status</th>
                            <?= tableSortTh('created_at', 'Submitted', $sort, $sortDir, $baseParams) ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingRequests as $request): ?>
                        <tr class="<?= !$request->viewed_at ? 'table-warning' : '' ?>">
                            <td>
                                <strong>#<?= $request->id ?></strong>
                                <?php if (!$request->viewed_at): ?>
                                <span class="loka-badge bg-danger ms-1">NEW</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($request->requester_name) ?></td>
                            <td><span class="loka-badge bg-light text-dark"><?= e($request->department_name) ?></span></td>
                            <td><?= truncate($request->purpose, 30) ?></td>
                            <td>
                                <div><?= formatDateTime($request->start_datetime) ?></div>
                                <small class="text-base-content/60">to <?= formatDateTime($request->end_datetime) ?></small>
                            </td>
                            <td>
                                <?php if ($request->vehicle_plate): ?>
                                    <div class="fw-medium"><?= e($request->vehicle_plate) ?></div>
                                    <small class="text-base-content/60"><?= e($request->vehicle_make . ' ' . $request->vehicle_model) ?></small>
                                <?php else: ?>
                                    <span class="text-base-content/60">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($request->status === 'revision'): ?>
                                <div class="flex items-center gap-1">
                                    <span class="loka-badge bg-warning text-dark"><i class="bi bi-pencil-square me-1"></i>Revision</span>
                                    <span class="text-warning">Needs Update</span>
                                </div>
                                <?php elseif ($request->status === 'pending'): ?>
                                <div class="flex items-center gap-1">
                                    <span class="loka-badge bg-info" title="Department Approval">Dept</span>
                                    <span class="text-warning" title="Waiting for your action">
                                        <i class="bi bi-clock-history"></i> Pending
                                    </span>
                                </div>
                                <?php else: ?>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-1">
                                        <span class="loka-badge bg-light text-dark" title="Department Approval">Dept</span>
                                        <?php if ($request->dept_status === 'approved'): ?>
                                        <span class="text-success" title="Department approved"><i class="bi bi-check-circle"></i> Done</span>
                                        <?php elseif ($request->dept_status === 'rejected'): ?>
                                        <span class="text-danger" title="Department rejected"><i class="bi bi-x-circle"></i> Rejected</span>
                                        <?php elseif ($request->dept_status === 'revision'): ?>
                                        <span class="text-warning" title="Under revision"><i class="bi bi-arrow-repeat"></i> Revision</span>
                                        <?php else: ?>
                                        <span class="text-secondary"><i class="bi bi-dash-circle"></i> -</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="loka-badge bg-primary" title="Motorpool Approval">MP</span>
                                        <span class="text-warning" title="Waiting for your action">
                                            <i class="bi bi-clock-history"></i> Pending
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDateTime($request->created_at) ?></td>
                            <td>
                                <a href="<?= APP_URL ?>/?page=approvals&action=view&id=<?= $request->id ?>"
                                   class="loka-btn-primary loka-btn-sm">
                                    <i class="bi bi-eye me-1"></i>Review
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?= listPaginationFooter($pendingPag, $pendingPageParams, 'p_pending') ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Processed Approvals -->
    <div class="loka-card">
        <div class="p-4 md:p-6">
            <?php if (empty($processedRequests)): ?>
            <div class="empty-state">
                <i class="bi bi-clipboard-check"></i>
                <h5>No processed requests</h5>
                <p class="text-base-content/60">You haven't processed any requests yet.</p>
            </div>
            <?php else: ?>
                <div class="loka-table-responsive">
                <table class="loka-table">
                    <thead>
                        <tr>
                            <?= tableSortTh('id', 'ID', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('requester', 'Requester', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('purpose', 'Purpose', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('approval_type', 'Approval Level', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('my_action', 'Your Action', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('action_date', 'Action Date', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('status', 'Final Status', $sort, $sortDir, $baseParams) ?>
                            <th>View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($processedRequests as $request): ?>
                        <tr>
                            <td><strong>#<?= $request->id ?></strong></td>
                            <td><?= e($request->requester_name) ?></td>
                            <td><?= truncate($request->purpose, 25) ?></td>
                            <td>
                                <span class="loka-badge bg-<?= $request->approval_type === 'motorpool' ? 'primary' : 'info' ?>">
                                    <?= ucfirst($request->approval_type) ?>
                                </span>
                            </td>
                            <td>
                                <span class="loka-badge bg-<?= $request->my_action === 'approved' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($request->my_action) ?>
                                </span>
                            </td>
                            <td><?= formatDateTime($request->action_date) ?></td>
                            <td><?= requestStatusBadge($request->status) ?></td>
                            <td>
                                <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $request->id ?>"
                                   class="loka-btn-outline-primary loka-btn-sm">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?= listPaginationFooter($processedPag, $processedPageParams, 'p_processed') ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
