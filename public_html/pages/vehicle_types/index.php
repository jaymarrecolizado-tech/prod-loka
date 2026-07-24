<?php
/**
 * LOKA - Vehicle Types Management
 *
 * Admin-only CRUD module for managing vehicle types
 */

requireRole('approver');

$pageTitle = 'Vehicle Types';
$searchQuery = listSearchQuery();

$whereClause = 'vt.deleted_at IS NULL';
$params = [];
if ($searchQuery) {
    $whereClause .= ' AND (vt.name LIKE ? OR vt.description LIKE ?)';
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
}

// Sorting (latest vehicle types first by default)
$allowedSortColumns = [
    'id' => 'vt.id',
    'created_at' => 'vt.created_at',
    'name' => 'vt.name',
    'description' => 'vt.description',
    'passenger_capacity' => 'vt.passenger_capacity',
    'vehicle_count' => 'vehicle_count',
];
$sortState = resolveTableSort($allowedSortColumns, 'created_at', 'DESC');
$sort = $sortState['key'];
$sortDir = $sortState['dir'];

$countRow = db()->fetch(
    "SELECT COUNT(*) as c FROM vehicle_types vt WHERE {$whereClause}",
    $params
);
$pag = listPaginationState((int) ($countRow->c ?? 0));

$vehicleTypes = db()->fetchAll(
    "SELECT vt.*,
            (SELECT COUNT(*) FROM vehicles v WHERE v.vehicle_type_id = vt.id AND v.deleted_at IS NULL) as vehicle_count
     FROM vehicle_types vt
     WHERE {$whereClause}
     ORDER BY {$sortState['orderSql']}
     LIMIT ? OFFSET ?",
    array_merge($params, [$pag['perPage'], $pag['offset']])
);

$baseParams = tableSortQueryParams($sortState, [
    'page' => 'vehicle_types',
    'q' => $searchQuery,
    'per_page' => $pag['perPage'],
]);

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

    <div class="loka-card mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="page" value="vehicle_types">
            <?= listSearchFieldHtml($searchQuery, 'Name or description...') ?>
            <?= perPageFieldHtml($pag['perPage']) ?>
            <div class="flex gap-2">
                <button type="submit" class="loka-btn-primary loka-btn-sm">Filter</button>
                <a href="<?= APP_URL ?>/?page=vehicle_types" class="loka-btn-secondary loka-btn-sm">Clear</a>
            </div>
        </form>
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
                                <?= tableSortTh('name', 'Name', $sort, $sortDir, $baseParams) ?>
                                <?= tableSortTh('description', 'Description', $sort, $sortDir, $baseParams) ?>
                                <?= tableSortTh('passenger_capacity', 'Passenger Capacity', $sort, $sortDir, $baseParams) ?>
                                <?= tableSortTh('vehicle_count', 'Vehicles Using', $sort, $sortDir, $baseParams) ?>
                                <?= tableSortTh('created_at', 'Created', $sort, $sortDir, $baseParams) ?>
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
                <?= listPaginationFooter($pag, $baseParams) ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
