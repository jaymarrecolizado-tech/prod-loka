<?php
/**
 * LOKA - Edit Driver Page
 */

requireRole(ROLE_APPROVER);

$driverId = (int) get('id');
$driver = db()->fetch(
    "SELECT d.*, u.name as driver_name, u.email FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.id = ? AND d.deleted_at IS NULL FOR UPDATE",
    [$driverId]
);

if (!$driver)
    redirectWith('/?page=drivers', 'danger', 'Driver not found.');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $licenseNumber = postSafe('license_number', '', 50);
    $licenseExpiry = postSafe('license_expiry', '', 20);
    $licenseClass = postSafe('license_class', 'B', 10);
    $yearsExperience = postInt('years_experience', 0);
    $status = postSafe('status', '', 20);
    $emergencyName = postSafe('emergency_contact_name', '', 100);
    $emergencyPhone = postSafe('emergency_contact_phone', '', 20);
    $notes = postSafe('notes', '', 500);

    if (empty($licenseNumber))
        $errors[] = 'License number is required';
    if (empty($licenseExpiry))
        $errors[] = 'License expiry is required';

    // Validate license expiry date
    if (!empty($licenseExpiry)) {
        $expiryDate = DateTime::createFromFormat('Y-m-d', $licenseExpiry);
        $now = new DateTime();

        if ($expiryDate < $now) {
            $errors[] = 'License expiry date cannot be in the past';
        }
    }

    if (empty($errors)) {
        db()->beginTransaction();

        try {
            // Re-fetch with lock to ensure atomicity
            $driver = db()->fetch(
                "SELECT d.*, u.name as driver_name, u.email FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.id = ? AND d.deleted_at IS NULL FOR UPDATE",
                [$driverId]
            );

            // Check unique license (exclude current)
            if ($licenseNumber && $licenseNumber !== $driver->license_number) {
                $existing = db()->fetch("SELECT id FROM drivers WHERE license_number = ? AND id != ? AND deleted_at IS NULL", [$licenseNumber, $driverId]);
                if ($existing) {
                    db()->rollback();
                    $errors[] = 'License number already exists';
                }
            }

            if (empty($errors)) {
                db()->update('drivers', [
                    'license_number' => $licenseNumber,
                    'license_expiry' => $licenseExpiry,
                    'license_class' => $licenseClass,
                    'years_experience' => $yearsExperience,
                    'status' => $status,
                    'emergency_contact_name' => $emergencyName,
                    'emergency_contact_phone' => $emergencyPhone,
                    'notes' => $notes,
                    'updated_at' => date(DATETIME_FORMAT)
                ], 'id = ?', [$driverId]);

                auditLog('driver_updated', 'driver', $driverId);
                db()->commit();
                redirectWith('/?page=drivers', 'success', 'Driver updated successfully.');
            }
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Failed to update driver';
        }
    }
}

$pageTitle = 'Edit Driver';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="mb-4">
        <h2 class="text-xl font-semibold mb-1">Edit Driver: <?= e($driver->driver_name) ?></h2>
        <div class="text-sm text-base-content/60">
            <a href="<?= APP_URL ?>" class="hover:text-primary">Dashboard</a>
            <span class="mx-1">/</span>
            <a href="<?= APP_URL ?>/?page=drivers" class="hover:text-primary">Drivers</a>
            <span class="mx-1">/</span>
            <span>Edit</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="loka-card">
                <div class="p-4 border-b border-base-200">
                    <h3 class="text-base font-semibold flex items-center gap-2">
                        <i class="bi bi-pencil"></i>Edit Driver
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
                                <label class="loka-form-label">User</label>
                                <input type="text" class="input input-bordered w-full"
                                    value="<?= e($driver->driver_name) ?> (<?= e($driver->email) ?>)" disabled>
                            </div>
                            <div>
                                <label class="loka-form-label">License Number <span class="text-error">*</span></label>
                                <input type="text" class="input input-bordered w-full" name="license_number"
                                    value="<?= e(post('license_number', $driver->license_number)) ?>" required>
                            </div>
                            <div>
                                <label class="loka-form-label">License Expiry <span class="text-error">*</span></label>
                                <input type="text" class="input input-bordered w-full datepicker" name="license_expiry"
                                    value="<?= e(post('license_expiry', $driver->license_expiry)) ?>" required>
                            </div>
                            <div>
                                <label class="loka-form-label">License Class</label>
                                <input type="text" class="input input-bordered w-full" name="license_class"
                                    value="<?= e(post('license_class', $driver->license_class)) ?>">
                            </div>
                            <div>
                                <label class="loka-form-label">Status</label>
                                <select class="select select-bordered w-full" name="status">
                                    <?php foreach (DRIVER_STATUS_LABELS as $key => $info): ?>
                                    <option value="<?= $key ?>" <?= post('status', $driver->status) === $key ? 'selected' : '' ?>><?= e($info['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="loka-form-label">Years Experience</label>
                                <input type="number" class="input input-bordered w-full" name="years_experience"
                                    value="<?= e(post('years_experience', $driver->years_experience)) ?>" min="0">
                            </div>
                            <div>
                                <label class="loka-form-label">Emergency Contact Name</label>
                                <input type="text" class="input input-bordered w-full" name="emergency_contact_name"
                                    value="<?= e(post('emergency_contact_name', $driver->emergency_contact_name)) ?>">
                            </div>
                            <div>
                                <label class="loka-form-label">Emergency Contact Phone</label>
                                <input type="text" class="input input-bordered w-full" name="emergency_contact_phone"
                                    value="<?= e(post('emergency_contact_phone', $driver->emergency_contact_phone)) ?>">
                            </div>
                            <div class="md:col-span-2">
                                <label class="loka-form-label">Notes</label>
                                <textarea class="textarea textarea-bordered w-full" name="notes"
                                    rows="2" maxlength="500"><?= e(post('notes', $driver->notes)) ?></textarea>
                            </div>
                        </div>
                        <div class="border-t border-base-200 mt-4 pt-4 flex gap-2">
                            <button type="submit" class="loka-btn-primary">
                                <i class="bi bi-check-lg mr-1"></i>Save Changes
                            </button>
                            <a href="<?= APP_URL ?>/?page=drivers" class="loka-btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
