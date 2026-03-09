<?php
/**
 * LOKA - Type 2 Trip Ticket Actions
 *
 * Handles view, edit, print, and submit for Type 2 tickets
 */

require_once INCLUDES_PATH . '/trip_ticket_helpers.php';

$action = get('action', '');
$ticketId = getInt('id');

// Load ticket
$ticket = db()->fetch(
    "SELECT tt.*, v.plate_number, v.make, v.model, v.fuel_type,
            du.name as driver_name
     FROM trip_tickets tt
     LEFT JOIN vehicles v ON tt.vehicle_id = v.id
     LEFT JOIN drivers d ON tt.driver_id = d.id
     LEFT JOIN users du ON d.user_id = du.id
     WHERE tt.id = ? AND tt.deleted_at IS NULL AND tt.ticket_type = 'type2'",
    [$ticketId]
);

if (!$ticket) {
    redirectWith('/?page=my-trip-tickets', 'danger', 'Ticket not found');
}

// Get trips for the week
$trips = fetchCompletedTripsForTicket($ticket->vehicle_id, $ticket->week_start, $ticket->week_end);

// Get passengers for each trip
foreach ($trips as $t) {
    $passengers = fetchRequestPassengers($t->request_id);
    $t->all_people = buildTripPeopleList($t->driver_name, $passengers);
}

// Get fuel refill data
$fuelRefillData = parseFuelRefillData($ticket->fuel_refill_data);

// Get balance info
$balanceInfo = json_decode($ticket->issues_description, true) ?: [];
$balanceStart = $balanceInfo['balance_start'] ?? '';
$balanceEnd = $balanceInfo['balance_end'] ?? '';

// Calculate totals
$totalDistance = (float) ($ticket->distance_traveled ?? 0);
$totalFuel = (float) ($ticket->fuel_consumed ?? 0);
$totalCost = (float) ($ticket->fuel_cost ?? 0);
$tripCount = (int) ($ticket->passengers ?? 0);

// Get generator/driver name
$generatorName = $ticket->driver_name ?: currentUser()->name;

// Handle different actions
if ($action === 'print-type2') {
    // Print the Type 2 ticket
    require_once __DIR__ . '/type2-print.php';
    exit;
}

