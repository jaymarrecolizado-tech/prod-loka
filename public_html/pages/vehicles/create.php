<?php
/**
 * LOKA - Create Vehicle Page
 */

requireRole(ROLE_APPROVER);

$pageTitle = 'Add Vehicle';
$errors = [];

$vehicleTypes = getVehicleTypes(); // Use cached vehicle types

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $plateNumber = postSafe('plate_number', '', 50);
    $make = postSafe('make', '', 100);
    $model = postSafe('model', '', 100);
    $year = postInt('year');
    $vehicleTypeId = postInt('vehicle_type_id');
    $color = postSafe('color', '', 50);
    $fuelType = postSafe('fuel_type', '', 20);
    $transmission = postSafe('transmission', '', 20);
    $mileage = postInt('mileage', 0);
    $notes = postSafe('notes', '', 500);
    
    // Validation
    if (empty($plateNumber)) $errors[] = 'Plate number is required';
    if (empty($make)) $errors[] = 'Make is required';
    if (empty($model)) $errors[] = 'Model is required';
    if (empty($year)) $errors[] = 'Year is required';
    if (empty($vehicleTypeId)) $errors[] = 'Vehicle type is required';
    
    // Check unique plate
    if ($plateNumber) {
        $existing = db()->fetch("SELECT id FROM vehicles WHERE plate_number = ? AND deleted_at IS NULL", [$plateNumber]);
        if ($existing) $errors[] = 'Plate number already exists';
    }
    
    if (empty($errors)) {
        db()->beginTransaction();
        
        try {
            $vehicleId = db()->insert('vehicles', [
                'plate_number' => $plateNumber,
                'make' => $make,
                'model' => $model,
                'year' => $year,
                'vehicle_type_id' => $vehicleTypeId,
                'color' => $color,
                'fuel_type' => $fuelType,
                'transmission' => $transmission,
                'mileage' => $mileage,
                'notes' => $notes,
                'status' => VEHICLE_AVAILABLE,
                'created_at' => date(DATETIME_FORMAT),
                'updated_at' => date(DATETIME_FORMAT)
            ]);
            
            auditLog('vehicle_created', 'vehicle', $vehicleId);

            db()->commit();
            clearVehicleCache(); // Clear vehicle cache after adding vehicle
            redirectWith('/?page=vehicles', 'success', 'Vehicle added successfully.');
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Failed to create vehicle.';
        }
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
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
    
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 lg:col-span-8">
            <div class="loka-card">
                <div class="p-4 border-b border-base-200">
                    <h5 class="mb-0"><i class="bi bi-car-front me-2"></i>Vehicle Details</h5>
                </div>
                <div class="p-4">
                    <?php if (!empty($errors)): ?>
                    <div class="loka-alert loka-alert-danger">
                        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <?= csrfField() ?>
                        
                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-12 md:col-span-6">
                                <label class="loka-form-label">Plate Number <span class="text-error">*</span></label>
                                <input type="text" class="loka-form-input" name="plate_number" value="<?= e(post('plate_number', '')) ?>" required>
                            </div>
                            <div class="col-span-12 md:col-span-6">
                                <label class="loka-form-label">Vehicle Type <span class="text-error">*</span></label>
                                <select class="loka-form-input" name="vehicle_type_id" required>
                                    <option value="">Select type...</option>
                                    <?php foreach ($vehicleTypes as $type): ?>
                                    <option value="<?= $type->id ?>" <?= post('vehicle_type_id') == $type->id ? 'selected' : '' ?>><?= e($type->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="loka-form-label">Make <span class="text-error">*</span></label>
                                <input type="text" class="loka-form-input" name="make" value="<?= e(post('make', '')) ?>" required>
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="loka-form-label">Model <span class="text-error">*</span></label>
                                <input type="text" class="loka-form-input" name="model" value="<?= e(post('model', '')) ?>" required>
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="loka-form-label">Year <span class="text-error">*</span></label>
                                <input type="text" class="loka-form-input" name="year" value="<?= e(post('year', date('Y'))) ?>" required>
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="loka-form-label">Color</label>
                                <input type="text" class="loka-form-input" name="color" value="<?= e(post('color', '')) ?>">
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="loka-form-label">Fuel Type</label>
                                <select class="loka-form-input" name="fuel_type">
                                    <option value="gasoline" <?= post('fuel_type') === 'gasoline' ? 'selected' : '' ?>>Gasoline</option>
                                    <option value="diesel" <?= post('fuel_type') === 'diesel' ? 'selected' : '' ?>>Diesel</option>
                                    <option value="electric" <?= post('fuel_type') === 'electric' ? 'selected' : '' ?>>Electric</option>
                                    <option value="hybrid" <?= post('fuel_type') === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                                </select>
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="loka-form-label">Transmission</label>
                                <select class="loka-form-input" name="transmission">
                                    <option value="automatic" <?= post('transmission') === 'automatic' ? 'selected' : '' ?>>Automatic</option>
                                    <option value="manual" <?= post('transmission') === 'manual' ? 'selected' : '' ?>>Manual</option>
                                </select>
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="loka-form-label">Mileage (km)</label>
                                <input type="number" class="loka-form-input" name="mileage" value="<?= e(post('mileage', '0')) ?>" min="0">
                            </div>
                            <div class="col-span-12">
                                <label class="loka-form-label">Notes</label>
                                <textarea class="loka-form-input" name="notes" rows="2" maxlength="500"><?= e(post('notes', '')) ?></textarea>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <button type="submit" class="loka-btn-primary"><i class="bi bi-check-lg me-1"></i>Save Vehicle</button>
                        <a href="<?= APP_URL ?>/?page=vehicles" class="loka-btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
