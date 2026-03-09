<?php
/**
 * LOKA - Generate Type 2 Vehicle Trip Ticket (Weekly Summary)
 *
 * Caretakers generate these weekly for each vehicle
 */

// Load helpers
require_once INCLUDES_PATH . '/trip_ticket_helpers.php';

// Only drivers/motorpool/admin can access
if (!isDriver() && !isMotorpool() && !isAdmin()) {
    redirectWith('/?page=dashboard', 'danger', 'You do not have permission to access this page.');
}

$pageTitle = 'Generate Weekly Trip Ticket (Type 2)';
$errors = [];
$success = '';

$action = get('action', 'form');
$ticketId = getInt('id');

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $vehicleId = postInt('vehicle_id');
    $weekStart = post('week_start');
    $weekEnd = post('week_end');

    // Validate
    if (!$vehicleId) {
        $errors[] = 'Please select a vehicle';
    }
    if (!$weekStart) {
        $errors[] = 'Week start date is required';
    }
    if (!$weekEnd) {
        $errors[] = 'Week end date is required';
    }

    // Check if ticket already exists
    if (empty($errors)) {
        $existing = getExistingType2Ticket($vehicleId, $weekStart);
        if ($existing && !$ticketId) {
            $errors[] = 'A Type 2 ticket already exists for this vehicle and week. Please edit the existing ticket.';
        }
    }

    if (empty($errors)) {
        try {
            db()->beginTransaction();

            // Get vehicle info
            $vehicle = db()->fetch("SELECT * FROM vehicles WHERE id = ? AND deleted_at IS NULL", [$vehicleId]);
            if (!$vehicle) {
                throw new Exception('Vehicle not found');
            }

            // Get driver info (caretaker)
            $driver = db()->fetch(
                "SELECT d.*, u.name as driver_name
                 FROM drivers d
                 JOIN users u ON d.user_id = u.id
                 WHERE d.user_id = ? AND d.deleted_at IS NULL",
                [userId()]
            );

            // Generate ticket number
            $ticketNumber = generateType2TicketNumber($vehicle->plate_number, $weekStart);
            $weekNumber = getWeekOfMonth($weekStart);

            // Get all completed trips for this week
            $trips = fetchCompletedTripsForTicket($vehicleId, $weekStart, $weekEnd);

            // Calculate totals
            $totalDistance = 0;
            $totalFuel = 0;
            $totalCost = 0;
            $tripCount = 0;

            foreach ($trips as $trip) {
                $tripCount++;
                $totalDistance += (float) ($trip->distance_traveled ?? 0);
                $totalFuel += (float) ($trip->fuel_consumed ?? 0);
                $totalCost += (float) ($trip->fuel_cost ?? 0);
            }

            // Collect fuel refill data from POST
            $fuelRefillData = [];
            if (isset($_POST['fuel_qty']) && is_array($_POST['fuel_qty'])) {
                foreach ($_POST['fuel_qty'] as $idx => $qty) {
                    $qtyVal = (float) $qty;
                    $amtVal = isset($_POST['fuel_amount'][$idx]) ? (float) $_POST['fuel_amount'][$idx] : 0;
                    $dateVal = post("fuel_date_{$idx}", '');
                    $itemsVal = postSafe("fuel_items_{$idx}", '', 255);
                    $remVal = postSafe("fuel_remarks_{$idx}", '', 255);

                    if ($qtyVal > 0 || $amtVal > 0 || !empty($dateVal)) {
                        $fuelRefillData[] = [
                            'qty' => $qtyVal,
                            'amount' => $amtVal,
                            'date' => $dateVal,
                            'items' => $itemsVal,
                            'remarks' => $remVal
                        ];
                    }
                }
            }

            // Additional summary fields
            $balanceStart = post('balance_start');
            $balanceEnd = post('balance_end');

            if ($ticketId) {
                // Update existing ticket
                db()->update('trip_tickets', [
                    'week_number' => $weekNumber,
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                    'distance_traveled' => $totalDistance,
                    'fuel_consumed' => $totalFuel,
                    'fuel_cost' => $totalCost,
                    'fuel_refill_data' => encodeFuelRefillData($fuelRefillData),
                    'passengers' => $tripCount, // Using this to store trip count
                    'updated_at' => date(DATETIME_FORMAT)
                ], 'id = ?', [$ticketId]);

                auditLog('update', 'trip_ticket', $ticketId);
                $success = 'Weekly trip ticket updated successfully!';
            } else {
                // Create new ticket
                $ticketId = db()->insert('trip_tickets', [
                    'ticket_type' => 'type2',
                    'ticket_number' => $ticketNumber,
                    'request_id' => 0, // No single request for Type 2
                    'driver_id' => $driver ? $driver->id : 0,
                    'trip_type' => 'official', // Default for Type 2
                    'start_date' => $weekStart . ' 00:00:00',
                    'end_date' => $weekEnd . ' 23:59:59',
                    'destination' => "Multiple destinations (weekly summary)",
                    'purpose' => "Weekly trip summary for vehicle {$vehicle->plate_number}",
                    'passengers' => $tripCount, // Trip count
                    'distance_traveled' => $totalDistance,
                    'fuel_consumed' => $totalFuel,
                    'fuel_cost' => $totalCost,
                    'fuel_refill_data' => encodeFuelRefillData($fuelRefillData),
                    'dispatch_guard_id' => userId(),
                    'arrival_guard_id' => userId(),
                    'status' => 'draft',
                    'approval_by' => getApprovalTypeForUser(),
                    'week_number' => $weekNumber,
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                    'created_by' => userId()
                ]);

                auditLog('create', 'trip_ticket', $ticketId);
                $success = 'Weekly trip ticket created successfully!';
            }

            // Store balance info in issues_description (temporary solution, could add proper columns)
            db()->update('trip_tickets', [
                'issues_description' => json_encode([
                    'balance_start' => $balanceStart,
                    'balance_end' => $balanceEnd
                ])
            ], 'id = ?', [$ticketId]);

            db()->commit();

            // Redirect to view the ticket
            redirectWith('/?page=my-trip-tickets&action=view-type2&id=' . $ticketId, 'success', $success);

        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Load existing ticket for editing
$ticket = null;
$vehicle = null;
$trips = [];
$weekStart = '';
$weekEnd = '';
$ticketNumber = '';
$balanceStart = '';
$balanceEnd = '';
$fuelRefillData = [];

if ($ticketId) {
    $ticket = db()->fetch(
        "SELECT tt.*, v.plate_number, v.make, v.model, v.fuel_type
         FROM trip_tickets tt
         LEFT JOIN vehicles v ON tt.vehicle_id = v.id
         WHERE tt.id = ? AND tt.deleted_at IS NULL",
        [$ticketId]
    );

    if ($ticket) {
        $vehicle = $ticket;
        $weekStart = $ticket->week_start;
        $weekEnd = $ticket->week_end;
        $ticketNumber = $ticket->ticket_number;

        // Get trips
        $trips = fetchCompletedTripsForTicket($ticket->vehicle_id, $weekStart, $weekEnd);

        // Parse fuel refill data
        $fuelRefillData = parseFuelRefillData($ticket->fuel_refill_data);

        // Parse balance info
        $balanceInfo = json_decode($ticket->issues_description, true) ?: [];
        $balanceStart = $balanceInfo['balance_start'] ?? '';
        $balanceEnd = $balanceInfo['balance_end'] ?? '';
    }
}

// Default values for new ticket
if (!$weekStart) {
    $weekStart = get('week_start', date('Y-m-d', strtotime('monday this week')));
}
if (!$weekEnd) {
    $weekEnd = get('week_end', date('Y-m-d', strtotime('sunday this week')));
}

// Get vehicles list
$vehicles = db()->fetchAll(
    "SELECT v.*, d.id as driver_id, u.name as caretaker_name
     FROM vehicles v
     LEFT JOIN drivers d ON v.driver_id = d.id AND d.deleted_at IS NULL
     LEFT JOIN users u ON d.user_id = u.id
     WHERE v.deleted_at IS NULL
     ORDER BY v.plate_number"
);

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-calendar-week me-2"></i><?= $ticketId ? 'Edit' : 'Generate' ?> Weekly Trip Ticket (Type 2)</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=my-trip-tickets">My Trip Tickets</a></li>
                    <li class="breadcrumb-item active">Type 2 Ticket</li>
                </ol>
            </nav>
        </div>
        <a href="?page=my-trip-tickets" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Error:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="type2Form">
        <input type="hidden" name="page" value="my-trip-tickets">
        <input type="hidden" name="action" value="generate-type2">
        <?php if ($ticketId): ?>
            <input type="hidden" name="id" value="<?= $ticketId ?>">
        <?php endif; ?>

        <div class="row">
            <!-- Left Column: Ticket Info -->
            <div class="col-lg-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Ticket Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Vehicle <span class="text-danger">*</span></label>
                            <select class="form-select" name="vehicle_id" id="vehicleSelect" required>
                                <option value="">Select Vehicle...</option>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?= $v->id ?>"
                                        data-plate="<?= e($v->plate_number) ?>"
                                        data-make="<?= e($v->make) ?>"
                                        data-model="<?= e($v->model) ?>"
                                        <?= ($vehicle && $v->id == $vehicle->id) ? 'selected' : '' ?>>
                                        <?= e($v->plate_number) ?> - <?= e($v->make) ?> <?= e($v->model) ?>
                                        <?php if ($v->caretaker_name): ?>
                                            (Caretaker: <?= e($v->caretaker_name) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Week Start <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="week_start" id="weekStart"
                                    value="<?= e($weekStart) ?>" required
                                    onchange="updateTicketNumber()">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Week End <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="week_end" id="weekEnd"
                                    value="<?= e($weekEnd) ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ticket Number</label>
                            <input type="text" class="form-control bg-light" id="ticketNumberDisplay"
                                value="<?= $ticketNumber ? e($ticketNumber) : 'Auto-generated on save' ?>" readonly>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Format:</strong> YEAR-PLATE-MONTH+WEEK<br>
                            <small class="text-muted">Example: 2026-448SQB-0301</small>
                        </div>

                        <div class="d-grid gap-2">
                            <?php if ($ticketId): ?>
                                <button type="button" class="btn btn-outline-primary"
                                    onclick="window.open('?page=my-trip-tickets&action=print-type2&id=<?= $ticketId ?>', '_blank')">
                                    <i class="bi bi-printer me-2"></i>Print Ticket
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-save me-2"></i>Update Ticket
                                </button>
                                <button type="submit" formaction="?page=my-trip-tickets&action=submit-type2&id=<?= $ticketId ?>"
                                    class="btn btn-primary"
                                    onclick="return confirm('Submit ticket for approval?')">
                                    <i class="bi bi-send me-2"></i>Submit for Approval
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-plus-circle me-2"></i>Generate Ticket
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Trips & Fuel -->
            <div class="col-lg-7">
                <!-- Auto-Aggregated Trips -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-list-check me-2"></i>Completed Trips (Auto-Aggregated)
                            <span class="badge bg-primary ms-2"><?= count($trips) ?> trips</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($trips)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-calendar-x fs-1"></i>
                                <p class="mt-2 mb-0">No completed trips found for this period.</p>
                                <small>Select a different week or vehicle.</small>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Date</th>
                                            <th>Destination</th>
                                            <th>Driver</th>
                                            <th class="text-end">Distance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($trips as $trip): ?>
                                            <tr>
                                                <td>
                                                    <small><?= formatDate($trip->start_date, 'M/d') ?></small><br>
                                                    <small class="text-muted"><?= formatTime($trip->start_date) ?></small>
                                                </td>
                                                <td>
                                                    <div><?= e(truncate($trip->destination, 30)) ?></div>
                                                    <small class="text-muted"><?= e(truncate($trip->purpose, 25)) ?></small>
                                                </td>
                                                <td><small><?= e($trip->driver_name) ?></small></td>
                                                <td class="text-end">
                                                    <strong><?= (int) $trip->distance_traveled ?> km</strong>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Fuel Refill Data (Manual Input) -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-fuel-pump me-2"></i>Fuel Refill Data
                            <span class="text-muted small ms-2">(Manual Input)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm" id="fuelTable">
                                <thead>
                                    <tr>
                                        <th style="width: 15%">Qty (L)</th>
                                        <th style="width: 15%">Amount (₱)</th>
                                        <th style="width: 15%">Date</th>
                                        <th style="width: 25%">Additional Items</th>
                                        <th style="width: 30%">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $fuelRows = max(count($fuelRefillData), 5);
                                    for ($i = 0; $i < $fuelRows; $i++):
                                        $fuel = $fuelRefillData[$i] ?? [];
                                    ?>
                                        <tr>
                                            <td>
                                                <input type="number" step="0.01" class="form-control form-control-sm"
                                                    name="fuel_qty[]" value="<?= $fuel['qty'] ?? '' ?>"
                                                    placeholder="0.00" oninput="calcTotals()">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control form-control-sm"
                                                    name="fuel_amount[]" value="<?= $fuel['amount'] ?? '' ?>"
                                                    placeholder="0.00" oninput="calcTotals()">
                                            </td>
                                            <td>
                                                <input type="date" class="form-control form-control-sm"
                                                    name="fuel_date_<?= $i ?>" value="<?= $fuel['date'] ?? '' ?>">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm"
                                                    name="fuel_items_<?= $i ?>" value="<?= e($fuel['items'] ?? '') ?>"
                                                    placeholder="Oil, filter...">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm"
                                                    name="fuel_remarks_<?= $i ?>" value="<?= e($fuel['remarks'] ?? '') ?>"
                                                    placeholder="GAS voucher #">
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>Total:</strong></td>
                                        <td><span id="totalQty"><?= number_format($totalFuel ?? 0, 2) ?> L</span></td>
                                        <td><span id="totalAmount">₱<?= number_format($totalCost ?? 0, 2) ?></span></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Balance Start (L)</label>
                                <input type="number" step="0.01" class="form-control"
                                    name="balance_start" value="<?= $balanceStart ?>" placeholder="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Balance End (L)</label>
                                <input type="number" step="0.01" class="form-control"
                                    name="balance_end" value="<?= $balanceEnd ?>" placeholder="0.00">
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-4">
                                <label class="form-label text-muted">Total Distance</label>
                                <div class="form-control bg-light">
                                    <strong><?= number_format($totalDistance ?? 0) ?> km</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted">Total Fuel</label>
                                <div class="form-control bg-light">
                                    <strong><?= number_format($totalFuel ?? 0, 2) ?> L</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted">Total Cost</label>
                                <div class="form-control bg-light">
                                    <strong>₱<?= number_format($totalCost ?? 0, 2) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function updateTicketNumber() {
    const select = document.getElementById('vehicleSelect');
    const option = select.options[select.selectedIndex];
    const weekStart = document.getElementById('weekStart').value;
    const display = document.getElementById('ticketNumberDisplay');

    if (option.value && weekStart) {
        const plate = option.dataset.plate;
        const date = new Date(weekStart);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const week = Math.ceil(date.getDate() / 7);
        display.value = `${year}-${plate}-${month}+${week}`;
    } else {
        display.value = 'Auto-generated on save';
    }
}

function calcTotals() {
    let qty = 0, amt = 0;
    document.querySelectorAll('#fuelTable tbody tr').forEach(row => {
        const qtyInput = row.querySelector('input[name="fuel_qty[]"]');
        const amtInput = row.querySelector('input[name="fuel_amount[]"]');
        qty += parseFloat(qtyInput.value) || 0;
        amt += parseFloat(amtInput.value) || 0;
    });
    document.getElementById('totalQty').textContent = qty.toFixed(2) + ' L';
    document.getElementById('totalAmount').textContent = '₱' + amt.toLocaleString('en-PH', {minimumFractionDigits: 2});
}

// Initialize
document.getElementById('vehicleSelect').addEventListener('change', updateTicketNumber);
calcTotals();
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
