<?php
/**
 * LOKA - Create User Page
 */

requireRole(ROLE_ADMIN);

$pageTitle = 'Add User';
$errors = [];
$security = Security::getInstance();

$departments = db()->fetchAll("SELECT * FROM departments WHERE deleted_at IS NULL AND status = 'active' ORDER BY name");

// Valid roles whitelist
$validRoles = array_keys(ROLE_LABELS);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $name = postSafe('name', '', 100);
    $email = $security->sanitizeEmail(post('email'));
    $password = post('password'); // Don't sanitize password
    $phone = postSafe('phone', '', 20);
    $role = post('role');
    $departmentId = postInt('department_id') ?: null;

    // Validation
    if (empty($name))
        $errors[] = 'Name is required';
    if (empty($email))
        $errors[] = 'Email is required';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email format';

    if (empty($password)) {
        $errors[] = 'Password is required';
    } else {
        // Validate password against policy
        $passwordErrors = $security->validatePassword($password);
        $errors = array_merge($errors, $passwordErrors);
    }

    if (empty($role)) {
        $errors[] = 'Role is required';
    } elseif (!in_array($role, $validRoles)) {
        $errors[] = 'Invalid role selected';
    }

    if ($email) {
        $existing = db()->fetch("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL", [$email]);
        if ($existing)
            $errors[] = 'Email already exists';
    }

    if (empty($errors)) {
        db()->beginTransaction();

        try {
            $auth = new Auth();
            $userId = db()->insert('users', [
                'name' => $name,
                'email' => $email,
                'password' => $auth->hashPassword($password),
                'phone' => $phone,
                'role' => $role,
                'department_id' => $departmentId,
                'status' => USER_ACTIVE,
                'failed_login_attempts' => 0,
                'created_at' => date(DATETIME_FORMAT),
                'updated_at' => date(DATETIME_FORMAT)
            ]);

            auditLog('user_created', 'user', $userId);
            $security->logSecurityEvent('user_created', "New user: $email ($role)", userId());

            db()->commit();
            clearUserCache(); // Clear user cache after creating user
            redirectWith('/?page=users', 'success', 'User created successfully.');
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Failed to create user.';
        }
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Add User</h1>
            <p class="text-sm text-base-content/60">Create a new user account</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="loka-card">
                <h5 class="text-lg font-semibold text-base-content mb-4"><i class="bi bi-person-plus me-2"></i>User Details</h5>
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
                                <input type="text" class="loka-form-input" name="name" value="<?= e(post('name', '')) ?>"
                                    required>
                            </div>
                            <div>
                                <label class="loka-form-label">Email <span class="text-error">*</span></label>
                                <input type="email" class="loka-form-input" name="email"
                                    value="<?= e(post('email', '')) ?>" required>
                            </div>
                            <div>
                                <label class="loka-form-label">Password <span class="text-error">*</span></label>
                                <input type="password" class="loka-form-input" name="password" required
                                    minlength="<?= PASSWORD_MIN_LENGTH ?>">
                                <small class="text-xs text-base-content/50"><?= e($security->getPasswordRequirements()) ?></small>
                            </div>
                            <div>
                                <label class="loka-form-label">Phone</label>
                                <input type="text" class="loka-form-input" name="phone"
                                    value="<?= e(post('phone', '')) ?>">
                            </div>
                            <div>
                                <label class="loka-form-label">Role <span class="text-error">*</span></label>
                                <select class="select select-bordered w-full bg-base-100" name="role" required>
                                    <option value="">Select role...</option>
                                    <?php foreach (ROLE_LABELS as $key => $info): ?>
                                        <option value="<?= $key ?>" <?= post('role') === $key ? 'selected' : '' ?>>
                                            <?= e($info['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="loka-form-label">Department</label>
                                <select class="select select-bordered w-full bg-base-100" name="department_id">
                                    <option value="">No department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept->id ?>" <?= post('department_id') == $dept->id ? 'selected' : '' ?>><?= e($dept->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="border-t border-base-200 my-6"></div>
                        <button type="submit" class="loka-btn-primary"><i class="bi bi-check-lg me-1"></i>Create
                            User</button>
                        <a href="<?= APP_URL ?>/?page=users" class="loka-btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>