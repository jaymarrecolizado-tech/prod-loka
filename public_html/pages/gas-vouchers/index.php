<?php
/**
 * LOKA - Gas Vouchers List Page
 */

requireAnyRole([ROLE_REQUESTER, ROLE_APPROVER, ROLE_MOTORPOOL, ROLE_ADMIN, ROLE_CHIEF_ADMIN_FINANCE]);

$pageTitle = 'Gas Vouchers';

// Determine what the user can see
$statusFilter = getSafe('status', '', 20);
$searchFilter = getSafe('search', '', 100);
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
     ORDER BY gv.created_at DESC",
    $params
);

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

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-fuel-pump me-2"></i>Gas Vouchers</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Gas Vouchers</li>
                </ol>
            </nav>
        </div>
        <a href="<?= APP_URL ?>/?page=gas-vouchers&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Gas Voucher
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-warning"><?= $counts['pending_review'] ?? 0 ?></div>
                    <small class="text-muted">Pending Review</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-info"><?= $counts['pending_approval'] ?? 0 ?></div>
                    <small class="text-muted">Pending Approval</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-success"><?= $counts['approved'] ?? 0 ?></div>
                    <small class="text-muted">Approved</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-secondary"><?= array_sum($counts) ?></div>
                    <small class="text-muted">Total Vouchers</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card table-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="gas-vouchers">

                <div class="col-12 col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="pending_review" <?= $statusFilter === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                        <option value="pending_approval" <?= $statusFilter === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Voucher no, plate, driver, purpose..."
                           value="<?= e($searchFilter) ?>">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                </div>

                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-sm btn-outline-primary me-2">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="<?= APP_URL ?>/?page=gas-vouchers" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Vouchers Table -->
    <div class="card table-card">
        <div class="card-body">
            <?php if (empty($vouchers)): ?>
            <div class="empty-state">
                <i class="bi bi-fuel-pump fs-1 text-muted"></i>
                <h5 class="mt-3">No gas vouchers found</h5>
                <p class="text-muted">Create your first gas voucher request to get started.</p>
                <a href="<?= APP_URL ?>/?page=gas-vouchers&action=create" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>New Gas Voucher
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover data-table">
                    <thead>
                        <tr>
                            <th>Voucher No.</th>
                            <th>Date</th>
                            <th>Driver / Vehicle</th>
                            <th>Fuel</th>
                            <th>Fund Source</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Total Cost</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vouchers as $v): ?>
                        <tr>
                            <td>
                                <strong class="text-primary"><?= e($v->voucher_no) ?></strong>
                                <?php if (!isAdmin() && !isApprover() && !isMotorpool() && !isChiefAdminFinance()): ?>
                                <br><small class="text-muted">by Me</small>
                                <?php else: ?>
                                <br><small class="text-muted"><?= e($v->requester_name) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e(date('M d, Y', strtotime($v->request_date))) ?>
                                <?php if ($v->date_withdrawn): ?>
                                <br><small class="text-muted">Withdrawn: <?= e(date('M d, Y', strtotime($v->date_withdrawn))) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($v->driver_name) ?></div>
                                <span class="badge bg-light text-dark"><?= e($v->vehicle_plate) ?></span>
                            </td>
                            <td>
                                <div><?= e($v->quantity) ?> <?= e($v->unit) ?></div>
                                <small class="text-muted"><?= e($v->fuel_type) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= e($v->fund_source) ?></span>
                            </td>
                            <td>
                                <span title="<?= e($v->purpose) ?>"><?= e(mb_substr($v->purpose, 0, 40)) ?><?= strlen($v->purpose) > 40 ? '…' : '' ?></span>
                            </td>
                            <td>
                                <?= gasVoucherStatusBadge($v->status) ?>
                            </td>
                            <td>
                                <?php if ($v->total_cost): ?>
                                ₱<?= number_format($v->total_cost, 2) ?>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= APP_URL ?>/?page=gas-vouchers&action=view&id=<?= $v->id ?>"
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($v->status === 'draft' && ($v->requested_by_user_id == userId() || isAdmin())): ?>
                                    <a href="<?= APP_URL ?>/?page=gas-vouchers&action=edit&id=<?= $v->id ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($v->status === 'approved'): ?>
                                    <a href="<?= APP_URL ?>/?page=gas-vouchers&action=print&id=<?= $v->id ?>"
                                       class="btn btn-sm btn-outline-success" title="Print Voucher" target="_blank">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (($v->status === 'pending_review' && (isMotorpool() || isApprover() || isAdmin() || isChiefAdminFinance())) ||
                                              ($v->status === 'pending_approval' && (isAdmin() || isMotorpool() || isChiefAdminFinance()))): ?>
                                    <a href="<?= APP_URL ?>/?page=gas-vouchers&action=approve&id=<?= $v->id ?>"
                                       class="btn btn-sm btn-outline-warning" title="Process">
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
