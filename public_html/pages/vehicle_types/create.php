<?php
/**
 * LOKA - Create Vehicle Type
 */

requireRole('admin');

$pageTitle = 'Add Vehicle Type';

if (isPost()) {
    requireCsrf();

    $name = postSafe('name', '', 50);
    $description = postSafe('description', '', 500);
    $passengerCapacity = postInt('passenger_capacity');

    // Validate
    if (empty($name)) {
        redirectWith('/?page=vehicle_types&action=create', 'danger', 'Vehicle type name is required.');
    }

    if ($passengerCapacity < 1 || $passengerCapacity > 50) {
        redirectWith('/?page=vehicle_types&action=create', 'danger', 'Passenger capacity must be between 1 and 50.');
    }

    // Check if name already exists
    $existing = db()->fetch(
        "SELECT id FROM vehicle_types WHERE name = ? AND deleted_at IS NULL",
        [$name]
    );

    if ($existing) {
        redirectWith('/?page=vehicle_types&action=create', 'danger', 'A vehicle type with this name already exists.');
    }

    // Create vehicle type
    db()->insert('vehicle_types', [
        'name' => $name,
        'description' => $description ?: null,
        'passenger_capacity' => $passengerCapacity,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    setFlashMessage('Vehicle type created successfully.', 'success');
    redirect('/?page=vehicle_types');
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <!-- Page Header -->
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-car-front me-2"></i>Add Vehicle Type</h1>
            <p class="text-sm text-base-content/60">Create a new vehicle type for the fleet</p>
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
                                   placeholder="e.g., Sedan, SUV, Van, Bus">
                            <span class="text-xs text-base-content/50">A unique name for this vehicle type</span>
                        </div>

                        <div class="mb-4">
                            <label for="passenger_capacity" class="loka-form-label">Passenger Capacity <span class="text-error">*</span></label>
                            <input type="number" class="loka-form-input" id="passenger_capacity" name="passenger_capacity"
                                   required min="1" max="50" value="4">
                            <span class="text-xs text-base-content/50">Number of passengers this vehicle type can accommodate (including driver)</span>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="loka-form-label">Description</label>
                            <textarea class="loka-form-input" id="description" name="description"
                                      rows="3" maxlength="500"
                                      placeholder="Optional description of this vehicle type..."></textarea>
                            <span class="text-xs text-base-content/50">Additional details about this vehicle type</span>
                        </div>

                        <div class="border-t border-base-200 my-6"></div>

                        <div class="flex gap-2">
                            <button type="submit" class="loka-btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Create Vehicle Type
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
                    <p class="text-sm text-base-content/60 mb-2">Vehicle types help organize your fleet and ensure proper vehicle assignment based on passenger count.</p>
                    <ul class="text-sm text-base-content/60">
                        <li><strong>Name:</strong> Unique identifier for the type (e.g., Sedan, SUV)</li>
                        <li><strong>Capacity:</strong> Maximum passengers including driver</li>
                        <li><strong>Description:</strong> Optional details about the vehicle type</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
