<?php
/**
 * LOKA - Audit Logs Page
 */

requireRole(ROLE_ADMIN);

$pageTitle = 'Audit Logs';

$userFilter = get('user', '');
$actionFilter = get('action', '');
$searchQuery = listSearchQuery();
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

if ($searchQuery) {
    $whereClause .= ' AND (a.action LIKE ? OR a.entity_type LIKE ? OR a.ip_address LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Sorting (latest logs first by default)
$allowedSortColumns = [
    'created_at' => 'a.created_at',
    'user_name' => 'u.name',
    'action' => 'a.action',
    'entity_type' => 'a.entity_type',
    'ip_address' => 'a.ip_address',
];
$sortState = resolveTableSort($allowedSortColumns, 'created_at', 'DESC');
$sort = $sortState['key'];
$sortDir = $sortState['dir'];

$countRow = db()->fetch(
    "SELECT COUNT(*) as c
     FROM audit_logs a
     LEFT JOIN users u ON a.user_id = u.id
     WHERE {$whereClause}",
    $params
);
$pag = listPaginationState((int) ($countRow->c ?? 0));

$logs = db()->fetchAll(
    "SELECT a.*, u.name as user_name, u.email as user_email
     FROM audit_logs a
     LEFT JOIN users u ON a.user_id = u.id
     WHERE {$whereClause}
     ORDER BY {$sortState['orderSql']}
     LIMIT ? OFFSET ?",
    array_merge($params, [$pag['perPage'], $pag['offset']])
);

$baseParams = tableSortQueryParams($sortState, [
    'page' => 'audit',
    'user' => $userFilter,
    'action' => $actionFilter,
    'q' => $searchQuery,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'per_page' => $pag['perPage'],
]);

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
    <div class="loka-card mb-4">
        <div class="p-4 md:p-6">
            <form method="GET" class="grid grid-cols-12 gap-3 items-end">
                <input type="hidden" name="page" value="audit">
                <div class="col-span-12 md:col-span-3">
                    <?= listSearchFieldHtml($searchQuery, 'Action, user, entity, IP...', 'loka-form-input') ?>
                </div>
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
                <div class="col-span-6 md:col-span-2">
                    <?= perPageFieldHtml($pag['perPage'], 'loka-form-input') ?>
                </div>
                <div class="col-span-12 md:col-span-2">
                    <button type="submit" class="loka-btn-outline-primary me-2"><i class="bi bi-filter me-1"></i>Filter</button>
                    <a href="<?= APP_URL ?>/?page=audit" class="loka-btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="loka-card">
        <div class="p-4 md:p-6">
            <div class="loka-table-responsive">
                <table class="loka-table">
                    <thead>
                        <tr>
                            <?= tableSortTh('created_at', 'Date/Time', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('user_name', 'User', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('action', 'Action', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('entity_type', 'Entity', $sort, $sortDir, $baseParams) ?>
                            <?= tableSortTh('ip_address', 'IP Address', $sort, $sortDir, $baseParams) ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-base-content/50 py-6">No audit logs match your filters.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= formatDateTime($log->created_at) ?></td>
                            <td>
                                <strong><?= e($log->user_name ?: 'System') ?></strong>
                                <?php if ($log->user_email): ?>
                                <small class="block text-base-content/60"><?= e($log->user_email) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="loka-badge bg-light text-dark"><?= e($log->action) ?></span></td>
                            <td>
                                <?= e($log->entity_type) ?>
                                <?php if ($log->entity_id): ?>
                                <small class="text-base-content/60">#<?= $log->entity_id ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-base-content/60"><?= e($log->ip_address ?: '-') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= listPaginationFooter($pag, $baseParams) ?>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
