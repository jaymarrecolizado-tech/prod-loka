<?php
/**
 * LOKA - View Maintenance Request
 */

requireRole(ROLE_APPROVER);

$maintenanceId = (int)get('id');

$maintenance = db()->fetch(
    "SELECT mr.*,
            v.plate_number, v.make, v.model, v.mileage,
            vt.name as vehicle_type,
            reporter.name as reporter_name, reporter.email as reporter_email,
            assigned.name as assigned_name, assigned.email as assigned_email
     FROM maintenance_requests mr
     JOIN vehicles v ON mr.vehicle_id = v.id
     LEFT JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
     JOIN users reporter ON mr.reported_by = reporter.id
     LEFT JOIN users assigned ON mr.assigned_to = assigned.id
     WHERE mr.id = ? AND mr.deleted_at IS NULL",
    [$maintenanceId]
);

if (!$maintenance) {
    redirectWith('/?page=maintenance', 'danger', 'Maintenance request not found.');
}

$pageTitle = 'Maintenance Request #' . $maintenanceId;

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h4 class="mb-1">Maintenance Request #<?= $maintenanceId ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=maintenance">Maintenance</a></li>
                    <li class="breadcrumb-item active">View</li>
                </ol>
            </nav>
        </div>
        <div>
            <?php if ($maintenance->status !== MAINTENANCE_STATUS_COMPLETED && $maintenance->status !== MAINTENANCE_STATUS_CANCELLED): ?>
            <a href="<?= APP_URL ?>/?page=maintenance&action=edit&id=<?= $maintenanceId ?>"
               class="loka-btn-outline-primary">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
            <?php endif; ?>
            <?php if (isAdmin() && $maintenance->status !== MAINTENANCE_STATUS_IN_PROGRESS): ?>
            <form method="POST" action="<?= APP_URL ?>/?page=maintenance&action=delete" class="inline"
                  onsubmit="return confirm('Are you sure you want to delete this maintenance request?')">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $maintenanceId ?>">
                <button type="submit" class="loka-btn-outline-error">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 lg:col-span-8">
            <div class="loka-card mb-4">
                <div class="px-6 py-4 border-b border-base-200">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Request Details</h5>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-12 gap-4 mb-3">
                        <div class="col-span-12 md:col-span-6">
                            <h6 class="text-base-content/60">Title</h6>
                            <p class="fw-medium"><?= e($maintenance->title) ?></p>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <h6 class="text-base-content/60">Status</h6>
                            <?php $statusInfo = MAINTENANCE_STATUSES[$maintenance->status] ?? ['label' => ucfirst(str_replace('_', ' ', $maintenance->status)), 'color' => 'secondary']; ?>
                            <span class="loka-badge bg-<?= $statusInfo['color'] ?> fs-6">
                                <?= $statusInfo['label'] ?>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-12 gap-4 mb-3">
                        <div class="col-span-12 md:col-span-4">
                            <h6 class="text-base-content/60">Type</h6>
                            <?php $typeInfo = MAINTENANCE_TYPES[$maintenance->type] ?? ['label' => ucfirst($maintenance->type)]; ?>
                            <p class="mb-0"><?= $typeInfo['label'] ?></p>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <h6 class="text-base-content/60">Priority</h6>
                            <?php $priorityInfo = MAINTENANCE_PRIORITIES[$maintenance->priority] ?? ['label' => ucfirst($maintenance->priority), 'color' => 'secondary']; ?>
                            <span class="loka-badge bg-<?= $priorityInfo['color'] ?>">
                                <?= $priorityInfo['label'] ?>
                            </span>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <h6 class="text-base-content/60">Reported</h6>
                            <p class="mb-0"><?= formatDateTime($maintenance->reported_at) ?></p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-base-content/60">Description</h6>
                        <p><?= nl2br(e($maintenance->description)) ?></p>
                    </div>

                    <?php if ($maintenance->resolution_notes): ?>
                    <div class="mb-3">
                        <h6 class="text-base-content/60">Resolution Notes</h6>
                        <p><?= nl2br(e($maintenance->resolution_notes)) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($maintenance->status === MAINTENANCE_STATUS_COMPLETED): ?>
            <div class="loka-card mb-4 border-success">
                <div class="px-6 py-4 border-b border-base-200 bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Completion Details</h5>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-4">
                            <h6 class="text-base-content/60">Completed Date</h6>
                            <p class="mb-0"><?= formatDate($maintenance->completed_date) ?></p>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <h6 class="text-base-content/60">Actual Cost</h6>
                            <p class="mb-0">
                                <?php if ($maintenance->actual_cost): ?>
                                    ₱<?= number_format($maintenance->actual_cost, 2) ?>
                                <?php else: ?>
                                    <span class="text-base-content/60">-</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <h6 class="text-base-content/60">Odometer at Completion</h6>
                            <p class="mb-0">
                                <?php if ($maintenance->odometer_reading): ?>
                                    <?= number_format($maintenance->odometer_reading) ?> km
                                <?php else: ?>
                                    <span class="text-base-content/60">-</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-span-12 lg:col-span-4">
            <div class="loka-card mb-4">
                <div class="px-6 py-4 border-b border-base-200">
                    <h6 class="mb-0"><i class="bi bi-car-front me-2"></i>Vehicle</h6>
                </div>
                <div class="p-6">
                    <p class="mb-1"><strong><?= e($maintenance->plate_number) ?></strong></p>
                    <p class="text-base-content/60 mb-0"><?= e($maintenance->make . ' ' . $maintenance->model) ?></p>
                    <small class="text-base-content/60"><?= e($maintenance->vehicle_type) ?></small>
                </div>
            </div>

            <div class="loka-card mb-4">
                <div class="px-6 py-4 border-b border-base-200">
                    <h6 class="mb-0"><i class="bi bi-person me-2"></i>Reported By</h6>
                </div>
                <div class="p-6">
                    <p class="mb-1"><strong><?= e($maintenance->reporter_name) ?></strong></p>
                    <small class="text-base-content/60"><?= e($maintenance->reporter_email) ?></small>
                </div>
            </div>

            <?php if ($maintenance->scheduled_date): ?>
            <div class="loka-card mb-4">
                <div class="px-6 py-4 border-b border-base-200">
                    <h6 class="mb-0"><i class="bi bi-calendar me-2"></i>Scheduled</h6>
                </div>
                <div class="p-6">
                    <p class="mb-0 fw-medium"><?= formatDate($maintenance->scheduled_date) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($maintenance->estimated_cost): ?>
            <div class="loka-card mb-4">
                <div class="px-6 py-4 border-b border-base-200">
                    <h6 class="mb-0"><i class="bi bi-cash me-2"></i>Cost Estimate</h6>
                </div>
                <div class="p-6">
                    <p class="mb-0 fw-medium">₱<?= number_format($maintenance->estimated_cost, 2) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <div class="loka-card">
                <div class="px-6 py-4 border-b border-base-200">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Timeline</h6>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <small class="text-base-content/60">Created</small><br>
                        <?= formatDateTime($maintenance->created_at) ?>
                    </li>
                    <li class="list-group-item">
                        <small class="text-base-content/60">Last Updated</small><br>
                        <?= formatDateTime($maintenance->updated_at) ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
