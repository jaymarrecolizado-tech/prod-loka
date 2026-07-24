<?php
/**
 * LOKA - My Trip Tickets Page
 *
 * Dedicated page for approvers to view trip tickets
 */

// Only approvers can access this page
if (!isApprover()) {
    redirectWith('/?page=dashboard', 'danger', 'This page is only for approvers.');
}

$pageTitle = 'My Trip Tickets';

// Get filter and search parameters
$statusFilter = get('status', '');
$searchQuery = listSearchQuery();

$fromSql = "FROM trip_tickets tt
     JOIN requests r ON tt.request_id = r.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN users dg ON tt.dispatch_guard_id = dg.id
     LEFT JOIN users ag ON tt.arrival_guard_id = ag.id
     LEFT JOIN users u_rev ON tt.reviewed_by = u_rev.id
     WHERE tt.deleted_at IS NULL";

$params = [];
$whereSql = '';

if ($statusFilter && in_array($statusFilter, ['draft', 'submitted', 'reviewed', 'approved'], true)) {
    $whereSql .= " AND tt.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $whereSql .= " AND (
        r.destination LIKE ? OR
        r.purpose LIKE ? OR
        tt.issues_description LIKE ? OR
        v.plate_number LIKE ? OR
        CAST(r.id AS CHAR) LIKE ?
    )";
    $searchParam = '%' . $searchQuery . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Sorting (latest created first by default)
$allowedSortColumns = [
    'id' => 'tt.id',
    'destination' => 'r.destination',
    'start_date' => 'tt.start_date',
    'plate_number' => 'v.plate_number',
    'status' => 'tt.status',
    'created_at' => 'tt.created_at',
];
$sortState = resolveTableSort($allowedSortColumns, 'created_at', 'DESC');
$sort = $sortState['key'];
$sortDir = $sortState['dir'];

$countRow = db()->fetch("SELECT COUNT(*) as c {$fromSql}{$whereSql}", $params);
$pag = listPaginationState((int) ($countRow->c ?? 0));

$tripTickets = db()->fetchAll(
    "SELECT tt.*,
            r.id as request_id, r.destination as trip_destination, r.purpose as trip_purpose,
            r.status as request_status,
            v.plate_number, v.make, v.model as vehicle_model,
            dg.name as dispatch_guard,
            ag.name as arrival_guard,
            u_rev.name as reviewed_by_name
     {$fromSql}{$whereSql}
     ORDER BY {$sortState['orderSql']}
     LIMIT ? OFFSET ?",
    array_merge($params, [$pag['perPage'], $pag['offset']])
);

$baseParams = tableSortQueryParams($sortState, [
    'page' => 'my-trip-tickets',
    'status' => $statusFilter,
    'q' => $searchQuery,
    'per_page' => $pag['perPage'],
]);

// Get counts for each status
$stats = [
    'all' => db()->fetchColumn("SELECT COUNT(*) FROM trip_tickets WHERE deleted_at IS NULL"),
    'draft' => db()->fetchColumn("SELECT COUNT(*) FROM trip_tickets WHERE status = 'draft' AND deleted_at IS NULL"),
    'submitted' => db()->fetchColumn("SELECT COUNT(*) FROM trip_tickets WHERE status = 'submitted' AND deleted_at IS NULL"),
    'reviewed' => db()->fetchColumn("SELECT COUNT(*) FROM trip_tickets WHERE status = 'reviewed' AND deleted_at IS NULL"),
    'approved' => db()->fetchColumn("SELECT COUNT(*) FROM trip_tickets WHERE status = 'approved' AND deleted_at IS NULL"),
];

// Approvers don't create tickets, so no completed trips section

require_once INCLUDES_PATH . '/header.php';
?>

<div class="p-4 md:p-6 space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold">
                <i class="bi bi-file-earmark-text mr-2"></i>My Trip Tickets
            </h1>
            <p class="text-base-content/60 mt-1">View and manage trip tickets</p>
        </div>
        <div class="flex gap-2">
            <a href="?page=my-trip-tickets&action=generate-summary" class="loka-btn-secondary">
                <i class="bi bi-file-earmark-spreadsheet mr-1"></i>Generate Vehicle Summary
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="loka-card">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="bg-primary/10 rounded-lg p-3">
                        <i class="bi bi-files text-primary text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h6 class="text-base-content/60 text-sm">All Tickets</h6>
                        <h3 class="text-2xl font-bold"><?= $stats['all'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="loka-card">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="bg-warning/10 rounded-lg p-3">
                        <i class="bi bi-clock text-warning text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h6 class="text-base-content/60 text-sm">Pending Review</h6>
                        <h3 class="text-2xl font-bold"><?= $stats['submitted'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="loka-card">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="bg-info/10 rounded-lg p-3">
                        <i class="bi bi-arrow-counterclockwise text-info text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h6 class="text-base-content/60 text-sm">Reviewed</h6>
                        <h3 class="text-2xl font-bold"><?= $stats['reviewed'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="loka-card">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="bg-success/10 rounded-lg p-3">
                        <i class="bi bi-check-circle text-success text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h6 class="text-base-content/60 text-sm">Approved</h6>
                        <h3 class="text-2xl font-bold"><?= $stats['approved'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="loka-card">
        <div class="p-6 space-y-4">
            <div>
                <label class="label">
                    <span class="label-text font-medium">Status Filter</span>
                </label>
                <div class="join w-full flex-wrap">
                    <a href="?page=my-trip-tickets"
                        class="join-item btn btn-sm <?= $statusFilter === '' ? 'btn-active' : '' ?>">
                        All (<?= $stats['all'] ?>)
                    </a>
                    <a href="?page=my-trip-tickets&status=submitted"
                        class="join-item btn btn-sm bg-warning text-warning-content <?= $statusFilter === 'submitted' ? 'btn-active' : '' ?>">
                        Pending (<?= $stats['submitted'] ?>)
                    </a>
                    <a href="?page=my-trip-tickets&status=reviewed"
                        class="join-item btn btn-sm bg-info text-info-content <?= $statusFilter === 'reviewed' ? 'btn-active' : '' ?>">
                        Reviewed (<?= $stats['reviewed'] ?>)
                    </a>
                    <a href="?page=my-trip-tickets&status=approved"
                        class="join-item btn btn-sm bg-success text-success-content <?= $statusFilter === 'approved' ? 'btn-active' : '' ?>">
                        Approved (<?= $stats['approved'] ?>)
                    </a>
                </div>
            </div>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="page" value="my-trip-tickets">
                <?php if ($statusFilter): ?>
                    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <?php endif; ?>
                <?= listSearchFieldHtml($searchQuery, 'Destination, purpose, plate, request #...') ?>
                <?= perPageFieldHtml($pag['perPage']) ?>
                <div class="flex gap-2">
                    <button type="submit" class="loka-btn-primary loka-btn-sm">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="?page=my-trip-tickets<?= $statusFilter ? '&status=' . e($statusFilter) : '' ?>" class="loka-btn-secondary loka-btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Trip Tickets List -->
    <div class="loka-card">
                    <div class="p-6">
                        <div class="font-bold text-lg mb-4">
                            <i class="bi bi-list-ul mr-2"></i>
                            My Trip Tickets
                        </div>
            <?php if (empty($tripTickets)): ?>
                <div class="text-center py-12">
                    <i class="bi bi-inbox text-4xl text-base-content/30"></i>
                    <p class="text-base-content/60 mt-4">
                        <?php if ($statusFilter): ?>
                            No trip tickets found with status "<?= ucfirst($statusFilter) ?>"
                        <?php elseif ($searchQuery): ?>
                            No trip tickets found matching "<?= e($searchQuery) ?>"
                        <?php else: ?>
                            You haven't created any trip tickets yet.
                            <br>
                            <a href="?page=my-trips" class="loka-btn-primary loka-btn-sm mt-3">
                                <i class="bi bi-calendar3 mr-1"></i>View My Trips
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="loka-table-responsive">
                    <table class="loka-table">
                        <thead>
                            <tr>
                                <?= tableSortTh('id', 'Ticket ID', $sort, $sortDir, $baseParams) ?>
                                <?= tableSortTh('destination', 'Destination', $sort, $sortDir, $baseParams) ?>
                                <?= tableSortTh('start_date', 'Date', $sort, $sortDir, $baseParams) ?>
                                <?= tableSortTh('plate_number', 'Vehicle', $sort, $sortDir, $baseParams) ?>
                                <?= tableSortTh('status', 'Status', $sort, $sortDir, $baseParams) ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tripTickets as $tt): ?>
                                <?php
                                $statusClass = '';
                                $statusIcon = '';
                                switch ($tt->status) {
                                    case 'draft':
                                        $statusClass = 'badge-ghost';
                                        $statusIcon = 'file-earmark';
                                        break;
                                    case 'submitted':
                                        $statusClass = 'badge-warning';
                                        $statusIcon = 'clock';
                                        break;
                                    case 'reviewed':
                                        $statusClass = 'badge-info';
                                        $statusIcon = 'arrow-counterclockwise';
                                        break;
                                    case 'approved':
                                        $statusClass = 'badge-success';
                                        $statusIcon = 'check-circle';
                                        break;
                                }

                                $tripTypeLabels = [
                                    'official' => ['label' => 'Official', 'color' => 'badge-success'],
                                    'personal' => ['label' => 'Personal', 'color' => 'badge-info'],
                                    'maintenance' => ['label' => 'Maintenance', 'color' => 'badge-warning'],
                                    'travel_order' => ['label' => 'Travel Order', 'color' => 'badge-primary'],
                                    'other' => ['label' => 'Other', 'color' => 'badge-ghost']
                                ];
                                $typeInfo = $tripTypeLabels[$tt->trip_type] ?? $tripTypeLabels['official'];
                                // Use custom label for "Other" type
                                if ($tt->trip_type === 'other' && !empty($tt->trip_type_other)) {
                                    $typeInfo['label'] = e($tt->trip_type_other);
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="font-bold">TT-<?= $tt->request_id ?></div>
                                        <div class="text-xs text-base-content/60">(Ref: VRF-<?= $tt->request_id ?>)</div>
                                    </td>
                                    <td>
                                        <div class="font-medium"><?= e($tt->trip_destination) ?></div>
                                        <div class="text-xs text-base-content/60"><?= truncate($tt->trip_purpose, 40) ?></div>
                                    </td>
                                    <td>
                                        <div class="text-sm">
                                            <i class="bi bi-calendar3 mr-1"></i>
                                            <?= formatDate($tt->start_date, 'M/d/Y') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="font-medium"><?= $tt->plate_number ?: 'N/A' ?></div>
                                        <div class="text-xs text-base-content/60"><?= $tt->make ?> <?= $tt->vehicle_model ?></div>
                                    </td>
                                    <td>
                                        <span class="loka-badge <?= $statusClass ?> gap-1">
                                            <i class="bi bi-<?= $statusIcon ?>"></i>
                                            <?= ucfirst($tt->status) ?>
                                        </span>
                                        <span class="loka-badge <?= $typeInfo['color'] ?>">
                                            <?= $typeInfo['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="join" role="group" aria-label="Ticket actions">
                                            <a href="?page=trip-tickets&action=view&id=<?= $tt->id ?>"
                                                class="join-item" title="View Ticket">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="?page=trip-tickets&action=export-pdf&id=<?= $tt->id ?>"
                                                class="join-item" title="Export PDF">
                                                <i class="bi bi-file-earmark-pdf"></i>
                                            </a>
                                            <a href="?page=trip-tickets&action=export-excel&id=<?= $tt->id ?>"
                                                class="join-item" title="Export Excel">
                                                <i class="bi bi-file-earmark-excel"></i>
                                            </a>
                                        </div>
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
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>