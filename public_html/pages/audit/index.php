<?php
/**
 * LOKA - Audit Logs Page
 */

requireRole(ROLE_ADMIN);

$pageTitle = 'Audit Logs';
$routeAction = (string) get('action', 'list');
$isExport = ($routeAction === 'export-csv');

$userFilter = get('user', '');
$actionFilter = trim((string) get('filter_action', ''));
// Legacy bookmarks used name="action" for the action filter text.
if ($actionFilter === '' && !$isExport) {
    $legacyAction = (string) get('action', '');
    if ($legacyAction !== '' && $legacyAction !== 'list') {
        $actionFilter = $legacyAction;
    }
}

$searchQuery = listSearchQuery();
$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-d'));

$params = [$startDate, $endDate . ' 23:59:59'];
$whereClause = 'a.created_at BETWEEN ? AND ?';

if ($userFilter) {
    $whereClause .= ' AND a.user_id = ?';
    $params[] = $userFilter;
}

if ($actionFilter !== '') {
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

$fromSql = "FROM audit_logs a
     LEFT JOIN users u ON a.user_id = u.id
     WHERE {$whereClause}";

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

$countRow = db()->fetch("SELECT COUNT(*) as c {$fromSql}", $params);
$totalLogs = (int) ($countRow->c ?? 0);
$pag = listPaginationState($totalLogs);

$baseParams = tableSortQueryParams($sortState, [
    'page' => 'audit',
    'user' => $userFilter,
    'filter_action' => $actionFilter,
    'q' => $searchQuery,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'per_page' => $pag['perPage'],
]);

if ($isExport) {
    $exportLimit = 10000;
    $exportRows = db()->fetchAll(
        "SELECT a.created_at, u.name as user_name, u.email as user_email,
                a.action, a.entity_type, a.entity_id, a.ip_address
         {$fromSql}
         ORDER BY {$sortState['orderSql']}
         LIMIT ?",
        array_merge($params, [$exportLimit])
    );

    auditLog('audit_logs_exported', 'audit_log', null, null, [
        'rows' => count($exportRows),
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);

    $filename = 'audit_logs_' . $startDate . '_to_' . $endDate . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Date/Time', 'User', 'Email', 'Action', 'Entity Type', 'Entity ID', 'IP Address']);
    foreach ($exportRows as $row) {
        fputcsv($out, [
            $row->created_at ?? '',
            $row->user_name ?: 'System',
            $row->user_email ?? '',
            $row->action ?? '',
            $row->entity_type ?? '',
            $row->entity_id ?? '',
            $row->ip_address ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$logs = db()->fetchAll(
    "SELECT a.*, u.name as user_name, u.email as user_email
     {$fromSql}
     ORDER BY {$sortState['orderSql']}
     LIMIT ? OFFSET ?",
    array_merge($params, [$pag['perPage'], $pag['offset']])
);

$exportUrl = '?' . http_build_query(array_filter(
    array_merge($baseParams, ['action' => 'export-csv']),
    static fn($value) => $value !== null && $value !== ''
));
$users = db()->fetchAll("SELECT id, name FROM users WHERE deleted_at IS NULL ORDER BY name");

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Audit Logs</h1>
            <p class="text-sm text-base-content/60">
                <?= number_format($totalLogs) ?> matching record<?= $totalLogs === 1 ? '' : 's' ?>
                · <?= (int) $pag['perPage'] ?> per page
            </p>
        </div>
        <a href="<?= e($exportUrl) ?>" class="loka-btn-secondary">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>

    <div class="loka-card mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="page" value="audit">
            <?= listSearchFieldHtml($searchQuery, 'Action, user, entity, IP...') ?>
            <div class="flex flex-col gap-1.5 min-w-[150px]">
                <label class="label py-0"><span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">User</span></label>
                <select name="user" class="select select-bordered select-sm bg-base-100">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?= $user->id ?>" <?= (string) $userFilter === (string) $user->id ? 'selected' : '' ?>><?= e($user->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex flex-col gap-1.5 min-w-[140px]">
                <label class="label py-0"><span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Action</span></label>
                <input type="text" name="filter_action" class="input input-bordered input-sm bg-base-100" value="<?= e($actionFilter) ?>" placeholder="e.g. login">
            </div>
            <div class="flex flex-col gap-1.5 min-w-[140px]">
                <label class="label py-0"><span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">From</span></label>
                <input type="text" class="input input-bordered input-sm bg-base-100 datepicker" name="start_date" value="<?= e($startDate) ?>">
            </div>
            <div class="flex flex-col gap-1.5 min-w-[140px]">
                <label class="label py-0"><span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">To</span></label>
                <input type="text" class="input input-bordered input-sm bg-base-100 datepicker" name="end_date" value="<?= e($endDate) ?>">
            </div>
            <?= perPageFieldHtml($pag['perPage']) ?>
            <div class="flex gap-2">
                <button type="submit" class="loka-btn-primary loka-btn-sm">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                <a href="<?= APP_URL ?>/?page=audit" class="loka-btn-secondary loka-btn-sm">Reset</a>
            </div>
        </form>
    </div>

    <div class="loka-card">
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
                        <td><span class="loka-badge bg-base-200 text-base-content"><?= e($log->action) ?></span></td>
                        <td>
                            <?= e($log->entity_type) ?>
                            <?php if ($log->entity_id): ?>
                            <small class="text-base-content/60">#<?= (int) $log->entity_id ?></small>
                            <?php endif; ?>
                        </td>
                        <td><small class="text-base-content/60"><?= e($log->ip_address ?: '-') ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 pb-4">
            <?= listPaginationFooter($pag, $baseParams) ?>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
