<?php
/**
 * LOKA - Edit Vehicle Page
 */

requireRole(ROLE_APPROVER);

$vehicleId = (int) get('id');
$errors = [];

$vehicle = db()->fetch("SELECT * FROM vehicles WHERE id = ? AND deleted_at IS NULL FOR UPDATE", [$vehicleId]);
if (!$vehicle) redirectWith('/?page=vehicles', 'danger', 'Vehicle not found.');

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
    $status = postSafe('status', '', 20);
    $notes = postSafe('notes', '', 500);
    
    if (empty($plateNumber)) $errors[] = 'Plate number is required';
    if (empty($make)) $errors[] = 'Make is required';
    if (empty($model)) $errors[] = 'Model is required';
    
    // Validate status transitions (only if status is actually changing)
    if ($status !== $vehicle->status) {
        $validTransitions = [
            'available' => ['in_use', 'maintenance'],
            'in_use' => ['available', 'completed'],
            'maintenance' => ['available'],
            'completed' => ['available']
        ];

        if (isset($validTransitions[$vehicle->status]) && !in_array($status, $validTransitions[$vehicle->status])) {
            $errors[] = "Cannot change vehicle status from {$vehicle->status} to {$status}";
        }
    }
    
    if (empty($errors)) {
        db()->beginTransaction();
        
        try {
            // Re-fetch with lock to ensure atomicity
            $vehicle = db()->fetch("SELECT * FROM vehicles WHERE id = ? AND deleted_at IS NULL FOR UPDATE", [$vehicleId]);
            
            // Check unique plate (exclude current)
            if ($plateNumber && $plateNumber !== $vehicle->plate_number) {
                $existing = db()->fetch("SELECT id FROM vehicles WHERE plate_number = ? AND id != ? AND deleted_at IS NULL", [$plateNumber, $vehicleId]);
                if ($existing) {
                    db()->rollback();
                    $errors[] = 'Plate number already exists';
                }
            }
            
            if (empty($errors)) {
                $oldData = (array) $vehicle;
                
                db()->update('vehicles', [
                    'plate_number' => $plateNumber,
                    'make' => $make,
                    'model' => $model,
                    'year' => $year,
                    'vehicle_type_id' => $vehicleTypeId,
                    'color' => $color,
                    'fuel_type' => $fuelType,
                    'transmission' => $transmission,
                    'mileage' => $mileage,
                    'status' => $status,
                    'notes' => $notes,
                    'updated_at' => date(DATETIME_FORMAT)
                ], 'id = ?', [$vehicleId]);
                
                auditLog('vehicle_updated', 'vehicle', $vehicleId, $oldData);
                db()->commit();
                clearVehicleCache(); // Clear vehicle cache after updating vehicle
                redirectWith('/?page=vehicles', 'success', 'Vehicle updated successfully.');
            }
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Failed to update vehicle';
        }
    }
}

$pageTitle = 'Edit Vehicle';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="mb-4">
        <h2 class="text-xl font-semibold mb-1">Edit Vehicle</h2>
        <div class="text-sm text-base-content/60">
            <a href="<?= APP_URL ?>" class="hover:text-primary">Dashboard</a>
            <span class="mx-1">/</span>
            <a href="<?= APP_URL ?>/?page=vehicles" class="hover:text-primary">Vehicles</a>
            <span class="mx-1">/</span>
            <span>Edit</span>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="loka-card">
                <div class="p-4 border-b border-base-200">
                    <h3 class="text-base font-semibold flex items-center gap-2">
                        <i class="bi bi-pencil"></i>Edit Vehicle
                    </h3>
                </div>
                <div class="p-4">
                    <?php if (!empty($errors)): ?>
                    <div class="loka-alert loka-alert-danger mb-4">
                        <ul class="list-disc list-inside">
                            <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <?= csrfField() ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="loka-form-label">Plate Number <span class="text-error">*</span></label>
                                <input type="text" class="input input-bordered w-full" name="plate_number" value="<?= e(post('plate_number', $vehicle->plate_number)) ?>" required>
                            </div>
                            <div>
                                <label class="loka-form-label">Vehicle Type</label>
                                <select class="select select-bordered w-full" name="vehicle_type_id">
                                    <?php foreach ($vehicleTypes as $type): ?>
                                    <option value="<?= $type->id ?>" <?= (post('vehicle_type_id', $vehicle->vehicle_type_id) == $type->id) ? 'selected' : '' ?>><?= e($type->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="loka-form-label">Make <span class="text-error">*</span></label>
                                <input type="text" class="input input-bordered w-full" name="make" value="<?= e(post('make', $vehicle->make)) ?>" required>
                            </div>
                            <div>
                                <label class="loka-form-label">Model <span class="text-error">*</span></label>
                                <input type="text" class="input input-bordered w-full" name="model" value="<?= e(post('model', $vehicle->model)) ?>" required>
                            </div>
                            <div>
                                <label class="loka-form-label">Year</label>
                                <input type="text" class="input input-bordered w-full" name="year" value="<?= e(post('year', $vehicle->year)) ?>">
                            </div>
                            <div>
                                <label class="loka-form-label">Color</label>
                                <input type="text" class="input input-bordered w-full" name="color" value="<?= e(post('color', $vehicle->color)) ?>">
                            </div>
                            <div>
                                <label class="loka-form-label">Fuel Type</label>
                                <select class="select select-bordered w-full" name="fuel_type">
                                    <option value="gasoline" <?= post('fuel_type', $vehicle->fuel_type) === 'gasoline' ? 'selected' : '' ?>>Gasoline</option>
                                    <option value="diesel" <?= post('fuel_type', $vehicle->fuel_type) === 'diesel' ? 'selected' : '' ?>>Diesel</option>
                                    <option value="electric" <?= post('fuel_type', $vehicle->fuel_type) === 'electric' ? 'selected' : '' ?>>Electric</option>
                                    <option value="hybrid" <?= post('fuel_type', $vehicle->fuel_type) === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                                </select>
                            </div>
                            <div>
                                <label class="loka-form-label">Transmission</label>
                                <select class="select select-bordered w-full" name="transmission">
                                    <option value="automatic" <?= post('transmission', $vehicle->transmission) === 'automatic' ? 'selected' : '' ?>>Automatic</option>
                                    <option value="manual" <?= post('transmission', $vehicle->transmission) === 'manual' ? 'selected' : '' ?>>Manual</option>
                                </select>
                            </div>
                            <div>
                                <label class="loka-form-label">Status</label>
                                <select class="select select-bordered w-full" name="status">
                                    <?php foreach (VEHICLE_STATUS_LABELS as $key => $info): ?>
                                    <option value="<?= $key ?>" <?= post('status', $vehicle->status) === $key ? 'selected' : '' ?>><?= e($info['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="loka-form-label">Mileage (km)</label>
                                <input type="number" class="input input-bordered w-full" name="mileage" value="<?= e(post('mileage', $vehicle->mileage)) ?>" min="0">
                            </div>
                            <div class="md:col-span-2">
                                <label class="loka-form-label">Notes</label>
                                <textarea class="textarea textarea-bordered w-full" name="notes" rows="2" maxlength="500"><?= e(post('notes', $vehicle->notes)) ?></textarea>
                            </div>
                        </div>
                        <div class="border-t border-base-200 mt-4 pt-4 flex gap-2">
                            <button type="submit" class="loka-btn-primary">
                                <i class="bi bi-check-lg mr-1"></i>Save Changes
                            </button>
                            <a href="<?= APP_URL ?>/?page=vehicles" class="loka-btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
