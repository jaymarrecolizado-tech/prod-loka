<?php
/**
 * LOKA - Edit Department Page
 */

requireRole(ROLE_ADMIN);

$deptId = (int) get('id');
$dept = db()->fetch("SELECT * FROM departments WHERE id = ? AND deleted_at IS NULL", [$deptId]);
if (!$dept) redirectWith('/?page=departments', 'danger', 'Department not found.');

$errors = [];
$users = getEmployees(userId() ?: 0); // Use cached employees

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $name = postSafe('name', '', 100);
    $description = postSafe('description', '', 500);
    $headUserId = postInt('head_user_id') ?: null;
    $status = postSafe('status', '', 20);
    
    if (empty($name)) $errors[] = 'Name is required';
    
    if ($name && $name !== $dept->name) {
        $existing = db()->fetch("SELECT id FROM departments WHERE name = ? AND id != ? AND deleted_at IS NULL", [$name, $deptId]);
        if ($existing) $errors[] = 'Department name already exists';
    }
    
    if (empty($errors)) {
        db()->update('departments', [
            'name' => $name,
            'description' => $description,
            'head_user_id' => $headUserId,
            'status' => $status,
            'updated_at' => date(DATETIME_FORMAT)
        ], 'id = ?', [$deptId]);

        auditLog('department_updated', 'department', $deptId);
        clearDepartmentCache(); // Clear department cache after updating department
        redirectWith('/?page=departments', 'success', 'Department updated successfully.');
    }
}

$pageTitle = 'Edit Department';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Edit Department: <?= e($dept->name) ?></h1>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="lg:col-span-1">
            <div class="loka-card">
                <div class="p-6">
                    <h5 class="text-lg font-semibold text-base-content mb-4"><i class="bi bi-pencil me-2"></i>Edit Department</h5>
                    <?php if (!empty($errors)): ?><div class="loka-alert loka-alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                    
                    <form method="POST">
                        <?= csrfField() ?>
                        <div class="mb-4">
                            <label class="loka-form-label">Name <span class="text-error">*</span></label>
                            <input type="text" class="loka-form-input" name="name" value="<?= e(post('name', $dept->name)) ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="loka-form-label">Description</label>
                            <textarea class="loka-form-input" name="description" rows="3" maxlength="500"><?= e(post('description', $dept->description)) ?></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="loka-form-label">Department Head</label>
                            <select class="select select-bordered w-full bg-base-100" name="head_user_id">
                                <option value="">Select head...</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user->id ?>" <?= post('head_user_id', $dept->head_user_id) == $user->id ? 'selected' : '' ?>><?= e($user->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="loka-form-label">Status</label>
                            <select class="select select-bordered w-full bg-base-100" name="status">
                                <option value="active" <?= post('status', $dept->status) === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= post('status', $dept->status) === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <hr class="border-t border-base-200 my-6">
                        <button type="submit" class="loka-btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                        <a href="<?= APP_URL ?>/?page=departments" class="loka-btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
