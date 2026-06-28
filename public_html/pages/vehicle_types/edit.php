<?php
/**
 * LOKA - Edit Vehicle Type
 */

requireRole('admin');

$id = getInt('id');

$vehicleType = db()->fetch(
    "SELECT * FROM vehicle_types WHERE id = ? AND deleted_at IS NULL",
    [$id]
);

if (!$vehicleType) {
    redirectWith('/?page=vehicle_types', 'danger', 'Vehicle type not found.');
}

$pageTitle = 'Edit Vehicle Type - ' . e($vehicleType->name);

if (isPost()) {
    requireCsrf();

    $name = postSafe('name', '', 50);
    $description = postSafe('description', '', 500);
    $passengerCapacity = postInt('passenger_capacity');

    // Validate
    if (empty($name)) {
        redirectWith('/?page=vehicle_types&action=edit&id=' . $id, 'danger', 'Vehicle type name is required.');
    }

    if ($passengerCapacity < 1 || $passengerCapacity > 50) {
        redirectWith('/?page=vehicle_types&action=edit&id=' . $id, 'danger', 'Passenger capacity must be between 1 and 50.');
    }

    // Check if name already exists (excluding current record)
    $existing = db()->fetch(
        "SELECT id FROM vehicle_types WHERE name = ? AND id != ? AND deleted_at IS NULL",
        [$name, $id]
    );

    if ($existing) {
        redirectWith('/?page=vehicle_types&action=edit&id=' . $id, 'danger', 'A vehicle type with this name already exists.');
    }

    // Update vehicle type
    db()->update('vehicle_types', [
        'name' => $name,
        'description' => $description ?: null,
        'passenger_capacity' => $passengerCapacity,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$id]);

    setFlashMessage('Vehicle type updated successfully.', 'success');
    redirect('/?page=vehicle_types');
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <!-- Page Header -->
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-car-front me-2"></i>Edit Vehicle Type</h1>
            <p class="text-sm text-base-content/60">Update vehicle type information</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="loka-card">
                <div class="p-6">
                    <form method="POST">
                        <?= csrfField() ?>

                        <div class="mb-4">
                            <label for="name" class="loka-form-label">Name <span class="text-error">*</span></label>
                            <input type="text" class="loka-form-input" id="name" name="name"
                                   required maxlength="50"
                                   value="<?= e($vehicleType->name) ?>"
                                   placeholder="e.g., Sedan, SUV, Van, Bus">
                            <span class="text-xs text-base-content/50">A unique name for this vehicle type</span>
                        </div>

                        <div class="mb-4">
                            <label for="passenger_capacity" class="loka-form-label">Passenger Capacity <span class="text-error">*</span></label>
                            <input type="number" class="loka-form-input" id="passenger_capacity" name="passenger_capacity"
                                   required min="1" max="50" value="<?= $vehicleType->passenger_capacity ?>">
                            <span class="text-xs text-base-content/50">Number of passengers this vehicle type can accommodate (including driver)</span>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="loka-form-label">Description</label>
                            <textarea class="loka-form-input" id="description" name="description"
                                      rows="3" maxlength="500"
                                      placeholder="Optional description of this vehicle type..."><?= e($vehicleType->description) ?></textarea>
                            <span class="text-xs text-base-content/50">Additional details about this vehicle type</span>
                        </div>

                        <div class="border-t border-base-200 my-6"></div>

                        <div class="flex gap-2">
                            <button type="submit" class="loka-btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Update Vehicle Type
                            </button>
                            <a href="<?= APP_URL ?>/?page=vehicle_types" class="loka-btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="loka-card">
                <div class="p-6">
                    <h5 class="text-lg font-semibold text-base-content mb-4"><i class="bi bi-info-circle me-1"></i>Help</h5>
                    <p class="text-sm text-base-content/60 mb-2">Editing vehicle types will affect how vehicles are displayed and assigned.</p>
                    <ul class="text-sm text-base-content/60">
                        <li><strong>Name:</strong> Must be unique across all vehicle types</li>
                        <li><strong>Capacity:</strong> Used for trip validation</li>
                        <li><strong>Vehicles:</strong> <?= db()->fetchColumn("SELECT COUNT(*) FROM vehicles WHERE vehicle_type_id = ? AND deleted_at IS NULL", [$id]) ?> vehicle(s) currently using this type</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
