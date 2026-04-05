<?php
/**
 * LOKA - Gas Voucher View Page
 */

$voucherId = (int) get('id', 0);
if (!$voucherId) {
    redirectWith('/?page=gas-vouchers', 'danger', 'Invalid voucher ID.');
}

$voucher = db()->fetch(
    "SELECT gv.*,
            u.name AS requester_name, u.email AS requester_email,
            reviewer.name AS reviewer_name,
            approver.name AS approver_name_full,
            rejector.name AS rejector_name
     FROM gas_vouchers gv
     JOIN users u ON gv.requested_by_user_id = u.id
     LEFT JOIN users reviewer ON gv.reviewed_by = reviewer.id
     LEFT JOIN users approver ON gv.approved_by = approver.id
     LEFT JOIN users rejector ON gv.rejected_by = rejector.id
     WHERE gv.id = ? AND gv.deleted_at IS NULL",
    [$voucherId]
);

if (!$voucher) {
    redirectWith('/?page=gas-vouchers', 'danger', 'Gas voucher not found.');
}

// Access control: only owner, admin, approvers, or Chief Admin/Finance can view
if ($voucher->requested_by_user_id != userId() && !isAdmin() && !isApprover() && !isMotorpool() && !isChiefAdminFinance()) {
    redirectWith('/?page=gas-vouchers', 'danger', 'Access denied.');
}

