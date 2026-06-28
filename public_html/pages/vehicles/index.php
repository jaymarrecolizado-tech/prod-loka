<?php
/**
 * LOKA - Vehicles List Page
 */

requireRole(ROLE_APPROVER);

$pageTitle = 'Vehicles';

// Get filters
$statusFilter = get('status', '');
$typeFilter = get('type', '');

// Build query
$params = [];
$whereClause = 'v.deleted_at IS NULL';

if ($statusFilter) {
    $whereClause .= ' AND v.status = ?';
    $params[] = $statusFilter;
}

if ($typeFilter) {
    $whereClause .= ' AND v.vehicle_type_id = ?';
    $params[] = $typeFilter;
}

$vehicles = db()->fetchAll(
    "SELECT v.*, vt.name as type_name, vt.passenger_capacity
     FROM vehicles v
     JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
     WHERE {$whereClause}
     ORDER BY v.plate_number",
    $params
);

$vehicleTypes = db()->fetchAll("SELECT * FROM vehicle_types WHERE deleted_at IS NULL ORDER BY name");

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Vehicles</h1>
            <p class="text-sm text-base-content/60">Manage fleet vehicles</p>
        </div>
        <?php if (isApprover()): ?>
            <a href="<?= APP_URL ?>/?page=vehicles&action=create" class="loka-btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Vehicle
            </a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="loka-card mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="page" value="vehicles">
            <div class="flex flex-col gap-1.5 min-w-[150px]">
                <label class="label">
                    <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Status</span>
                </label>
                <select name="status" class="select select-bordered select-sm bg-base-100">
                    <option value="">All Statuses</option>
                    <?php foreach (VEHICLE_STATUS_LABELS as $key => $info): ?>
                        <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($info['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex flex-col gap-1.5 min-w-[150px]">
                <label class="label">
                    <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Type</span>
                </label>
                <select name="type" class="select select-bordered select-sm bg-base-100">
                    <option value="">All Types</option>
                    <?php foreach ($vehicleTypes as $type): ?>
                        <option value="<?= $type->id ?>" <?= $typeFilter == $type->id ? 'selected' : '' ?>><?= e($type->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="loka-btn-primary loka-btn-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Filter
                </button>
                <a href="<?= APP_URL ?>/?page=vehicles" class="loka-btn-secondary loka-btn-sm">Reset</a>
            </div>
        </form>
    </div>

    <!-- Vehicles Table -->
    <div class="loka-card">
        <?php if (empty($vehicles)): ?>
            <div class="loka-empty">
                <svg class="mx-auto w-12 h-12 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 17h.01M16 17h.01M3 11l1.5-5A2 2 0 016.4 4h11.2a2 2 0 011.9 1.4L21 11M3 11h18M3 11v6a1 1 0 001 1h1a1 1 0 001-1v-1h12v1a1 1 0 001 1h1a1 1 0 001-1v-6"/>
                </svg>
                <p class="mt-2 text-base-content/60">No vehicles found</p>
            </div>
        <?php else: ?>
            <div class="loka-table-responsive">
                <table class="loka-table">
                    <thead>
                        <tr>
                            <th>Plate #</th>
                            <th>Vehicle</th>
                            <th>Type</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Mileage</th>
                            <th class="text-center w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td>
                                    <span class="font-mono text-xs font-semibold text-primary"><?= e($vehicle->plate_number) ?></span>
                                </td>
                                <td>
                                    <p class="text-sm font-medium text-base-content"><?= e($vehicle->make . ' ' . $vehicle->model) ?></p>
                                    <p class="text-xs text-base-content/60"><?= e($vehicle->year) ?> • <?= e($vehicle->color) ?></p>
                                </td>
                                <td>
                                    <span class="loka-badge loka-badge-sm bg-base-200 text-base-content/70"><?= e($vehicle->type_name) ?></span>
                                </td>
                                <td class="text-sm text-base-content"><?= $vehicle->passenger_capacity ?></td>
                                <td><?= vehicleStatusBadge($vehicle->status) ?></td>
                                <td class="text-sm text-base-content"><?= number_format($vehicle->mileage) ?> km</td>
                                <td>
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="<?= APP_URL ?>/?page=vehicles&action=view&id=<?= $vehicle->id ?>"
                                            class="loka-btn-icon text-primary hover:bg-primary/10" title="View">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <?php if (isApprover()): ?>
                                            <a href="<?= APP_URL ?>/?page=vehicles&action=edit&id=<?= $vehicle->id ?>"
                                                class="loka-btn-icon text-base-content/60 hover:bg-base-200" title="Edit">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </a>
                                            <form method="POST" action="<?= APP_URL ?>/?page=vehicles&action=delete" style="display:inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= $vehicle->id ?>">
                                                <button type="submit" class="loka-btn-icon text-error hover:bg-error/10" title="Delete"
                                                    data-confirm="Delete this vehicle?">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
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

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
