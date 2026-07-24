<?php
/**
 * LOKA - Gas Vouchers List Page
 */

if (!canAccessGasVouchers()) {
    redirectWith('/?page=dashboard', 'danger', 'You do not have permission to access this page.');
}

$pageTitle = 'Gas Vouchers';

// Determine what the user can see
$statusFilter = getSafe('status', '', 20);
$searchFilter = listSearchQuery();
$dateFrom     = getSafe('date_from', '', 20);
$dateTo       = getSafe('date_to', '', 20);

$whereClause = 'gv.deleted_at IS NULL';
$params = [];

// Non-admins see only their own vouchers unless they are approvers or Chief Admin/Finance
if (!isAdmin() && !isMotorpool() && !isApprover() && !isChiefAdminFinance()) {
    $whereClause .= ' AND gv.requested_by_user_id = ?';
    $params[] = userId();
}

if ($statusFilter) {
    $whereClause .= ' AND gv.status = ?';
    $params[] = $statusFilter;
}

if ($searchFilter) {
    $whereClause .= ' AND (gv.voucher_no LIKE ? OR gv.vehicle_plate LIKE ? OR gv.driver_name LIKE ? OR gv.purpose LIKE ? OR gv.fund_source LIKE ?)';
    $params = array_merge($params, ["%$searchFilter%", "%$searchFilter%", "%$searchFilter%", "%$searchFilter%", "%$searchFilter%"]);
}

if ($dateFrom) {
    $whereClause .= ' AND gv.request_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $whereClause .= ' AND gv.request_date <= ?';
    $params[] = $dateTo;
}

// Sorting (latest created first by default)
$allowedSortColumns = [
    'voucher_no' => 'gv.voucher_no',
    'request_date' => 'gv.request_date',
    'created_at' => 'gv.created_at',
    'status' => 'gv.status',
    'vehicle_plate' => 'gv.vehicle_plate',
    'driver_name' => 'gv.driver_name',
    'requester' => 'u.name',
];
$sortState = resolveTableSort($allowedSortColumns, 'created_at', 'DESC');
$sort = $sortState['key'];
$sortDir = $sortState['dir'];

$countRow = db()->fetch(
    "SELECT COUNT(*) as c FROM gas_vouchers gv WHERE {$whereClause}",
    $params
);
$pag = listPaginationState((int) ($countRow->c ?? 0));

$vouchers = db()->fetchAll(
    "SELECT gv.*,
            u.name AS requester_name,
            reviewer.name AS reviewer_name,
            approver.name AS approver_name_full
     FROM gas_vouchers gv
     JOIN users u ON gv.requested_by_user_id = u.id
     LEFT JOIN users reviewer ON gv.reviewed_by = reviewer.id
     LEFT JOIN users approver ON gv.approved_by = approver.id
     WHERE {$whereClause}
     ORDER BY {$sortState['orderSql']}
     LIMIT ? OFFSET ?",
    array_merge($params, [$pag['perPage'], $pag['offset']])
);

$baseParams = tableSortQueryParams($sortState, [
    'page' => 'gas-vouchers',
    'status' => $statusFilter,
    'q' => $searchFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'per_page' => $pag['perPage'],
]);

// Count by status for summary badges
$statusCounts = db()->fetchAll(
    "SELECT status, COUNT(*) as cnt FROM gas_vouchers WHERE deleted_at IS NULL GROUP BY status"
);
$counts = [];
foreach ($statusCounts as $s) {
    $counts[$s->status] = $s->cnt;
}

// Pending review (for OIC Motorpool / Motorpool Head)
$pendingReviewCount = 0;
if (isMotorpool() || isApprover() || isAdmin() || isChiefAdminFinance()) {
    $pendingReviewCount = $counts['pending_review'] ?? 0;
}

