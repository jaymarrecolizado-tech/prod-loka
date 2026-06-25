<?php
/**
 * LOKA - Guard Dashboard
 *
 * Guards can view today's scheduled trips and record:
 * - Dispatch time (when vehicle leaves)
 * - Arrival time (when vehicle returns)
 */

requireRole(ROLE_GUARD);

$today = date('Y-m-d');
$filter = get('filter', 'today'); // today, pending_dispatch, pending_arrival, completed

// Build query based on filter
$sql = "SELECT r.*,
            u.name as requester_name, u.phone as requester_phone,
            d.name as department_name,
            v.plate_number, v.make, v.model as vehicle_model,
            dr.license_number as driver_license,
            driver_user.name as driver_name, driver_user.phone as driver_phone,
            mph.name as motorpool_head_name,
            dispatch_guard.name as dispatch_guard_name,
            arrival_guard.name as arrival_guard_name
     FROM requests r
     JOIN users u ON r.user_id = u.id
     JOIN departments d ON r.department_id = d.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     LEFT JOIN drivers dr ON r.driver_id = dr.id AND dr.deleted_at IS NULL
     LEFT JOIN users driver_user ON dr.user_id = driver_user.id
     LEFT JOIN users mph ON r.motorpool_head_id = mph.id
     LEFT JOIN users dispatch_guard ON r.dispatch_guard_id = dispatch_guard.id
     LEFT JOIN users arrival_guard ON r.arrival_guard_id = arrival_guard.id
     WHERE r.status = 'approved'
     AND r.deleted_at IS NULL";

$params = [];

switch ($filter) {
    case 'pending_dispatch':
        $sql .= " AND r.status = 'approved' AND r.actual_dispatch_datetime IS NULL";
        break;
    case 'pending_arrival':
        $sql .= " AND r.status = 'approved' AND r.actual_dispatch_datetime IS NOT NULL
                  AND r.actual_arrival_datetime IS NULL";
        break;
    case 'completed':
        $sql .= " AND r.status = 'approved' AND r.actual_arrival_datetime IS NOT NULL";
        break;
    case 'today':
    default:
        $sql .= " AND r.status = 'approved' AND DATE(r.start_datetime) = ?";
        $params[] = $today;
        break;
}

$sql .= " ORDER BY r.start_datetime ASC";

$trips = db()->fetchAll($sql, $params);

// Get statistics for today
$statsToday = db()->fetch(
    "SELECT
        COUNT(*) as total_scheduled,
        SUM(CASE WHEN actual_dispatch_datetime IS NULL THEN 1 ELSE 0 END) as pending_dispatch,
        SUM(CASE WHEN actual_dispatch_datetime IS NOT NULL AND actual_arrival_datetime IS NULL THEN 1 ELSE 0 END) as on_trip,
        SUM(CASE WHEN actual_arrival_datetime IS NOT NULL THEN 1 ELSE 0 END) as completed
     FROM requests
     WHERE status = 'approved'
     AND DATE(start_datetime) = ?
     AND deleted_at IS NULL",
    [$today]
);

// Get tab counts
$tabCounts = db()->fetch(
    "SELECT
        COUNT(*) as all_scheduled,
        SUM(CASE WHEN actual_dispatch_datetime IS NULL THEN 1 ELSE 0 END) as all_pending_dispatch,
        SUM(CASE WHEN actual_dispatch_datetime IS NOT NULL AND actual_arrival_datetime IS NULL THEN 1 ELSE 0 END) as all_on_trip,
        SUM(CASE WHEN actual_arrival_datetime IS NOT NULL THEN 1 ELSE 0 END) as all_completed
     FROM requests
     WHERE status = 'approved'
     AND deleted_at IS NULL"
);