// For view/edit actions, render the page
require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1"><i class="bi bi-calendar-week me-2"></i>Type 2 Trip Ticket</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=my-trip-tickets">My Trip Tickets</a></li>
                    <li class="breadcrumb-item active">Type 2 Ticket #<?= $ticketId ?></li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="?page=my-trip-tickets" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
            <a href="?page=my-trip-tickets&action=print-type2&id=<?= $ticketId ?>"
                class="btn btn-outline-danger" target="_blank">
                <i class="bi bi-printer me-1"></i>Print
            </a>
            <?php if ($ticket->status === 'draft'): ?>
                <a href="?page=my-trip-tickets&action=edit-type2&id=<?= $ticketId ?>"
                    class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <form method="POST" action="?page=my-trip-tickets&action=submit-type2&id=<?= $ticketId ?>"
                    class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <button type="submit" class="btn btn-success"
                        onclick="return confirm('Submit this ticket for approval?')">
                        <i class="bi bi-send me-1"></i>Submit for Approval
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ticket Info Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-file-earmark-spreadsheet me-2"></i>
                    <?= e($ticket->ticket_number) ?>
                </h5>
                <div>
                    <?= tripTicketTypeBadge('type2') ?>
                    <?= tripTicketStatusBadge($ticket->status) ?>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Vehicle Information</h6>
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Plate Number:</th>
                            <td><strong><?= e($ticket->plate_number) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Make/Model:</th>
                            <td><?= e($ticket->make) ?> <?= e($ticket->model) ?></td>
                        </tr>
                        <tr>
                            <th>Fuel Type:</th>
                            <td><?= e($ticket->fuel_type) ?></td>
                        </tr>
                        <tr>
                            <th>Driver/Caretaker:</th>
                            <td><?= e($generatorName) ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Ticket Information</h6>
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Week Number:</th>
                            <td><strong>Week <?= $ticket->week_number ?></strong></td>
                        </tr>
                        <tr>
                            <th>Date Range:</th>
                            <td><?= formatDate($ticket->week_start) ?> to <?= formatDate($ticket->week_end) ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td><?= tripTicketStatusBadge($ticket->status) ?></td>
                        </tr>
                        <tr>
                            <th>Date Created:</th>
                            <td><?= formatDateTime($ticket->created_at) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body text-center py-2">
                    <h6 class="text-muted mb-1">Total Trips</h6>
                    <h3 class="mb-0 text-primary"><?= $tripCount ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body text-center py-2">
                    <h6 class="text-muted mb-1">Total Distance</h6>
                    <h3 class="mb-0 text-success"><?= number_format($totalDistance) ?> <small class="fs-6">km</small></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body text-center py-2">
                    <h6 class="text-muted mb-1">Total Fuel</h6>
                    <h3 class="mb-0 text-info"><?= number_format($totalFuel, 2) ?> <small class="fs-6">L</small></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body text-center py-2">
                    <h6 class="text-muted mb-1">Total Cost</h6>
                    <h3 class="mb-0 text-warning">₱<?= number_format($totalCost, 2) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Trips List -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="bi bi-list-check me-2"></i>Completed Trips (<?= count($trips) ?>)
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($trips)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-calendar-x fs-1"></i>
                    <p class="mt-2 mb-0">No trips found for this period.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Destination</th>
                                <th>People</th>
                                <th class="text-end">Distance</th>
                                <th class="text-end">Fuel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trips as $trip): ?>
                                <tr>
                                    <td><?= formatDate($trip->start_date, 'M/d/Y') ?></td>
                                    <td>
                                        <small><?= formatTime($trip->start_date) ?></small>
                                        <span class="text-muted">→</span>
                                        <small><?= formatTime($trip->end_date) ?></small>
                                    </td>
                                    <td>
                                        <div><?= e($trip->destination) ?></div>
                                        <small class="text-muted"><?= e(truncate($trip->purpose, 30)) ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <?php foreach ($trip->all_people as $idx => $person): ?>
                                                <?= $idx > 0 ? ', ' : '' ?><?= e($person['name']) ?>
                                                <?php if ($person['role'] === 'Driver'): ?>
                                                    <span class="badge bg-primary badge-sm">D</span>
                                                <?php elseif ($person['role'] === 'Guest'): ?>
                                                    <span class="badge bg-info badge-sm">G</span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </small>
                                    </td>
                                    <td class="text-end"><strong><?= (int) $trip->distance_traveled ?> km</strong></td>
                                    <td class="text-end">
                                        <?php if ($trip->fuel_consumed > 0): ?>
                                            <?= number_format($trip->fuel_consumed, 2) ?> L
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Fuel Refill Data -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="bi bi-fuel-pump me-2"></i>Fuel Refill Data
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Qty (L)</th>
                            <th>Amount (₱)</th>
                            <th>Date</th>
                            <th>Additional Items</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fuelRefillData)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    No fuel refill data entered
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fuelRefillData as $fuel): ?>
                                <tr>
                                    <td><?= $fuel['qty'] > 0 ? number_format($fuel['qty'], 2) : '—' ?></td>
                                    <td><?= $fuel['amount'] > 0 ? '₱' . number_format($fuel['amount'], 2) : '—' ?></td>
                                    <td><?= $fuel['date'] ?: '—' ?></td>
                                    <td><?= e($fuel['items'] ?: '—') ?></td>
                                    <td><?= e($fuel['remarks'] ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($fuelRefillData)): ?>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="2" class="text-end"><strong>Total:</strong></td>
                                <td><?= number_format($totalFuel, 2) ?> L</td>
                                <td colspan="2">₱<?= number_format($totalCost, 2) ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
