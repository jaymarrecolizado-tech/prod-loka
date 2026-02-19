<?php
/**
 * LOKA - Create Department Page
 */

requireRole(ROLE_ADMIN);

$pageTitle = 'Add Department';
$result = null;
$errors = [];

// Get cached employees for department head selection
$users = getEmployees(userId() ?: 0);

if (beginFormProcessing()) {
    // Collect form data
    $data = [
        'name' => postString('name'),
        'description' => postString('description'),
        'head_user_id' => postInt('head_user_id') ?: null,
        'status' => 'active'
    ];

    // Validate using shared function
    $errors = validateDepartmentForm($data);

    // Process if no errors
    if (empty($errors)) {
        $result = processDepartmentForm($data);
        if ($result->isSuccess()) {
            $result->redirect();
        }
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h4 class="mb-1">Add Department</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=departments">Departments</a></li>
                <li class="breadcrumb-item active">Add</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-building me-2"></i>Department Details</h5>
                </div>
                <div class="card-body">
                    <?= showErrors($errors) ?>

                    <form method="POST">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name"
                                value="<?= e(post('name')) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?= e(post('description')) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Head</label>
                            <select class="form-select" name="head_user_id">
                                <option value="">Select head...</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user->id ?>"
                                        <?= post('head_user_id') == $user->id ? 'selected' : '' ?>>
                                        <?= e($user->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <hr class="my-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Create Department
                        </button>
                        <a href="<?= APP_URL ?>/?page=departments" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
