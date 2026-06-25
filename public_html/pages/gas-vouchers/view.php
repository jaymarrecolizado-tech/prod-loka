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
            rejector.name AS rejector_name,
            req_reviewer.name AS requested_reviewer_name,
            req_approver.name AS requested_approver_name
     FROM gas_vouchers gv
     JOIN users u ON gv.requested_by_user_id = u.id
     LEFT JOIN users reviewer ON gv.reviewed_by = reviewer.id
     LEFT JOIN users approver ON gv.approved_by = approver.id
     LEFT JOIN users rejector ON gv.rejected_by = rejector.id
     LEFT JOIN users req_reviewer ON gv.requested_reviewer_id = req_reviewer.id
     LEFT JOIN users req_approver ON gv.requested_approver_id = req_approver.id
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

<div class="loka-page">
    <div class="max-w-5xl mx-auto">

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="bi bi-fuel-pump"></i>Gas Voucher
                </h1>
                <div class="text-sm text-base-content/60 mt-1">
                    <a href="<?= APP_URL ?>" class="link link-primary">Dashboard</a>
                    <span class="mx-1">/</span>
                    <a href="<?= APP_URL ?>/?page=gas-vouchers" class="link link-primary">Gas Vouchers</a>
                    <span class="mx-1">/</span>
                    <span><?= e($voucher->voucher_no) ?></span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($voucher->status === 'approved'): ?>
                <a href="<?= APP_URL ?>/?page=gas-vouchers&action=print&id=<?= $voucher->id ?>"
                   class="loka-btn loka-btn-success" target="_blank">
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
                   class="loka-btn loka-btn-secondary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/?page=gas-vouchers" class="loka-btn loka-btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <!-- Status Banner -->
        <div class="alert alert-<?= gasVoucherStatusColor($voucher->status) ?> mb-6">
            <i class="bi bi-info-circle-fill text-lg"></i>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left Column: Main Details -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Voucher Details -->
                <div class="loka-card">
                    <div class="loka-card-header">
                        <h3 class="loka-card-title"><i class="bi bi-file-earmark-text me-2"></i>Voucher Details</h3>
                    </div>
                    <div class="loka-card-body">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <div class="text-xs text-base-content/50 mb-1">Voucher No.</div>
                                <div class="text-xl font-bold text-primary"><?= e($voucher->voucher_no) ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-base-content/50 mb-1">Request Date</div>
                                <div class="font-semibold"><?= e(date('M d, Y', strtotime($voucher->request_date))) ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-base-content/50 mb-1">Requested By</div>
                                <div class="font-semibold"><?= e($voucher->requester_name) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vehicle & Driver -->
                <div class="loka-card">
                    <div class="loka-card-header">
                        <h3 class="loka-card-title"><i class="bi bi-car-front me-2"></i>Vehicle & Driver</h3>
                    </div>
                    <div class="loka-card-body">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs text-base-content/50 mb-1">Driver (Bearer)</div>
                                <div class="font-semibold"><?= e($voucher->driver_name) ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-base-content/50 mb-1">Vehicle Plate No.</div>
                                <div class="font-semibold"><span class="badge badge-neutral text-lg"><?= e($voucher->vehicle_plate) ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Articles / Fuel -->
                <div class="loka-card">
                    <div class="loka-card-header bg-warning text-dark">
                        <h3 class="loka-card-title"><i class="bi bi-fuel-pump me-2"></i>Articles Requested</h3>
                    </div>
                    <div class="loka-card-body p-0">
                        <div class="loka-table-responsive">
                            <table class="loka-table">
                                <thead>
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
                <div class="loka-card">
                    <div class="loka-card-header">
                        <h3 class="loka-card-title"><i class="bi bi-clipboard-data me-2"></i>Fund & Purpose</h3>
                    </div>
                    <div class="loka-card-body">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <div class="text-xs text-base-content/50 mb-1">Fund Source</div>
                                <span class="badge badge-secondary text-lg"><?= e($voucher->fund_source) ?></span>
                                <div class="text-xs text-base-content/50 mt-1">Project/program the fuel is derived from</div>
                            </div>
                            <?php if ($voucher->chargeable_against): ?>
                            <div>
                                <div class="text-xs text-base-content/50 mb-1">Chargeable Against</div>
                                <span class="badge badge-secondary text-lg"><?= e($voucher->chargeable_against) ?></span>
                                <div class="text-xs text-base-content/50 mt-1">Specific project/budget the fuel is charged to</div>
                            </div>
                            <?php endif; ?>
                            <?php if ($voucher->saro_no): ?>
                            <div>
                                <div class="text-xs text-base-content/50 mb-1">SARO No.</div>
                                <div class="font-semibold"><?= e($voucher->saro_no) ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="sm:col-span-3">
                                <div class="text-xs text-base-content/50 mb-1">Purpose</div>
                                <p class="mb-0"><?= e($voucher->purpose) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Workflow -->
            <div class="space-y-6">

                <!-- Approval Workflow -->
                <div class="loka-card">
                    <div class="loka-card-header">
                        <h3 class="loka-card-title"><i class="bi bi-diagram-3 me-2"></i>Approval Workflow</h3>
                    </div>
                    <div class="loka-card-body p-0">
                        <ul class="divide-y divide-base-200">

                            <!-- Step 1: Submitted -->
                            <li class="flex items-start gap-3 p-4">
                                <div class="text-success mt-1"><i class="bi bi-check-circle-fill text-lg"></i></div>
                                <div>
                                    <div class="font-semibold">Submitted</div>
                                    <div class="text-xs text-base-content/50">by <?= e($voucher->requester_name) ?></div>
                                    <div class="text-xs text-base-content/50"><?= e(date('M d, Y h:i A', strtotime($voucher->created_at))) ?></div>
                                </div>
                            </li>

                            <!-- Step 2: Reviewed by OIC Motorpool -->
                            <li class="flex items-start gap-3 p-4">
                                <?php if (in_array($voucher->status, ['pending_approval', 'approved', 'rejected']) && $voucher->reviewed_by): ?>
                                <div class="text-success mt-1"><i class="bi bi-check-circle-fill text-lg"></i></div>
                                <div>
                                    <div class="font-semibold">Reviewed</div>
                                    <div class="text-xs text-base-content/50">by <?= e($voucher->reviewer_name) ?> (OIC, Motor Pool)</div>
                                    <div class="text-xs text-base-content/50"><?= e(date('M d, Y h:i A', strtotime($voucher->reviewed_at))) ?></div>
                                    <?php if ($voucher->reviewer_notes): ?>
                                    <div class="mt-1 text-xs text-base-content/50 italic">"<?= e($voucher->reviewer_notes) ?>"</div>
                                    <?php endif; ?>
                                    <?php if ($voucher->requested_reviewer_name && $voucher->requested_reviewer_name !== $voucher->reviewer_name): ?>
                                    <div class="mt-1 text-xs text-base-content/50">Motorpool Head: <?= e($voucher->requested_reviewer_name) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php elseif ($voucher->status === 'pending_review'): ?>
                                <div class="text-warning mt-1"><i class="bi bi-hourglass-split text-lg"></i></div>
                                <div>
                                    <div class="font-semibold">Pending Review</div>
                                    <div class="text-xs text-base-content/50">OIC, Motor Pool Unit</div>
                                </div>
                                <?php else: ?>
                                <div class="text-base-content/30 mt-1"><i class="bi bi-circle text-lg"></i></div>
                                <div class="text-base-content/50">
                                    <div class="font-semibold">Review</div>
                                    <div class="text-xs">OIC, Motor Pool Unit</div>
                                </div>
                                <?php endif; ?>
                            </li>

                            <!-- Step 3: Approved by Chief Admin & Finance -->
                            <li class="flex items-start gap-3 p-4">
                                <?php if ($voucher->status === 'approved'): ?>
                                <div class="text-success mt-1"><i class="bi bi-check-circle-fill text-lg"></i></div>
                                <div>
                                    <div class="font-semibold">Approved</div>
                                    <div class="text-xs text-base-content/50">by <?= e($voucher->approver_name_full) ?> (Chief, Admin & Finance)</div>
                                    <div class="text-xs text-base-content/50"><?= e(date('M d, Y h:i A', strtotime($voucher->approved_at))) ?></div>
                                    <?php if ($voucher->approver_notes): ?>
                                    <div class="mt-1 text-xs text-base-content/50 italic">"<?= e($voucher->approver_notes) ?>"</div>
                                    <?php endif; ?>
                                    <?php if ($voucher->requested_approver_name && $voucher->requested_approver_name !== $voucher->approver_name_full): ?>
                                    <div class="mt-1 text-xs text-base-content/50">Chief Admin & Finance: <?= e($voucher->requested_approver_name) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php elseif ($voucher->status === 'rejected'): ?>
                                <div class="text-error mt-1"><i class="bi bi-x-circle-fill text-lg"></i></div>
                                <div>
                                    <div class="font-semibold text-error">Rejected</div>
                                    <div class="text-xs text-base-content/50">by <?= e($voucher->rejector_name ?? 'N/A') ?></div>
                                    <?php if ($voucher->rejection_reason): ?>
                                    <div class="mt-1 text-xs text-error"><?= e($voucher->rejection_reason) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php elseif ($voucher->status === 'pending_approval'): ?>
                                <div class="text-warning mt-1"><i class="bi bi-hourglass-split text-lg"></i></div>
                                <div>
                                    <div class="font-semibold">Pending Approval</div>
                                    <div class="text-xs text-base-content/50">Chief, Admin. and Finance Division</div>
                                </div>
                                <?php else: ?>
                                <div class="text-base-content/30 mt-1"><i class="bi bi-circle text-lg"></i></div>
                                <div class="text-base-content/50">
                                    <div class="font-semibold">Final Approval</div>
                                    <div class="text-xs">Chief, Admin. and Finance Division</div>
                                </div>
                                <?php endif; ?>
                            </li>

                        </ul>
                    </div>
                </div>

                <!-- Payment Status -->
                <div class="loka-card">
                    <div class="loka-card-header">
                        <h3 class="loka-card-title"><i class="bi bi-cash me-2"></i>Payment Status</h3>
                    </div>
                    <div class="loka-card-body text-center">
                        <?php
                        $payColors = ['unpaid' => 'badge-warning', 'paid' => 'badge-success', 'cancelled' => 'badge-error', 'processed' => 'badge-info'];
                        $payColor = $payColors[$voucher->payment_status] ?? 'badge-neutral';
                        ?>
                        <span class="badge <?= $payColor ?> text-lg px-4 py-2">
                            <?= ucfirst($voucher->payment_status) ?>
                        </span>
                        <?php if ($voucher->date_withdrawn): ?>
                        <div class="mt-2 text-xs text-base-content/50">Withdrawn: <?= e(date('M d, Y', strtotime($voucher->date_withdrawn))) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ((isAdmin() || isChiefAdminFinance()) && $voucher->status === 'approved'): ?>
                    <div class="loka-card-footer">
                        <form method="POST" action="<?= APP_URL ?>/?page=gas-vouchers&action=update-payment&id=<?= $voucher->id ?>">
                            <?= csrfField() ?>
                            <div class="flex gap-2">
                                <select name="payment_status" class="select select-bordered select-sm flex-1">
                                    <option value="unpaid" <?= $voucher->payment_status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                    <option value="paid" <?= $voucher->payment_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                                    <option value="processed" <?= $voucher->payment_status === 'processed' ? 'selected' : '' ?>>Processed</option>
                                    <option value="cancelled" <?= $voucher->payment_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                                <button type="submit" class="loka-btn loka-btn-primary loka-btn-sm">Update</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Process Actions -->
                <?php if (($voucher->status === 'pending_review' && (isMotorpool() || isApprover() || isAdmin() || isChiefAdminFinance())) ||
                          ($voucher->status === 'pending_approval' && (isAdmin() || isMotorpool() || isChiefAdminFinance()))): ?>
                <div class="loka-card border-2 border-warning">
                    <div class="loka-card-header bg-warning text-dark">
                        <h3 class="loka-card-title"><i class="bi bi-check-circle me-2"></i>Process This Voucher</h3>
                    </div>
                    <div class="loka-card-body">
                        <a href="<?= APP_URL ?>/?page=gas-vouchers&action=approve&id=<?= $voucher->id ?>"
                           class="loka-btn loka-btn-warning w-full">
                            <i class="bi bi-pencil-square me-1"></i>Review / Approve
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Cancel button for owner -->
                <?php if (in_array($voucher->status, ['draft', 'pending_review']) && $voucher->requested_by_user_id == userId()): ?>
                <div class="loka-card border-2 border-error mt-3">
                    <div class="loka-card-body">
                        <form method="POST" action="<?= APP_URL ?>/?page=gas-vouchers&action=cancel&id=<?= $voucher->id ?>"
                              onsubmit="return confirm('Cancel this gas voucher request?')">
                            <?= csrfField() ?>
                            <button type="submit" class="loka-btn loka-btn-outline-error w-full">
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

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
