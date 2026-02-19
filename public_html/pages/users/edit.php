<?php
/**
 * LOKA - Edit User Page
 */

requireRole(ROLE_ADMIN);

$userId = (int) get('id');
$user = db()->fetch("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
if (!$user) redirectWith('/?page=users', 'danger', 'User not found.');

$result = null;
$errors = [];

// Get cached departments
$departments = getDepartments();

if (beginFormProcessing()) {
    // Collect form data
    $data = [
        'name' => postString('name'),
        'email' => postString('email'),
        'password' => post('password'), // Optional for edits
        'phone' => postString('phone'),
        'role' => postString('role'),
        'department_id' => postInt('department_id') ?: null
    ];

    // Validate using shared function (password optional for edits)
    $errors = validateUserForm($data, $userId, !empty($data['password']));

    // Process if no errors
    if (empty($errors)) {
        $result = processUserForm($data, $userId);
        if ($result->isSuccess()) {
            $result->redirect();
        }
    }
}

$pageTitle = 'Edit User: ' . e($user->name);
require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h4 class="mb-1">Edit User: <?= e($user->name) ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=users">Users</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit User</h5>
                </div>
                <div class="card-body">
                    <?= showErrors($errors) ?>

                    <form method="POST">
                        <?= csrfField() ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name"
                                    value="<?= e(post('name', $user->name)) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email"
                                    value="<?= e(post('email', $user->email)) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="password"
                                    minlength="8" placeholder="Leave blank to keep current password">
                                <small class="text-muted">Leave blank to keep current password</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone"
                                    value="<?= e(post('phone', $user->phone)) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" name="role" required>
                                    <?php foreach (ROLE_LABELS as $key => $info): ?>
                                        <option value="<?= $key ?>"
                                            <?= post('role', $user->role) === $key ? 'selected' : '' ?>>
                                            <?= e($info['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department_id">
                                    <option value="">No department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept->id ?>"
                                            <?= post('department_id', $user->department_id) == $dept->id ? 'selected' : '' ?>>
                                            <?= e($dept->name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <hr class="my-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                        <a href="<?= APP_URL ?>/?page=users" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