$pageTitle = 'Gas Voucher: ' . $voucher->voucher_no;

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-fuel-pump me-2"></i>Gas Voucher</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=gas-vouchers">Gas Vouchers</a></li>
                            <li class="breadcrumb-item active"><?= e($voucher->voucher_no) ?></li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($voucher->status === 'approved'): ?>
                    <a href="<?= APP_URL ?>/?page=gas-vouchers&action=print&id=<?= $voucher->id ?>"
                       class="btn btn-success" target="_blank">
                        <i class="bi bi-printer me-1"></i>Print Voucher
                    </a>
                    <?php endif; ?>
                    <?php
                    $canEditView = false;
                    if ($voucher->status === 'draft' && ($voucher->requested_by_user_id == userId() || isAdmin())) {
                        $canEditView = true;
                    } elseif (in_array($voucher->status, ['pending_review', 'pending_approval']) && (isApprover() || isMotorpool() || isAdmin() || isChiefAdminFinance())) {
                        $canEditView = true;
                    }
                    if ($canEditView):
                    ?>
                    <a href="<?= APP_URL ?>/?page=gas-vouchers&action=edit&id=<?= $voucher->id ?>"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/?page=gas-vouchers" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>

            <!-- Status Banner -->
            <div class="alert alert-<?= gasVoucherStatusColor($voucher->status) ?> d-flex align-items-center mb-4">
                <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                <div>
                    <strong>Status: <?= gasVoucherStatusLabel($voucher->status) ?></strong>
                    <?php if ($voucher->status === 'pending_review'): ?>
                    — Awaiting review by OIC, Motor Pool Unit.
                    <?php elseif ($voucher->status === 'pending_approval'): ?>
                    — Awaiting final approval by Chief, Admin. and Finance Division.
                    <?php elseif ($voucher->status === 'approved'): ?>
                    — This voucher is authorized. Bearer may secure the fuel/items.
                    <?php elseif ($voucher->status === 'rejected'): ?>
                    — This voucher has been rejected. <?= $voucher->rejection_reason ? 'Reason: ' . e($voucher->rejection_reason) : '' ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-4">

                <!-- Left Column: Main Details -->
                <div class="col-lg-8">

                    <!-- Voucher Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Voucher Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Voucher No.</small>
                                    <strong class="fs-5 text-primary"><?= e($voucher->voucher_no) ?></strong>
                                </div>
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Request Date</small>
                                    <strong><?= e(date('M d, Y', strtotime($voucher->request_date))) ?></strong>
                                </div>
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Requested By</small>
                                    <strong><?= e($voucher->requester_name) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle & Driver -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-car-front me-2"></i>Vehicle & Driver</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Driver (Bearer)</small>
                                    <strong><?= e($voucher->driver_name) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Vehicle Plate No.</small>
                                    <strong><span class="badge bg-dark fs-6"><?= e($voucher->vehicle_plate) ?></span></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Articles / Fuel -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="bi bi-fuel-pump me-2"></i>Articles Requested</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Qty</th>
                                            <th>Unit</th>
                                            <th>Article</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?= e($voucher->quantity) ?></td>
                                            <td><?= e($voucher->unit) ?></td>
                                            <td><?= e($voucher->fuel_type) ?></td>
                                        </tr>
                                        <?php if ($voucher->other_items || $voucher->other_qty || $voucher->other_unit): ?>
                                        <tr>
                                            <td><?= e($voucher->other_qty) ?></td>
                                            <td><?= e($voucher->other_unit) ?></td>
                                            <td><?= e($voucher->other_items) ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Purpose & Fund -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Fund & Purpose</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Fund Source</small>
                                    <span class="badge bg-secondary fs-6"><?= e($voucher->fund_source) ?></span>
                                    <div class="form-text mt-1">Project/program the fuel is derived from</div>
                                </div>
                                <?php if ($voucher->chargeable_against): ?>
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Chargeable Against</small>
                                    <span class="badge bg-secondary fs-6"><?= e($voucher->chargeable_against) ?></span>
                                    <div class="form-text mt-1">Specific project/budget the fuel is charged to</div>
                                </div>
                                <?php endif; ?>
                                <?php if ($voucher->saro_no): ?>
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">SARO No.</small>
                                    <strong><?= e($voucher->saro_no) ?></strong>
                                </div>
                                <?php endif; ?>
                                <div class="col-12">
                                    <small class="text-muted d-block">Purpose</small>
                                    <p class="mb-0"><?= e($voucher->purpose) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Workflow -->
                <div class="col-lg-4">

                    <!-- Approval Workflow -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Approval Workflow</h6>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">

                                <!-- Step 1: Submitted -->
                                <li class="list-group-item d-flex align-items-start gap-3 py-3">
                                    <div class="text-success mt-1"><i class="bi bi-check-circle-fill fs-5"></i></div>
                                    <div>
                                        <div class="fw-semibold">Submitted</div>
                                        <small class="text-muted">by <?= e($voucher->requester_name) ?></small><br>
                                        <small class="text-muted"><?= e(date('M d, Y h:i A', strtotime($voucher->created_at))) ?></small>
                                    </div>
                                </li>

                                <!-- Step 2: Reviewed by OIC Motorpool -->
                                <li class="list-group-item d-flex align-items-start gap-3 py-3">
                                    <?php if (in_array($voucher->status, ['pending_approval', 'approved', 'rejected']) && $voucher->reviewed_by): ?>
                                    <div class="text-success mt-1"><i class="bi bi-check-circle-fill fs-5"></i></div>
                                    <div>
                                        <div class="fw-semibold">Reviewed</div>
                                        <small class="text-muted">by <?= e($voucher->reviewer_name) ?> (OIC, Motor Pool)</small><br>
                                        <small class="text-muted"><?= e(date('M d, Y h:i A', strtotime($voucher->reviewed_at))) ?></small>
                                        <?php if ($voucher->reviewer_notes): ?>
                                        <div class="mt-1"><em class="text-muted small">"<?= e($voucher->reviewer_notes) ?>"</em></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php elseif ($voucher->status === 'pending_review'): ?>
                                    <div class="text-warning mt-1"><i class="bi bi-hourglass-split fs-5"></i></div>
                                    <div>
                                        <div class="fw-semibold">Pending Review</div>
                                        <small class="text-muted">OIC, Motor Pool Unit</small>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-muted mt-1"><i class="bi bi-circle fs-5"></i></div>
                                    <div class="text-muted">
                                        <div class="fw-semibold">Review</div>
                                        <small>OIC, Motor Pool Unit</small>
                                    </div>
                                    <?php endif; ?>
                                </li>

                                <!-- Step 3: Approved by Chief Admin & Finance -->
                                <li class="list-group-item d-flex align-items-start gap-3 py-3">
                                    <?php if ($voucher->status === 'approved'): ?>
                                    <div class="text-success mt-1"><i class="bi bi-check-circle-fill fs-5"></i></div>
                                    <div>
                                        <div class="fw-semibold">Approved</div>
                                        <small class="text-muted">by <?= e($voucher->approver_name_full) ?> (Chief, Admin & Finance)</small><br>
                                        <small class="text-muted"><?= e(date('M d, Y h:i A', strtotime($voucher->approved_at))) ?></small>
                                        <?php if ($voucher->approver_notes): ?>
                                        <div class="mt-1"><em class="text-muted small">"<?= e($voucher->approver_notes) ?>"</em></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php elseif ($voucher->status === 'rejected'): ?>
                                    <div class="text-danger mt-1"><i class="bi bi-x-circle-fill fs-5"></i></div>
                                    <div>
                                        <div class="fw-semibold text-danger">Rejected</div>
                                        <small class="text-muted">by <?= e($voucher->rejector_name ?? 'N/A') ?></small>
                                        <?php if ($voucher->rejection_reason): ?>
                                        <div class="mt-1 text-danger small"><?= e($voucher->rejection_reason) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php elseif ($voucher->status === 'pending_approval'): ?>
                                    <div class="text-warning mt-1"><i class="bi bi-hourglass-split fs-5"></i></div>
                                    <div>
                                        <div class="fw-semibold">Pending Approval</div>
                                        <small class="text-muted">Chief, Admin. and Finance Division</small>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-muted mt-1"><i class="bi bi-circle fs-5"></i></div>
                                    <div class="text-muted">
                                        <div class="fw-semibold">Final Approval</div>
                                        <small>Chief, Admin. and Finance Division</small>
                                    </div>
                                    <?php endif; ?>
                                </li>

                            </ul>
                        </div>
                    </div>

                    <!-- Payment Status -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-cash me-2"></i>Payment Status</h6>
                        </div>
                        <div class="card-body text-center">
                            <?php
                            $payColors = ['unpaid' => 'warning', 'paid' => 'success', 'cancelled' => 'danger', 'processed' => 'info'];
                            $payColor = $payColors[$voucher->payment_status] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $payColor ?> fs-6 px-4 py-2">
                                <?= ucfirst($voucher->payment_status) ?>
                            </span>
                            <?php if ($voucher->date_withdrawn): ?>
                            <div class="mt-2 text-muted small">Withdrawn: <?= e(date('M d, Y', strtotime($voucher->date_withdrawn))) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ((isAdmin() || isChiefAdminFinance()) && $voucher->status === 'approved'): ?>
                        <div class="card-footer">
                            <form method="POST" action="<?= APP_URL ?>/?page=gas-vouchers&action=update-payment&id=<?= $voucher->id ?>">
                                <?= csrfField() ?>
                                <div class="input-group input-group-sm">
                                    <select name="payment_status" class="form-select">
                                        <option value="unpaid" <?= $voucher->payment_status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                        <option value="paid" <?= $voucher->payment_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                                        <option value="processed" <?= $voucher->payment_status === 'processed' ? 'selected' : '' ?>>Processed</option>
                                        <option value="cancelled" <?= $voucher->payment_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Process Actions -->
                    <?php if (($voucher->status === 'pending_review' && (isMotorpool() || isApprover() || isAdmin() || isChiefAdminFinance())) ||
                              ($voucher->status === 'pending_approval' && (isAdmin() || isMotorpool() || isChiefAdminFinance()))): ?>
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="bi bi-check-circle me-2"></i>Process This Voucher</h6>
                        </div>
                        <div class="card-body">
                            <a href="<?= APP_URL ?>/?page=gas-vouchers&action=approve&id=<?= $voucher->id ?>"
                               class="btn btn-warning d-block">
                                <i class="bi bi-pencil-square me-1"></i>Review / Approve
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Cancel button for owner -->
                    <?php if (in_array($voucher->status, ['draft', 'pending_review']) && $voucher->requested_by_user_id == userId()): ?>
                    <div class="card border-danger mt-3">
                        <div class="card-body">
                            <form method="POST" action="<?= APP_URL ?>/?page=gas-vouchers&action=cancel&id=<?= $voucher->id ?>"
                                  onsubmit="return confirm('Cancel this gas voucher request?')">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-outline-danger d-block w-100">
                                    <i class="bi bi-x-circle me-1"></i>Cancel Voucher
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
