<?php
/**
 * LOKA - View Request Page
 */

$requestId = (int) get('id');

// Get request with all related data
$request = db()->fetch(
    "SELECT r.*,
            u.name as requester_name, u.email as requester_email, u.phone as requester_phone,
            d.name as department_name,
            v.plate_number, v.make, v.model as vehicle_model, v.color as vehicle_color,
            vt.name as vehicle_type,
            dr.license_number as driver_license,
            (SELECT name FROM users WHERE id = dr.user_id) as driver_name,
            (SELECT phone FROM users WHERE id = dr.user_id) as driver_phone,
            appr.name as approver_name,
            mph.name as motorpool_head_name,
            dg.name as dispatch_guard_name,
            ag.name as arrival_guard_name
     FROM requests r
     JOIN users u ON r.user_id = u.id
     JOIN departments d ON r.department_id = d.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
     LEFT JOIN drivers dr ON r.driver_id = dr.id AND dr.deleted_at IS NULL
     LEFT JOIN users appr ON r.approver_id = appr.id
     LEFT JOIN users mph ON r.motorpool_head_id = mph.id
     LEFT JOIN users dg ON r.dispatch_guard_id = dg.id
     LEFT JOIN users ag ON r.arrival_guard_id = ag.id
     WHERE r.id = ? AND r.deleted_at IS NULL",
    [$requestId]
);

if (!$request) {
    redirectWith('/?page=requests', 'danger', 'Request not found.');
}

// Check access - only owner or approver/admin can view
if ($request->user_id !== userId() && !isApprover()) {
    redirectWith('/?page=requests', 'danger', 'You do not have permission to view this request.');
}

// Mark notifications as read when requester views their request
if ($request->user_id === userId()) {
    db()->update('notifications', 
        ['is_read' => 1], 
        'user_id = ? AND link LIKE ? AND is_read = 0',
        [userId(), '%page=requests%action=view%id=' . $requestId . '%']
    );
}

// Get approval history
$approvals = db()->fetchAll(
    "SELECT a.*, u.name as approver_name
     FROM approvals a
     JOIN users u ON a.approver_id = u.id
     WHERE a.request_id = ?
     ORDER BY a.created_at ASC",
    [$requestId]
);

// Get workflow status
$workflow = db()->fetch(
    "SELECT * FROM approval_workflow WHERE request_id = ?",
    [$requestId]
);

// Get passengers (users and guests)
$passengers = db()->fetchAll(
    "SELECT rp.user_id, u.id as user_table_id, u.name, u.email, d.name as department_name, rp.guest_name
     FROM request_passengers rp
     LEFT JOIN users u ON rp.user_id = u.id
     LEFT JOIN departments d ON u.department_id = d.id
     WHERE rp.request_id = ?
     ORDER BY u.name, rp.guest_name",
    [$requestId]
);

// Get assignment history
$assignmentHistory = db()->fetchAll(
    "SELECT ah.*, 
            u.name as assigned_by_name,
            v.plate_number, v.make, v.model as vehicle_model,
            pv.plate_number as prev_plate_number, pv.make as prev_make, pv.model as prev_vehicle_model,
            d_user.name as driver_name,
            pd_user.name as prev_driver_name
     FROM assignment_history ah
     JOIN users u ON ah.assigned_by = u.id
     LEFT JOIN vehicles v ON ah.vehicle_id = v.id
     LEFT JOIN vehicles pv ON ah.previous_vehicle_id = pv.id
     LEFT JOIN drivers d ON ah.driver_id = d.id
     LEFT JOIN users d_user ON d.user_id = d_user.id
     LEFT JOIN drivers pd ON ah.previous_driver_id = pd.id
     LEFT JOIN users pd_user ON pd.user_id = pd_user.id
     WHERE ah.request_id = ?
     ORDER BY ah.created_at ASC",
    [$requestId]
);

