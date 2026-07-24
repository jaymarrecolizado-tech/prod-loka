<?php
/**
 * Assign drivers responsible for vehicle care (Motorpool / Admin / All Father).
 */

if (!canManageCareAssignments()) {
    redirectWith('/?page=maintenance&action=schedule', 'danger', 'Motorpool or Administrator access required.');
}

$pageTitle = 'Vehicle Care Assignments';
$errors = [];
$flashOk = null;

$vehicles = db()->fetchAll(
    "SELECT id, plate_number, make, model FROM vehicles WHERE deleted_at IS NULL ORDER BY plate_number"
);
$drivers = db()->fetchAll(
    "SELECT d.id, u.name, u.email
     FROM drivers d
     JOIN users u ON u.id = d.user_id
     WHERE d.deleted_at IS NULL AND u.deleted_at IS NULL AND u.status = 'active'
     ORDER BY u.name"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $op = postSafe('op', '', 20);

    if ($op === 'assign') {
        $vehicleId = postInt('vehicle_id');
        $driverId = postInt('driver_id');
        if (!$vehicleId || !$driverId) {
            $errors[] = 'Select a vehicle and a driver.';
        } else {
            $exists = db()->fetch(
                "SELECT id, deleted_at FROM vehicle_care_assignments
                 WHERE vehicle_id = ? AND driver_id = ? LIMIT 1",
                [$vehicleId, $driverId]
            );
            if ($exists && !$exists->deleted_at) {
                $errors[] = 'That assignment already exists.';
            } elseif ($exists && $exists->deleted_at) {
                db()->update('vehicle_care_assignments', [
                    'deleted_at' => null,
                    'assigned_by' => userId(),
                    'updated_at' => date(DATETIME_FORMAT),
                ], 'id = ?', [$exists->id]);
                auditLog('care_assign_restore', 'vehicle_care_assignment', (int) $exists->id);
                $flashOk = 'Assignment restored.';
            } else {
                $id = db()->insert('vehicle_care_assignments', [
                    'vehicle_id' => $vehicleId,
                    'driver_id' => $driverId,
                    'assigned_by' => userId(),
                    'created_at' => date(DATETIME_FORMAT),
                ]);
                auditLog('care_assign', 'vehicle_care_assignment', (int) $id, null, [
                    'vehicle_id' => $vehicleId,
                    'driver_id' => $driverId,
                ]);
                $flashOk = 'Driver assigned to vehicle care.';
            }
        }
    } elseif ($op === 'remove') {
        $assignId = postInt('id');
        $row = db()->fetch(
            "SELECT * FROM vehicle_care_assignments WHERE id = ? AND deleted_at IS NULL",
            [$assignId]
        );
        if (!$row) {
            $errors[] = 'Assignment not found.';
        } else {
            db()->update('vehicle_care_assignments', [
                'deleted_at' => date(DATETIME_FORMAT),
                'updated_at' => date(DATETIME_FORMAT),
            ], 'id = ?', [$assignId]);
            auditLog('care_unassign', 'vehicle_care_assignment', $assignId, (array) $row, null);
            $flashOk = 'Assignment removed.';
        }
    }
}

$assignments = db()->fetchAll(
    "SELECT vca.*, v.plate_number, v.make, v.model, u.name AS driver_name, u.email AS driver_email
     FROM vehicle_care_assignments vca
     JOIN vehicles v ON v.id = vca.vehicle_id
     JOIN drivers d ON d.id = vca.driver_id
     JOIN users u ON u.id = d.user_id
     WHERE vca.deleted_at IS NULL
     ORDER BY v.plate_number, u.name"
);

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1">Vehicle Care Assignments</h4>
            <p class="text-base-content/60 mb-0">Drivers responsible for documents, PMS, and cleaning of each plate</p>
        </div>
        <a href="<?= APP_URL ?>/?page=maintenance&action=schedule" class="loka-btn-secondary">Back to Schedule</a>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="loka-alert loka-alert-danger mb-3"><?= e($err) ?></div>
    <?php endforeach; ?>
    <?php if ($flashOk): ?>
        <div class="loka-alert loka-alert-success mb-3"><?= e($flashOk) ?></div>
    <?php endif; ?>

    <div class="loka-card mb-4">
        <div class="p-6">
            <h6 class="mb-3">Assign driver to vehicle</h6>
            <form method="POST" class="flex flex-wrap gap-3 items-end">
                <?= csrfField() ?>
                <input type="hidden" name="op" value="assign">
                <div class="flex flex-col gap-1 min-w-[200px]">
                    <label class="loka-form-label">Vehicle</label>
                    <select name="vehicle_id" class="loka-form-input" required>
                        <option value="">Select…</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?= (int) $v->id ?>"><?= e($v->plate_number) ?> — <?= e($v->make . ' ' . $v->model) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex flex-col gap-1 min-w-[200px]">
                    <label class="loka-form-label">Driver</label>
                    <select name="driver_id" class="loka-form-input" required>
                        <option value="">Select…</option>
                        <?php foreach ($drivers as $d): ?>
                            <option value="<?= (int) $d->id ?>"><?= e($d->name) ?> (<?= e($d->email) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="loka-btn-primary">Assign</button>
            </form>
        </div>
    </div>

    <div class="loka-card">
        <div class="p-6">
            <?php if (empty($assignments)): ?>
                <p class="text-base-content/60 mb-0">No care assignments yet.</p>
            <?php else: ?>
                <div class="loka-table-responsive">
                    <table class="loka-table">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Driver</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $a): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($a->plate_number) ?></strong>
                                        <div class="text-sm text-base-content/60"><?= e($a->make . ' ' . $a->model) ?></div>
                                    </td>
                                    <td>
                                        <?= e($a->driver_name) ?>
                                        <div class="text-sm text-base-content/60"><?= e($a->driver_email) ?></div>
                                    </td>
                                    <td class="text-right">
                                        <form method="POST" class="inline" onsubmit="return confirm('Remove this assignment?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="op" value="remove">
                                            <input type="hidden" name="id" value="<?= (int) $a->id ?>">
                                            <button type="submit" class="loka-btn-secondary loka-btn-sm">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
