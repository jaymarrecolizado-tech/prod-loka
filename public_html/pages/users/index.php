<?php
/**
 * LOKA - Users List Page
 */

requireRole(ROLE_MOTORPOOL);

$pageTitle = 'Users';
$roleFilter = get('role', '');
$statusFilter = get('status', '');
$searchQuery = trim(get('search', ''));

// Pagination
$perPage = 15;
$currentPage = max(1, (int) get('p', 1));
$offset = ($currentPage - 1) * $perPage;

$params = [];
$whereClause = 'deleted_at IS NULL';
$whereClauseAliased = 'u.deleted_at IS NULL';

if ($roleFilter) {
    $whereClause .= ' AND role = ?';
    $whereClauseAliased .= ' AND u.role = ?';
    $params[] = $roleFilter;
}

if ($statusFilter) {
    $whereClause .= ' AND status = ?';
    $whereClauseAliased .= ' AND u.status = ?';
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $whereClause .= ' AND (name LIKE ? OR email LIKE ?)';
    $whereClauseAliased .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
    $searchPattern = '%' . $searchQuery . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

// Get total count for pagination
$totalUsers = db()->count('users', $whereClause, $params);
$totalPages = ceil($totalUsers / $perPage);

// Get paginated users
$users = db()->fetchAll(
    "SELECT u.*, d.name as department_name
     FROM users u
     LEFT JOIN departments d ON u.department_id = d.id
     WHERE {$whereClauseAliased}
     ORDER BY u.name
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

$departments = db()->fetchAll("SELECT * FROM departments WHERE departments.deleted_at IS NULL ORDER BY name");

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Users</h1>
            <p class="text-sm text-base-content/60">Manage system users and permissions</p>
        </div>
        <?php if (isAdmin()): ?>
        <a href="<?= APP_URL ?>/?page=users&action=create" class="loka-btn-primary"><i class="bi bi-plus-lg me-1"></i>Add User</a>
        <?php endif; ?>
    </div>

    <div class="loka-card mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="page" value="users">
            <div class="min-w-[150px]">
                <label class="loka-form-label">Search (Name/Email)</label>
                <input type="text" name="search" class="loka-form-input" placeholder="Search by name or email..." value="<?= e($searchQuery) ?>">
            </div>
            <div class="min-w-[150px]">
                <label class="loka-form-label">Role</label>
                <select name="role" class="select select-bordered w-full bg-base-100">
                    <option value="">All Roles</option>
                    <?php foreach (ROLE_LABELS as $key => $info): ?>
                    <option value="<?= $key ?>" <?= $roleFilter === $key ? 'selected' : '' ?>><?= e($info['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="min-w-[150px]">
                <label class="loka-form-label">Status</label>
                <select name="status" class="select select-bordered w-full bg-base-100">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div>
                <button type="submit" class="loka-btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= APP_URL ?>/?page=users" class="loka-btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="loka-card">
        <div class="loka-table-responsive">
            <table class="loka-table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><span class="font-semibold text-base-content"><?= e($user->name) ?></span></td>
                        <td><?= e($user->email) ?></td>
                        <td><span class="loka-badge bg-base-200 text-base-content"><?= e($user->department_name ?: 'None') ?></span></td>
                        <td><?= roleBadge($user->role) ?></td>
                        <td><span class="loka-badge <?= $user->status === 'active' ? 'bg-success/20 text-success' : 'bg-base-200 text-base-content/60' ?>"><?= ucfirst($user->status) ?></span></td>
                        <td><?= $user->last_login_at ? formatDateTime($user->last_login_at) : 'Never' ?></td>
                        <td>
                            <?php if (isAdmin()): ?>
                            <div class="flex items-center gap-1">
                                <a href="<?= APP_URL ?>/?page=users&action=edit&id=<?= $user->id ?>" class="loka-btn-icon text-base-content/60 hover:bg-base-200"><i class="bi bi-pencil"></i></a>
                                <a href="<?= APP_URL ?>/?page=users&action=toggle&id=<?= $user->id ?>" class="loka-btn-icon <?= $user->status === 'active' ? 'text-warning hover:bg-warning/10' : 'text-success hover:bg-success/10' ?>" data-confirm="<?= $user->status === 'active' ? 'Deactivate' : 'Activate' ?> this user?">
                                    <i class="bi bi-<?= $user->status === 'active' ? 'x-circle' : 'check-circle' ?>"></i>
                                </a>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="border-t border-base-200 px-6 py-4">
            <div class="flex justify-center">
                <div class="join">
                    <a class="join-item btn btn-sm <?= $currentPage <= 1 ? 'btn-disabled' : '' ?>" href="<?= APP_URL ?>/?page=users&p=<?= $currentPage - 1 ?><?= $roleFilter ? '&role=' . $roleFilter : '' ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>">&laquo;</a>

                    <?php
                    $start = max(1, $currentPage - 2);
                    $end = min($totalPages, $currentPage + 2);

                    if ($start > 1): ?>
                    <a class="join-item btn btn-sm" href="<?= APP_URL ?>/?page=users&p=1<?= $roleFilter ? '&role=' . $roleFilter : '' ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>">1</a>
                    <?php if ($start > 2): ?>
                    <button class="join-item btn btn-sm btn-disabled">...</button>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a class="join-item btn btn-sm <?= $i === $currentPage ? 'btn-primary' : '' ?>" href="<?= APP_URL ?>/?page=users&p=<?= $i ?><?= $roleFilter ? '&role=' . $roleFilter : '' ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                    <button class="join-item btn btn-sm btn-disabled">...</button>
                    <?php endif; ?>
                    <a class="join-item btn btn-sm" href="<?= APP_URL ?>/?page=users&p=<?= $totalPages ?><?= $roleFilter ? '&role=' . $roleFilter : '' ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>"><?= $totalPages ?></a>
                    <?php endif; ?>

                    <a class="join-item btn btn-sm <?= $currentPage >= $totalPages ? 'btn-disabled' : '' ?>" href="<?= APP_URL ?>/?page=users&p=<?= $currentPage + 1 ?><?= $roleFilter ? '&role=' . $roleFilter : '' ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>">&raquo;</a>
                </div>
            </div>
            <div class="text-center mt-2">
                <span class="text-xs text-base-content/50">Page <?= $currentPage ?> of <?= $totalPages ?> (<?= $totalUsers ?> total users)</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
