<?php
/**
 * LOKA - Vehicle Types Management
 *
 * Admin-only CRUD module for managing vehicle types
 */

requireRole('approver');

$pageTitle = 'Vehicle Types';

// Get all vehicle types with vehicle count
$vehicleTypes = db()->fetchAll(
    "SELECT vt.*,
            (SELECT COUNT(*) FROM vehicles v WHERE v.vehicle_type_id = vt.id AND v.deleted_at IS NULL) as vehicle_count
     FROM vehicle_types vt
     WHERE vt.deleted_at IS NULL
     ORDER BY vt.name"
);

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <!-- Page Header -->
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-car-front me-2"></i>Vehicle Types</h1>
            <p class="text-sm text-base-content/60">Manage vehicle types for the fleet</p>
        </div>
        <div>
            <a href="<?= APP_URL ?>/?page=vehicle_types&action=create" class="loka-btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Add Vehicle Type
            </a>
        </div>
    </div>

    <!-- Vehicle Types Table -->
    <div class="loka-card">
        <div class="p-6">
            <?php if (empty($vehicleTypes)): ?>
                <div class="loka-empty">
                    <i class="bi bi-car-front fs-1 text-base-content/60"></i>
                    <p class="text-base-content/60 mt-3">No vehicle types found. Add your first vehicle type to get started.</p>
                </div>
            <?php else: ?>
                <div class="loka-table-responsive">
                    <table class="loka-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Passenger Capacity</th>
                                <th>Vehicles Using</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicleTypes as $type): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($type->name) ?></strong>
                                    </td>
                                    <td>
                                        <span class="text-xs text-base-content/50"><?= e($type->description ?: 'No description') ?></span>
                                    </td>
                                    <td>
                                        <span class="loka-badge bg-primary/20 text-primary">
                                            <i class="bi bi-people me-1"></i><?= $type->passenger_capacity ?> seats
                                        </span>
                                    </td>
                                    <td>
                                        <span class="loka-badge <?= $type->vehicle_count > 0 ? 'bg-success/20 text-success' : 'bg-base-200 text-base-content/60' ?>">
                                            <?= $type->vehicle_count ?> vehicle<?= $type->vehicle_count != 1 ? 's' : '' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-xs text-base-content/50"><?= formatDate($type->created_at) ?></span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-1">
                                            <a href="<?= APP_URL ?>/?page=vehicle_types&action=edit&id=<?= $type->id ?>"
                                               class="loka-btn-icon text-primary hover:bg-primary/10">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <?php if ($type->vehicle_count == 0): ?>
                                                <a href="<?= APP_URL ?>/?page=vehicle_types&action=delete&id=<?= $type->id ?>"
                                                   class="loka-btn-icon text-error hover:bg-error/10"
                                                   data-confirm="Delete this vehicle type? This action cannot be undone.">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="loka-btn-icon text-base-content/30" disabled
                                                        title="Cannot delete: vehicles are using this type">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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
