<?php
/**
 * LOKA - Edit User Page
 */

requireRole(ROLE_ADMIN);

$userId = (int) get('id');
$user = db()->fetch("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL FOR UPDATE", [$userId]);
if (!$user)
    redirectWith('/?page=users', 'danger', 'User not found.');

$errors = [];
$departments = getDepartments(); // Use cached departments

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $name = postSafe('name', '', 100);
    $email = post('email');
    $password = post('password');
    $phone = postSafe('phone', '', 20);
    $role = postSafe('role', '', 20);
    $departmentId = postInt('department_id') ?: null;

    if (empty($name))
        $errors[] = 'Name is required';
    if (empty($email))
        $errors[] = 'Email is required';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email format';
    if ($password && strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters';

    if (empty($errors)) {
        db()->beginTransaction();

        try {
            // Re-fetch with lock to ensure atomicity
            $user = db()->fetch("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL FOR UPDATE", [$userId]);

            // Check unique email (exclude current)
            if ($email && $email !== $user->email) {
                $existing = db()->fetch("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL", [$email, $userId]);
                if ($existing) {
                    db()->rollback();
                    $errors[] = 'Email already exists';
                }
            }

            if (empty($errors)) {
                $updateData = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                    'department_id' => $departmentId,
                    'updated_at' => date(DATETIME_FORMAT)
                ];

                if ($password) {
                    $auth = new Auth();
                    $updateData['password'] = $auth->hashPassword($password);
                }

                db()->update('users', $updateData, 'id = ?', [$userId]);
                auditLog('user_updated', 'user', $userId);
                db()->commit();
                clearUserCache(); // Clear user cache after updating user
                redirectWith('/?page=users', 'success', 'User updated successfully.');
            }
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Failed to update user: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit User';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Edit User: <?= e($user->name) ?></h1>
            <p class="text-sm text-base-content/60">Update user account details</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="loka-card">
                <h5 class="text-lg font-semibold text-base-content mb-4"><i class="bi bi-pencil me-2"></i>Edit User</h5>
                <div class="p-6">
                    <?php if (!empty($errors)): ?>
                        <div class="loka-alert loka-alert-danger">
                            <ul class="mb-0"><?php foreach ($errors as $e): ?>
                                    <li><?= e($e) ?></li><?php endforeach; ?>
                            </ul>
                        </div><?php endif; ?>

                    <form method="POST">
                        <?= csrfField() ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="loka-form-label">Full Name <span class="text-error">*</span></label>
                                <input type="text" class="loka-form-input" name="name"
                                    value="<?= e(post('name', $user->name)) ?>" required>
                            </div>
                            <div>
                                <label class="loka-form-label">Email <span class="text-error">*</span></label>
                                <input type="email" class="loka-form-input" name="email"
                                    value="<?= e(post('email', $user->email)) ?>" required>
                            </div>
                            <div>
                                <label class="loka-form-label">New Password</label>
                                <input type="password" class="loka-form-input" name="password" minlength="8">
                                <small class="text-xs text-base-content/50">Leave blank to keep current password</small>
                            </div>
                            <div>
                                <label class="loka-form-label">Phone</label>
                                <input type="text" class="loka-form-input" name="phone"
                                    value="<?= e(post('phone', $user->phone)) ?>">
                            </div>
                            <div>
                                <label class="loka-form-label">Role</label>
                                <select class="select select-bordered w-full bg-base-100" name="role">
                                    <?php foreach (ROLE_LABELS as $key => $info): ?>
                                        <option value="<?= $key ?>" <?= post('role', $user->role) === $key ? 'selected' : '' ?>><?= e($info['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="loka-form-label">Department</label>
                                <select class="select select-bordered w-full bg-base-100" name="department_id">
                                    <option value="">No department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept->id ?>" <?= post('department_id', $user->department_id) == $dept->id ? 'selected' : '' ?>><?= e($dept->name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="border-t border-base-200 my-6"></div>
                        <button type="submit" class="loka-btn-primary"><i class="bi bi-check-lg me-1"></i>Save
                            Changes</button>
                        <a href="<?= APP_URL ?>/?page=users" class="loka-btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>