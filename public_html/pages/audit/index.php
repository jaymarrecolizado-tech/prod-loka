<?php
/**
 * LOKA - Audit Logs Page
 */

requireRole(ROLE_ADMIN);

$pageTitle = 'Audit Logs';

$userFilter = get('user', '');
$actionFilter = get('action', '');
$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-d'));

$params = [$startDate, $endDate . ' 23:59:59'];
$whereClause = 'a.created_at BETWEEN ? AND ?';

if ($userFilter) {
    $whereClause .= ' AND a.user_id = ?';
    $params[] = $userFilter;
}

if ($actionFilter) {
    $whereClause .= ' AND a.action LIKE ?';
    $params[] = '%' . $actionFilter . '%';
}

$logs = db()->fetchAll(
    "SELECT a.*, u.name as user_name, u.email as user_email
     FROM audit_logs a
     LEFT JOIN users u ON a.user_id = u.id
     WHERE {$whereClause}
     ORDER BY a.created_at DESC
     LIMIT 500",
    $params
);

$users = db()->fetchAll("SELECT id, name FROM users WHERE deleted_at IS NULL ORDER BY name");

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h4 class="mb-1">Audit Logs</h4>
            <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li><li class="breadcrumb-item active">Audit Logs</li></ol></nav>
        </div>
    </div>

    <!-- Filters -->
    <div class="card table-card mb-4">
        <div class="card-body">
            <form method="GET" class="grid grid-cols-12 gap-3 items-end">
                <input type="hidden" name="page" value="audit">
                <div class="col-span-6 md:col-span-2">
                    <label class="loka-form-label">User</label>
                    <select name="user" class="loka-form-input">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= $user->id ?>" <?= $userFilter == $user->id ? 'selected' : '' ?>><?= e($user->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-span-6 md:col-span-2">
                    <label class="loka-form-label">Action</label>
                    <input type="text" name="action" class="loka-form-input" value="<?= e($actionFilter) ?>" placeholder="e.g. login">
                </div>
                <div class="col-span-6 md:col-span-2">
                    <label class="loka-form-label">Start Date</label>
                    <input type="text" class="loka-form-input datepicker" name="start_date" value="<?= e($startDate) ?>">
                </div>
                <div class="col-span-6 md:col-span-2">
                    <label class="loka-form-label">End Date</label>
                    <input type="text" class="loka-form-input datepicker" name="end_date" value="<?= e($endDate) ?>">
                </div>
                <div class="col-span-12 md:col-span-3">
                    <button type="submit" class="loka-btn-outline-primary me-2"><i class="bi bi-filter me-1"></i>Filter</button>
                    <a href="<?= APP_URL ?>/?page=audit" class="loka-btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card table-card">
        <div class="card-body">
            <div class="loka-table-responsive">
                <table class="loka-table data-table">
                    <thead>
                        <tr><th>Date/Time</th><th>User</th><th>Action</th><th>Entity</th><th>IP Address</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= formatDateTime($log->created_at) ?></td>
                            <td>
                                <strong><?= e($log->user_name ?: 'System') ?></strong>
                                <?php if ($log->user_email): ?>
                                <small class="block text-base-content/60"><?= e($log->user_email) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark"><?= e($log->action) ?></span></td>
                            <td>
                                <?= e($log->entity_type) ?>
                                <?php if ($log->entity_id): ?>
                                <small class="text-base-content/60">#<?= $log->entity_id ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-base-content/60"><?= e($log->ip_address ?: '-') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
