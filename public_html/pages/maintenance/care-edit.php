<?php
/**
 * View / approve / complete / cancel a vehicle care schedule item.
 */

$id = getInt('id');
$item = db()->fetch(
    "SELECT vcs.*, v.plate_number, v.make, v.model, v.mileage
     FROM vehicle_care_schedules vcs
     JOIN vehicles v ON v.id = vcs.vehicle_id
     WHERE vcs.id = ? AND vcs.deleted_at IS NULL",
    [$id]
);

if (!$item || !canViewCareVehicle((int) $item->vehicle_id)) {
    redirectWith('/?page=maintenance&action=schedule', 'danger', 'Care item not found.');
}

$pageTitle = 'Care #' . $id;
$errors = [];
$canApprove = canApproveCareSchedules();
$canComplete = $canApprove || (currentDriverId() && in_array((int) $item->vehicle_id, careVehicleIdsForDriver(), true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $op = postSafe('op', '', 20);

    if ($op === 'approve' && $canApprove && $item->status === CARE_STATUS_PENDING) {
        db()->update('vehicle_care_schedules', [
            'status' => CARE_STATUS_SCHEDULED,
            'approved_by' => userId(),
            'approved_at' => date(DATETIME_FORMAT),
            'updated_at' => date(DATETIME_FORMAT),
        ], 'id = ?', [$id]);
        notifyCareStakeholders(
            (int) $item->vehicle_id,
            'care_schedule_scheduled',
            'Care item approved',
            "{$item->title} for {$item->plate_number} is scheduled for " . formatDate($item->due_date) . ".",
            '/?page=maintenance&action=care-edit&id=' . $id
        );
        auditLog('care_schedule_approve', 'vehicle_care_schedule', $id);
        redirectWith('/?page=maintenance&action=care-edit&id=' . $id, 'success', 'Approved and scheduled.');
    }

    if ($op === 'save' && $canApprove) {
        $title = postSafe('title', '', 255);
        $notes = postSafe('notes', '', 2000);
        $dueDate = postSafe('due_date', '', 20);
        $careType = postSafe('care_type', '', 32);
        if ($title === '' || !strtotime($dueDate) || !isset(CARE_TYPES[$careType])) {
            $errors[] = 'Title, type, and due date are required.';
        } else {
            db()->update('vehicle_care_schedules', [
                'title' => $title,
                'notes' => $notes !== '' ? $notes : null,
                'due_date' => $dueDate,
                'care_type' => $careType,
                'updated_at' => date(DATETIME_FORMAT),
            ], 'id = ?', [$id]);
            auditLog('care_schedule_edit', 'vehicle_care_schedule', $id);
            redirectWith('/?page=maintenance&action=care-edit&id=' . $id, 'success', 'Updated.');
        }
    }

    if ($op === 'complete' && $canComplete && in_array($item->status, [CARE_STATUS_PENDING, CARE_STATUS_SCHEDULED], true)) {
        $mileage = post('completed_mileage') !== '' ? postInt('completed_mileage') : null;
        db()->update('vehicle_care_schedules', [
            'status' => CARE_STATUS_COMPLETED,
            'completed_at' => date(DATETIME_FORMAT),
            'completed_by' => userId(),
            'completed_mileage' => $mileage,
            'updated_at' => date(DATETIME_FORMAT),
        ], 'id = ?', [$id]);

        // Recurring: create next scheduled item
        $typeInfo = CARE_TYPES[$item->care_type] ?? null;
        if ($typeInfo && !empty($typeInfo['recurring']) && $item->interval_days) {
            $nextDue = date('Y-m-d', strtotime($item->due_date . ' +' . (int) $item->interval_days . ' days'));
            $nextId = db()->insert('vehicle_care_schedules', [
                'vehicle_id' => $item->vehicle_id,
                'care_type' => $item->care_type,
                'title' => $item->title,
                'notes' => $item->notes,
                'due_date' => $nextDue,
                'status' => CARE_STATUS_SCHEDULED,
                'proposed_by' => userId(),
                'approved_by' => userId(),
                'approved_at' => date(DATETIME_FORMAT),
                'interval_days' => $item->interval_days,
                'interval_km' => $item->interval_km,
                'created_at' => date(DATETIME_FORMAT),
            ]);
            auditLog('care_schedule_recur', 'vehicle_care_schedule', (int) $nextId, null, ['from' => $id]);
        }

        notifyCareStakeholders(
            (int) $item->vehicle_id,
            'care_schedule_completed',
            'Care item completed',
            "{$item->title} for {$item->plate_number} was marked completed.",
            '/?page=maintenance&action=schedule'
        );
        auditLog('care_schedule_complete', 'vehicle_care_schedule', $id);
        redirectWith('/?page=maintenance&action=schedule', 'success', 'Marked completed.');
    }

    if ($op === 'cancel' && $canApprove && $item->status !== CARE_STATUS_COMPLETED) {
        db()->update('vehicle_care_schedules', [
            'status' => CARE_STATUS_CANCELLED,
            'updated_at' => date(DATETIME_FORMAT),
        ], 'id = ?', [$id]);
        notifyCareStakeholders(
            (int) $item->vehicle_id,
            'care_schedule_cancelled',
            'Care item cancelled',
            "{$item->title} for {$item->plate_number} was cancelled.",
            '/?page=maintenance&action=schedule'
        );
        auditLog('care_schedule_cancel', 'vehicle_care_schedule', $id);
        redirectWith('/?page=maintenance&action=schedule', 'success', 'Cancelled.');
    }

    $item = db()->fetch(
        "SELECT vcs.*, v.plate_number, v.make, v.model, v.mileage
         FROM vehicle_care_schedules vcs
         JOIN vehicles v ON v.id = vcs.vehicle_id
         WHERE vcs.id = ? AND vcs.deleted_at IS NULL",
        [$id]
    );
}

$statusInfo = CARE_STATUSES[$item->status] ?? ['label' => $item->status, 'color' => 'secondary'];
$typeInfo = CARE_TYPES[$item->care_type] ?? ['label' => $item->care_type];

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8 max-w-3xl">
    <div class="flex justify-between items-start mb-4 gap-3">
        <div>
            <h4 class="mb-1">Care #<?= (int) $item->id ?></h4>
            <p class="text-base-content/60 mb-0">
                <?= e($item->plate_number) ?> — <?= e($item->make . ' ' . $item->model) ?>
            </p>
        </div>
        <span class="loka-badge bg-<?= e($statusInfo['color']) ?>"><?= e($statusInfo['label']) ?></span>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="loka-alert loka-alert-danger mb-3"><?= e($err) ?></div>
    <?php endforeach; ?>

    <div class="loka-card mb-4">
        <div class="p-6 space-y-2">
            <div><strong>Type:</strong> <?= e($typeInfo['label']) ?></div>
            <div><strong>Title:</strong> <?= e($item->title) ?></div>
            <div><strong>Due:</strong> <?= formatDate($item->due_date) ?></div>
            <?php if ($item->notes): ?>
                <div><strong>Notes:</strong> <?= nl2br(e($item->notes)) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canApprove && $item->status === CARE_STATUS_PENDING): ?>
        <form method="POST" class="mb-3">
            <?= csrfField() ?>
            <input type="hidden" name="op" value="approve">
            <button type="submit" class="loka-btn-primary">Approve & schedule</button>
        </form>
    <?php endif; ?>

    <?php if ($canApprove && !in_array($item->status, [CARE_STATUS_COMPLETED, CARE_STATUS_CANCELLED], true)): ?>
        <div class="loka-card mb-4">
            <div class="p-6">
                <h6 class="mb-3">Edit</h6>
                <form method="POST" class="space-y-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="op" value="save">
                    <select name="care_type" class="loka-form-input">
                        <?php foreach (CARE_TYPES as $k => $info): ?>
                            <option value="<?= e($k) ?>" <?= $item->care_type === $k ? 'selected' : '' ?>><?= e($info['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="title" class="loka-form-input" value="<?= e($item->title) ?>" required>
                    <input type="date" name="due_date" class="loka-form-input" value="<?= e($item->due_date) ?>" required>
                    <textarea name="notes" class="loka-form-input" rows="3"><?= e($item->notes ?? '') ?></textarea>
                    <button type="submit" class="loka-btn-secondary">Save changes</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="flex flex-wrap gap-2">
        <?php if ($canComplete && in_array($item->status, [CARE_STATUS_PENDING, CARE_STATUS_SCHEDULED], true)): ?>
            <form method="POST" class="flex flex-wrap gap-2 items-end">
                <?= csrfField() ?>
                <input type="hidden" name="op" value="complete">
                <div>
                    <label class="loka-form-label">Odometer (optional)</label>
                    <input type="number" name="completed_mileage" class="loka-form-input" min="0"
                           value="<?= (int) ($item->mileage ?? 0) ?>">
                </div>
                <button type="submit" class="loka-btn-primary">Mark completed</button>
            </form>
        <?php endif; ?>
        <?php if ($canApprove && $item->status !== CARE_STATUS_COMPLETED && $item->status !== CARE_STATUS_CANCELLED): ?>
            <form method="POST" onsubmit="return confirm('Cancel this care item?');">
                <?= csrfField() ?>
                <input type="hidden" name="op" value="cancel">
                <button type="submit" class="loka-btn-secondary">Cancel item</button>
            </form>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/?page=maintenance&action=schedule" class="loka-btn-secondary">Back</a>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