$pageTitle = 'Guard Dashboard';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <!-- Page Header -->
    <div class="loka-page-header">
        <div>
            <h1 class="text-2xl font-bold text-base-content flex items-center gap-2">
                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Guard Dashboard
            </h1>
            <p class="text-sm text-base-content/60">Track vehicle dispatch and arrival times</p>
        </div>
        <div>
            <span class="loka-badge bg-base-200 text-base-content">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <?= formatDate($today) ?>
            </span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="loka-stat-card">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-lg bg-primary/10">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-base-content/60 uppercase tracking-wide">Scheduled Today</p>
                    <p class="text-2xl font-bold text-base-content"><?= $statsToday->total_scheduled ?? 0 ?></p>
                </div>
            </div>
        </div>
        <div class="loka-stat-card">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-lg bg-warning/10">
                    <svg class="w-6 h-6 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                </div>
                <div>
                    <p class="text-xs text-base-content/60 uppercase tracking-wide">Pending Dispatch</p>
                    <p class="text-2xl font-bold text-base-content"><?= $tabCounts->all_pending_dispatch ?? 0 ?></p>
                </div>
            </div>
        </div>
        <div class="loka-stat-card">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-lg bg-info/10">
                    <svg class="w-6 h-6 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </div>
                <div>
                    <p class="text-xs text-base-content/60 uppercase tracking-wide">On Trip</p>
                    <p class="text-2xl font-bold text-base-content"><?= $tabCounts->all_on_trip ?? 0 ?></p>
                </div>
            </div>
        </div>
        <div class="loka-stat-card">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-lg bg-success/10">
                    <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-base-content/60 uppercase tracking-wide">Completed</p>
                    <p class="text-2xl font-bold text-base-content"><?= $tabCounts->all_completed ?? 0 ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="loka-card">
        <div class="border-b border-base-200">
            <div class="flex gap-0 overflow-x-auto">
                <?php
                $tabs = [
                    'today' => ['label' => "Today's Trips", 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>', 'count' => $statsToday->total_scheduled ?? 0],
                    'pending_dispatch' => ['label' => 'Pending Dispatch', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>', 'count' => $tabCounts->all_pending_dispatch ?? 0],
                    'pending_arrival' => ['label' => 'On Trip', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>', 'count' => $tabCounts->all_on_trip ?? 0],
                    'completed' => ['label' => 'Completed', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>', 'count' => $tabCounts->all_completed ?? 0],
                ];
                foreach ($tabs as $key => $tab):
                    $href = $key === 'today' ? APP_URL . '/?page=guard' : APP_URL . "/?page=guard&filter={$key}";
                ?>
                <a href="<?= $href ?>"
                   class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap <?= $filter === $key ? 'border-primary text-primary' : 'border-transparent text-base-content/60 hover:text-base-content hover:border-base-300' ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $tab['icon'] ?></svg>
                    <?= $tab['label'] ?>
                    <span class="loka-badge loka-badge-sm <?= $filter === $key ? 'bg-primary/20 text-primary' : 'bg-base-200 text-base-content/60' ?>"><?= $tab['count'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="p-4">
            <?php if (empty($trips)): ?>
                <div class="loka-empty">
                    <svg class="mx-auto w-12 h-12 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="mt-2 text-base-content/60">
                        <?php if ($filter === 'today'): ?>
                            No approved trips scheduled for today.
                        <?php elseif ($filter === 'pending_dispatch'): ?>
                            No trips pending dispatch.
                        <?php elseif ($filter === 'pending_arrival'): ?>
                            No vehicles currently on trip.
                        <?php elseif ($filter === 'completed'): ?>
                            No trips completed today.
                        <?php else: ?>
                            No trips found.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="loka-table-responsive">
                    <table class="loka-table">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Time</th>
                                <th>Vehicle</th>
                                <th>Driver</th>
                                <th>Destination</th>
                                <th>Status</th>
                                <th>Dispatch</th>
                                <th>Arrival</th>
                                <th class="text-center w-28">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trips as $trip): ?>
                                <tr>
                                    <td>
                                        <span class="font-mono text-xs font-semibold text-primary">#<?= $trip->id ?></span>
                                        <p class="text-xs text-base-content/60"><?= e($trip->requester_name) ?></p>
                                    </td>
                                    <td>
                                        <p class="text-sm text-base-content flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                            <?= formatDateTime($trip->start_datetime) ?>
                                        </p>
                                        <p class="text-sm text-base-content flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>
                                            <?= formatDateTime($trip->end_datetime) ?>
                                        </p>
                                    </td>
                                    <td>
                                        <?php if ($trip->plate_number): ?>
                                            <p class="text-sm font-medium text-base-content"><?= e($trip->plate_number) ?></p>
                                            <p class="text-xs text-base-content/60"><?= e($trip->make . ' ' . $trip->vehicle_model) ?></p>
                                        <?php else: ?>
                                            <span class="text-sm text-base-content/40">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($trip->driver_name): ?>
                                            <p class="text-sm font-medium text-base-content"><?= e($trip->driver_name) ?></p>
                                            <p class="text-xs text-base-content/60"><?= e($trip->driver_phone) ?></p>
                                        <?php else: ?>
                                            <span class="text-sm text-base-content/40">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm text-base-content"><?= e($trip->destination) ?></td>
                                    <td>
                                        <?php if ($trip->actual_arrival_datetime): ?>
                                            <span class="loka-badge bg-success/20 text-success">Completed</span>
                                        <?php elseif ($trip->actual_dispatch_datetime): ?>
                                            <span class="loka-badge bg-primary/20 text-primary">On Trip</span>
                                        <?php else: ?>
                                            <span class="loka-badge bg-warning/20 text-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($trip->actual_dispatch_datetime): ?>
                                            <p class="text-sm text-success flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                <?= formatDateTime($trip->actual_dispatch_datetime) ?>
                                            </p>
                                            <p class="text-xs text-base-content/60">by <?= e($trip->dispatch_guard_name ?? 'Unknown') ?></p>
                                        <?php else: ?>
                                            <span class="text-sm text-base-content/40">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($trip->actual_arrival_datetime): ?>
                                            <p class="text-sm text-success flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                <?= formatDateTime($trip->actual_arrival_datetime) ?>
                                            </p>
                                            <p class="text-xs text-base-content/60">by <?= e($trip->arrival_guard_name ?? 'Unknown') ?></p>
                                        <?php else: ?>
                                            <span class="text-sm text-base-content/40">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-center gap-1">
                                            <?php if (!$trip->actual_dispatch_datetime): ?>
                                                <button type="button" class="loka-btn-sm bg-success text-success-content hover:bg-success/90"
                                                        onclick="document.getElementById('dispatchModal<?= $trip->id ?>').showModal()">
                                                    Dispatch
                                                </button>
                                            <?php elseif (!$trip->actual_arrival_datetime): ?>
                                                <button type="button" class="loka-btn-sm bg-primary text-primary-content hover:bg-primary/90"
                                                        onclick="document.getElementById('arrivalModal<?= $trip->id ?>').showModal()">
                                                    Arrival
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="loka-btn-sm bg-base-200 text-base-content/60" disabled>
                                                    Done
                                                </button>
                                            <?php endif; ?>

                                            <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= $trip->id ?>"
                                               class="loka-btn-icon text-primary hover:bg-primary/10" target="_blank" title="View">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </a>
                                        </div>
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

<!-- Dispatch Modals -->
<?php foreach ($trips as $trip): ?>
    <?php if (!$trip->actual_dispatch_datetime): ?>
        <dialog id="dispatchModal<?= $trip->id ?>" class="modal">
            <div class="modal-box bg-base-100 p-0 max-w-lg">
                <form method="POST" action="<?= APP_URL ?>/?page=guard&action=record_dispatch">
                    <?= csrfField() ?>
                    <input type="hidden" name="request_id" value="<?= $trip->id ?>">

                    <div class="p-6 border-b border-base-200">
                        <h5 class="text-base-content font-semibold flex items-center gap-2">
                            <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            Record Dispatch
                        </h5>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="loka-alert loka-alert-info">
                            <strong>Request #<?= $trip->id ?></strong><br>
                            <?= e($trip->requester_name) ?> — <?= e($trip->destination) ?>
                        </div>

                        <div>
                            <label class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Vehicle</label>
                            <p class="text-sm font-medium text-base-content"><?= e($trip->plate_number ?? 'Not assigned') ?></p>
                            <p class="text-xs text-base-content/60"><?= e($trip->make . ' ' . $trip->vehicle_model) ?></p>
                        </div>

                        <div>
                            <label class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Driver</label>
                            <p class="text-sm font-medium text-base-content"><?= e($trip->driver_name ?? 'Not assigned') ?></p>
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Dispatch Time <span class="text-error">*</span></span>
                            </label>
                            <input type="datetime-local"
                                   class="input input-bordered input-sm bg-base-100"
                                   id="dispatch_time<?= $trip->id ?>"
                                   name="dispatch_time"
                                   value="<?= date('Y-m-d\TH:i') ?>"
                                   required>
                            <label class="label">
                                <span class="label-text-alt text-xs text-base-content/50">Current time is pre-filled. Adjust if needed.</span>
                            </label>
                        </div>

                        <!-- Travel Documents -->
                        <div>
                            <label class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Travel Documents (Optional)</label>
                            <div class="bg-base-200/50 rounded-lg p-3 space-y-2 mt-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="has_travel_order" id="has_travel_order<?= $trip->id ?>" value="1" onchange="toggleTravelOrderInput(<?= $trip->id ?>)" class="checkbox checkbox-sm checkbox-primary">
                                    <span class="text-sm text-base-content">Travel Order Present</span>
                                </label>
                                <input type="text" name="travel_order_number" id="travel_order_number<?= $trip->id ?>" class="input input-bordered input-sm bg-base-100 w-full" placeholder="Travel Order No. (Required if checked)" style="display:none;">

                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="has_official_business_slip" id="has_ob_slip<?= $trip->id ?>" value="1" onchange="toggleObSlipInput(<?= $trip->id ?>)" class="checkbox checkbox-sm checkbox-primary">
                                    <span class="text-sm text-base-content">Official Business Slip Present</span>
                                </label>
                                <input type="text" name="ob_slip_number" id="ob_slip_number<?= $trip->id ?>" class="input input-bordered input-sm bg-base-100 w-full" placeholder="OB Slip No. (Required if checked)" style="display:none;">
                            </div>
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Notes (Optional)</span>
                            </label>
                            <textarea class="textarea textarea-bordered textarea-sm bg-base-100 h-20"
                                      name="guard_notes"
                                      placeholder="Any observations about the vehicle condition, passengers, etc."></textarea>
                        </div>
                    </div>

                    <div class="p-6 border-t border-base-200 flex justify-end gap-2">
                        <form method="dialog">
                            <button type="submit" class="btn btn-sm">Cancel</button>
                        </form>
                        <button type="submit" class="btn btn-sm bg-success text-success-content hover:bg-success/90">
                            Confirm Dispatch
                        </button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="submit">close</button>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($trip->actual_dispatch_datetime && !$trip->actual_arrival_datetime): ?>
        <dialog id="arrivalModal<?= $trip->id ?>" class="modal">
            <div class="modal-box bg-base-100 p-0 max-w-lg">
                <form method="POST" action="<?= APP_URL ?>/?page=guard&action=record_arrival">
                    <?= csrfField() ?>
                    <input type="hidden" name="request_id" value="<?= $trip->id ?>">

                    <div class="p-6 border-b border-base-200">
                        <h5 class="text-base-content font-semibold flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>
                            Record Arrival
                        </h5>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="loka-alert loka-alert-info">
                            <strong>Request #<?= $trip->id ?></strong><br>
                            <?= e($trip->requester_name) ?> — <?= e($trip->destination) ?>
                        </div>

                        <div>
                            <label class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Vehicle</label>
                            <p class="text-sm font-medium text-base-content"><?= e($trip->plate_number ?? 'Not assigned') ?></p>
                        </div>

                        <div>
                            <label class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Driver</label>
                            <p class="text-sm font-medium text-base-content"><?= e($trip->driver_name ?? 'Not assigned') ?></p>
                        </div>

                        <div>
                            <label class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Dispatched At</label>
                            <p class="text-sm text-success flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <?= formatDateTime($trip->actual_dispatch_datetime) ?>
                            </p>
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Arrival Time <span class="text-error">*</span></span>
                            </label>
                            <input type="datetime-local"
                                   class="input input-bordered input-sm bg-base-100"
                                   id="arrival_time<?= $trip->id ?>"
                                   name="arrival_time"
                                   value="<?= date('Y-m-d\TH:i') ?>"
                                   required>
                            <label class="label">
                                <span class="label-text-alt text-xs text-base-content/50">Current time is pre-filled. Adjust if needed.</span>
                            </label>
                        </div>

                        <?php if ($trip->mileage_start): ?>
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Ending Mileage (Optional)</span>
                            </label>
                            <input type="number" class="input input-bordered input-sm bg-base-100" name="mileage_end"
                                   min="<?= $trip->mileage_start ?>" placeholder="Current odometer reading">
                            <label class="label">
                                <span class="label-text-alt text-xs text-base-content/50">Starting mileage was <strong><?= $trip->mileage_start ?> km</strong>. If entered, system will calculate actual trip distance.</span>
                            </label>
                        </div>
                        <?php endif; ?>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Notes (Optional)</span>
                            </label>
                            <textarea class="textarea textarea-bordered textarea-sm bg-base-100 h-20"
                                      name="guard_notes"
                                      placeholder="Any observations about the vehicle condition upon return..."></textarea>
                        </div>
                    </div>

                    <div class="p-6 border-t border-base-200 flex justify-end gap-2">
                        <form method="dialog">
                            <button type="submit" class="btn btn-sm">Cancel</button>
                        </form>
                        <button type="submit" class="btn btn-sm bg-primary text-primary-content hover:bg-primary/90">
                            Confirm Arrival
                        </button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="submit">close</button>
            </form>
        </dialog>
    <?php endif; ?>
<?php endforeach; ?>

<script>
function toggleTravelOrderInput(id) {
    const checkbox = document.getElementById('has_travel_order' + id);
    const input = document.getElementById('travel_order_number' + id);
    if (checkbox && input) {
        input.style.display = checkbox.checked ? 'block' : 'none';
        input.required = checkbox.checked;
    }
}

function toggleObSlipInput(id) {
    const checkbox = document.getElementById('has_ob_slip' + id);
    const input = document.getElementById('ob_slip_number' + id);
    if (checkbox && input) {
        input.style.display = checkbox.checked ? 'block' : 'none';
        input.required = checkbox.checked;
    }
}
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
