<?php
/**
 * LOKA - Create Driver Page
 */

requireRole(ROLE_APPROVER);

$pageTitle = 'Add Driver';
$errors = [];

// Get users that are not already drivers
$availableUsers = db()->fetchAll(
    "SELECT u.id, u.name, u.email FROM users u 
     WHERE u.deleted_at IS NULL AND u.status = 'active'
     AND u.id NOT IN (SELECT user_id FROM drivers WHERE deleted_at IS NULL)
     ORDER BY u.name"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $userId = postInt('user_id');
    $licenseNumber = postSafe('license_number', '', 50);
    $licenseExpiry = postSafe('license_expiry', '', 20);
    $licenseClass = postSafe('license_class', 'B', 10);
    $yearsExperience = postInt('years_experience', 0);
    $emergencyName = postSafe('emergency_contact_name', '', 100);
    $emergencyPhone = postSafe('emergency_contact_phone', '', 20);
    $notes = postSafe('notes', '', 500);
    
    if (!$userId) $errors[] = 'User is required';
    if (empty($licenseNumber)) $errors[] = 'License number is required';
    if (empty($licenseExpiry)) $errors[] = 'License expiry is required';
    
    if ($licenseNumber) {
        $existing = db()->fetch("SELECT id FROM drivers WHERE license_number = ? AND deleted_at IS NULL", [$licenseNumber]);
        if ($existing) $errors[] = 'License number already exists';
    }
    
    if (empty($errors)) {
        $driverId = db()->insert('drivers', [
            'user_id' => $userId,
            'license_number' => $licenseNumber,
            'license_expiry' => $licenseExpiry,
            'license_class' => $licenseClass,
            'years_experience' => $yearsExperience,
            'emergency_contact_name' => $emergencyName,
            'emergency_contact_phone' => $emergencyPhone,
            'notes' => $notes,
            'status' => DRIVER_AVAILABLE,
            'created_at' => date(DATETIME_FORMAT),
            'updated_at' => date(DATETIME_FORMAT)
        ]);
        
        auditLog('driver_created', 'driver', $driverId);
        redirectWith('/?page=drivers', 'success', 'Driver added successfully.');
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Add Driver</h1>
            <p class="text-sm text-base-content/60">Create a new driver profile</p>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="loka-card">
                <div class="p-6">
                    <h5 class="text-lg font-semibold text-base-content mb-4"><i class="bi bi-person-badge me-2"></i>Driver Details</h5>
                    
                    <?php if (!empty($errors)): ?>
                    <div class="loka-alert loka-alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <?= csrfField() ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="loka-form-label">User Account <span class="text-error">*</span></label>
                                <select class="select select-bordered w-full bg-base-100" name="user_id" required>
                                    <option value="">Select user...</option>
                                    <?php foreach ($availableUsers as $user): ?>
                                    <option value="<?= $user->id ?>" <?= post('user_id') == $user->id ? 'selected' : '' ?>><?= e($user->name) ?> (<?= e($user->email) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="loka-form-label">License Number <span class="text-error">*</span></label>
                                <input type="text" class="loka-form-input" name="license_number" value="<?= e(post('license_number', '')) ?>" required>
                            </div>
                            <div>
                                <label class="loka-form-label">License Expiry <span class="text-error">*</span></label>
                                <input type="text" class="loka-form-input datepicker" name="license_expiry" value="<?= e(post('license_expiry', '')) ?>" required>
                            </div>
                            <div>
                                <label class="loka-form-label">License Class</label>
                                <input type="text" class="loka-form-input" name="license_class" value="<?= e(post('license_class', 'B')) ?>">
                            </div>
                            <div>
                                <label class="loka-form-label">Years Experience</label>
                                <input type="number" class="loka-form-input" name="years_experience" value="<?= e(post('years_experience', '0')) ?>" min="0">
                            </div>
                            <div>
                                <label class="loka-form-label">Emergency Contact Name</label>
                                <input type="text" class="loka-form-input" name="emergency_contact_name" value="<?= e(post('emergency_contact_name', '')) ?>">
                            </div>
                            <div>
                                <label class="loka-form-label">Emergency Contact Phone</label>
                                <input type="text" class="loka-form-input" name="emergency_contact_phone" value="<?= e(post('emergency_contact_phone', '')) ?>">
                            </div>
                            <div class="md:col-span-2">
                                <label class="loka-form-label">Notes</label>
                                <textarea class="loka-form-input" name="notes" rows="2" maxlength="500"><?= e(post('notes', '')) ?></textarea>
                            </div>
                        </div>
                        <div class="border-t border-base-200 my-6"></div>
                        <button type="submit" class="loka-btn-primary"><i class="bi bi-check-lg me-1"></i>Save Driver</button>
                        <a href="<?= APP_URL ?>/?page=drivers" class="loka-btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
