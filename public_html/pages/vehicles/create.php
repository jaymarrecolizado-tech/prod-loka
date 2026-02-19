<?php
/**
 * LOKA - Create Vehicle Page
 */

requireRole(ROLE_APPROVER);

$pageTitle = 'Add Vehicle';
$result = null;
$errors = [];

// Get cached vehicle types
$vehicleTypes = getVehicleTypes();

if (beginFormProcessing()) {
    // Collect form data
    $data = [
        'plate_number' => postString('plate_number'),
        'make' => postString('make'),
        'model' => postString('model'),
        'year' => postString('year'),
        'vehicle_type_id' => postInt('vehicle_type_id'),
        'color' => postString('color'),
        'fuel_type' => postString('fuel_type'),
        'transmission' => postString('transmission'),
        'mileage' => postInt('mileage', 0),
        'notes' => postString('notes'),
        'status' => VEHICLE_AVAILABLE
    ];

    // Validate using shared function
    $errors = validateVehicleForm($data);

    // Process if no errors
    if (empty($errors)) {
        $result = processVehicleForm($data);
        if ($result->isSuccess()) {
            $result->redirect();
        }
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h4 class="mb-1">Add Vehicle</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=vehicles">Vehicles</a></li>
                <li class="breadcrumb-item active">Add</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-car-front me-2"></i>Vehicle Details</h5>
                </div>
                <div class="card-body">
                    <?= showErrors($errors) ?>

                    <form method="POST">
                        <?= csrfField() ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Plate Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="plate_number"
                                    value="<?= e(post('plate_number')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Vehicle Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="vehicle_type_id" required>
                                    <option value="">Select type...</option>
                                    <?php foreach ($vehicleTypes as $type): ?>
                                        <option value="<?= $type->id ?>"
                                            <?= post('vehicle_type_id') == $type->id ? 'selected' : '' ?>>
                                            <?= e($type->name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Make <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="make"
                                    value="<?= e(post('make')) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Model <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="model"
                                    value="<?= e(post('model')) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Year <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="year"
                                    value="<?= e(post('year')) ?>" min="1990" max="<?= date('Y') + 1 ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Color</label>
                                <input type="text" class="form-control" name="color"
                                    value="<?= e(post('color')) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fuel Type</label>
                                <select class="form-select" name="fuel_type">
                                    <option value="">Select...</option>
                                    <option value="gasoline" <?= post('fuel_type') === 'gasoline' ? 'selected' : '' ?>>Gasoline</option>
                                    <option value="diesel" <?= post('fuel_type') === 'diesel' ? 'selected' : '' ?>>Diesel</option>
                                    <option value="electric" <?= post('fuel_type') === 'electric' ? 'selected' : '' ?>>Electric</option>
                                    <option value="hybrid" <?= post('fuel_type') === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Transmission</label>
                                <select class="form-select" name="transmission">
                                    <option value="">Select...</option>
                                    <option value="manual" <?= post('transmission') === 'manual' ? 'selected' : '' ?>>Manual</option>
                                    <option value="automatic" <?= post('transmission') === 'automatic' ? 'selected' : '' ?>>Automatic</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Mileage</label>
                                <input type="number" class="form-control" name="mileage"
                                    value="<?= e(post('mileage', '0')) ?>" min="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3"><?= e(post('notes')) ?></textarea>
                            </div>
                        </div>
                        <hr class="my-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Add Vehicle
                        </button>
                        <a href="<?= APP_URL ?>/?page=vehicles" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
