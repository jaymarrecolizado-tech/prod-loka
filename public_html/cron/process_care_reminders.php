<?php
/**
 * LOKA - Vehicle care schedule reminders (CLI or include)
 * Sends email+SMS via notify() at 7d, 1d, due day, and overdue (daily).
 */

if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/../config/bootstrap.php';
}

/**
 * @return array{sent:int,skipped:int}
 */
function processCareReminders(): array
{
    $sent = 0;
    $skipped = 0;
    $today = date('Y-m-d');

    $rows = db()->fetchAll(
        "SELECT vcs.*, v.plate_number
         FROM vehicle_care_schedules vcs
         JOIN vehicles v ON v.id = vcs.vehicle_id AND v.deleted_at IS NULL
         WHERE vcs.deleted_at IS NULL
           AND vcs.status = ?
           AND vcs.due_date IS NOT NULL",
        [CARE_STATUS_SCHEDULED]
    );

    foreach ($rows as $row) {
        $due = $row->due_date;
        $days = (int) floor((strtotime($due) - strtotime($today)) / 86400);
        $link = '/?page=maintenance&action=care-edit&id=' . (int) $row->id;
        $label = CARE_TYPES[$row->care_type]['label'] ?? $row->care_type;
        $base = "{$label} for {$row->plate_number}: {$row->title} (due " . formatDate($due) . ")";

        $kind = null;
        $title = 'Vehicle Care Reminder';
        $message = null;
        $update = [];

        if ($days === 7 && empty($row->reminded_7d_at)) {
            $kind = '7d';
            $message = "Reminder (7 days): {$base}";
            $update['reminded_7d_at'] = date(DATETIME_FORMAT);
        } elseif ($days === 1 && empty($row->reminded_1d_at)) {
            $kind = '1d';
            $message = "Reminder (tomorrow): {$base}";
            $update['reminded_1d_at'] = date(DATETIME_FORMAT);
        } elseif ($days === 0 && empty($row->reminded_due_at)) {
            $kind = 'due';
            $message = "Due today: {$base}";
            $update['reminded_due_at'] = date(DATETIME_FORMAT);
        } elseif ($days < 0) {
            $overdueOn = $row->reminded_overdue_on ?? null;
            if ($overdueOn !== $today && $days >= -7) {
                $kind = 'overdue';
                $message = "Overdue (" . abs($days) . " day(s)): {$base}";
                $update['reminded_overdue_on'] = $today;
            }
        }

        if ($kind === null || $message === null) {
            $skipped++;
            continue;
        }

        notifyCareStakeholders(
            (int) $row->vehicle_id,
            'care_schedule_reminder',
            $title,
            $message,
            $link
        );
        $update['updated_at'] = date(DATETIME_FORMAT);
        db()->update('vehicle_care_schedules', $update, 'id = ?', [$row->id]);
        $sent++;
    }

    return ['sent' => $sent, 'skipped' => $skipped];
}

if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $r = processCareReminders();
    echo date('c') . " CARE reminders sent={$r['sent']} skipped={$r['skipped']}\n";
}
