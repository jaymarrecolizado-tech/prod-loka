<?php
/**
 * LOKA - Departments List Page
 */

requireRole(ROLE_MOTORPOOL);

$pageTitle = 'Departments';

$departments = db()->fetchAll(
    "SELECT d.*, u.name as head_name,
            (SELECT COUNT(*) FROM users WHERE department_id = d.id AND deleted_at IS NULL) as user_count
     FROM departments d
     LEFT JOIN users u ON d.head_user_id = u.id
     WHERE d.deleted_at IS NULL
     ORDER BY d.name"
);

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Departments</h1>
            <p class="text-sm text-base-content/60">Manage fleet departments</p>
        </div>
        <?php if (isAdmin()): ?>
        <a href="<?= APP_URL ?>/?page=departments&action=create" class="loka-btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Department
        </a>
        <?php endif; ?>
    </div>

    <div class="loka-card">
        <div class="p-6">
            <?php if (empty($departments)): ?>
            <div class="loka-empty"><i class="bi bi-building"></i><h5>No departments found</h5></div>
            <?php else: ?>
            <div class="loka-table-responsive">
                <table class="loka-table data-table">
                    <thead><tr><th>Name</th><th>Description</th><th>Head</th><th>Users</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td><span class="font-semibold text-base-content"><?= e($dept->name) ?></span></td>
                            <td><?= truncate($dept->description ?: '-', 40) ?></td>
                            <td><?= e($dept->head_name ?: '-') ?></td>
                            <td><span class="loka-badge bg-base-200 text-base-content"><?= $dept->user_count ?></span></td>
                            <td><span class="loka-badge bg-<?= $dept->status === 'active' ? 'success/20 text-success' : 'base-200 text-base-content/60' ?>"><?= ucfirst($dept->status) ?></span></td>
                            <td>
                                <?php if (isAdmin()): ?>
                                <a href="<?= APP_URL ?>/?page=departments&action=edit&id=<?= $dept->id ?>" class="loka-btn-icon text-base-content/60 hover:bg-base-200"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
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
