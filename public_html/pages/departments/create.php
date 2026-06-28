<?php
/**
 * LOKA - Create Department Page
 */

requireRole(ROLE_ADMIN);

$pageTitle = 'Add Department';
$errors = [];

$users = getEmployees(userId() ?: 0); // Use cached employees, exclude current user (but none for this page)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $name = postSafe('name', '', 100);
    $description = postSafe('description', '', 500);
    $headUserId = postInt('head_user_id') ?: null;
    
    if (empty($name)) $errors[] = 'Name is required';
    
    if ($name) {
        $existing = db()->fetch("SELECT id FROM departments WHERE name = ? AND deleted_at IS NULL", [$name]);
        if ($existing) $errors[] = 'Department name already exists';
    }
    
    if (empty($errors)) {
        $deptId = db()->insert('departments', [
            'name' => $name,
            'description' => $description,
            'head_user_id' => $headUserId,
            'status' => 'active',
            'created_at' => date(DATETIME_FORMAT),
            'updated_at' => date(DATETIME_FORMAT)
        ]);

        auditLog('department_created', 'department', $deptId);
        clearDepartmentCache(); // Clear department cache after creating department
        redirectWith('/?page=departments', 'success', 'Department created successfully.');
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Add Department</h1>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="lg:col-span-1">
            <div class="loka-card">
                <div class="p-6">
                    <h5 class="text-lg font-semibold text-base-content mb-4"><i class="bi bi-building me-2"></i>Department Details</h5>
                    <?php if (!empty($errors)): ?><div class="loka-alert loka-alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                    
                    <form method="POST">
                        <?= csrfField() ?>
                        <div class="mb-4">
                            <label class="loka-form-label">Name <span class="text-error">*</span></label>
                            <input type="text" class="loka-form-input" name="name" value="<?= e(post('name', '')) ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="loka-form-label">Description</label>
                            <textarea class="loka-form-input" name="description" rows="3" maxlength="500"><?= e(post('description', '')) ?></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="loka-form-label">Department Head</label>
                            <select class="select select-bordered w-full bg-base-100" name="head_user_id">
                                <option value="">Select head...</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user->id ?>" <?= post('head_user_id') == $user->id ? 'selected' : '' ?>><?= e($user->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <hr class="border-t border-base-200 my-6">
                        <button type="submit" class="loka-btn-primary"><i class="bi bi-check-lg me-1"></i>Create Department</button>
                        <a href="<?= APP_URL ?>/?page=departments" class="loka-btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