$pageTitle = 'Request #' . $requestId;

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <div>
            <h2 class="text-xl font-semibold mb-1">Request #<?= $requestId ?></h2>
            <div class="text-sm text-base-content/60">
                <a href="<?= APP_URL ?>" class="hover:text-primary">Dashboard</a>
                <span class="mx-1">/</span>
                <a href="<?= APP_URL ?>/?page=requests" class="hover:text-primary">Requests</a>
                <span class="mx-1">/</span>
                <span>View</span>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= APP_URL ?>/?page=requests&action=print&id=<?= $requestId ?>"
                class="loka-btn-secondary" target="_blank">
                <i class="bi bi-printer mr-1"></i>Print Form
            </a>

            <?php if ($request->user_id === userId() && in_array($request->status, [STATUS_PENDING, STATUS_DRAFT])): ?>
                <a href="<?= APP_URL ?>/?page=requests&action=edit&id=<?= $requestId ?>"
                    class="loka-btn-primary">
                    <i class="bi bi-pencil mr-1"></i>Edit
                </a>
            <?php endif; ?>

            <?php if ($request->user_id === userId() && !in_array($request->status, [STATUS_COMPLETED, STATUS_CANCELLED])): ?>
                <button type="button" class="btn btn-outline btn-error" onclick="document.getElementById('cancelRequestModal').showModal()">
                    <i class="bi bi-x-circle mr-1"></i>Cancel Request
                </button>
            <?php endif; ?>

            <?php if (isMotorpool() && $request->status === STATUS_APPROVED): ?>
                <button type="button" class="btn btn-warning" onclick="document.getElementById('overrideModal').showModal()">
                    <i class="bi bi-pencil-square mr-1"></i>Override Vehicle/Driver
                </button>
                <button type="button" class="btn btn-success" onclick="document.getElementById('completeModal').showModal()">
                    <i class="bi bi-check-circle mr-1"></i>Complete Trip
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Details -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Status Card -->
            <div class="loka-card">
                <div class="p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="text-sm text-base-content/50">Status</span>
                            <div class="mt-1"><?= requestStatusBadge($request->status) ?></div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm text-base-content/50">Created</span>
                            <div class="font-medium"><?= formatDateTime($request->created_at) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cancellation Details (shown only for admins/approvers/motorpool when cancelled) -->
            <?php if ($request->status === STATUS_CANCELLED && (isAdmin() || isApprover() || isMotorpool())): ?>
                <?php
                // Get cancellation details from audit log
                $cancellationAudit = db()->fetch(
                    "SELECT * FROM audit_logs
                     WHERE entity_type = 'request' AND entity_id = ? AND action = 'request_cancelled'
                     ORDER BY created_at DESC LIMIT 1",
                    [$requestId]
                );
                ?>
                <div class="loka-card border border-error">
                    <div class="p-4 bg-error text-error-content">
                        <h3 class="font-semibold flex items-center gap-2">
                            <i class="bi bi-x-circle"></i>Cancellation Details
                        </h3>
                    </div>
                    <div class="p-4">
                        <?php if ($cancellationAudit): ?>
                            <?php
                            $auditData = json_decode($cancellationAudit->new_values, true);
                            $cancelledByUserId = $auditData['cancelled_by'] ?? null;
                            $cancellationReason = $auditData['reason'] ?? 'No reason provided';
                            $isAdminOverride = $auditData['is_admin_override'] ?? false;

                            // Get canceller details
                            $canceller = null;
                            if ($cancelledByUserId) {
                                $canceller = db()->fetch("SELECT name, email FROM users WHERE id = ?", [$cancelledByUserId]);
                            }
                            ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-sm text-base-content/50">Cancelled By</label>
                                    <div class="font-semibold">
                                        <?php if ($canceller): ?>
                                            <?= e($canceller->name) ?>
                                            <?php if ($isAdminOverride): ?>
                                            <span class="badge badge-warning ml-2">Admin Override</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            System
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm text-base-content/50">Cancelled On</label>
                                    <div class="font-semibold"><?= formatDateTime($cancellationAudit->created_at) ?></div>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-sm text-base-content/50">Reason for Cancellation</label>
                                    <div class="alert alert-warning mt-1">
                                        <i class="bi bi-chat-left-quote mr-2"></i>
                                        <?= nl2br(e($cancellationReason)) ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-base-content/50">Cancellation details not found in audit log.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Trip Details -->
            <div class="loka-card">
                <div class="p-4 border-b border-base-200">
                    <h3 class="font-semibold flex items-center gap-2">
                        <i class="bi bi-geo-alt"></i>Trip Details
                    </h3>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm text-base-content/50">Start Date/Time</label>
                            <div class="font-medium"><?= formatDateTime($request->start_datetime) ?></div>
                        </div>
                        <div>
                            <label class="text-sm text-base-content/50">End Date/Time</label>
                            <div class="font-medium"><?= formatDateTime($request->end_datetime) ?></div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-base-content/50">Purpose</label>
                            <div class="font-medium"><?= nl2br(e($request->purpose)) ?></div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-base-content/50">Destination</label>
                            <div class="font-medium"><?= e($request->destination) ?></div>
                        </div>
                        <div class="md:col-span-2">
                            <?php 
                            // Calculate actual passenger count: requester (1) + passengers in table
                            $actualPassengerCount = count($passengers) + 1;
                            ?>
                            <label class="text-sm text-base-content/50">Passengers (<?= $actualPassengerCount ?>)</label>
                            <div class="flex flex-wrap gap-1 mt-1">
                                <span class="badge badge-primary">
                                    <i class="bi bi-person-fill mr-1"></i><?= e($request->requester_name) ?> (Requester)
                                </span>
                                <?php foreach ($passengers as $passenger): ?>
                                    <span class="badge badge-<?= $passenger->user_id ? 'secondary' : 'info' ?>">
                                        <i class="bi bi-person<?= $passenger->user_id ? '' : '-plus' ?> mr-1"></i>
                                        <?= e($passenger->name ?: $passenger->guest_name) ?>
                                        <?= $passenger->user_id ? '' : ' <span class="text-xs">(Guest)</span>' ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if ($request->notes): ?>
                            <div class="md:col-span-2">
                                <label class="text-sm text-base-content/50">Notes</label>
                                <div><?= nl2br(e($request->notes)) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Mileage Information -->
            <?php if ($request->mileage_start || $request->mileage_end || $request->mileage_actual): ?>
                <div class="loka-card">
                    <div class="p-4 border-b border-base-200">
                        <h3 class="font-semibold flex items-center gap-2">
                            <i class="bi bi-speedometer2"></i>Mileage Information
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <?php if ($request->mileage_start): ?>
                                <div>
                                    <h6 class="text-base-content/50 mb-2">Starting Mileage</h6>
                                    <div class="text-2xl font-bold text-primary"><?= number_format($request->mileage_start) ?> km</div>
                                    <small class="text-base-content/40">Recorded at dispatch</small>
                                </div>
                            <?php endif; ?>
                            <?php if ($request->mileage_end): ?>
                                <div>
                                    <h6 class="text-base-content/50 mb-2">Ending Mileage</h6>
                                    <div class="text-2xl font-bold text-success"><?= number_format($request->mileage_end) ?> km</div>
                                    <small class="text-base-content/40">Recorded at arrival</small>
                                </div>
                            <?php endif; ?>
                            <?php if ($request->mileage_actual): ?>
                                <div>
                                    <h6 class="text-base-content/50 mb-2">Actual Distance</h6>
                                    <div class="text-2xl font-bold text-info"><?= number_format($request->mileage_actual) ?> km</div>
                                    <small class="text-base-content/40">Total trip distance</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Travel Documents -->
            <?php if ($request->has_travel_order || $request->has_official_business_slip): ?>
                <div class="loka-card">
                    <div class="p-4 border-b border-base-200">
                        <h3 class="font-semibold flex items-center gap-2">
                            <i class="bi bi-file-earmark-text"></i>Travel Documents
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php if ($request->has_travel_order): ?>
                                <div class="flex items-center p-3 bg-success/10 rounded-lg">
                                    <i class="bi bi-check-circle-fill text-success text-2xl mr-3"></i>
                                    <div>
                                        <div class="font-semibold">Travel Order</div>
                                        <div class="text-base-content/50">Number: <strong><?= e($request->travel_order_number) ?></strong></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($request->has_official_business_slip): ?>
                                <div class="flex items-center p-3 bg-primary/10 rounded-lg">
                                    <i class="bi bi-check-circle-fill text-primary text-2xl mr-3"></i>
                                    <div>
                                        <div class="font-semibold">Official Business Slip</div>
                                        <div class="text-base-content/50">Number: <strong><?= e($request->ob_slip_number) ?></strong></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Vehicle & Driver Assignment -->
            <?php if ($request->vehicle_id || $request->driver_id): ?>
                <div class="loka-card">
                    <div class="p-4 border-b border-base-200">
                        <h3 class="font-semibold flex items-center gap-2">
                            <i class="bi bi-car-front"></i>Assignment
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php if ($request->vehicle_id): ?>
                                <div>
                                    <h6 class="text-base-content/50 mb-2">Vehicle</h6>
                                    <div class="flex items-center">
                                        <div class="bg-primary/10 rounded-lg p-3 mr-3">
                                            <i class="bi bi-car-front text-primary text-2xl"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold"><?= e($request->plate_number) ?></div>
                                            <div class="text-base-content/50"><?= e($request->make . ' ' . $request->vehicle_model) ?></div>
                                            <small class="text-base-content/40"><?= e($request->vehicle_type) ?> • <?= e($request->vehicle_color) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($request->driver_id): ?>
                                <div>
                                    <h6 class="text-base-content/50 mb-2">Driver</h6>
                                    <div class="flex items-center">
                                        <div class="bg-success/10 rounded-lg p-3 mr-3">
                                            <i class="bi bi-person-badge text-success text-2xl"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold"><?= e($request->driver_name) ?></div>
                                            <div class="text-base-content/50"><?= e($request->driver_phone) ?></div>
                                            <small class="text-base-content/40">License: <?= e($request->driver_license) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Assignment History -->
            <?php if (!empty($assignmentHistory)): ?>
                <div class="loka-card border border-warning">
                    <div class="p-4 bg-warning/25">
                        <h3 class="font-semibold flex items-center gap-2">
                            <i class="bi bi-clock-history"></i>Assignment History
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="loka-table-responsive">
                            <table class="loka-table">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Action</th>
                                        <th>Vehicle</th>
                                        <th>Driver</th>
                                        <th>By</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignmentHistory as $i => $ah): ?>
                                        <tr>
                                            <td>
                                                <small><?= formatDateTime($ah->created_at) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($ah->action === 'assigned'): ?>
                                                    <span class="badge badge-success">Assigned</span>
                                                <?php elseif ($ah->action === 'overridden'): ?>
                                                    <span class="badge badge-warning">Overridden</span>
                                                <?php elseif ($ah->action === 'released'): ?>
                                                    <span class="badge badge-secondary">Released</span>
                                                <?php elseif ($ah->action === 'completed'): ?>
                                                    <span class="badge badge-primary">Completed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($ah->vehicle_id): ?>
                                                    <div>
                                                        <strong><?= e($ah->plate_number) ?></strong>
                                                        <br><small class="text-base-content/40"><?= e($ah->make . ' ' . $ah->vehicle_model) ?></small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-base-content/40">-</span>
                                                <?php endif; ?>
                                                <?php if ($ah->previous_vehicle_id && $ah->action === 'overridden'): ?>
                                                    <div class="mt-1">
                                                        <small class="text-error">
                                                            <i class="bi bi-arrow-up"></i> Was: <?= e($ah->prev_plate_number) ?> <?= e($ah->prev_make ?? '') ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($ah->driver_id): ?>
                                                    <strong><?= e($ah->driver_name) ?></strong>
                                                <?php else: ?>
                                                    <span class="text-base-content/40">-</span>
                                                <?php endif; ?>
                                                <?php if ($ah->previous_driver_id && $ah->action === 'overridden'): ?>
                                                    <div class="mt-1">
                                                        <small class="text-error">
                                                            <i class="bi bi-arrow-up"></i> Was: <?= e($ah->prev_driver_name) ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?= e($ah->assigned_by_name) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($ah->reason): ?>
                                                    <small class="text-base-content/40 italic"><?= e($ah->reason) ?></small>
                                                <?php else: ?>
                                                    <span class="text-base-content/40">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Guard Tracking - Actual Dispatch/Arrival Times -->
            <?php if ($request->actual_dispatch_datetime || $request->actual_arrival_datetime): ?>
                <div class="loka-card border border-success">
                    <div class="p-4 bg-success text-success-content">
                        <h3 class="font-semibold flex items-center gap-2">
                            <i class="bi bi-shield-check"></i>Guard Tracking
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php if ($request->actual_dispatch_datetime): ?>
                                <div>
                                    <h6 class="text-base-content/50 mb-2">
                                        <i class="bi bi-box-arrow-right text-success mr-1"></i>Actual Dispatch
                                    </h6>
                                    <div class="flex items-center">
                                        <div class="bg-success/10 rounded-lg p-3 mr-3">
                                            <i class="bi bi-clock text-success text-2xl"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold"><?= formatDateTime($request->actual_dispatch_datetime) ?></div>
                                            <?php if ($request->dispatch_guard_name): ?>
                                                <small class="text-base-content/40">Recorded by: <?= e($request->dispatch_guard_name) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($request->actual_arrival_datetime): ?>
                                <div>
                                    <h6 class="text-base-content/50 mb-2">
                                        <i class="bi bi-box-arrow-in-left text-primary mr-1"></i>Actual Arrival
                                    </h6>
                                    <div class="flex items-center">
                                        <div class="bg-primary/10 rounded-lg p-3 mr-3">
                                            <i class="bi bi-clock-history text-primary text-2xl"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold"><?= formatDateTime($request->actual_arrival_datetime) ?></div>
                                            <?php if ($request->arrival_guard_name): ?>
                                                <small class="text-base-content/40">Recorded by: <?= e($request->arrival_guard_name) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($request->guard_notes): ?>
                            <div class="mt-3 pt-3 border-t border-base-200">
                                <h6 class="text-base-content/50 mb-2"><i class="bi bi-sticky mr-1"></i>Guard Notes</h6>
                                <div class="bg-base-200 p-3 rounded-lg">
                                    <?= nl2br(e($request->guard_notes)) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Approval History -->
            <div class="loka-card">
                <div class="p-4 border-b border-base-200">
                    <h3 class="font-semibold flex items-center gap-2">
                        <i class="bi bi-clock-history"></i>Approval History
                    </h3>
                </div>
                <div class="p-4">
                    <?php if (empty($approvals)): ?>
                        <p class="text-base-content/50">No approval actions yet.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($approvals as $approval): ?>
                                <div class="flex gap-3">
                                    <div>
                                        <?php if ($approval->status === 'approved'): ?>
                                            <span class="badge badge-success badge-lg rounded-full p-2"><i class="bi bi-check-lg"></i></span>
                                        <?php else: ?>
                                            <span class="badge badge-error badge-lg rounded-full p-2"><i class="bi bi-x-lg"></i></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="font-medium">
                                            <?= e($approval->approver_name) ?>
                                            <span class="<?= $approval->status === 'approved' ? 'text-success' : 'text-error' ?>">
                                                <?= ucfirst($approval->status) ?>
                                            </span>
                                            (<?= ucfirst($approval->approval_type) ?> Level)
                                        </div>
                                        <small class="text-base-content/40"><?= formatDateTime($approval->created_at) ?></small>
                                        <?php if ($approval->comments): ?>
                                            <div class="mt-1 italic">"<?= e($approval->comments) ?>"</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-4">
            <!-- Requester Info -->
            <div class="loka-card">
                <div class="p-4 border-b border-base-200">
                    <h3 class="text-sm font-semibold flex items-center gap-2">
                        <i class="bi bi-person"></i>Requester
                    </h3>
                </div>
                <div class="p-4">
                    <div class="font-semibold"><?= e($request->requester_name) ?></div>
                    <div class="text-sm text-base-content/50"><?= e($request->requester_email) ?></div>
                    <?php if ($request->requester_phone): ?>
                        <div class="text-sm text-base-content/50"><?= e($request->requester_phone) ?></div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <span class="badge badge-ghost"><?= e($request->department_name) ?></span>
                    </div>
                </div>
            </div>

            <!-- Workflow Status -->
            <div class="loka-card">
                <div class="p-4 border-b border-base-200">
                    <h3 class="text-sm font-semibold flex items-center gap-2">
                        <i class="bi bi-diagram-3"></i>Approval Workflow
                    </h3>
                </div>
                <div class="p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <div>
                            <?php if (in_array($request->status, [STATUS_PENDING])): ?>
                                <span class="badge badge-warning badge-lg rounded-full p-2"><i class="bi bi-hourglass"></i></span>
                            <?php elseif (in_array($request->status, [STATUS_REJECTED, STATUS_CANCELLED])): ?>
                                <span class="badge badge-error badge-lg rounded-full p-2"><i class="bi bi-x"></i></span>
                            <?php else: ?>
                                <span class="badge badge-success badge-lg rounded-full p-2"><i class="bi bi-check"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="font-medium">Department Approval</div>
                            <?php if ($request->approver_name): ?>
                                <small class="text-primary block"><i class="bi bi-person-check mr-1"></i><?= e($request->approver_name) ?></small>
                            <?php endif; ?>
                            <small class="text-base-content/50">
                                <?php
                                if ($request->status === STATUS_PENDING)
                                    echo 'Pending';
                                elseif ($request->status === STATUS_REJECTED)
                                    echo 'Rejected';
                                else
                                    echo 'Approved';
                                ?>
                            </small>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div>
                            <?php if (in_array($request->status, [STATUS_PENDING_MOTORPOOL])): ?>
                                <span class="badge badge-warning badge-lg rounded-full p-2"><i class="bi bi-hourglass"></i></span>
                            <?php elseif (in_array($request->status, [STATUS_APPROVED, STATUS_COMPLETED])): ?>
                                <span class="badge badge-success badge-lg rounded-full p-2"><i class="bi bi-check"></i></span>
                            <?php elseif (in_array($request->status, [STATUS_REJECTED])): ?>
                                <span class="badge badge-error badge-lg rounded-full p-2"><i class="bi bi-x"></i></span>
                            <?php else: ?>
                                <span class="badge badge-secondary badge-lg rounded-full p-2"><i class="bi bi-dash"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="font-medium">Motorpool Approval</div>
                            <?php if ($request->motorpool_head_name): ?>
                                <small class="text-primary block"><i class="bi bi-person-check mr-1"></i><?= e($request->motorpool_head_name) ?></small>
                            <?php endif; ?>
                            <small class="text-base-content/50">
                                <?php
                                if ($request->status === STATUS_PENDING_MOTORPOOL)
                                    echo 'Pending';
                                elseif (in_array($request->status, [STATUS_APPROVED, STATUS_COMPLETED]))
                                    echo 'Approved';
                                elseif ($request->status === STATUS_REJECTED)
                                    echo 'Rejected';
                                else
                                    echo 'Waiting';
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isMotorpool() && $request->status === STATUS_APPROVED): ?>
    <!-- Override Vehicle/Driver Modal -->
    <dialog id="overrideModal" class="modal">
        <div class="modal-box bg-base-100 p-0 max-w-2xl">
            <form method="POST" action="<?= APP_URL ?>/?page=requests&action=override">
                <?= csrfField() ?>
                <input type="hidden" name="request_id" value="<?= $requestId ?>">

                <div class="p-6 bg-warning">
                    <h5 class="text-warning-content font-semibold flex items-center gap-2">
                        <i class="bi bi-exclamation-triangle mr-2"></i>Override Vehicle/Driver Assignment
                    </h5>
                </div>

                <div class="p-6 space-y-4">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle mr-2"></i>
                        <strong>Warning:</strong> Overriding will reassign the vehicle and/or driver for this approved trip. 
                        This may create conflicts with other scheduled trips. Use with caution.
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="loka-form-label">Current Vehicle</label>
                            <div class="font-semibold text-base-content/50">
                                <?= $request->plate_number ? e($request->plate_number . ' - ' . $request->make . ' ' . $request->vehicle_model) : 'Not assigned' ?>
                            </div>
                        </div>
                        <div>
                            <label class="loka-form-label">New Vehicle</label>
                            <select class="select select-bordered w-full" name="vehicle_id" required>
                                <option value="">Select vehicle...</option>
                                <?php 
                                $availableVehicles = db()->fetchAll(
                                    "SELECT v.*, vt.name as type_name 
                                     FROM vehicles v 
                                     JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
                                     WHERE v.deleted_at IS NULL 
                                     ORDER BY v.plate_number"
                                );
                                foreach ($availableVehicles as $v): 
                                ?>
                                <option value="<?= $v->id ?>" <?= $request->vehicle_id == $v->id ? 'selected' : '' ?>>
                                    <?= e($v->plate_number) ?> - <?= e($v->make . ' ' . $v->model) ?>
                                    (<?= e($v->type_name) ?>) - <?= ucfirst($v->status) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="loka-form-label">Current Driver</label>
                            <div class="font-semibold text-base-content/50">
                                <?= $request->driver_name ? e($request->driver_name) : 'Not assigned' ?>
                            </div>
                        </div>
                        <div>
                            <label class="loka-form-label">New Driver</label>
                            <select class="select select-bordered w-full" name="driver_id" required>
                                <option value="">Select driver...</option>
                                <?php 
                                $availableDrivers = db()->fetchAll(
                                    "SELECT d.*, u.name, u.phone 
                                     FROM drivers d 
                                     JOIN users u ON d.user_id = u.id 
                                     WHERE d.deleted_at IS NULL AND u.status = 'active'
                                     ORDER BY u.name"
                                );
                                foreach ($availableDrivers as $d): 
                                ?>
                                <option value="<?= $d->id ?>" <?= $request->driver_id == $d->id ? 'selected' : '' ?>>
                                    <?= e($d->name) ?> - <?= e($d->license_number) ?>
                                    (<?= ucfirst(str_replace('_', ' ', $d->status)) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="loka-form-label">Override Reason <span class="text-error">*</span></label>
                            <textarea class="textarea textarea-bordered w-full" name="override_reason" rows="2" 
                                      placeholder="Explain why you are overriding the vehicle/driver assignment..." required maxlength="500"></textarea>
                        </div>
                    </div>
                </div>

                <div class="p-6 border-t border-base-200 flex justify-end gap-2">
                    <form method="dialog">
                        <button type="submit" class="btn btn-secondary">Cancel</button>
                    </form>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-pencil-square mr-1"></i>Confirm Override
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="submit">close</button>
        </form>
    </dialog>
    
    <!-- Complete Trip Modal -->
    <dialog id="completeModal" class="modal">
        <div class="modal-box bg-base-100 p-0 max-w-2xl">
            <form method="POST" action="<?= APP_URL ?>/?page=requests&action=complete">
                <?= csrfField() ?>
                <input type="hidden" name="request_id" value="<?= $requestId ?>">

                <div class="p-6 bg-success">
                    <h5 class="text-success-content font-semibold flex items-center gap-2">
                        <i class="bi bi-check-circle mr-2"></i>Complete Trip
                    </h5>
                </div>

                <div class="p-6 space-y-4">
                    <p class="text-base-content/50">Mark this trip as completed. This will release the vehicle and driver back to
                        available status.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="loka-form-label">Vehicle</label>
                            <div class="font-semibold"><?= e($request->plate_number) ?> -
                                <?= e($request->make . ' ' . $request->vehicle_model) ?></div>
                        </div>

                        <div>
                            <label class="loka-form-label">Driver</label>
                            <div class="font-semibold"><?= e($request->driver_name) ?></div>
                        </div>
                    </div>

                    <div>
                        <label for="ending_mileage" class="loka-form-label">Ending Mileage (Optional)</label>
                        <input type="number" class="input input-bordered w-full" id="ending_mileage" name="ending_mileage"
                            placeholder="Current odometer reading">
                    </div>

                    <div>
                        <label for="completion_notes" class="loka-form-label">Completion Notes (Optional)</label>
                        <textarea class="textarea textarea-bordered w-full" id="completion_notes" name="completion_notes" rows="2"
                            placeholder="Any notes about the trip..." maxlength="500"></textarea>
                    </div>
                </div>

                <div class="p-6 border-t border-base-200 flex justify-end gap-2">
                    <form method="dialog">
                        <button type="submit" class="btn btn-secondary">Cancel</button>
                    </form>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg mr-1"></i>Mark Complete
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="submit">close</button>
        </form>
    </dialog>
<?php endif; ?>

<!-- Cancel Request Modal -->
<?php if ($request->user_id === userId() && !in_array($request->status, [STATUS_COMPLETED, STATUS_CANCELLED])): ?>
<dialog id="cancelRequestModal" class="modal">
    <div class="modal-box bg-base-100 p-0 max-w-lg border-2 border-error">
        <div class="p-6 bg-error">
            <h5 class="text-error-content font-semibold flex items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill mr-2"></i>Cancel Request?
            </h5>
        </div>
        <form method="POST" action="<?= APP_URL ?>/?page=requests&action=cancel&id=<?= $requestId ?>">
            <?= csrfField() ?>
            <input type="hidden" name="confirm_cancel" value="1">
            <div class="p-6 space-y-4">
                <div class="text-center">
                    <i class="bi bi-x-circle-fill text-error" style="font-size: 4rem;"></i>
                </div>

                <h5 class="text-center font-semibold">Are you sure you want to cancel this request?</h5>

                <div class="bg-base-200 rounded-lg p-4">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <div class="font-semibold text-base-content/50">Request #:</div>
                        <div class="col-span-2"><?= $requestId ?></div>
                        <div class="font-semibold text-base-content/50">Destination:</div>
                        <div class="col-span-2"><?= e($request->destination) ?></div>
                        <div class="font-semibold text-base-content/50">Date/Time:</div>
                        <div class="col-span-2"><?= formatDateTime($request->start_datetime) ?></div>
                    </div>
                </div>

                <div class="alert alert-error flex items-start">
                    <i class="bi bi-exclamation-triangle-fill shrink-0 mr-2 mt-1"></i>
                    <div>
                        <strong>This action cannot be undone!</strong>
                        <ul class="list-disc list-inside mt-2">
                            <li>The request will be marked as cancelled</li>
                            <li>Assigned vehicle and driver will be released</li>
                            <li>All approvers and passengers will be notified</li>
                        </ul>
                    </div>
                </div>

                <?php if ($request->status === STATUS_APPROVED): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle-fill mr-2"></i>
                    <strong>Attention:</strong> This request has already been approved.
                    <?php if ($request->vehicle_plate): ?>
                    <div class="mt-2"><strong>Vehicle:</strong> <?= e($request->vehicle_number) ?> - <?= e($request->vehicle_make) ?> <?= e($request->vehicle_model) ?></div>
                    <?php endif; ?>
                    <?php if ($request->driver_name): ?>
                    <div><strong>Driver:</strong> <?= e($request->driver_name) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div>
                    <label for="cancel_reason" class="loka-form-label font-semibold">
                        <i class="bi bi-chat-left-text mr-1"></i>Reason for cancellation
                        <span class="text-error">*</span>
                    </label>
                    <textarea class="textarea textarea-bordered w-full" id="cancel_reason" name="reason" rows="2" required
                        placeholder="Please provide a reason for cancelling this request..." maxlength="500"></textarea>
                    <small class="text-base-content/40">This field is required</small>
                </div>
            </div>
            <div class="p-6 border-t border-base-200 flex justify-end gap-2">
                <form method="dialog">
                    <button type="button" class="btn btn-outline btn-lg">
                        <i class="bi bi-x-lg mr-1"></i>No, Keep Request
                    </button>
                </form>
                <button type="submit" class="btn btn-error btn-lg">
                    <i class="bi bi-check-lg mr-1"></i>Yes, Cancel Request
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button type="submit">close</button>
    </form>
</dialog>
<?php endif; ?>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