// Pending approval (for Chief Admin & Finance / Admin role)
$pendingApprovalCount = 0;
if (isAdmin() || isMotorpool() || isChiefAdminFinance()) {
    $pendingApprovalCount = $counts['pending_approval'] ?? 0;
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <i class="bi bi-fuel-pump"></i>Gas Vouchers
            </h1>
            <div class="text-sm text-base-content/60 mt-1">
                <a href="<?= APP_URL ?>" class="link link-primary">Dashboard</a>
                <span class="mx-1">/</span>
                <span>Gas Vouchers</span>
            </div>
        </div>
        <a href="<?= APP_URL ?>/?page=gas-vouchers&action=create" class="loka-btn loka-btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Gas Voucher
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="stat bg-base-100 rounded-box shadow-sm border border-base-200">
            <div class="stat-title">Pending Review</div>
            <div class="stat-value text-warning"><?= $counts['pending_review'] ?? 0 ?></div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow-sm border border-base-200">
            <div class="stat-title">Pending Approval</div>
            <div class="stat-value text-info"><?= $counts['pending_approval'] ?? 0 ?></div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow-sm border border-base-200">
            <div class="stat-title">Approved</div>
            <div class="stat-value text-success"><?= $counts['approved'] ?? 0 ?></div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow-sm border border-base-200">
            <div class="stat-title">Total Vouchers</div>
            <div class="stat-value text-secondary"><?= array_sum($counts) ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="loka-card mb-6">
        <div class="loka-card-body">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="page" value="gas-vouchers">

                <div class="w-full sm:w-auto">
                    <label class="label label-text text-xs">Status</label>
                    <select name="status" class="select select-bordered select-sm w-full sm:w-40">
                        <option value="">All</option>
                        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="pending_review" <?= $statusFilter === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                        <option value="pending_approval" <?= $statusFilter === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <?= listSearchFieldHtml($searchFilter, 'Voucher no, plate, driver, purpose...') ?>

                <div class="w-auto">
                    <label class="label label-text text-xs">Date From</label>
                    <input type="date" name="date_from" class="input input-bordered input-sm w-40" value="<?= e($dateFrom) ?>">
                </div>
                <div class="w-auto">
                    <label class="label label-text text-xs">Date To</label>
                    <input type="date" name="date_to" class="input input-bordered input-sm w-40" value="<?= e($dateTo) ?>">
                </div>

                <?= perPageFieldHtml($pag['perPage']) ?>

                <div class="flex gap-2">
                    <button type="submit" class="loka-btn loka-btn-primary loka-btn-sm">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="<?= APP_URL ?>/?page=gas-vouchers" class="loka-btn loka-btn-secondary loka-btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Vouchers Table -->
    <div class="loka-card">
        <div class="loka-card-body">
            <?php if (empty($vouchers)): ?>
            <div class="empty-state">
                <i class="bi bi-fuel-pump text-5xl text-base-content/20"></i>
                <h3 class="mt-3 text-lg font-semibold">No gas vouchers found</h3>
                <p class="text-sm text-base-content/50">Create your first gas voucher request to get started.</p>
                <a href="<?= APP_URL ?>/?page=gas-vouchers&action=create" class="loka-btn loka-btn-primary mt-2">
                    <i class="bi bi-plus-lg me-1"></i>New Gas Voucher
                </a>
            </div>
            <?php else: ?>
            <div class="loka-table-responsive">
                <table class="loka-table">
                    <thead>
                        <tr>
                            <?= tableSortTh('voucher_no', 'Voucher No.', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('request_date', 'Date', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('driver_name', 'Driver / Vehicle', $sort, $sortDir, $baseParams) ?>
                            <th>Fuel</th>
                            <th>Fund Source</th>
                            <th>Purpose</th>
                            <?= tableSortTh('status', 'Status', $sort, $sortDir, $baseParams) ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vouchers as $v): ?>
                        <tr>
                            <td>
                                <div class="font-bold text-primary"><?= e($v->voucher_no) ?></div>
                                <?php if (!isAdmin() && !isApprover() && !isMotorpool() && !isChiefAdminFinance()): ?>
                                <div class="text-xs text-base-content/50">by Me</div>
                                <?php else: ?>
                                <div class="text-xs text-base-content/50"><?= e($v->requester_name) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e(date('M d, Y', strtotime($v->request_date))) ?>
                                <?php if ($v->date_withdrawn): ?>
                                <div class="text-xs text-base-content/50">Withdrawn: <?= e(date('M d, Y', strtotime($v->date_withdrawn))) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="font-semibold"><?= e($v->driver_name) ?></div>
                                <span class="loka-badge badge-neutral text-xs"><?= e($v->vehicle_plate) ?></span>
                            </td>
                            <td>
                                <div><?= e($v->quantity) ?> <?= e($v->unit) ?></div>
                                <div class="text-xs text-base-content/50"><?= e($v->fuel_type) ?></div>
                            </td>
                            <td>
                                <span class="loka-badge badge-secondary"><?= e($v->fund_source) ?></span>
                            </td>
                            <td>
                                <span title="<?= e($v->purpose) ?>"><?= e(mb_substr($v->purpose, 0, 40)) ?><?= strlen($v->purpose) > 40 ? '…' : '' ?></span>
                            </td>
                            <td>
                                <?= gasVoucherStatusBadge($v->status) ?>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    <a href="<?= APP_URL ?>/?page=gas-vouchers&action=view&id=<?= $v->id ?>"
                                       class="loka-btn loka-btn-sm loka-btn-ghost text-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($v->status === 'draft' && ($v->requested_by_user_id == userId() || isAdmin())): ?>
                                    <a href="<?= APP_URL ?>/?page=gas-vouchers&action=edit&id=<?= $v->id ?>"
                                       class="loka-btn loka-btn-sm loka-btn-ghost" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($v->status === 'approved'): ?>
                                    <a href="<?= APP_URL ?>/?page=gas-vouchers&action=print&id=<?= $v->id ?>"
                                       class="loka-btn loka-btn-sm loka-btn-ghost text-success" title="Print Voucher" target="_blank">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (($v->status === 'pending_review' && (isMotorpool() || isApprover() || isAdmin() || isChiefAdminFinance())) ||
                                              ($v->status === 'pending_approval' && (isAdmin() || isMotorpool() || isChiefAdminFinance()))): ?>
                                    <a href="<?= APP_URL ?>/?page=gas-vouchers&action=approve&id=<?= $v->id ?>"
                                       class="loka-btn loka-btn-sm loka-btn-ghost text-warning" title="Process">
                                        <i class="bi bi-check-circle"></i>
                                    </a>
                                    <?php endif; ?>
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
