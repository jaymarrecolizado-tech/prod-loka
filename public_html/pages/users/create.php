<?php
/**
 * LOKA - Create User Page
 */

requireRole(ROLE_ADMIN);

$pageTitle = 'Add User';
$result = null;
$errors = [];

// Get cached departments
$departments = getDepartments();

if (beginFormProcessing()) {
    // Collect form data
    $data = [
        'name' => postString('name'),
        'email' => postString('email'),
        'password' => post('password'),
        'phone' => postString('phone'),
        'role' => postString('role'),
        'department_id' => postInt('department_id') ?: null
    ];

    // Validate using shared function (password required for new users)
    $errors = validateUserForm($data, null, true);

    // Process if no errors
    if (empty($errors)) {
        $result = processUserForm($data);
        if ($result->isSuccess()) {
            $result->redirect();
        }
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h4 class="mb-1">Add User</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=users">Users</a></li>
                <li class="breadcrumb-item active">Add</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>User Details</h5>
                </div>
                <div class="card-body">
                    <?= showErrors($errors) ?>

                    <form method="POST">
                        <?= csrfField() ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name"
                                    value="<?= e(post('name')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email"
                                    value="<?= e(post('email')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password"
                                    minlength="8" required>
                                <small class="text-muted"><?= Security::getInstance()->getPasswordRequirements() ?></small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone"
                                    value="<?= e(post('phone')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" name="role" required>
                                    <option value="">Select role...</option>
                                    <?php foreach (ROLE_LABELS as $key => $info): ?>
                                        <option value="<?= $key ?>"><?= e($info['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department_id">
                                    <option value="">No department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept->id ?>"
                                            <?= post('department_id') == $dept->id ? 'selected' : '' ?>>
                                            <?= e($dept->name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <hr class="my-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Create User
                        </button>
                        <a href="<?= APP_URL ?>/?page=users" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
