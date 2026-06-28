<?php
/**
 * LOKA - Drivers List Page
 */

requireRole(ROLE_APPROVER);

$pageTitle = 'Drivers';
$statusFilter = get('status', '');

$params = [];
$whereClause = 'd.deleted_at IS NULL';

if ($statusFilter) {
    $whereClause .= ' AND d.status = ?';
    $params[] = $statusFilter;
}

$drivers = db()->fetchAll(
    "SELECT d.*, u.name as driver_name, u.email, u.phone
     FROM drivers d
     JOIN users u ON d.user_id = u.id AND u.deleted_at IS NULL
     WHERE {$whereClause}
     ORDER BY u.name",
    $params
);

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Drivers</h1>
            <p class="text-sm text-base-content/60">Manage fleet drivers</p>
        </div>
        <?php if (isApprover()): ?>
            <a href="<?= APP_URL ?>/?page=drivers&action=create" class="loka-btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Driver
            </a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="loka-card mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="page" value="drivers">
            <div class="flex flex-col gap-1.5 min-w-[150px]">
                <label class="label">
                    <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Status</span>
                </label>
                <select name="status" class="select select-bordered select-sm bg-base-100">
                    <option value="">All Statuses</option>
                    <?php foreach (DRIVER_STATUS_LABELS as $key => $info): ?>
                        <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($info['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="loka-btn-primary loka-btn-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Filter
                </button>
                <a href="<?= APP_URL ?>/?page=drivers" class="loka-btn-secondary loka-btn-sm">Reset</a>
            </div>
        </form>
    </div>

    <!-- Drivers Table -->
    <div class="loka-card">
        <?php if (empty($drivers)): ?>
            <div class="loka-empty">
                <svg class="mx-auto w-12 h-12 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <p class="mt-2 text-base-content/60">No drivers found</p>
            </div>
        <?php else: ?>
            <div class="loka-table-responsive">
                <table class="loka-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>License #</th>
                            <th>License Expiry</th>
                            <th>Experience</th>
                            <th>Status</th>
                            <th>Contact</th>
                            <th class="text-center w-20">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drivers as $driver): ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="loka-avatar loka-avatar-sm">
                                            <?= strtoupper(substr($driver->driver_name, 0, 2)) ?>
                                        </div>
                                        <span class="text-sm font-medium text-base-content"><?= e($driver->driver_name) ?></span>
                                    </div>
                                </td>
                                <td class="text-sm text-base-content font-mono"><?= e($driver->license_number) ?></td>
                                <td>
                                    <?php
                                    $expiry = strtotime($driver->license_expiry);
                                    $daysUntil = ($expiry - time()) / 86400;
                                    $expiryClass = $daysUntil < 30 ? 'text-error' : ($daysUntil < 90 ? 'text-warning' : 'text-base-content');
                                    ?>
                                    <span class="text-sm <?= $expiryClass ?>"><?= formatDate($driver->license_expiry) ?></span>
                                </td>
                                <td class="text-sm text-base-content"><?= $driver->years_experience ?> years</td>
                                <td><?= driverStatusBadge($driver->status) ?></td>
                                <td class="text-sm text-base-content/60"><?= e($driver->phone ?: '—') ?></td>
                                <td>
                                    <div class="flex items-center justify-center gap-1">
                                        <?php if (isApprover()): ?>
                                            <a href="<?= APP_URL ?>/?page=drivers&action=edit&id=<?= $driver->id ?>"
                                                class="loka-btn-icon text-base-content/60 hover:bg-base-200" title="Edit">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </a>
                                            <form method="POST" action="<?= APP_URL ?>/?page=drivers&action=delete" style="display:inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= $driver->id ?>">
                                                <button type="submit" class="loka-btn-icon text-error hover:bg-error/10" title="Delete"
                                                    data-confirm="Delete this driver?">
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
